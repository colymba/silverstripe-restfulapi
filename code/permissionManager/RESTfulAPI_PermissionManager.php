<?php
/**
 * Basic required structure for any RESTfulAPI Permission Manager
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Permission
 */
interface RESTfulAPI_PermissionManager
{
  /**
   * Checks if a given DataObject or Class
   * can be accessed with a given API request by a Member
   * 
   * @param  string|DataObject       $model       Model's classname or DataObject to check permission for
   * @param  DataObject|null         $member      Member to check permission agains
   * @param  string                  $httpMethod  API request HTTP method
   * @return Boolean                              true or false if permission was given or not
   */
  public function checkPermission($model, $member = null, $httpMethod);
}