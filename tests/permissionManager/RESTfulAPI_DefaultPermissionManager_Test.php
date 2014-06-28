<?php
/**
 * Default Permission Manager Test suite
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Tests
 */
class RESTfulAPI_DefaultPermissionManager_Test extends RESTfulAPI_Tester
{
  protected $requiredExtensions = array(
    'Group' => array('RESTfulAPI_GroupExtension')
  );

  protected $extraDataObjects = array(
    'ApiTest_Library'
  );

  protected function getAuthenticator()
  {
    $injector = new Injector();
    $auth     = new RESTfulAPI_TokenAuthenticator();

    $injector->inject($auth);

    return $auth;
  }


  public function setUpOnce()
  {
    parent::setUpOnce();

    Member::create(array(
      'Email' => 'admin@api.com',
      'Password' => 'admin'
    ))->write();
    $member = Member::get()->filter(array(
      'Email' => 'admin@api.com'
    ))->first();
    $member->addToGroupByCode('restfulapi-administrators');

    Member::create(array(
      'Email' => 'editor@api.com',
      'Password' => 'editor'
    ))->write();
    $member = Member::get()->filter(array(
      'Email' => 'editor@api.com'
    ))->first();
    $member->addToGroupByCode('restfulapi-editors');

    Member::create(array(
      'Email' => 'reader@api.com',
      'Password' => 'reader'
    ))->write();
    $member = Member::get()->filter(array(
      'Email' => 'reader@api.com'
    ))->first();
    $member->addToGroupByCode('restfulapi-readers');

    Member::create(array(
      'Email' => 'stranger@api.com',
      'Password' => 'stranger'
    ))->write();
  }


  /* **********************************************************
   * TESTS
   * */

  /**
   * Checks that the API respects the permissions
   * set on the DataObject can() methods
   */
  public function testPermissions()
  {
    Config::inst()->update('RESTfulAPI', 'access_control_policy', 'ACL_CHECK_MODEL_ONLY');
    Config::inst()->update('RESTfulAPI', 'cors', array(
      'Enabled'       => false
    ));    
    
    // GET with permission = OK
    $response = Director::test('api/auth/login?email=admin@api.com&pwd=admin');
    $json = json_decode($response->getBody());
    $requestHeaders = $this->getRequestHeaders();
    $requestHeaders['X-Silverstripe-Apitoken'] = $json->token;

    $response = Director::test('api/ApiTest_Library/1', null, null, 'GET', null, $requestHeaders);

    $this->assertEquals(
      $response->getStatusCode(),
      200,
      "Member of 'restfulapi-administrators' Group should see result."
    );
    
    // GET with NO Permission = BAD
    $response = Director::test('api/auth/login?email=stranger@api.com&pwd=stranger');
    $json = json_decode($response->getBody());
    $requestHeaders = $this->getRequestHeaders();
    $requestHeaders['X-Silverstripe-Apitoken'] = $json->token;
    
    $response = Director::test('api/ApiTest_Library/1', null, null, 'GET', null, $requestHeaders);

    $this->assertEquals(
      $response->getStatusCode(),
      403,
      "Member without permission should NOT see result."
    );
  }
}