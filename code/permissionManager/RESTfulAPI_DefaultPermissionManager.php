<?php
/**
 * Default RESTfulAPI Permission Manager
 * Matches the request HTTP method with the DataObject can() method.
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Permission
 */
class RESTfulAPI_DefaultPermissionManager implements RESTfulAPI_PermissionManager
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
  public function checkPermission($model, $member = null, $httpMethod)
  {
    if ( is_string($model) ) $model = singleton($model);

    // check permission depending on HTTP verb
    // default to true
    switch ( strtoupper($httpMethod) )
    {
      case 'GET':
        return $model->canView($member);
        break;

      case 'POST':        
        return $model->canCreate($member);
        break;

      case 'PUT':
        return $model->canEdit($member);
        break;

      case 'DELETE':
        return $model->canDelete($member);
        break;
      
      default:
        return true;
        break;
    }
  }
}