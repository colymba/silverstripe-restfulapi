<?php
/**
 * TokenAuthenticator Test suite
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Tests
 */
class RESTfulAPI_TokenAuthenticator_Test extends RESTfulAPI_Tester
{
  protected $requiredExtensions = array(
    'Member' => array('RESTfulAPI_TokenAuthExtension')
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
      'Email' => 'test@test.com',
      'Password' => 'test'
    ))->write();
  }


  /* **********************************************************
   * TESTS
   * */


  /**
   * Checks that the Member gets logged in
   * and a token is returned
   */
  public function testLogin()
  {
    $member = Member::get()->filter(array(
      'Email' => 'test@test.com'
    ))->first();

    $auth = $this->getAuthenticator();
    $request = new SS_HTTPRequest(
      'GET',
      'api/auth/login',
      array(
        'email' => 'test@test.com',
        'pwd'   => 'test'
      )
    );

    $result = $auth->login($request);

    $this->assertEquals(
      Member::currentUserID(),
      $member->ID,
      "TokenAuth successful login should login the user"
    );

    $this->assertTrue(
      is_string($result['token']),
      "TokenAuth successful login should return token as string"
    );
  }


  /**
   * Checks that the Member is logged out
   */
  public function testLogout()
  {
    $auth = $this->getAuthenticator();
    $request = new SS_HTTPRequest(
      'GET',
      'api/auth/logout',
      array(
        'email' => 'test@test.com'
      )
    );

    $result = $auth->logout($request);

    $this->assertNull(
      Member::currentUser(),
      "TokenAuth successful logout should logout the user"
    );
  }


  /**
   * Checks that a string token is returned
   */
  public function testGetToken()
  {
    $member = Member::get()->filter(array(
      'Email' => 'test@test.com'
    ))->first();

    $auth   = $this->getAuthenticator();
    $result = $auth->getToken($member->ID);

    $this->assertTrue(
      is_string($result),
      "TokenAuth getToken should return token as string"
    );
  }


  /**
   * Checks that a new toekn is generated
   */
  public function testResetToken()
  {
    $member = Member::get()->filter(array(
      'Email' => 'test@test.com'
    ))->first();

    $auth     = $this->getAuthenticator();
    $oldToken = $auth->getToken($member->ID);

    $auth->resetToken($member->ID);
    $newToken = $auth->getToken($member->ID);

    $this->assertThat(
      $oldToken,
      $this->logicalNot(
        $this->equalTo($newToken)
      ),
      "TokenAuth reset token should generate a new token"
    );
  }


  /**
   * Checks authenticator return owner
   */
  public function testGetOwner()
  {
    $member = Member::get()->filter(array(
      'Email' => 'test@test.com'
    ))->first();

    $auth = $this->getAuthenticator();
    $auth->resetToken($member->ID);
    $token = $auth->getToken($member->ID);

    $request = new SS_HTTPRequest(
      'GET',
      'api/ApiTest_Book/1'
    );
    $request->addHeader('X-Silverstripe-Apitoken', $token);

    $result = $auth->getOwner($request);

    $this->assertEquals(
      'test@test.com',
      $result->Email,
      "TokenAuth should return owner when passed valid token."
    );
  }


  /**
   * Checks authentication works with a generated token
   */
  public function testAuthenticate()
  {
    $member = Member::get()->filter(array(
      'Email' => 'test@test.com'
    ))->first();

    $auth = $this->getAuthenticator();
    $request = new SS_HTTPRequest(
      'GET',
      'api/ApiTest_Book/1'
    );    

    $auth->resetToken($member->ID);
    $token = $auth->getToken($member->ID);
    $request->addHeader('X-Silverstripe-Apitoken', $token);

    $result = $auth->authenticate($request);

    $this->assertTrue(
      $result,
      "TokenAuth authentication success should return true"
    );

    $auth->resetToken($member->ID);
    $result = $auth->authenticate($request);

    $this->assertContainsOnlyInstancesOf(
      'RESTfulAPI_Error',
      array($result),
      "TokenAuth authentication failure should return a RESTfulAPI_Error"
    );
  }
}