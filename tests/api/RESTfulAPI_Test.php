<?php
/**
 * RESTfulAPI Test suite
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Tests
 */
class RESTfulAPI_Test extends RESTfulAPI_Tester
{
  protected $extraDataObjects = array(
    'ApiTest_Author',
    'ApiTest_Book',
    'ApiTest_Library'
  );


  /* **********************************************************
   * TESTS
   * */


  /**
   * Checks that api access config check works
   */
  public function testDataObjectAPIEnaled()
  {
    // ----------------
    // Method Calls
    
    // Disabled by default
    $enabled = RESTfulAPI::api_access_control('ApiTest_Author');
    $this->assertFalse( $enabled );

    // Enabled
    Config::inst()->update('ApiTest_Author', 'api_access', true);
    $enabled = RESTfulAPI::api_access_control('ApiTest_Author');
    $this->assertTrue( $enabled );

    // Method specific
    Config::inst()->update('ApiTest_Author', 'api_access', 'GET,POST');

    $enabled = RESTfulAPI::api_access_control('ApiTest_Author');
    $this->assertTrue( $enabled );

    $enabled = RESTfulAPI::api_access_control('ApiTest_Author', 'POST');
    $this->assertTrue( $enabled );

    $enabled = RESTfulAPI::api_access_control('ApiTest_Author', 'PUT');
    $this->assertFalse( $enabled );

    // ----------------
    // API Calls
    /*
    // Access authorised
    $response = Director::test('api/ApiTest_Author/1', null, null, 'GET'); 
    $this->assertEquals(
      $response->getStatusCode(),
      200
    );

    // Access denied
    Config::inst()->update('ApiTest_Author', 'api_access', false);
    $response = Director::test('api/ApiTest_Author/1', null, null, 'GET');
    $this->assertEquals(
      $response->getStatusCode(),
      403
    );

    // Access denied
    Config::inst()->update('ApiTest_Author', 'api_access', 'POST');
    $response = Director::test('api/ApiTest_Author/1', null, null, 'GET');
    $this->assertEquals(
      $response->getStatusCode(),
      403
    );
    */
  }


  /* **********************************************************************
   * CORS
   * */

  /**
   * Check that CORS headers aren't set
   * when disabled via config
   */
  public function testCORSDisabled()
  {
    Config::inst()->update('RESTfulAPI', 'cors', array(
      'Enabled' => false
    ));

    $requestHeaders = $this->getOPTIONSHeaders();
    $response       = Director::test('api/ApiTest_Book/1', null, null, 'OPTIONS', null, $requestHeaders);
    $headers        = $response->getHeaders();

    $this->assertFalse( array_key_exists('Access-Control-Allow-Origin', $headers) );
    $this->assertFalse( array_key_exists('Access-Control-Allow-Headers', $headers) );
    $this->assertFalse( array_key_exists('Access-Control-Allow-Methods', $headers) );
    $this->assertFalse( array_key_exists('Access-Control-Max-Age', $headers) );
  }


  /**
   * Checks default allow all CORS settings
   */
  public function testCORSAllowAll()
  {
    $corsConfig      = Config::inst()->get('RESTfulAPI', 'cors');
    $requestHeaders  = $this->getOPTIONSHeaders('GET', 'http://google.com');
    $response        = Director::test('api/ApiTest_Book/1', null, null, 'OPTIONS', null, $requestHeaders);
    $responseHeaders = $response->getHeaders();

    $this->assertEquals(
      $responseHeaders['Access-Control-Allow-Origin'],
      $requestHeaders['Origin']
    );

    $this->assertEquals(
      $responseHeaders['Access-Control-Allow-Methods'],
      $corsConfig['Allow-Methods']
    );

    $this->assertEquals(
      $responseHeaders['Access-Control-Allow-Headers'],
      $requestHeaders['Access-Control-Request-Headers']
    );

    $this->assertEquals(
      $responseHeaders['Access-Control-Max-Age'],
      $corsConfig['Max-Age']
    );
  }


  /**
   * Checks CORS only allow HTTP methods specify in config
   */
  public function testCORSHTTPMethodFiltering()
  {
    Config::inst()->update('RESTfulAPI', 'cors', array(
      'Enabled'       => true,
      'Allow-Origin'  => '*',
      'Allow-Headers' => '*',
      'Allow-Methods' => 'GET',
      'Max-Age'       => 86400
    ));
    
    // Seding GET request, GET should be allowed
    $requestHeaders  = $this->getRequestHeaders();
    $response        = Director::test('api/ApiTest_Book/1', null, null, 'GET', null, $requestHeaders);
    $responseHeaders = $response->getHeaders();

    $this->assertEquals(
      $responseHeaders['Access-Control-Allow-Methods'],
      'GET'
    );

    // Seding POST request, only GET should be allowed
    $response        = Director::test('api/ApiTest_Book/1', null, null, 'POST', null, $requestHeaders);
    $responseHeaders = $response->getHeaders();

    $this->assertEquals(
      $responseHeaders['Access-Control-Allow-Methods'],
      'GET'
    );
  }


  /* **********************************************************************
   * API REQUESTS
   * */

  public function testFullAPIRequest()
  {
    Config::inst()->update('ApiTest_Author', 'api_access', true);

    // Basic serializer
    Config::inst()->update('RESTfulAPI', 'dependencies', array(
      'authenticator' => null,
      'queryHandler'  => '%$RESTfulAPI_DefaultQueryHandler',
      'serializer'    => '%$RESTfulAPI_BasicSerializer'
    ));
    Config::inst()->update('RESTfulAPI', 'dependencies', array(
      'deSerializer'    => '%$RESTfulAPI_BasicDeSerializer'
    ));

    $response = Director::test('api/ApiTest_Author/1', null, null, 'GET');
    var_dump($response);

    $this->assertEquals(
      $response->getStatusCode(),
      200,
      "API request for existing record should resolve"
    );

    $json = json_decode($response->getBody());
    $this->assertEquals(
      JSON_ERROR_NONE,
      json_last_error(),
      "API request should return valid JSON"
    );

    // EmberData serializer
    Config::inst()->update('RESTfulAPI', 'dependencies', array(
      'authenticator' => null,
      'queryHandler'  => '%$RESTfulAPI_DefaultQueryHandler',
      'serializer'    => '%$RESTfulAPI_EmberDataSerializer'
    ));
    Config::inst()->update('RESTfulAPI', 'dependencies', array(
      'deSerializer'    => '%$RESTfulAPI_EmberDataDeSerializer'
    ));

    $response = Director::test('api/ApiTest_Author/1', null, null, 'GET');

    $this->assertEquals(
      $response->getStatusCode(),
      200,
      "API request for existing record should resolve"
    );

    $json = json_decode($response->getBody());
    $this->assertEquals(
      JSON_ERROR_NONE,
      json_last_error(),
      "API request should return valid JSON"
    );
  }
}