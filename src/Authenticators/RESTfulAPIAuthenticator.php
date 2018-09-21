<?php

namespace colymba\RESTfulAPI\Authenticators;

use SilverStripe\Control\HTTPRequest;

/**
 * Basic required structure for any RESTfulAPI Authenticator
 *
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 *
 * @package RESTfulAPI
 * @subpackage Authentication
 */
interface RESTfulAPIAuthenticator
{
    /**
     * Checks if a request to the API is authenticated
     *
     * @param  HTTPRequest          $request    HTTP API request
     * @return true|RESTfulAPIError               True if token is valid OR RESTfulAPIError with details
     */
    public function authenticate(HTTPRequest $request);

    /**
     * Returns the DataObject related to the authenticated request
     *
     * @param  HTTPRequest          $request    HTTP API request
     * @return null|DataObject                     null if failed or the DataObject related to the request
     */
    public function getOwner(HTTPRequest $request);
}
