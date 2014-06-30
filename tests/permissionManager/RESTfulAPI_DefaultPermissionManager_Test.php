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
  protected $extraDataObjects = array(
    'ApiTest_Library'
  );

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
      'Email' => 'stranger@api.com',
      'Password' => 'stranger'
    ))->write();
  }

  protected function getAdminToken()
  {
    $response = Director::test('api/auth/login?email=admin@api.com&pwd=admin');
    $json     = json_decode($response->getBody());
    return $json->token;
  }

  protected function getStrangerToken()
  {
    $response = Director::test('api/auth/login?email=stranger@api.com&pwd=stranger');
    $json     = json_decode($response->getBody());
    return $json->token;
  }

  /* **********************************************************
   * TESTS
   * */

  /**
   * Test READ permissions are honoured
   */
  public function testReadPermissions()
  {
    Config::inst()->update('RESTfulAPI', 'access_control_policy', 'ACL_CHECK_MODEL_ONLY');
    Config::inst()->update('RESTfulAPI', 'cors', array(
      'Enabled' => false
    ));    
    
    // GET with permission = OK
    $requestHeaders = $this->getRequestHeaders();
    $requestHeaders['X-Silverstripe-Apitoken'] = $this->getAdminToken();
    $response = Director::test('api/ApiTest_Library/1', null, null, 'GET', null, $requestHeaders);

    $this->assertEquals(
      $response->getStatusCode(),
      200,
      "Member of 'restfulapi-administrators' Group should be able to READ records."
    );
    
    // GET with NO Permission = BAD
    $requestHeaders = $this->getRequestHeaders();
    $requestHeaders['X-Silverstripe-Apitoken'] = $this->getStrangerToken();    
    $response = Director::test('api/ApiTest_Library/1', null, null, 'GET', null, $requestHeaders);

    $this->assertEquals(
      $response->getStatusCode(),
      403,
      "Member without permission should NOT be able to READ records."
    );
  }

  /**
   * Test EDIT permissions are honoured
   */
  public function testEditPermissions()
  {
    Config::inst()->update('RESTfulAPI', 'access_control_policy', 'ACL_CHECK_MODEL_ONLY');
    Config::inst()->update('RESTfulAPI', 'cors', array(
      'Enabled' => false
    ));    
    
    // PUT with permission = OK
    $requestHeaders = $this->getRequestHeaders();
    $requestHeaders['X-Silverstripe-Apitoken'] = $this->getAdminToken();
    $response = Director::test('api/ApiTest_Library/1', null, null, 'PUT', '{"Name":"Api"}', $requestHeaders);

    $this->assertEquals(
      $response->getStatusCode(),
      200,
      "Member of 'restfulapi-administrators' Group should be able to EDIT records."
    );
    
    // PUT with NO Permission = BAD
    $requestHeaders = $this->getRequestHeaders();
    $requestHeaders['X-Silverstripe-Apitoken'] = $this->getStrangerToken();    
    $response = Director::test('api/ApiTest_Library/1', null, null, 'PUT', '{"Name":"Api"}', $requestHeaders);

    $this->assertEquals(
      $response->getStatusCode(),
      403,
      "Member without permission should NOT be able to EDIT records."
    );
  }

  /**
   * Test CREATE permissions are honoured
   */
  public function testCreatePermissions()
  {
    Config::inst()->update('RESTfulAPI', 'access_control_policy', 'ACL_CHECK_MODEL_ONLY');
    Config::inst()->update('RESTfulAPI', 'cors', array(
      'Enabled' => false
    ));    
    
    // POST with permission = OK
    $requestHeaders = $this->getRequestHeaders();
    $requestHeaders['X-Silverstripe-Apitoken'] = $this->getAdminToken();
    $response = Director::test('api/ApiTest_Library', null, null, 'POST', '{"Name":"Api"}', $requestHeaders);
    
    $this->assertEquals(
      $response->getStatusCode(),
      200,
      "Member of 'restfulapi-administrators' Group should be able to CREATE records."
    );
    
    // POST with NO Permission = BAD
    $requestHeaders = $this->getRequestHeaders();
    $requestHeaders['X-Silverstripe-Apitoken'] = $this->getStrangerToken();    
    $response = Director::test('api/ApiTest_Library', null, null, 'POST', '{"Name":"Api"}', $requestHeaders);

    $this->assertEquals(
      $response->getStatusCode(),
      403,
      "Member without permission should NOT be able to CREATE records."
    );
  }

  /**
   * Test DELETE permissions are honoured
   */
  public function testDeletePermissions()
  {
    Config::inst()->update('RESTfulAPI', 'access_control_policy', 'ACL_CHECK_MODEL_ONLY');
    Config::inst()->update('RESTfulAPI', 'cors', array(
      'Enabled' => false
    ));    
    
    // DELETE with permission = OK
    $requestHeaders = $this->getRequestHeaders();
    $requestHeaders['X-Silverstripe-Apitoken'] = $this->getAdminToken();
    $response = Director::test('api/ApiTest_Library/1', null, null, 'DELETE', null, $requestHeaders);
    
    $this->assertEquals(
      $response->getStatusCode(),
      200,
      "Member of 'restfulapi-administrators' Group should be able to DELETE records."
    );
    
    // DELETE with NO Permission = BAD
    $requestHeaders = $this->getRequestHeaders();
    $requestHeaders['X-Silverstripe-Apitoken'] = $this->getStrangerToken();    
    $response = Director::test('api/ApiTest_Library/1', null, null, 'DELETE', null, $requestHeaders);

    $this->assertEquals(
      $response->getStatusCode(),
      403,
      "Member without permission should NOT be able to DELETE records."
    );
  }
}