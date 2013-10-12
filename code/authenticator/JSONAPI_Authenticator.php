<?php
/**
 * Basic required structure for any API Authenticator
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package SS_JSONAPI
 * @subpackage Authentication
 */
interface JSONAPI_Authenticator
{
	/**
   * Login a user into the Framework and generates API token
   * 
   * @param  SS_HTTPRequest   $request  HTTP request containing 'email' & 'pwd' vars
   */
   public function login(SS_HTTPRequest $request);

  /**
   * Logout a user and update member's API token with an expired one
   * 
   * @param  SS_HTTPRequest   $request    HTTP request containing 'email' var
   */
  public function logout(SS_HTTPRequest $request);

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