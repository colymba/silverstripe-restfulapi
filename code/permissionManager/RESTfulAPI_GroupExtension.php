<?php
/**
 * Group extension used to create the defaults API Groups
 * - API Admin   => ALL ACCESS
 * - API Editor  => VIEW + EDIT + CREATE
 * - API Reader  => VIEW
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Permission
 */
class RESTfulAPI_GroupExtension extends DataExtension implements PermissionProvider
{
  /**
   * Basic RESTfulAPI Permission set
   * 
   * @return Array Default API permission set
   */
  public function providePermissions()
  {
    return array(
      'RESTfulAPI_VIEW' => array(
        'name'     => 'Access records through the RESTful API',
        'category' => 'RESTful API Access',
        'help'     => 'Allow for a user to access/view record(s) through the API'
      ),
      'RESTfulAPI_EDIT' => array(
        'name'     => 'Edit records through the RESTful API',
        'category' => 'RESTful API Access',
        'help'     => 'Allow for a user to submit a record changes through the API'
      ),
      'RESTfulAPI_CREATE' => array(
        'name'     => 'Create records through the RESTful API',
        'category' => 'RESTful API Access',
        'help'     => 'Allow for a user to create a new record through the API'
      ),
      'RESTfulAPI_DELETE' => array(
        'name'     => 'Delete records through the RESTful API',
        'category' => 'RESTful API Access',
        'help'     => 'Allow for a user to delete a record through the API'
      )
    );
  }


  /**
   * Create the default Groups 
   * and add default admin to admin group
   */
  public function requireDefaultRecords()
  {
    // Readers
    $readersGroup = DataObject::get('Group')->filter(array(
      'Code' => 'restfulapi-readers'
    ));

    if ( !$readersGroup->count() )
    {
      $readerGroup = new Group();
      $readerGroup->Code  = 'restfulapi-readers';
      $readerGroup->Title = 'RESTful API Readers';
      $readerGroup->Sort  = 0;
      $readerGroup->write();
      Permission::grant($readerGroup->ID, 'RESTfulAPI_VIEW');
    }

    // Editors
    $editorsGroup = DataObject::get('Group')->filter(array(
      'Code' => 'restfulapi-editors'
    ));

    if ( !$editorsGroup->count() )
    {
      $editorGroup = new Group();
      $editorGroup->Code  = 'restfulapi-editors';
      $editorGroup->Title = 'RESTful API Editors';
      $editorGroup->Sort  = 0;
      $editorGroup->write();
      Permission::grant($editorGroup->ID, 'RESTfulAPI_VIEW');
      Permission::grant($editorGroup->ID, 'RESTfulAPI_EDIT');
      Permission::grant($editorGroup->ID, 'RESTfulAPI_CREATE');
    }

    // Admins
    $adminsGroup = DataObject::get('Group')->filter(array(
      'Code' => 'restfulapi-administrators'
    ));

    if ( !$adminsGroup->count() )
    {
      $adminGroup = new Group();
      $adminGroup->Code  = 'restfulapi-administrators';
      $adminGroup->Title = 'RESTful API Administrators';
      $adminGroup->Sort  = 0;
      $adminGroup->write();
      Permission::grant($adminGroup->ID, 'RESTfulAPI_VIEW');
      Permission::grant($adminGroup->ID, 'RESTfulAPI_EDIT');
      Permission::grant($adminGroup->ID, 'RESTfulAPI_CREATE');
      Permission::grant($adminGroup->ID, 'RESTfulAPI_DELETE');
    }
  }
}