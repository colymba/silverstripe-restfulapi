<?php
/**
 * RESTfulAPI Token authenticator
 * handles login, logout and request authentication via token
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Authentication
 */
class RESTfulAPI_TokenAuthenticator implements RESTfulAPI_Authenticator
{

	/**
   * Authentication token life in ms
   * 
   * @var integer
   */
  private static $tokenLife = 10800000; //3 * 60 * 60 * 1000;


  const AUTH_CODE_LOGGED_IN     = 0;
  const AUTH_CODE_LOGIN_FAIL    = 1;
  const AUTH_CODE_TOKEN_INVALID = 2;
  const AUTH_CODE_TOKEN_EXPIRED = 3;


  /**
   * Login a user into the Framework and generates API token
   * 
   * @param  SS_HTTPRequest   $request  HTTP request containing 'email' & 'pwd' vars
   * @return string                     JSON object of the result {result, message, code, token, member}
   */
  public function login(SS_HTTPRequest $request)
  {
    $email    = $request->requestVar('email');
    $pwd      = $request->requestVar('pwd');
    $member   = false;
    $response = array();

    if( $email && $pwd )
    {
      $member = MemberAuthenticator::authenticate(array(
        'Email'    => $email,
        'Password' => $pwd
      ));
      if ( $member )
      {
        $life   = Config::inst()->get( 'APIController', 'tokenLife', Config::INHERITED );
        $expire = time() + $life;
        $token  = sha1( $member->Email . $member->ID . time() );

        $member->ApiToken = $token;
        $member->ApiTokenExpire = $expire;
        $member->write();
        $member->login();
      }
    }
    
    if ( !$member )
    {
      $response['result']  = false;
      $response['message'] = 'Authentication fail.';
      $response['code']    = self::AUTH_CODE_LOGIN_FAIL;
    }
    else{
      $response['result']       = true;
      $response['message']      = 'Logged in.';
      $response['code']         = self::AUTH_CODE_LOGGED_IN;
      $response['token']        = $token;
      //$response['member']       = $this->parseObject($member);
    }

    //return Convert::raw2json($response);
    //$this->answer( Convert::raw2json($response) );
    return $response;
  }


  /**
   * Logout a user and update member's API token with an expired one
   * 
   * @param  SS_HTTPRequest   $request    HTTP request containing 'email' var
   */
  public function logout(SS_HTTPRequest $request)
  {
    $email = $request->requestVar('email');
    $member = Member::get()->filter(array('Email' => $email))->first();
    if ( $member )
    {
      //logout
      $member->logout();
      //generate expired token
      $token  = sha1( $member->Email . $member->ID . time() );
      $life   = Config::inst()->get( 'APIController', 'tokenLife', Config::INHERITED );
      $expire = time() - ($life * 2);
      //write
      $member->ApiToken = $token;
      $member->ApiTokenExpire = $expire;
      $member->write();
    }
  }


  /**
   * Sends password recovery email
   * 
   * @param  SS_HTTPRequest   $request    HTTP request containing 'email' vars
   * @return string                       JSON 'email' = false if email fails (Member doesn't will not be reported)
   */
  public function lostPassword(SS_HTTPRequest $request)
  {
    $email = Convert::raw2sql( $request->requestVar('email') );
    $member = DataObject::get_one('Member', "\"Email\" = '{$email}'");
    $sent = true;

    if($member)
    {
      $token = $member->generateAutologinTokenAndStoreHash();

      $e = Member_ForgotPasswordEmail::create();
      $e->populateTemplate($member);
      $e->populateTemplate(array(
        'PasswordResetLink' => Security::getPasswordResetLink($member, $token)
      ));
      $e->setTo($member->Email);
      $sent = $e->send();
    }

    $this->answer( Convert::raw2json(array(
      'email' => $sent
    )));
  }


  /**
   * Checks if a request to the API is authenticated
   * Gets API Token from HTTP Request and return Auth result
   * 
   * @param  SS_HTTPRequest   $request    HTTP API request
   * @return array                        authentication result:
   * array(
   *  'valid' => boolean  // true if the request is authorize
   *  'message' => string // message to return to the client
   *  'code' => integer   // response code associated with result if any
   * )
   */
  public function authenticate(SS_HTTPRequest $request)
  {
    //get the token
    $token = $request->getHeader("X-Silverstripe-Apitoken");
    if (!$token)
    {
      $token = $request->requestVar('token');
    }

    if ( $token )
    {
      //check token validity
      return $this->validateAPIToken( $token );
    }
    else{
      //no token, bad news
      return array(
        'valid'   => false,
        'message' => 'Token invalid.',
        'code'    => self::AUTH_CODE_TOKEN_INVALID
      );
    }
  }
  

  /**
   * Validate the API token
   * 
   * @param  SS_HTTPRequest   $request    HTTP request with API token header "X-Silverstripe-Apitoken" or 'token' request var
   * @return array                        Result and eventual error message (valid, message, code)
   */
  private function validateAPIToken(string $token)
  {
    //get Member with that token
    $member = Member::get()->filter(array('ApiToken' => $token))->first();
    if ( $member )
    {
      //check token expiry
      $tokenExpire  = $member->ApiTokenExpire;
      $now          = time();
      $life         = Config::inst()->get( 'RESTfulAPI_TokenAuthenticator', 'tokenLife', Config::INHERITED );

      if ( $tokenExpire > ($now - $life) )
      {
        //all good, log Member in
        $member->logIn();

        return array(
          'valid'   => true,
          'message' => 'Token valid.',
          'code'    => self::AUTH_CODE_LOGGED_IN
        );
      }
      else{
        //too old
        return array(
          'valid'   => false,
          'message' => 'Token expired.',
          'code'    => self::AUTH_CODE_TOKEN_EXPIRED
        );
      }        
    }
    else{
      //token not found
      return array(
        'valid'   => false,
        'message' => 'Token invalid.', //not sure it's wise to say it doesn't exist. Let's be shady here
        'code'    => self::AUTH_CODE_TOKEN_INVALID
      );
    }    
  }
	
}