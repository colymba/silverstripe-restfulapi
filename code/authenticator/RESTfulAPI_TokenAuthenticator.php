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


  /**
   * HTTP Header name storing authentication token
   * 
   * @var string
   */
  private static $tokenHeader = 'X-Silverstripe-Apitoken';


  /**
   * Fallback GET/POST HTTP query var storing authentication token
   * 
   * @var string
   */
  private static $tokenQueryVar = 'token';


  /**
   * Class name to query for token validation
   * 
   * @var string
   */
  private static $tokenOwnerClass = 'Member';

  /**
   * Stores current token authentication configurations
   * header, var, class, db columns....
   * 
   * @var array
   */
  protected $tokenConfig;


  const AUTH_CODE_LOGGED_IN     = 0;
  const AUTH_CODE_LOGIN_FAIL    = 1;
  const AUTH_CODE_TOKEN_INVALID = 2;
  const AUTH_CODE_TOKEN_EXPIRED = 3;


  /**
   * Instanciation + config aquisition
   */
  public function __construct()
  {
    $config = array();
    $configInstance = Config::inst();    

    $config['life']     = $configInstance->get( 'RESTfulAPI_TokenAuthenticator', 'tokenLife', Config::INHERITED );
    $config['header']   = $configInstance->get( 'RESTfulAPI_TokenAuthenticator', 'tokenHeader', Config::INHERITED );
    $config['queryVar'] = $configInstance->get( 'RESTfulAPI_TokenAuthenticator', 'tokenQueryVar', Config::INHERITED );
    $config['owner']    = $configInstance->get( 'RESTfulAPI_TokenAuthenticator', 'tokenOwnerClass', Config::INHERITED );

    $tokenDBColumns = $configInstance->get( 'RESTfulAPI_TokenAuthExtension', 'db', Config::INHERITED );
    $tokenDBColumn  = array_search('Varchar', $tokenDBColumns);
    $expireDBColumn = array_search('Int', $tokenDBColumns);

    if ( $tokenDBColumn !== false )
    {
      $config['DBColumn'] = $tokenDBColumn;
    }
    else{
      $config['DBColumn'] = 'ApiToken';
    }

    if ( $expireDBColumn !== false )
    {
      $config['expireDBColumn'] = $expireDBColumn;
    }
    else{
      $config['expireDBColumn'] = 'ApiTokenExpire';
    }

    $this->tokenConfig = $config;
  }


  /**
   * Login a user into the Framework and generates API token
   * Only works if the token owner is a Member
   *
   * @param  SS_HTTPRequest   $request  HTTP request containing 'email' & 'pwd' vars
   * @return array                      login result with token
   */
  public function login(SS_HTTPRequest $request)
  {
    $response = array();

    if ( $this->tokenConfig['owner'] === 'Member' )
    {
      $email    = $request->requestVar('email');
      $pwd      = $request->requestVar('pwd');
      $member   = false;
      

      if( $email && $pwd )
      {
        $member = MemberAuthenticator::authenticate(array(
          'Email'    => $email,
          'Password' => $pwd
        ));
        if ( $member )
        {
          $life   = $this->tokenConfig['life'];
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
    }

    return $response;
  }


  /**
   * Logout a user from framework
   * and update token with an expired one
   * if token owner class is a Member
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

      if ( $this->tokenConfig['owner'] === 'Member' )
      {
        //generate expired token
        $token  = sha1( $member->Email . $member->ID . time() );
        $life   = $this->tokenConfig['life'];
        $expire = time() - ($life * 2);
        //write
        $member->ApiToken = $token;
        $member->ApiTokenExpire = $expire;
        $member->write();
      }      
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
    $token = $request->getHeader( $this->tokenConfig['header'] );
    if (!$token)
    {
      $token = $request->requestVar( $this->tokenConfig['queryVar'] );
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
    $tokenOwner = DataObject::get_one( $this->tokenConfig['owner'] )->filter(
                    $this->tokenConfig['DBColumn'],
                    $token
                  );

    if ( $tokenOwner )
    {
      //check token expiry
      $tokenExpire  = $tokenOwner->{$this->tokenConfig['expireDBColumn']};
      $now          = time();
      $life         = $this->tokenConfig['life'];

      if ( $tokenExpire > ($now - $life) )
      {
        //all good, log Member in
        if ( is_a($tokenOwner, 'Member') )
        {
          $tokenOwner->logIn();
        }        

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