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
   * Authentication token life in seconds
   * 
   * @var integer
   * @config
   */
  private static $tokenLife = 10800; //3 * 60 * 60;


  /**
   * HTTP Header name storing authentication token
   * 
   * @var string
   * @config
   */
  private static $tokenHeader = 'X-Silverstripe-Apitoken';


  /**
   * Fallback GET/POST HTTP query var storing authentication token
   * 
   * @var string
   * @config
   */
  private static $tokenQueryVar = 'token';


  /**
   * Class name to query for token validation
   * 
   * @var string
   * @config
   */
  private static $tokenOwnerClass = 'Member';


  /**
   * Whether or not the token should auto-update on activity.
   * When set to true, the token will automatically update its lifetime, similar
   * to a session-ping.
   *
   * @var boolean
   * @config
   */
  private static $autoRefreshLifetime = false;


  /**
   * DB Column to store the refresh token in.
   * @var string
   */
  private static $refreshTokenColumn = 'ApiRefreshToken';


  /**
   * Stores current token authentication configurations
   * header, var, class, db columns....
   * 
   * @var array
   */
  protected $tokenConfig;


  const AUTH_CODE_LOGGED_IN             = 0;
  const AUTH_CODE_LOGIN_FAIL            = 1;
  const AUTH_CODE_TOKEN_INVALID         = 2;
  const AUTH_CODE_TOKEN_EXPIRED         = 3;
  const AUTH_CODE_REFRESH_TOKEN_MISSING = 4;
  const AUTH_CODE_REFRESH_TOKEN_INVALID = 5;


  /**
   * List of URL accessible actions
   * 
   * @var array
   */
  private static $allowed_actions = array(
    'login',
    'logout',
    'lostPassword',
    'refreshToken'
  );


  /**
   * Instanciation + config aquisition
   */
  public function __construct()
  {
    $config = array();
    $configInstance = Config::inst();    

    $config['life']             = $configInstance->get('RESTfulAPI_TokenAuthenticator', 'tokenLife');
    $config['header']           = $configInstance->get('RESTfulAPI_TokenAuthenticator', 'tokenHeader');
    $config['queryVar']         = $configInstance->get('RESTfulAPI_TokenAuthenticator', 'tokenQueryVar');
    $config['owner']            = $configInstance->get('RESTfulAPI_TokenAuthenticator', 'tokenOwnerClass');
    $config['autoRefresh']      = $configInstance->get('RESTfulAPI_TokenAuthenticator', 'autoRefreshLifetime');
    $config['refreshDBColumn']  = $configInstance->get('RESTfulAPI_TokenAuthenticator', 'refreshTokenColumn');

    $tokenDBColumns = $configInstance->get('RESTfulAPI_TokenAuthExtension', 'db');
    $tokenDBColumn  = array_search('Varchar(160)', $tokenDBColumns);
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
          $tokenData = $this->updateToken($member);
          $member->login();
        }
      }
      
      if ( !$member )
      {
        $response['result']       = false;
        $response['message']      = 'Authentication fail.';
        $response['code']         = self::AUTH_CODE_LOGIN_FAIL;
      }
      else{
        $response['result']       = true;
        $response['message']      = 'Logged in.';
        $response['code']         = self::AUTH_CODE_LOGGED_IN;
        $response['token']        = $tokenData['token'];
        $response['expire']       = $tokenData['expire'];
        $response['refreshtoken'] = $tokenData['refreshtoken'];
        $response['userID']       = $member->ID;
      }
    }

    return $response;
  }

  /**
   * Perform a refresh of the API token.
   * In order to do so, the user has to supply his current (non-expired) API-token and the refresh-token
   * that was returned on login.
   * @param SS_HTTPRequest $request
   * @return array|RESTfulAPI_Error
   */
  public function refreshToken(SS_HTTPRequest $request)
  {
    // need to be authenticated to refresh the token
    $response = $this->authenticate($request);
    if($response !== true){
      return $response;
    }

    if($refreshToken = $request->requestVar('refreshtoken')){
      $apiToken         = $this->getRequestToken($request);
      $tokenDBColumn    = $this->tokenConfig['DBColumn'];
      $refreshDBColumn  = $this->tokenConfig['refreshDBColumn'];
      $ownerTable       = $this->tokenConfig['owner'];

      // find the owner that belongs to the refresh-token
      $owner = DataObject::get($ownerTable)->filter(array($refreshDBColumn => $refreshToken))->first();

      // check if the owner exists and if the API token also matches
      if(!$owner || $owner->{$tokenDBColumn} !== $apiToken){
        return new RESTfulAPI_Error(403,
          'Refreshing tokens failed.',
          array(
            'message' => 'Refresh token invalid.',
            'code'    => self::AUTH_CODE_REFRESH_TOKEN_INVALID
          )
        );
      }

      return $this->updateToken($owner);
    }

    //no refresh-token, bad news
    return new RESTfulAPI_Error(403,
      'Refresh token missing.',
      array(
        'message' => 'Refresh token missing.',
        'code'    => self::AUTH_CODE_REFRESH_TOKEN_MISSING
      )
    );
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
        $this->updateToken($member, true);
      }      
    }
  }


  /**
   * Sends password recovery email
   * 
   * @param  SS_HTTPRequest   $request    HTTP request containing 'email' vars
   * @return array                        'email' => false if email fails (Member doesn't exist will not be reported)
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

    return array( 'email' => $sent );
  }


  /**
   * Return the stored API token for a specific owner
   * 
   * @param  integer $id ID of the token owner
   * @return string      API token for the owner
   */
  public function getToken($id)
  {
    if ( $id )
    {
      $ownerClass = $this->tokenConfig['owner'];
      $owner      = DataObject::get_by_id($ownerClass, $id);

      if ( $owner )
      {
        $tokenDBColumn = $this->tokenConfig['DBColumn'];
        return $owner->{$tokenDBColumn};
      }
      else {
        user_error("API Token owner '$ownerClass' not found with ID = $id", E_USER_WARNING);
      }
    }
    else{
      user_error("RESTfulAPI_TokenAuthenticator::getToken() requires an ID as argument.", E_USER_WARNING);
    }
  }


  /**
   * Reset an owner's token
   * if $expired is set to true the owner's will have a new invalidated/expired token
   * 
   * @param  integer $id      ID of the token owner
   * @param  boolean $expired if true the token will be invalidated
   */
  public function resetToken($id, $expired = false)
  {
    if ( $id )
    {
      $ownerClass = $this->tokenConfig['owner'];
      $owner      = DataObject::get_by_id($ownerClass, $id);

      if ( $owner )
      {
        $this->updateToken($owner, $expired);
      }
      else{
        user_error("API Token owner '$ownerClass' not found with ID = $id", E_USER_WARNING);
      }
    }
    else{
      user_error("RESTfulAPI_TokenAuthenticator::resetToken() requires an ID as argument.", E_USER_WARNING);
    }
  }

  /**
   * Update the token of a token owner by recreating the token and refresh-token values
   * @param $owner          The token owner instance to update
   * @param bool $expired   Set to true to generate an outdated token
   * @return array|null     Token data array('token' => HASH, 'refreshtoken' => REFRESH_TOKEN, 'expire' => EXPIRY_DATE)
   */
  private function updateToken(DataObject $owner, $expired = false)
  {
    // DB field names
    $tokenDBColumn    = $this->tokenConfig['DBColumn'];
    $expireDBColumn   = $this->tokenConfig['expireDBColumn'];
    $refreshDBColumn  = $this->tokenConfig['refreshDBColumn'];

    // token lifetime
    $life             = $this->tokenConfig['life'];
    $expire           = 0;

    // create the API access-token
    $token = $this->createUniqueToken($owner, $tokenDBColumn);

    $refreshToken = null;
    if ( !$expired )
    {
      $expire = time() + $life;
      // create a refresh-token
      $refreshToken = $this->createUniqueToken($owner, $refreshDBColumn);
    }
    else{
      $expire = time() - ($life * 2);
    }

    $owner->{$expireDBColumn} = $expire;
    $owner->{$tokenDBColumn} = $token;
    if($refreshToken){
      $owner->{$refreshDBColumn} = $refreshToken;
    }
    $owner->write();

    return array(
      'expire'        => $expire,
      'refreshtoken'  => $refreshToken,
      'token'         => $token
    );
  }

  /**
   * Create a unique token for the given owner on the given column
   * @param DataObject  $owner      the data-owner
   * @param string      $DBcolumn   string the DB column that contains the token
   * @return string
   */
  protected function createUniqueToken($owner, $DBcolumn)
  {
    // get all the existing tokens from the DB, so we don't recreate the same token again
    $existingTokens = $owner->get()->column($DBcolumn);

    // don't perform more than 100 attempts.. if that happens something is severely flawed and we should error out
    for($i = 0; $i < 100; $i++){
      $token = $this->generateToken();
      if(!in_array($token, $existingTokens, true)){
        return $token;
      }
    }

    user_error('Unable to create unique token', E_USER_ERROR);
  }

  /**
   * Generates an encrypted random token
   *
   * @return string the token string
   */
  protected function generateToken()
  {
    $generator = new RandomGenerator();
    $tokenString = $generator->randomToken();

    $e = PasswordEncryptor::create_for_algorithm('blowfish'); //blowfish isn't URL safe and maybe too long?
    $salt = $e->salt($tokenString);
    $token = $e->encrypt($tokenString, $salt);

    return substr($token, 7);
  }


  /**
   * Returns the DataObject related to the token
   * that sent the authenticated request
   * 
   * @param  SS_HTTPRequest          $request    HTTP API request
   * @return null|DataObject                     null if failed or the DataObject token owner related to the request
   */
  public function getOwner(SS_HTTPRequest $request)
  {
    $owner = null;

    if ( $token = $this->getRequestToken($request) )
    {
      $SQL_token = Convert::raw2sql($token);
      
      $owner = DataObject::get_one(
        $this->tokenConfig['owner'],
        "\"".$this->tokenConfig['DBColumn']."\"='" . $SQL_token . "'",
        false
      );

      if ( !$owner )
      {
        $owner = null;
      }
    }

    return $owner;
  }


  /**
   * Checks if a request to the API is authenticated
   * Gets API Token from HTTP Request and return Auth result
   * 
   * @param  SS_HTTPRequest           $request    HTTP API request
   * @return true|RESTfulAPI_Error                True if token is valid OR RESTfulAPI_Error with details
   */
  public function authenticate(SS_HTTPRequest $request)
  {
    if ( $token = $this->getRequestToken($request))
    {
      //check token validity
      return $this->validateAPIToken( $token );
    }
    else{
      //no token, bad news
      return new RESTfulAPI_Error(403,
        'Token invalid.',
        array(
          'message' => 'Token invalid.',
          'code'    => self::AUTH_CODE_TOKEN_INVALID
        )
      );
    }
  }

  /**
   * Get the token that was sent with the given request
   * @param SS_HTTPRequest $request
   * @return string|null the token parameter from the request
   */
  protected function getRequestToken(SS_HTTPRequest $request)
  {
    //get the token
    $token = $request->getHeader( $this->tokenConfig['header'] );
    if (!$token)
    {
      $token = $request->requestVar( $this->tokenConfig['queryVar'] );
    }
    return $token;
  }
  

  /**
   * Validate the API token
   * 
   * @param  string                 $token    Authentication token
   * @return true|RESTfulAPI_Error            True if token is valid OR RESTfulAPI_Error with details
   */
  private function validateAPIToken($token)
  {
    //get owner with that token
    $SQL_token = Convert::raw2sql($token);
    $tokenColumn = $this->tokenConfig['DBColumn'];

    $tokenOwner = DataObject::get_one(
      $this->tokenConfig['owner'],
      "\"".$this->tokenConfig['DBColumn']."\"='" . $SQL_token . "'",
      false
    );

    if ( $tokenOwner )
    {
      //check token expiry
      $tokenExpire  = $tokenOwner->{$this->tokenConfig['expireDBColumn']};
      $now          = time();
      $life         = $this->tokenConfig['life'];

      if ( $tokenExpire > ($now - $life) )
      {
        // check if token should automatically be updated
        if($this->tokenConfig['autoRefresh']){
          $tokenOwner->setField($this->tokenConfig['expireDBColumn'], $now + $life);
          $tokenOwner->write();
        }
        //all good, log Member in
        if ( is_a($tokenOwner, 'Member') )
        {
          $tokenOwner->logIn();
        }

        return true;
      }
      else{
        //too old        
        return new RESTfulAPI_Error(403,
          'Token expired.',
          array(
            'message' => 'Token expired.',
            'code'    => self::AUTH_CODE_TOKEN_EXPIRED
          )
        );
      }        
    }
    else{
      //token not found
      //not sure it's wise to say it doesn't exist. Let's be shady here
      return new RESTfulAPI_Error(403,
        'Token invalid.',
        array(
          'message' => 'Token invalid.',
          'code'    => self::AUTH_CODE_TOKEN_INVALID
        )
      );
    }    
  }
	
}