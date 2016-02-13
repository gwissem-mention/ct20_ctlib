<?php
namespace CTLib\Component\Monolog;

/**
 * Remove database password from database connection error log record.
 *
 * @author Ziwei Ren <zren@celltrak.com>
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
            if (strpos($record['message'], 'PDO->__construct(') !== false) {
                $connectionStr = 
                    strstr(
                        strstr($record['message'], 'PDO->__construct('),
                        ')',
                        true
                    );
                
                if ($connectionStr) {
                    $replaceStr = '**********';
                    $connectionStrArray = explode(',', $connectionStr);
                    $password = $connectionStrArray[2];
                    $record['message'] = str_replace(
                        $password, 
                        $replaceStr, 
                        $record['message']
                    );
                }
            }
            
            if (strpos($record['message'], 'DriverPDOConnection->__construct(') !== false) {
                $connectionStr = 
                    strstr(
                        strstr($record['message'], 'DriverPDOConnection->__construct('),
                        ')',
                        true
                    );
                
                if ($connectionStr) {
                    $replaceStr = '**********';
                    $connectionStrArray = explode(',', $connectionStr);
                    $password = $connectionStrArray[2];
                    $record['message'] = str_replace(
                        $password, 
                        $replaceStr, 
                        $record['message']
                    );
                }
            }
            
            if (strpos($record['message'], 'PDOMySqlDriver->connect') !== false) {
                $connectionStr = 
                    strstr(
                        strstr($record['message'], 'PDOMySqlDriver->connect'),
                        ')', 
                        true);
                
                if ($connectionStr) {
                    $replaceStr = '**********';
                    $connectionStrArray = explode(',', $connectionStr);
                    $password = $connectionStrArray[2];
                    $record['message'] = str_replace(
                        $password, 
                        $replaceStr, 
                        $record['message']
                    );
                }
            }
        }
        
        return $record;
    }
}