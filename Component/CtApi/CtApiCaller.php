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
    const API_AUTHENTICATE_PATH = '/authenticate';

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var CtApiAuthenticators
     */
    protected $ctApiAuthenticators;
    
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
     * post document
     * @param string $id
     * @param string $path
     * @param string $body
     * @param array $headers
     * @param string $ctApiAuthenticatorName 
     * @return string http response code 
     */
    public function post(
        $id,        
        $path,
        $body = NULL,
        $headers = [],
        $ctApiAuthenticatorName = 'default'
    ) {


        $token = $this->getToken($ctApiAuthenticatorName);

        $requiredHeaders = [
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: $token"
            ];

        if (is_array($headers)) {
            $requiredHeaders = array_merge($requiredHeaders, $headers);
        }

        $this->logger->debug("ct_api_caller: started posting for document, primary id: $id");

        $url = rtrim($this->url, '/') . '/' . ltrim($path, '/');

        $httpResponseCode = $this->send($url, $requiredHeaders, $body);

        if ($httpResponseCode == 401) {

            $this->setToken($ctApiAuthenticatorName, '');
            $token = $this->getToken($ctApiAuthenticatorName);
            $requiredHeaders['Authorization'] = $token;

            $httpResponseCode = $this->send($url, $requiredHeaders, $body);

        }

        if ($httpResponseCode != 200) {
            throw new CTApiCallerException($httpResponseCode, $response, json_encode($request));
        }

        return $httpResponseCode;
    }

    /*
     * send data to API
     * @param string $url   
     * @param array $headers      
     * @param string $body
     * @return string http response code 
    */
    private function send(
        $url,
        $headers,
        $body
    ) {

        $request = new Curl($url);
        $request->httpheader = $headers;

        if ($body) {
            $request->postfields = $body;
        }

        $response = $request->exec();

        if ($errorNum = $request->errno()) {
            throw new \Exception("ct_api_caller: Failed sending request to '{$url}' with error '{$request->error()}' ({$errorNum})");
        }

        $httpResponseCode = $request->info(CURLINFO_HTTP_CODE);

        return $httpResponseCode;
    }

    /**
     * get given ctApiAuthenticator's token
     *
     * @param string ctApiAuthenticatorName
     * @return string $token 
     */
    private function getToken($ctApiAuthenticatorName) 
    {
        $token = $this->ctApiAuthenticators[$ctApiAuthenticatorName]->getToken();
        if ($token == '') {
            $token = $this->requestToken($ctApiAuthenticatorName);
            $this->setToken($ctApiAuthenticatorName, $token);
        }
        return $token;
    }

    /**
     * set given ctApiAuthenticator's token
     *
     * @param string ctApiAuthenticatorName
     * @param string $token 
     */
    private function setToken(
        $ctApiAuthenticatorName,
        $token
    ) {
        $this->ctApiAuthenticators[$ctApiAuthenticatorName]->setToken($token);
    }

    /**
     * get request token for ctApiAuthenticator
     *
     * @param string ctApiAuthenticatorName
     * @return string $token 
     */
    private function requestToken($ctApiAuthenticatorName) 
    {

        $url = rtrim($this->url, '/') . self::API_AUTHENTICATE_PATH;
        $credentials = $this->ctApiAuthenticators[$ctApiAuthenticatorName]->getCredentials();
        $requiredHeaders = [
                        'siteId' => $credentials['siteId'],
                        'serviceAuth' => $credentials['auth']
                    ];

        $request = new Curl($url);
        $request->httpheader = $requiredHeaders;

        $response = $request->exec();

        if ($errorNum = $request->errno()) {
            throw new \Exception("ct_api_caller: Failed requesting auth to '{$url}' with error '{$request->error()}' ({$errorNum})");
        }

        $httpResponseCode = $request->info(CURLINFO_HTTP_CODE);

        if ($httpResponseCode !=200) {
            throw new CTApiCallerException($httpResponseCode, $response, json_encode($request));
        }

        $responseDetail = json_decode($response);
        return $responseDetail->token;       
    }

    /**
     * add ctApiAuthenticators
     *
     * @param string $ctApiAuthenticatorName
     * @param string $ctApiAuthenticator
     * @return void 
     */
    protected function addAuthenticators(
        $ctApiAuthenticatorName,
        $ctApiAuthenticator
    ) {

        $this->ctApiAuthenticators[$authenticatorName] = $authenticator;
    }

}