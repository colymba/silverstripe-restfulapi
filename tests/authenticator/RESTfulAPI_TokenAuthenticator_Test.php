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

  protected function getFlawedAuthenticator()
  {
    $injector = new Injector();
    $auth     = new FlawedAuthenticator();

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

  public function testTokenRefresh()
  {
    $auth = $this->getAuthenticator();
    $request = new SS_HTTPRequest(
      'GET',
      'api/auth/login',
      array(),
      array('email' => 'test@test.com', 'pwd' => 'test')
    );

    $result = $auth->login($request);
    $this->assertContains(
      'refreshtoken',
      array_keys($result),
      'Login response should return a refresh token'
    );

    $token = $result['token'];
    $refreshtoken = $result['refreshtoken'];
    $request = new SS_HTTPRequest(
      'GET',
      'api/auth/refreshToken',
      array(),
      array('refreshtoken' => $refreshtoken)
    );
    $request->addHeader('X-Silverstripe-Apitoken', $token);

    $result2 = $auth->refreshToken($request);
    $resultKeys = array_keys($result2);
    // test if the result contains values for 'refreshtoken', 'token', 'expire'
    foreach(array('refreshtoken', 'token', 'expire') as $key){
      $this->assertContains(
        $key,
        $resultKeys,
        'Refreshing the token should return an array that contains ' . $key
      );
    }

    $newToken = $result2['token'];
    $newRefreshToken = $result2['refreshtoken'];
    $this->assertThat(
      $token,
      $this->logicalNot(
        $this->equalTo($newToken)
      ),
      "TokenAuth refreshToken should generate a new token"
    );

    $this->assertThat(
      $refreshtoken,
      $this->logicalNot(
        $this->equalTo($newRefreshToken)
      ),
      "TokenAuth refreshToken should generate a new refresh token"
    );
  }

  /**
   * Test edge case of a member with the same API token
   */
  public function testTokenUniqueness()
  {
    $member = Member::get()->filter(array(
      'Email' => 'test@test.com'
    ))->first();

    // get an authenticator that always creates the same token
    $auth = $this->getFlawedAuthenticator();
    $auth->resetToken($member->ID);

    $newMemberID = Member::create(array(
      'Email' => 'TestMember',
      'Password' => 'test'
    ))->write();

    $hasErrors = false;
    try {
      $auth->resetToken($newMemberID);
    } catch(Exception $e){
      $hasErrors = true;
    }

    $this->assertTrue(
      $hasErrors,
      'Creating the same token twice should raise an error'
    );
  }


  public function test()
  {
  }
}

/**
 * Flawed authenticator to test the case when non-unique tokens are generated
 */
class FlawedAuthenticator extends RESTfulAPI_TokenAuthenticator
{
  protected function generateToken()
  {
    return 'I always return the same token!';
  }
}