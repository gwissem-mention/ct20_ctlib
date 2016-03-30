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
     * add ctApiAuthenticator
     *
     * @param string $ctApiAuthenticatorName
     * @param string $ctApiAuthenticator
     * @return void 
     */
    public function addAuthenticator(
        $ctApiAuthenticatorName,
        $ctApiAuthenticator
    ) {

        $this->ctApiAuthenticators[$ctApiAuthenticatorName] = $ctApiAuthenticator;
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

        $result = $this->send($path, $body, $parameters, $method, $ctApiAuthenticatorName);

        return $result;
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

        $result = $this->send($path, $body, $parameters, $method, $ctApiAuthenticatorName);

        return $result;
    }

    /**
     * get from Api
     * @param string $path
     * @param array $parameters
     * @param string $ctApiAuthenticatorName 
     * @return array 
     */
    public function get(        
        $path,
        $parameters = [],
        $ctApiAuthenticatorName = 'default'
    ) {

        $method = 'get';

        $result = $this->send($path, NULL, $parameters, $method, $ctApiAuthenticatorName);

        return $result;
    }

    /**
     * delete from Api
     * @param string $path
     * @param array $parameters
     * @param string $ctApiAuthenticatorName 
     * @return string http response code 
     */
    public function delete(        
        $path,
        $parameters = [],
        $ctApiAuthenticatorName = 'default'
    ) {

        $method = 'delete';
        
        $responseBody = $this->send($path, NULL, $parameters, $method, $ctApiAuthenticatorName);

        return $responseBody;
    }

    /*
     * send data to API
     * @param string $path        
     * @param string $body
     * @param array $parameters
     * @param string $method
     * @param string $ctApiAuthenticatorName       
     * @return array  
    */
    private function send(
        $path,
        $body,
        $parameters,
        $method,
        $ctApiAuthenticatorName
    ) {
        $token = $this->getToken($ctApiAuthenticatorName, false);

        $headers = [
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        ];

        $url = rtrim($this->url, '/') . '/' . ltrim($path, '/');
        $queryString = '';

        if (count($parameters) > 0) {
            $queryString = '?' . http_build_query($parameters);
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

            switch ($httpResponseCode) {
                case 401:
                    $token = $this->getToken($ctApiAuthenticatorName, true);
                    $headers[2] = "Authorization: Bearer $token";
                    $attempts++;
                    break;
                
                case 200:
                    break;
            }

            if ($httpResponseCode == 200) {
                break;
            }
        }

        if ($httpResponseCode != 200) {
            throw new CTApiCallerException($httpResponseCode, $response, json_encode($request));
        }         
      
        return $response;
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
        $getNewOne = false
    ) {

        if ($getNewOne) {
            $token = $this->requestToken($ctApiAuthenticatorName);
            $this->setToken($ctApiAuthenticatorName, $token);            
        } else {
            if (!array_key_exists($ctApiAuthenticatorName, $this->ctApiAuthenticators)) {
                throw new \Exception("ct_api_caller: authenticator {$ctApiAuthenticatorName} not exists");
            }

            $token = $this->ctApiAuthenticators[$ctApiAuthenticatorName]->getToken();
            
            if ($token == NULL) {
                $token = $this->requestToken($ctApiAuthenticatorName);
                $this->setToken($ctApiAuthenticatorName, $token);                 
            }
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

        $queryString = '?' . http_build_query($credentials);
        $url .= $queryString;

        $request = new Curl($url);
        $request->__set('post' , 1);

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



}