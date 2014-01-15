<?php
/**
 * SilverStripe 3 RESTful API
 * 
 * This module implements a RESTful API
 * with flexible configuration for model querying and response serialization
 * through independent components.
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 */
class RESTfulAPI extends Controller
{

  /**
   * Lets you select if the API requires authentication for access
   * false = no authentication required
   * true  = authentication required for all HTTP methods
   * array = authentication required for selected HTTP methods e.g. array('POST', 'PUT', 'DELETE')
   * 
   * @var boolean|array
   */
  private static $authentication_policy = false;


  /**
   * Stores the current API's authentication settings
   * as set by the $requiresAuthentication config
   *  
   * @var boolean|array
   */
  protected $authenticationPolicy;


  /**
   * Current Authenticator instance
   * 
   * @var RESTfulAPI_Authenticator
   */
  public $authenticator;


  /**
   * Current QueryHandler instance
   * 
   * @var RESTfulAPI_QueryHandler
   */
  public $queryHandler;


  /**
   * Current serializer instance
   * 
   * @var RESTfulAPI_Serializer
   */
  public $serializer;


  /**
   * Injector dependencies
   * Override in configuration to use your custom classes
   * 
   * @var array
   */
  private static $dependencies = array(
    'authenticator' => '%$RESTfulAPI_TokenAuthenticator',
    'queryHandler'  => '%$RESTfulAPI_DefaultQueryHandler',
    'serializer'    => '%$RESTfulAPI_BasicSerializer'
  );


  /**
   * Embedded records setting
   * Specify which relation ($has_one, $has_many, $many_many) model data should be embedded into the response
   *
   * Map of relations to embed for specific record classname
   * 'RequestedClass' => array('RelationNameToEmbed', 'Another')
   *
   * Non embedded response:
   * {
   *   'member': {
   *     'name': 'John',
   *     'favourites': [1, 2]
   *   }
   * }
   *
   * Response with embedded record:
   * {
   *   'member': {
   *     'name': 'John',
   *     'favourites': [{
   *         'id': 1,
   *         'name': 'Mark'
   *      },{
   *         'id': 2,
   *         'name': 'Maggie'
   *      }]
   *    }
   * }
   * 
   * @var array
   */
  private static $embedded_records;


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
   * 
   * @return RESTfulAPI_QueryHandler QueryHandler instance
   */
  public function getqueryHandler()
  {
    return $this->queryHandler;
  }


  /**
   * Returns current serializer instance
   * 
   * @return RESTfulAPI_Serializer Serializer instance
   */
  public function getserializer()
  {
    return $this->serializer;
  }
  

  /**
   * Constructor....
   */
  public function __construct()
  {  
    parent::__construct();

    //get authentication policy config
    $this->authenticationPolicy = $this->config()->authentication_policy;
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
      $this->answer(null, true);
    }

    parent::init();
  }

  /**
   * Handles authentications methods
   * get response from API Authenticator
   * then passes it on to $answer()
   * 
   * @param  SS_HTTPRequest $request HTTP request
   */
  public function auth(SS_HTTPRequest $request)
  {
    $action = $request->param('Action');

    if ( $this->authenticator )
    {
      $className = get_class($this->authenticator);
      $allowedActions = Config::inst()->get( $className, 'allowed_actions', Config::INHERITED );
      if ( !$allowedActions )
      {
        $allowedActions = array();
      }

      if ( in_array($action, $allowedActions) )
      {
        if ( method_exists($this->authenticator, $action) )
        {
          $response = $this->authenticator->$action($request);
          $response = $this->serializer->serialize( $response );
          $this->answer($response);
        }
        else{          
          //let's be shady here instead
          $this->error( new RESTfulAPI_Error(403,
            "Action '$action' not allowed."
          ));
        }
      }
      else{
        $this->error( new RESTfulAPI_Error(403,
          "Action '$action' not allowed."
        ));
      }
      
    }
  }
  

  /**
   * Main API hub swith
   * All requests pass through here and are redirected depending on HTTP verb and params
   *
   * @todo move authentication check to another methode
   * 
   * @param  SS_HTTPRequest   $request    HTTP request
   * @return string                       json object of the models found
   */
  function index(SS_HTTPRequest $request)
  {
    //check authentication if enabled
    if ( $this->authenticator )
    {
      $authALL    = $this->authenticationPolicy === true;
      $authMethod = is_array($this->authenticationPolicy) && in_array($request->httpMethod(), $this->authenticationPolicy);

      if ( $authALL || $authMethod )
      {
        $authResult = $this->authenticator->authenticate($request);

        if ( $authResult instanceof RESTfulAPI_Error )
        {
          //Authentication failed return error to client
          $this->error($authResult);
        }
      }
    }

    //pass control to query handler
    $data = $this->queryHandler->handleQuery( $request );
    //catch + return errors
    if ( $data instanceof RESTfulAPI_Error )
    {
      $this->error($data);
    }

    //serialize response
    $json = $this->serializer->serialize( $data );
    //catch + return errors
    if ( $json instanceof RESTfulAPI_Error )
    {
      $this->error($json);
    }

    //all is good reply normally
    $this->answer( $json );
  }


  /**
   * Output the API response to client
   * then exit.
   * 
   * @param  string           $json             Response body
   * @param  boolean          $corsPreflight    Set to true if this is a XHR preflight request answer. CORS shoud be enabled.
   */
  function answer( $json = null, $corsPreflight = false )
  {
    $answer = new SS_HTTPResponse();

    //set response body
    if ( !$corsPreflight )
    {
      $answer->setBody($json);
    }

    //set CORS if needed
    $answer = $this->setAnswerCORS( $answer );

    $answer->addHeader('Content-Type', $this->serializer->getcontentType() );
    
    // save controller's response then return/output
    $this->response = $answer;

    if( Director::is_cli() )
    {
      return $answer;
    }
    else{
      $answer->output();
      exit;
    }
  }


  /**
   * Handles formatting and output error message
   * then exit.
   * 
   * @param  RESTfulAPI_Error $error Error object to return
   */
  function error(RESTfulAPI_Error $error)
  {
    $answer = new SS_HTTPResponse();

    $body = $this->serializer->serialize($error->body);
    $answer->setBody($body);

    $answer->setStatusCode($error->code, $error->message);
    $answer->addHeader('Content-Type', $this->serializer->getcontentType() );

    $answer = $this->setAnswerCORS($answer);
    
    // save controller's response then return/output
    $this->response = $answer;

    if( Director::is_cli() )
    {
      return $answer;
    }
    else{
      $answer->output();
      exit;
    }
  }


  /**
   * Apply the proper CORS response heardes
   * to an SS_HTTPResponse
   * 
   * @param SS_HTTPResponse $answer The updated response if CORS are neabled
   */
  private function setAnswerCORS(SS_HTTPResponse $answer)
  {
    $cors = Config::inst()->get( 'RESTfulAPI', 'cors', Config::INHERITED );

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


  /**
   * Checks and returns a model api_access config.
   * api_access config can be:
   * - unset, default to false
   * - false, access is always denied
   * - true, access is always granted
   * - comma separated list of allowed HTTP methods
   * 
   * @param  string  $model      Model's classname
   * @param  string  $httpMethod API request HTTP method
   * @return boolean             true if access is granted, false otherwise
   */
  public static function isAPIEnabled($model, $httpMethod = 'GET')
  {
    $rules = singleton($model)->stat('api_access');
    if ( is_string($rules) )
    {
      $rules = explode(',', strtoupper($rules));
      if ( in_array($httpMethod, $rules) )
      {
        return true;
      }
      else{
        return false;
      }
    }

    return $rules;
  }

}