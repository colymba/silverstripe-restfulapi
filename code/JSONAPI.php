<?php
/**
 * SilverStripe 3 JSON REST API
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package SS_JSONAPI
 */
class JSONAPI extends Controller
{
  /**
   * Lets you select if the API requires authentication for access
   * @var boolean
   */
  private static $requiresAuthentication = false;


  /**
   * Lets you select which class handles authentication
   * @var string
   */
  private static $authenticatorClass = 'JSONAPI_TokenAuthenticator';


  /**
   * Current Authenticator instance
   * @var class
   */
  private $authenticator = null;


  /**
   * Lets you select which class handles model queries
   * @var string
   */
  private static $queryHandlerClass = 'JSONAPI_DefaultQueryHandler';


  /**
   * Current QueryHandler instance
   * @var JSONAPI_QueryHandler
   */
  private $queryHandler = null;


  /**
   * Lets you select which class handles model serialization
   * @var string
   */
  private static $serializerClass = 'JSONAPI_DefaultSerializer';


  /**
   * Current serializer instance
   * @var JSONAPI_Serializer
   */
  private $serializer = null;


  /**
   * Cross-Origin Resource Sharing (CORS)
   * API settings for cross domain XMLHTTPRequest
   *
   * Enabled        true|false      enable/disable CORS
   * Allow-Origin   String|Array    '*' to allow all, 'http://domain.com' to allow single domain, array('http://domain.com', 'http://site.com') to allow multiple domains
   * Allow-Headers  String          '*' to allow all or comma separated list of headers
   * Allow-Methods  String          comma separated list of allowed methods
   * Max-Age        Integer         Preflight/OPTIONS request caching time in seconds (NOTE has no effect if Authentification is enabled => custom header = always preflight)
   *
   * @var array 
   */
  private static $cors = array(
    'Enabled'       => true,
    'Allow-Origin'  => '*',
    'Allow-Headers' => '*',
    'Allow-Methods' => 'OPTIONS, POST, GET, PUT, DELETE',
    'Max-Age'       => 86400
  );  


  /**
   * URL handler allowed actions
   * 
   * @var array
   */
  private static $allowed_actions = array(
    'index',
    'auth'
  );


  /**
   * URL handler definition
   * 
   * @var array
   */
  public static $url_handlers = array(
    'auth/$Action' => 'auth',
    '$ClassName/$ID' => 'index'
  );


  /**
   * Returns current query handler instance
   * @return JSONAPI_QueryHandler QueryHandler instance
   */
  public function getqueryHandler()
  {
    return $this->queryHandler;
  }


  /**
   * Returns current serializer instance
   * @return JSONAPI_Serializer Serializer instance
   */
  public function getserializer()
  {
    return $this->serializer;
  }
  

  /**
   * Handles modules instanciation etc...
   * @todo Check if module implement the right interface
   */
  public function __construct()
  {  
    //creates authenticator instance if required
    $requiresAuth = Config::inst()->get( 'JSONAPI', 'requiresAuthentication', Config::INHERITED );
    if ( $requiresAuth )
    {
      $authClass = Config::inst()->get( 'JSONAPI', 'authenticatorClass', Config::INHERITED );
      if ( $authClass && class_exists($authClass) )
      {
        $this->authenticator = Injector::inst()->create($authClass);
      }
      else{
        user_error("JSON API Authenticator class '$authClass' doesn't exist."
        . "No Authenticator defined.", E_USER_WARNING);
      }
    }


    //creates serializer instance    
    $serializerClass = Config::inst()->get( 'JSONAPI', 'serializerClass', Config::INHERITED );
    if ( class_exists($serializerClass) )
    {
      $this->serializer = Injector::inst()->create($serializerClass, $this);
    }
    else{
      user_error("JSON API Serializer class '$serializerClass' doesn't exist.", E_USER_ERROR);
    }


    //creates query handler instance
    $queryHandlerClass = Config::inst()->get( 'JSONAPI', 'queryHandlerClass', Config::INHERITED );
    if ( class_exists($queryHandlerClass) )
    {
      $this->queryHandler = Injector::inst()->create($queryHandlerClass, $this);
    }
    else{
      user_error("JSON API Query Handler class '$queryHandlerClass' doesn't exist.", E_USER_ERROR);
    }


    parent::__construct();
  }

  /**
   * Controller inititalisation
   * Catches CORS preflight request marked with HTTPMethod 'OPTIONS'
   */
  public function init()
  {
    //catch preflight request
    if ( $this->request->httpMethod() === 'OPTIONS' )
    {
      $this->answer(null, false, true);
    }

    parent::init();
  }

  /**
   * Handles authentications methods
   * get response from API Authenticator
   * then passes it on to $answer()
   * @param  SS_HTTPRequest $request HTTP request
   */
  public function auth(SS_HTTPRequest $request)
  {
    $action = $request->param('Action');

    if ( $this->authenticator )
    {
      if ( method_exists($this->authenticator, $action) )
      {
        $response = $this->authenticator->$action($request);
        $this->answer($response);
      }
      else{
        $className = get_class($this->authenticator);

        $this->answer(null, array(
          'code' => 404,
          'description' => "Action '$action' isn't available on class $className."
        ));
      }
    }
  }
  

  /**
   * Main API hub swith
   * All requests pass through here and are redirected depending on HTTP verb and params
   *
   * @todo move authentication check to another methode
   * @param  SS_HTTPRequest   $request    HTTP request
   * @return string                       json object of the models found
   * @see    answer()
   */
  function index(SS_HTTPRequest $request)
  {
    //check authentication if enabled
    if ( $this->authenticator )
    {
      $auth = $this->authenticator->authenticate($request);

      if ( !$auth['valid'] )
      {
        //Authentication failed return error to client
        $response = Convert::raw2json(array(
          'message' => $auth['message'],
          'code'    => $auth['code']
        ));

        $this->answer(
          $response,
          array(
            'code' => 403,
            'description' => $auth['message']
          )
        );
      }
    }

    $data = $this->queryHandler->handleQuery( $request );

    $json = $this->serializer->serialize( $data );

    $this->answer( $json );
  }


  /**
   * Returns the API response to client
   * 
   * @param  string           $json             Response body
   * @param  boolean|array    $error            Use false if not an error otherwise pass array('code' => statusCode, 'description' => statusDescription)
   * @param  boolean          $corsPreflight    Set to true if this is a XHR preflight request answer. CORS shoud be enabled.
   * @return SS_HTTPResponse                    API response to client
   */
  function answer( $json = null, $error = false, $corsPreflight = false )
  {
    $answer = new SS_HTTPResponse();

    //set response body
    if ( !$corsPreflight )
    {
      $answer->setBody($json); 

      //Set status code+descript, i.e. 403 Access denied
      if ( $error !== false )
      {
        $answer->setStatusCode($error['code'], $error['description']);
      }     
    }

    //set CORS if needed
    $answer = $this->setAnswerCORS( $answer );

    $answer->addHeader('Content-Type', 'application/json; charset=utf-8');
    
    //Output + exit
    $answer->output();
    exit;
  }


  /**
   * Apply the proper CORS response heardes
   * to an SS_HTTPResponse
   * 
   * @param SS_HTTPResponse $answer The updated response if CORS are neabled
   */
  private function setAnswerCORS(SS_HTTPResponse $answer)
  {
    $cors = Config::inst()->get( 'JSONAPI', 'cors', Config::INHERITED );

    // skip if CORS is not enabled
    if ( !$cors['Enabled'] )
    {
      return $answer;
    }

    //check if Origin is allowed
    $allowedOrigin = $cors['Allow-Origin'];
    $requestOrigin = $this->request->getHeader('Origin');
    if ( $requestOrigin )
    {
      if ( $cors['Allow-Origin'] === '*' )
      {
        $allowedOrigin = $requestOrigin;
      }
      else if ( is_array($cors['Allow-Origin']) )
      {
        if ( in_array($requestOrigin, $cors['Allow-Origin']) )
        {
          $allowedOrigin = $requestOrigin;
        }
      }
    }      
    $answer->addHeader('Access-Control-Allow-Origin', $allowedOrigin);
    
    //allowed headers
    $allowedHeaders = '';
    $requestHeaders = $this->request->getHeader('Access-Control-Request-Headers');  
    if ( $cors['Allow-Headers'] === '*' )
    {
      $allowedHeaders = $requestHeaders;
    }
    else{
      $allowedHeaders = $cors['Allow-Headers'];
    }
    $answer->addHeader('Access-Control-Allow-Headers', $allowedHeaders);

    //allowed method
    $answer->addHeader('Access-Control-Allow-Methods', $cors['Allow-Methods']);

    //max age
    $answer->addHeader('Access-Control-Max-Age', $cors['Max-Age']);

    return $answer;
  }

}