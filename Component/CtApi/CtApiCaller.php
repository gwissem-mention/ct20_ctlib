<?php
namespace CTLib\Component\CtApi;

use CTLib\Util\Curl;
use CTLib\Component\CtApi\CtApiCallerException;

/**
 * calling methods for ct api.
 *
 * @author Li Gao <lgao@celltrak.com>
 */
class CtApiCaller
{

    /**
     * Path used to authenticate caller and retrieve a persistent API token.
     */
    const API_AUTHENTICATE_PATH = '/authenticate';


    /**
     * @var string
     */
    protected $apiUrl;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $authenticators;

    /**
     * @param string $apiUrl
     * @param Logger logger
     */
    public function __construct($apiUrl, $logger)
    {
        $this->apiUrl           = rtrim($apiUrl, '/');
        $this->logger           = $logger;
        $this->authenticators   = [];
    }

    /**
     * Adds API Authenticator.
     *
     * @param string $authenticatorName
     * @param CtApiCallerAuthenticator $authenticator
     * @return void
     */
    public function addAuthenticator(
        $authenticatorName,
        CtApiCallerAuthenticator $authenticator
    ) {
        $this->authenticators[$authenticatorName] = $authenticator;
    }

    /**
     * Sends GET requests.
     * @param string $path
     * @param array $parameters
     * @param string $authenticatorName
     * @return string Response body.
     */
    public function get(
        $path,
        array $parameters = [],
        $authenticatorName = 'default'
    ) {
        $method = 'GET';
        $response = $this->send($path, NULL, $parameters, $method, $authenticatorName);
        return $response;
    }

    /**
     * Sends POST request.
     * @param string $path
     * @param mixed $body
     * @param array $parameters
     * @param string $authenticatorName
     * @return string Response body.
     */
    public function post(
        $path,
        $body = NULL,
        array $parameters = [],
        $authenticatorName = 'default'
    ) {
        $method = 'POST';
        $response = $this->send($path, $body, $parameters, $method, $authenticatorName);
        return $response;
    }

    /**
     * Sends PUT request.
     * @param string $path
     * @param mixed $body
     * @param array $parameters
     * @param string $authenticatorName
     * @return string Response body.
     */
    public function put(
        $path,
        $body = NULL,
        array $parameters = [],
        $authenticatorName = 'default'
    ) {
        $method = 'PUT';
        $response = $this->send($path, $body, $parameters, $method, $authenticatorName);
        return $response;
    }

    /**
     * Sends PATCH request.
     * @param string $path
     * @param mixed $body
     * @param array $parameters
     * @param string $authenticatorName
     * @return string Response body.
     */
    public function patch(
        $path,
        $body = NULL,
        array $parameters = [],
        $authenticatorName = 'default'
    ) {
        $method = 'PATCH';
        $response = $this->send($path, $body, $parameters, $method, $authenticatorName);
        return $response;
    }

    /**
     * Sends DELETE request.
     * @param string $path
     * @param array $parameters
     * @param string $authenticatorName
     * @return string Response body.
     */
    public function delete(
        $path,
        array $parameters = [],
        $authenticatorName = 'default'
    ) {
        $method = 'DELETE';
        $response = $this->send($path, NULL, $parameters, $method, $authenticatorName);
        return $response;
    }

    /**
     * Helper method to send all API requests.
     * @param string $path
     * @param mixed $body
     * @param array $parameters
     * @param string $method
     * @param string $authenticatorName
     * @return string Response body.
     */
    protected function send(
        $path,
        $body,
        array $parameters,
        $method,
        $authenticatorName
    ) {
        // All API requests need to have a valid token. Use the specified
        // CtApiCallerAuthenticator to facilitate the retrieval of this token.
        $authenticator = $this->getAuthenticator($authenticatorName);

        // Get the API token from the authenticator.
        $token = $authenticator->getToken();

        if (!$token) {
            // No API token set, yet. Need to request a new one.
            $this->logger->debug("CtApiCaller: no API token set; requesting a new one");

            // Use this authenticator's credentials to request a new token.
            $credentials = $authenticator->getCredentials();
            $token = $this->requestApiToken($credentials);

            $this->logger->debug("CtApiCaller: new API token = {$token}");

            // Save token for future requests.
            $authenticator->setToken($token);
        }

        $request = $this->buildRequest($path, $body, $parameters, $method, $token);
        $response = $request->exec();

        if ($errorNum = $request->errno()) {
            throw new \Exception("ct_api_caller: Failed sending request to '{$path}' with error '{$request->error()}' ({$errorNum})");
        }

        $httpResponseCode = $request->info(CURLINFO_HTTP_CODE);

        switch ($httpResponseCode) {
            case 200:
                return $response;

            case 401:
                // Token expired. Invalidate existing token so next attempt will
                // request a new one.
                $this->logger->debug("CtApiCaller: API token has expired; invaliding and resending request");

                $authenticator->setToken(null);
                return $this->send($path, $body, $parameters, $method, $authenticatorName);

            default:
                // Any other response code is an exception case.
                throw new CtApiCallerException($httpResponseCode, $response, $request);
        }
    }

    /**
     * Builds API request.
     * @param  string $path
     * @param  mixed $body
     * @param  array $parameters
     * @param  string $method
     * @param  string $token
     * @return Curl
     */
    protected function buildRequest(
        $path,
        $body,
        array $parameters,
        $method,
        $token
    ) {
        $headers = [
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        ];

        $url = $this->apiUrl . '/' . ltrim($path, '/');

        if ($parameters) {
            $queryString = '?' . http_build_query($parameters);
            $url .= $queryString;
        }

        $request = new Curl($url);
        $request->httpheader = $headers;

        switch (strtoupper($method)) {
            case 'GET':
                $request->httpget = true;
                break;
            case 'POST':
                $request->post = true;
                break;
            case 'PUT':
                $request->customrequest = 'PUT';
                break;
            case 'PATCH':
                $request->customrequest = 'PATCH';
                break;
            case 'DELETE':
                $request->customrequest = 'DELETE';
                break;
            default:
                throw new \InvalidArgumentException("'{$method}' is not a supported request method");
        }

        if ($body) {
            if (!is_string($body)) {
                $body = json_encode($body);
            }

            $request->postfields = $body;
        }

        return $request;
    }

    /**
     * Sends authentication request to API to retrieve new API token.
     *
     * @param array $credentials
     * @return string $token
     */
    protected function requestApiToken($credentials)
    {
        $url = $this->apiUrl . self::API_AUTHENTICATE_PATH;

        $headers = [
            "Accept: application/json",
            "Content-Type: application/json"
        ];

        $request = new Curl($url);
        $request->httpheader = $headers;
        $request->post = true;
        $request->postfields = json_encode($credentials);

        $response = $request->exec();

        if ($errorNum = $request->errno()) {
            throw new \Exception("ct_api_caller: Failed requesting auth to '{$url}' with error '{$request->error()}' ({$errorNum})");
        }

        $httpResponseCode = $request->info(CURLINFO_HTTP_CODE);

        if ($httpResponseCode != 200) {
            throw new CtApiCallerException($httpResponseCode, $response, $request);
        }

        $decodedResponse = json_decode($response);
        return $decodedResponse->token;
    }

    /**
     * Returns caller authenticator registered to name.
     * @param  string $authenticatorName
     * @return CtApiCallerAuthenticator
     */
    protected function getAuthenticator($authenticatorName)
    {
        if (!isset($this->authenticators[$authenticatorName])) {
            throw new \InvalidArgumentException("ct_api_caller: authenticator '{$authenticatorName}' does not exist");
        }

        return $this->authenticators[$authenticatorName];
    }

}