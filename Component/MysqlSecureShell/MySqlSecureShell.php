<?php
namespace CTLib\Component\MySqlSecureShell;

use CTLib\Component\Monolog\Logger;
use CTLib\Util\Util;


class MySqlSecureShell
{

    public function __construct(
        Logger $logger,
        $pathToUserFile,
        $pathToPasswordFile,
        $pathToBinary,
        $pathToTempDir
    ) {
        $this->logger               = $logger;
        $this->pathToUserFile       = $pathToUserFile;
        $this->pathToPasswordFile   = $pathToPasswordFile;
        $this->pathToBinary         = $pathToBinary;
        $this->pathToTempDir        = $pathToTempDir;
    }

    public function execute($sql, $dbHost, $dbName = null)
    {
        if (empty($sql)) {
            throw new \InvalidArgumentException('$sql is required and cannot be empty string');
        }

        if (empty($dbHost)) {
            throw new \InvalidArgumentException('$dbHost is required and cannot be empty string');
        }

        if (!is_null($dbName) && empty($dbName)) {
            throw new \InvalidArgumentException('$dbName cannot be empty string');
        }

        $tempFile = $this->saveSqlToTempFile($sql);

        $cmd = $this->getBaseCommand();

        $cmd .= " --host=" . $dbHost;

        if ($dbName) {
            $cmd .= " -D " . $dbName;
        }

        $cmd .= " -e 'source {$tempFile}'";

        $cmd .= " 2>&1";

        $output = null;
        exec($cmd, $output, $result);

        $this->removeTempFile($tempFile);

        if ($result === 0) {
            return true;
        } else {
            list($message, $code) = $this->parseFailureOutput($output, $sql);
            throw new MySqlSecureShellExecuteException($message, $code);
        }
    }

    protected function getBaseCommand()
    {
        if (isset($this->baseCmd)) {
            return $this->baseCmd;
        }

        if (is_executable($this->pathToBinary) == false) {
            throw new MySqlSecureShellConfigException("Path to MySQL Secure Shell binary '{$this->pathToBinary}' is not executable");
        }

        if (is_readable($this->pathToUserFile) == false) {
            throw new MySqlSecureShellConfigException("Path to MySQL Secure Shell user '{$this->pathToUserFile}' is not readable file");
        }

        if (is_readable($this->pathToPasswordFile) == false) {
            throw new MySqlSecureShellConfigException("Path to MySQL Secure Shell pass '{$this->pathToPasswordFile}' is not readable file");
        }

        $catUser = '`cat ' . $this->pathToUserFile . '`';
        $catPassword = '`cat ' . $this->pathToPasswordFile . '`';

        $this->baseCmd = $this->pathToBinary
             . " --user=" . $catUser
             . " --password=" . $catPassword
             . " ";

        return $this->baseCmd;
    }

    protected function saveSqlToTempFile($sql)
    {
        $tempFile = $this->pathToTempDir . "/mysqlsecureshell-" . Util::guid();

        if (file_put_contents($tempFile, $sql) === false) {
            throw new \RuntimeException("Cannot save SQL to '{$tempFile}'");
        }

        return $tempFile;
    }

    protected function removeTempFile($tempFile)
    {
        $result = @unlink($tempFile);

        if ($result === false) {
            $this->logger->warn("MySqlSecureShell cannot remove temp query file at '{$tempFile}'");
        }
    }

    protected function parseFailureOutput(array $output, $sql)
    {
        $dbError = $output[0];

        // ERROR 1054 (42S22) at line 1 in file: '/tmp/mysqlsecureshell-B3FBDB14-B676-BA6A-1820-D39F4BBCF41C': Unknown column 'siteid' in 'where clause'
        // ERROR 1146 (42S02) at line 1 in file: '/tmp/mysqlsecureshell-AFBD3ED1-6829-24D1-815E-09CAFB7793AE': Table 'gateway.site2' doesn't exist
        // ERROR 2005 (HY000): Unknown MySQL server host 'localhost2' (110)
        // ERROR 1049 (42000): Unknown database 'gateway2'
        // ERROR 1045 (28000): Access denied for user 'vagrant2'@'localhost' (using password: YES)
        // ERROR 1142 (42000) at line 1 in file: '/tmp/mysqlsecureshell-061BAA6D-8400-6296-54EA-577051DC5904': UPDATE command denied to user 'vagrant2'@'localhost' for table 'site'
        $pattern = "/^ERROR (\d+) \(([0-9A-Z]+)\)( at line (\d+) in file: .+)?: (.+)$/";

        if (preg_match($pattern, $dbError, $matches) === false) {
            throw new \RuntimeException("Invalid regexp used to parse db error: {$pattern}");
        }

        $intErrorCode   = $matches[1];
        $strErrorCode   = $matches[2];
        $errorLineNumber= $matches[4];
        $dbErrorMsg     = $matches[5];

        $exceptionMsg = "MySQL Error {$intErrorCode} ({$strErrorCode})"
                      . " " . $dbErrorMsg;

        if ($errorLineNumber) {
            $errorLineIndex = $errorLineNumber - 1;
            $sqlLines = explode("\n", $sql);

            $startLineIndex = max(0, $errorLineIndex - 3);

            $relevantLines = array_slice($sqlLines, $startLineIndex, 6);
            $lineIndex = $startLineIndex;

            foreach ($relevantLines as $relevantLine) {
                $exceptionMsg .= "\n" . ($lineIndex + 1) . " --> " . $relevantLine;
                $lineIndex += 1;
            }
        }

        return [$exceptionMsg, $intErrorCode];
    }




}
