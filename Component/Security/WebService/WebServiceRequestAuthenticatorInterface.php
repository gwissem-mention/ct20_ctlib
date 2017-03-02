<?php
namespace CTLib\Component\Security\WebService;

use Symfony\Component\HttpFoundation\Request;

/**
 * Defines classes that can be used to authenticate a web service request.
 * @author Mike Turoff
 */
interface WebServiceRequestAuthenticatorInterface
{

    /**
     * Indicates whether this authenticator handles the Request.
     * @param  Request $request
     * @return boolean
     */
    public function isHandledRequest(Request $request);

    /**
     * Indicates whether the Request is authenticated.
     * @param  Request $request
     * @return boolean
     */
    public function isAuthenticatedRequest(Request $request);

    /**
     * Returns Response when authentication fails.
     * @return Response
     */
    public function getAuthenticationFailureResponse();

}
