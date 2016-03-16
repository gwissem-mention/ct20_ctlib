<?php
namespace CTLib\Component\CtApi;

/**
 * API used by CtApiCallerAuthenticator class
 *
 * @author Li Gao <lgao@celltrak.com>
 */
interface CtApiCallerAuthenticator
{
    
    /**
     * get Api credentials.
     *
     * @return array [siteId=>siteId, auth=>authentication]
     */
    public function getCredentials();

    /**
     * get Api site token.
     * @return string $token
     */
    public function getToken();

    /**
     * Set Api token.
     * @param string $token
     * @return void
     */
    public function setToken($token);    

}