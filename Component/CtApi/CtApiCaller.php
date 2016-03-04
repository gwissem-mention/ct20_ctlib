<?php
namespace CTLib\Component\CtApi;


/**
 * calling methods for ct api.
 *
 * @author Li Gao <lgao@celltrak.com>
 */
class ApiCaller
{

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $url;

    
    /**
     * @param Logger logger
     * @param string $url
     */
    public function __construct(
        $logger,
        $url
        ) {

        $this->logger = $logger;  
        $this->url = $url;
    }

    /**
     * post activity document
     * @param integer $activityId
     * @param string $patialURL
     * @param string $body
     * @param array $headers     
     * @return boolean 
     */
    public function post(
        $activityId,        
        $partialUrl,
        $body = '',
        $headers = array()
        ) {

        try {

            $requiredHeaders = array(
                "Accept: application/json",
                "Content-Type: application/json"
                );

            if (is_array($headers)) {
                $requiredHeaders = array_merge($requiredHeaders, $headers);

            }

            $this->logger->info("ct_api_caller: started posting for activityId $activityId");     

            $postedSuccessful = false;

            $url = rtrim($this->url, '/') . '/' . ltrim($partialUrl, '/');
            $ch = curl_init();
    
            curl_setopt ( $ch , CURLOPT_URL, $url);
            curl_setopt ( $ch , CURLOPT_RETURNTRANSFER , 1 );
            curl_setopt ( $ch , CURLOPT_FAILONERROR, 1);
            curl_setopt ( $ch , CURLOPT_VERBOSE , 0 );
            curl_setopt ( $ch , CURLOPT_HEADER , 1 );
    
            curl_setopt ( $ch , CURLOPT_HTTPHEADER, $requiredHeaders );
            curl_setopt ( $ch , CURLOPT_POST, true );
            curl_setopt ( $ch , CURLOPT_POSTFIELDS, $body );
    
            $response = curl_exec($ch);
            $position = strpos($response,"HTTP/1.1 200 OK");
            if ($position!==false) {
                $postedSuccessful = true;
            }    

            if ($postedSuccessful) {
                $this->logger->info("ct_api_caller: finished posting for activityId $activityId");  
                return true;
            } else {
                $this->logger->info("ct_api_caller: failed posting for activityId $activityId. returned response: $response");
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error("ct_api_caller: failed posting for activityId $activityId. curl exception: " . $e->getMessage());
            return false;

        }
    }

}