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
   * @param  SS_HTTPRequest   $request    HTTP API request
   * @return array 												authentication result:
   * array(
   * 	'valid' => boolean  // true if the request is authorize
   * 	'message' => string // message to return to the client
   * 	'code' => integer   // response code associated with result if any
   * )
   */
  public function authenticate(SS_HTTPRequest $request);
}