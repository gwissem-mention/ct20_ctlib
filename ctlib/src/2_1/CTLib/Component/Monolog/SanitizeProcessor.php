<?php
namespace CTLib\Component\Monolog;

/**
 * Remove database password from database connection error log record.
 *
 */
class SanitizeProcessor
{
        
    /**
     * Remove database password from database connection error log record.
     *
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        if ($record['message']) {
            if(strstr($record['message'], 'PDO->__construct(') && strstr($record['message'], 'DriverPDOConnection->__construct(') && strstr($record['message'], 'PDOMySqlDriver->connect') ) {
                $connectionStr = strstr(strstr($record['message'], 'PDO->__construct('), ')', true);
                if($connectionStr) {
                    $connectionStrArray = explode(',', $connectionStr);
                    $password = $connectionStrArray[2];
                    $record['message'] = str_replace($password, '', $record['message']);
                }
            }
        }
        
        return $record;
    }
}