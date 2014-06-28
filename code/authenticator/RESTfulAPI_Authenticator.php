<?php
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
interface RESTfulAPI_Authenticator
{
  /**
   * Checks if a request to the API is authenticated
   * 
   * @param  SS_HTTPRequest          $request    HTTP API request
   * @return true|RESTfulAPI_Error               True if token is valid OR RESTfulAPI_Error with details
   */
  public function authenticate(SS_HTTPRequest $request);


  /**
   * Returns the DataObject related to the authenticated request
   * 
   * @param  SS_HTTPRequest          $request    HTTP API request
   * @return null|DataObject                     null if failed or the DataObject related to the request
   */
  public function getOwner(SS_HTTPRequest $request);
}