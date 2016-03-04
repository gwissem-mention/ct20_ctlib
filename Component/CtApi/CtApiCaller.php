<?php
namespace CTLib\Component\CtApi;

use CTLib\Util\Curl;
use CTLib\Component\CtApi\CTApiCallerException;

/**
 * calling methods for ct api.
 *
 * @author Li Gao <lgao@celltrak.com>
 */
class CtApiCaller
{

    const CURL_EXCEPTION = 'Curl_Exception';

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
     * @return string http response code 
     */
    public function post(
        $activityId,        
        $partialUrl,
        $body = NULL,
        $headers = []
    ) {


        $requiredHeaders = [
            "Accept: application/json",
            "Content-Type: application/json"
            ];

        if (is_array($headers)) {
            $requiredHeaders = array_merge($requiredHeaders, $headers);
        }

        $this->logger->debug("ct_api_caller: started posting for activityId $activityId");

        $url = rtrim($this->url, '/') . '/' . ltrim($partialUrl, '/');

        $request = new Curl($url);
        $request->httpheader = $requiredHeaders;

        if ($body) {
            $request->postfields = $body;
        }

        $response = $request->exec();

        if ($errorNum = $request->errno()) {
            throw new \Exception("ct_api_caller: Failed sending request to '{$url}' with error '{$request->error()}' ({$errorNum})");
        }

        $httpResponseCode = $request->info(CURLINFO_HTTP_CODE);

        if ($httpResponseCode != 200) {
            throw new CTApiCallerException($httpResponseCode, $response, (string) $request);
        }

        return $httpResponseCode;
    }  

}