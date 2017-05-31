<?php
namespace CTLib\Component\MySqlSecureShell;

use CTLib\Component\Monolog\Logger;
use CTLib\Util\Util;


/**
 * Utility for executing queries through MySQL command line using securely
 * accessed database user credentials.
 *
 * @author Mike Turoff
 */
class MySqlSecureShell
{

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     * Path to file that contains the database username.
     */
    protected $pathToUsernameFile;

    /**
     * @var string
     * Path to file that contains the database password.
     */
    protected $pathToPasswordFile;

    /**
     * @var string
     * Path to mysql client binary.
     */
    protected $pathToBinary;

    /**
     * @var string
     * Path to temp directory where the SQL scripts will be temporarily written.
     */
    protected $pathToTempDir;


    /**
     * @param Logger $logger
     * @param string $pathToUsernameFile
     * @param string $pathToPasswordFile
     * @param string $pathToBinary
     * @param string $pathToTempDir
     */
    public function __construct(
        Logger $logger,
        $pathToUsernameFile,
        $pathToPasswordFile,
        $pathToBinary,
        $pathToTempDir
    ) {
        $this->logger               = $logger;
        $this->pathToUsernameFile   = $pathToUsernameFile;
        $this->pathToPasswordFile   = $pathToPasswordFile;
        $this->pathToBinary         = $pathToBinary;
        $this->pathToTempDir        = $pathToTempDir;
    }

    /**
     * Execute one or more SQL queries.
     * @param string $sql
     * @param string $dbHost
     * @param string $dbName
     * @return void
     * @throws InvalidArgumentException
     * @throws MySqlSecureShellExecuteException
     * @throws MySqlSecureShellConfigException
     */
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
            $cmd .= " --database=" . $dbName;
        }

        $cmd .= " --execute='source {$tempFile}'";

        $cmd .= " 2>&1";

        $output = null;
        exec($cmd, $output, $result);

        $this->removeTempFile($tempFile);

        if ($result === 1) {
            list($message, $code) = $this->parseFailureOutput($output, $sql);
            throw new MySqlSecureShellExecuteException($message, $code);
        }
    }

    /**
     * Returns base execution command string based on the configuration.
     * @return string
     * @throws MySqlSecureShellConfigException
     */
    protected function getBaseCommand()
    {
        if (isset($this->baseCmd)) {
            return $this->baseCmd;
        }

        if (is_executable($this->pathToBinary) == false) {
            throw new MySqlSecureShellConfigException("MySQL client binary '{$this->pathToBinary}' is not executable");
        }

        if (is_readable($this->pathToUsernameFile) == false) {
            throw new MySqlSecureShellConfigException("Username file '{$this->pathToUsernameFile}' is not readable");
        }

        if (is_readable($this->pathToPasswordFile) == false) {
            throw new MySqlSecureShellConfigException("Password file '{$this->pathToPasswordFile}' is not readable");
        }

        $catUsername = '`cat ' . $this->pathToUsernameFile . '`';
        $catPassword = '`cat ' . $this->pathToPasswordFile . '`';

        $this->baseCmd = $this->pathToBinary
             . " --user=" . $catUsername
             . " --password=" . $catPassword
             . " ";

        return $this->baseCmd;
    }

    /**
     * Saves SQL script to temporary file.
     * @param string $sql
     * @return string   Path to temporary file.
     * @throws MySqlSecureShellConfigException
     * @throws RuntimeException
     */
    protected function saveSqlToTempFile($sql)
    {
        if (is_dir($this->pathToTempDir)) {
            if (is_writable($this->pathToTempDir) == false) {
                throw new MySqlSecureShellConfigException("Temporary directory '{$this->pathToTempDir}' is not writeable");
            }
        } else {
            if (mkdir($this->pathToTempDir, 0750, true) == false) {
                throw new MySqlSecureShellConfigException("Cannot create temporary directory '{$this->pathToTempDir}'");
            }
        }

        $tempFile = $this->pathToTempDir . "/mysqlsecureshell-" . Util::guid();

        if (file_put_contents($tempFile, $sql) === false) {
            throw new \RuntimeException("Cannot save SQL to '{$tempFile}'");
        }

        return $tempFile;
    }

    /**
     * Removes temporary file.
     * @param string $tempFile
     * @return void
     */
    protected function removeTempFile($tempFile)
    {
        $result = @unlink($tempFile);

        if ($result === false) {
            $this->logger->warn("MySqlSecureShell cannot remove temp query file at '{$tempFile}'");
        }
    }

    /**
     * Parses failure command output message into exception code and message.
     * @param array $output
     * @param string $sql
     * @return array [$exceptionMsg, $code]
     */
    protected function parseFailureOutput(array $output, $sql)
    {
        // Parse the error string returned by the mysql command line. Here are
        // a bunch of examples:
        //
        // ERROR 1054 (42S22) at line 1 in file: '/tmp/mysqlsecureshell-B3FBDB14-B676-BA6A-1820-D39F4BBCF41C': Unknown column 'siteid' in 'where clause'
        // ERROR 1146 (42S02) at line 1 in file: '/tmp/mysqlsecureshell-AFBD3ED1-6829-24D1-815E-09CAFB7793AE': Table 'gateway.site2' doesn't exist
        // ERROR 2005 (HY000): Unknown MySQL server host 'localhost2' (110)
        // ERROR 1049 (42000): Unknown database 'gateway2'
        // ERROR 1045 (28000): Access denied for user 'vagrant2'@'localhost' (using password: YES)
        // ERROR 1142 (42000) at line 1 in file: '/tmp/mysqlsecureshell-061BAA6D-8400-6296-54EA-577051DC5904': UPDATE command denied to user 'vagrant2'@'localhost' for table 'site'

        $dbError = $output[0];
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
                $exceptionMsg .= "\n  LINE " . ($lineIndex + 1) . ": " . $relevantLine;
                $lineIndex += 1;
            }
        }

        return [$exceptionMsg, $intErrorCode];
    }

}
