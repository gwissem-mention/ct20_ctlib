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
        $this->ctApiAuthenticators = [];
    }

    /**
     * post to Api
     * @param string $path
     * @param string $body
     * @param array $parameters     
     * @param string $ctApiAuthenticatorName 
     * @return string http response code 
     */
    public function post(        
        $path,
        $body = NULL,
        $parameters = [],        
        $ctApiAuthenticatorName = 'default'
    ) {

        $method = 'post';
        $url = rtrim($this->url, '/') . '/' . ltrim($path, '/');

        $result = $this->send($url, $body, $parameters, $ctApiAuthenticatorName);

        return $result['code'];
    }

    /**
     * put to Api
     * @param string $path
     * @param string $body
     * @param array $parameters     
     * @param string $ctApiAuthenticatorName 
     * @return string http response code 
     */
    public function put(        
        $path,
        $body = NULL,
        $parameters = [],
        $ctApiAuthenticatorName = 'default'
    ) {

        $method = 'put';
        $url = rtrim($this->url, '/') . '/' . ltrim($path, '/');

        $result = $this->send($url, $body, $parameters, $method, $ctApiAuthenticatorName);

        return $result['code'];
    }

    /**
     * get from Api
     * @param string $path
     * @param string $body
     * @param array $parameters
     * @param string $ctApiAuthenticatorName 
     * @return array 
     */
    public function get(        
        $path,
        $body = NULL,
        $parameters = [],
        $ctApiAuthenticatorName = 'default'
    ) {

        $method = 'get';
        $url = rtrim($this->url, '/') . '/' . ltrim($path, '/');

        $result = $this->send($url, $body, $parameters, $method, $ctApiAuthenticatorName);

        return $result;
    }

    /**
     * delete from Api
     * @param string $path
     * @param string $body
     * @param array $parameters
     * @param string $ctApiAuthenticatorName 
     * @return string http response code 
     */
    public function delete(        
        $path,
        $body = NULL,
        $parameters = [],
        $ctApiAuthenticatorName = 'default'
    ) {

        $method = 'delete';
        $url = rtrim($this->url, '/') . '/' . ltrim($path, '/');

        $result = $this->send($url, $body, $parameters, $method, $ctApiAuthenticatorName);

        return $result['code'];
    }

    /*
     * send data to API
     * @param string $url        
     * @param string $body
     * @param array $parameters
     * @param string $method
     * @param string $ctApiAuthenticatorName       
     * @return array  
    */
    private function send(
        $url,
        $body,
        $parameters,
        $method,
        $ctApiAuthenticatorName
    ) {

        $token = $this->getToken($ctApiAuthenticatorName, false);

        $headers = [
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: $token"
            ];

        $queryString = '';
        foreach ($parameters as $key => $value) {
            $queryString .= "&$key=" . urlencode($value); 
        }

        if (count($parameters) > 0) {
            $queryString = '?' . ltrim($queryString, '&');
            $url .= $queryString;
        }

        $attempts = 0;

        while ($attempts <= 1) {
            $request = new Curl($url);        
            $request->__set($method , 1);

            if ($body) {
                $request->postfields = $body;
            }

            $response = $request->exec();

            if ($errorNum = $request->errno()) {
                throw new \Exception("ct_api_caller: Failed sending request to '{$url}' with error '{$request->error()}' ({$errorNum})");
            }

            $httpResponseCode = $request->info(CURLINFO_HTTP_CODE);

            if ($httpResponseCode == 401) {

                $token = $this->getToken($ctApiAuthenticatorName, true);
                $headers['Authorization'] = $token;
                $attempts++;
                continue;
            } else if ($httpResponseCode == 200) {
                break;
            }
        }

        if ($httpResponseCode != 200) {
            throw new CTApiCallerException($httpResponseCode, $response, json_encode($request));
        }         
      
        return ['code' => $httpResponseCode,
                'body' => $response
            ];
    }

    /**
     * get given ctApiAuthenticator's token
     *
     * @param string ctApiAuthenticatorName
     * @param boolean $getNewOne
     * @return string $token 
     */
    private function getToken(
        $ctApiAuthenticatorName,
        $getNewOne
    ) {

        if ($getNewOne) {
            $token = $this->requestToken($ctApiAuthenticatorName);
            $this->setToken($ctApiAuthenticatorName, $token);            
        } else {
            if (!array_key_exists($ctApiAuthenticatorName, $this->ctApiAuthenticators)) {
                throw new \Exception("ct_api_caller: authenticator {$ctApiAuthenticatorName} not exists");
            }

            $token = $this->ctApiAuthenticators[$ctApiAuthenticatorName]->getToken();
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

        if (!array_key_exists($ctApiAuthenticatorName, $this->ctApiAuthenticators)) {
            throw new \Exception("ct_api_caller: authenticator {$ctApiAuthenticatorName} not exists");
        }

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

        if (!array_key_exists($ctApiAuthenticatorName, $this->ctApiAuthenticators)) {
            throw new \Exception("ct_api_caller: authenticator {$ctApiAuthenticatorName} not exists");
        }

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
    public function addAuthenticators(
        $ctApiAuthenticatorName,
        $ctApiAuthenticator
    ) {

        $this->ctApiAuthenticators[$authenticatorName] = $authenticator;
    }

}