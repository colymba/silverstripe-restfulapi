<?php
/**
 * SilverStripe 3.1 JSON REST API
 * Specifically made to use with EmberJS/EmberData DS.RESTAdapter which is based on Rails ActiveModel::Serializers
 *
 * Exposes /login and /logout methods to use with API token
 * Request should be in the format '/Model', '/Model/ID', '/Model?foo=bar&bar=foo'
 *
 * Some resources and background on Ember Rest Adapter:
 * @link http://emberjs.com/guides/models/the-rest-adapter/
 * @link https://github.com/emberjs/data
 * @link https://speakerdeck.com/dgeb/optimizing-an-api-for-ember-data
 * @link https://github.com/rails-api/active_model_serializers
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package ss_json_rest_api
 */
class APIController extends Controller
{
  /**
   * If true 'all' requests will be checked for authentication with a token
   * 
   * @var boolean
   */
  private static $useTokenAuthentication = false;

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
   * Embedded records setting
   * Specify which relation ($has_one, $has_many, $many_many) model data should be embedded into the response
   *
   * Map of classes to embed for specific record
   * 'RequestedClass' => array('ClassToEmbed', 'Another')
   *
   * Non embedded response:
   * {
   *   'member': {
   *     'name': 'John',
   *     'favourite_ids': [1, 2]
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
  private static $embeddedRecords = array(
    'Member' => array('Favourites')
  );

  /**
   * Sideloaded records settings
   * @todo
   * 
   * @var array
   */
  private static $sideloadedRecords = array(
  );

  /**
   * URL handler allowed actions
   * 
   * @var array
   */
  private static $allowed_actions = array(
    'index',
    'login',
    'logout',
    'lostpassword'
  );

  /**
   * URL handler definition
   * 
   * @var array
   */
  public static $url_handlers = array(
    'login' => 'login',
    'logout' => 'logout',
    'lostpassword' => 'lostPassword',
    '$ClassName/$ID' => 'index'
  );

  /**
   * Stores the currently requested data (Model class + ID)
   * 
   * @var array
   */
  private $requestData = array(
    'model'  => null,
    'id'     => null,
    'params' => null
  );

  /**
   * Search Filter Modifiers Separator used in the query var
   * i.e. ?column__EndsWith=suffix
   * 
   * @var string
   */
  private static $searchFilterModifiersSeparator = '__';

  /**
   * Controller inititalisation
   * Catches CORS preflight request marked with HTTPMethod 'OPTIONS'
   */
  public function init()
  {
    parent::init();

    //catch preflight request
    if ( $this->request->httpMethod() === 'OPTIONS' )
    {
      $this->answer(null, false, true);
    }
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
    }

    //Set status code+descript, i.e. 403 Access denied
    if ( $error !== false && !$corsPreflight )
    {
      $answer->setStatusCode($error['code'], $error['description']);
    }

    //If CORS is enabled sort out headers
    if ( self::$cors['Enabled'] )
    {
      //check if Origin is allowed
      $allowedOrigin = self::$cors['Allow-Origin'];
      $requestOrigin = $this->request->getHeader('Origin');
      if ( $requestOrigin )
      {
        if ( self::$cors['Allow-Origin'] === '*' )
        {
          $allowedOrigin = $requestOrigin;
        }
        else if ( is_array(self::$cors['Allow-Origin']) )
        {
          if ( in_array($requestOrigin, self::$cors['Allow-Origin']) )
          {
            $allowedOrigin = $requestOrigin;
          }
        }
      }      
      $answer->addHeader('Access-Control-Allow-Origin', $allowedOrigin);
      
      //allowed headers
      $allowedHeaders = '';
      $requestHeaders = $this->request->getHeader('Access-Control-Request-Headers');  
      if ( self::$cors['Allow-Headers'] === '*' )
      {
        $allowedHeaders = $requestHeaders;
      }
      else{
        $allowedHeaders = self::$cors['Allow-Headers'];
      }
      $answer->addHeader('Access-Control-Allow-Headers', $allowedHeaders);

      //allowed method
      $answer->addHeader('Access-Control-Allow-Methods', self::$cors['Allow-Methods']);

      //max age
      $answer->addHeader('Access-Control-Max-Age', self::$cors['Max-Age']);
    }

    $answer->addHeader('Content-Type', 'application/json; charset=utf-8');
    
    //Output + exit
    $answer->output();
    exit;
  }

  /**
   * Login a user into the Framework and generates API token
   * 
   * @param  SS_HTTPRequest   $request  HTTP request containing 'email' & 'pwd' vars
   * @return string                     JSON object of the result {result, message, code, token, member}
   */
  function login(SS_HTTPRequest $request)
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
      /*
      $memberData               = $this->parseObject($member);
      $memberData['ClassName']  = $memberData['class_name'];
      unset($memberData['class_name']);
      $response['member']       = $this->camelizeObjectAttributes($memberData);
      */
      $response['member']       = $this->parseObject($member);
    }

    //return Convert::raw2json($response);
    $this->answer( Convert::raw2json($response) );
  }

  /**
   * Logout a user and update member's API token with an expired one
   * 
   * @param  SS_HTTPRequest   $request    HTTP request containing 'email' var
   */
  function logout(SS_HTTPRequest $request)
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
  function lostPassword(SS_HTTPRequest $request)
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
   * Validate the API token from an HTTP Request header or var
   * 
   * @param  SS_HTTPRequest   $request    HTTP request with API token header "X-Silverstripe-Apitoken" or 'token' request var
   * @return array                        Result and eventual error message (valid, message, code)
   */
  function validateAPIToken(SS_HTTPRequest $request)
  {
    $response = array();

    //get the token
    $token = $request->getHeader("X-Silverstripe-Apitoken");
    if (!$token)
    {
      $token = $request->requestVar('token');
    }

    if ( $token )
    {
      //get Member with that token
      $member = Member::get()->filter(array('ApiToken' => $token))->first();
      if ( $member )
      {
        //check token expiry
        $tokenExpire  = $member->ApiTokenExpire;
        $now          = time();
        $life         = Config::inst()->get( 'APIController', 'tokenLife', Config::INHERITED );

        if ( $tokenExpire > ($now - $life) )
        {
          //all good, log Member in
          $member->logIn();
          $response['valid'] = true;
        }
        else{
          //too old
          $response['valid']   = false;
          $response['message'] = 'Token expired.';
          $response['code']    = self::AUTH_CODE_TOKEN_EXPIRED;
        }        
      }      
    }
    else{
      //no token, bad news
      $response['valid']   = false;
      $response['message'] = 'Token invalid.';
      $response['code']    = self::AUTH_CODE_TOKEN_INVALID;
    }
    return $response;
  }

  /**
   * Main API hub swith
   * All requests pass through here and are redirected depending on HTTP verb and params
   * 
   * @param  SS_HTTPRequest   $request    HTTP request
   * @return string                       json object of the models found
   * @see    answer()
   */
  function index(SS_HTTPRequest $request)
  {
    //check authentication if enabled
    if ( self::$useTokenAuthentication )
    {
      $validToken = $this->validateAPIToken($request);
      if ( !$validToken['valid'] )
      {
        $response = Convert::raw2json(array(
          'message' => $validToken['message'],
          'code'    => $validToken['code']
        ));
        $this->answer( $response, array( 'code' => 403, 'description' => $validToken['message'] ) );
      }
    }

    //get requested model(s) details
    $model       = $request->param('ClassName');
    $id          = $request->param('ID');
    $response    = false;
    $queryParams = $this->parseQueryParams( $request->getVars() );

    //convert model name to SS conventions
    if ($model)
    {
      $model = ucfirst( Inflector::singularize( Inflector::camelize( $model ) ) );
    }

    //store requested model data and query data
    $this->requestData['model'] = $model;
    if ($id)
    {
      $this->requestData['id'] = $id;
    }
    if ($queryParams)
    {
      $this->requestData['params'] = $queryParams;
    }

    //map HTTP word to API method
    //@TODO handle error
    if ( $request->isGET() )
    {
      $response = $this->findModel($model, $id, $queryParams, $request);
      $response = $this->parseJSON($response);
    }
    elseif ( $request->isPOST() )
    {
      $response = $this->createModel($model, $request);
    }
    elseif ( $request->isPUT() )
    {
      $response = $this->updateModel($model, $id, $request);
      $response = $this->parseJSON($response);
    }
    elseif ( $request->isDELETE() )
    {
      $response = $this->deleteModel($model, $id, $request);
    }

    $this->answer( $response );
  }

  /**
   * Parse the query parameters to appropriate Column, Value, Search Filter Modifiers
   * array(
   *   array(
   *     'Column'   => ColumnName,
   *     'Value'    => ColumnValue,
   *     'Modifier' => ModifierType
   *   )
   * )
   * 
   * @param  array  $params raw GET vars array
   * @return array          formatted query parameters
   */
  function parseQueryParams(array $params)
  {
    $parsedParams = array();

    foreach ($params as $key__mod => $value)
    {
      if ( $key__mod === 'url' ) continue;

      $param = array();  

      $key__mod = explode(
        self::$searchFilterModifiersSeparator,
        $key__mod
      );

      $param['Column'] = ucfirst( $this->ucIDKeys( $key__mod[0] ) );
      $param['Value'] = $value;
      if ( isset($key__mod[1]) ) $param['Modifier'] = $key__mod[1];
      else $param['Modifier'] = null;

      array_push($parsedParams, $param);
    }

    return $parsedParams;
  }


  /* **************************************************************************************************
   * DATA QUERIES
   */
  

  /**
   * Finds 1 or more objects of class $model
   * 
   * @param  string                 $model          Model(s) class to find
   * @param  boolean\integr         $id             The ID of the model to find or false
   * @param  array                  $queryParams    Query parameters and modifiers
   * @param  SS_HTTPRequest         $request        The original HTTP request
   * @return DataObject|DataList                    Result of the search (note: DataList can be empty) 
   */
  function findModel(string $model, $id = false, $queryParams, SS_HTTPRequest $request)
  {    
    if ($id)
    {
      $return = DataObject::get_by_id($model, $id);
    }
    else{
      // ":StartsWith", ":EndsWith", ":PartialMatch", ":GreaterThan", ":LessThan", ":Negation"
      // sort, rand, limit

      $return = DataObject::get($model);

      if ( count($queryParams) > 0 )
      {
        foreach ($queryParams as $param)
        {

          if ( $param['Column'] )
          {
            $param['Column'] = Inflector::camelize( $param['Column'] );

            // handle sorting by column
            if ( $param['Modifier'] === 'sort' )
            {
              $return = $return->sort(array(
                $param['Column'] => $param['Value']
              ));
            }
            // normal modifiers / search filters
            else if ( $param['Modifier'] )
            {
              $return = $return->filter(array(
                $param['Column'].':'.$param['Modifier'] => $param['Value']
              ));
            }
            // no modifier / search filter
            else{
              $return = $return->filter(array(
                $param['Column'] => $param['Value']
              ));
            }
          }
          else{
            // random
            if ( $param['Modifier'] === 'rand' )
            {
              $return = $return->sort('RAND()');
            }
            // limits
            else if ( $param['Modifier'] === 'limit' )
            {
              // range + offset
              if ( is_array($param['Value']) )
              {
                $return = $return->limit($param['Value'][0], $param['Value'][1]);
              }
              // range only
              else{
                $return = $return->limit($param['Value']);
              }
            }
          }

        }
      }
    }

    return $return;
  }

  /**
   * Create object of class $model
   * @todo
   * @param  string         $model
   * @param  SS_HTTPRequest $request
   * @return [type]
   */
  function createModel(string $model, SS_HTTPRequest $request)
  {

  }

  /**
   * Update databse record or $model
   *
   * @param String $model the model class to update
   * @param Integer $id The ID of the model to update
   * @param SS_HTTPRequest the original request
   *
   * @return DataObject The updated model 
   */
  function updateModel($model, $id, $request)
  {
    $model = DataObject::get_by_id($model, $id);
    $payload = $this->decodePayload( $request->getBody() );

    if ( $model && $payload )
    {
      $has_one            = Config::inst()->get( $model->ClassName, 'has_one', Config::INHERITED );
      $has_many           = Config::inst()->get( $model->ClassName, 'has_many', Config::INHERITED );
      $many_many          = Config::inst()->get( $model->ClassName, 'many_many', Config::INHERITED );
      $belongs_many_many  = Config::inst()->get( $model->ClassName, 'belongs_many_many', Config::INHERITED );

      $modelData          = array_shift( $payload );
      $hasChanges         = false;
      $hasRelationChanges = false;

      foreach ($modelData as $attribute => $value)
      {
        if ( !is_array($value) )
        {
          if ( $model->{$attribute} != $value )
          {
            $model->{$attribute} = $value;
            $hasChanges          = true;
          }
        }
        else{
          //has_many, many_many or $belong_many_many
          if ( array_key_exists($attribute, $has_many) || array_key_exists($attribute, $many_many) || array_key_exists($attribute, $belongs_many_many) )
          {
            $hasRelationChanges = true;
            $ssList = $model->{$attribute}();            
            $ssList->removeAll(); //reset list
            foreach ($value as $associatedModel)
            {
              $ssList->add( $associatedModel['ID'] );              
            }
          }
        }
      }

      if ( $hasChanges || $hasRelationChanges )
      {
        $model->write(false, false, false, $hasRelationChanges);
      }
    }

    return $model;
  }

  /**
   * delete object of class $model
   * @TODO
   */
  function deleteModel($model, $id, $request)
  {

  }
  

  /* **************************************************************************************************
   * DATA PARSING
   */
  

  /**
   * Parse DataList/DataObject to JSON
   *
   * @param DataList|DataObject $data The data to parse to JSON for client response
   *
   * @return String JSON representation of $data
   */
  function parseJSON($data)
  {
    //nothing to parse -> return an empty response
    if ( !$data )
    {      
      $className = $this->requestData['model'];
      $className = strtolower( Inflector::underscore( Inflector::singularize($className) ) );

      $root = new stdClass();
      $root->{$className} = new stdClass();

      return Convert::raw2json($root);
    }

    //multiple results to parse
    if ( $data instanceof DataList )
    {

      $className  = $data->dataClass;
      $className  = strtolower( Inflector::underscore( Inflector::pluralize($className) ) );

      $data       = $data->toArray();
      $modelsList = array();

      foreach ($data as $obj)
      {
        $newObj = $this->parseObject($obj);
        array_push($modelsList, $newObj);
      }

      $root = new stdClass();
      $root->{$className} = $modelsList;

    }
    //one DataObject to parse
    else{

      $className = $data->ClassName;
      $className = strtolower( Inflector::underscore( Inflector::singularize($className) ) );
      $obj       = $this->parseObject($data);     

      //Side loading
      //@TODO
      /*
      $sideloadingOptions = $this::$sideloadedRecords;
      if ( array_key_exists( ucfirst(Inflector::camelize($className)), $sideloadingOptions) )
      {
        $relations = $this->loadObjectRelations( $data );
        $root      = $this->addRelationsToJSONRoot($obj, $relations);
      }
      else{
        $root = new stdClass();
        $root->{$className} = $obj;
      }
      */

      $root = new stdClass();
      $root->{$className} = $obj;

    }
    return Convert::raw2json($root);
  }

  /**
   * Combine an Object map and its Relations
   * into one root Object ready to be returned as JSON
   */
  function addRelationsToJSONRoot($obj, $relations)
  {
    $root = new stdClass();
    $className = strtolower( Inflector::singularize($obj['class_name']) );
    $root->{$className} = $obj;

    if ($relations)
    {
      
      if ( isset($relations['has_one']) )
      {
        //print_r($relations['has_one']);
        foreach ($relations['has_one'] as $relation => $object)
        {
          $class = strtolower( Inflector::singularize($relation) );
          $root->{$class} = $object;
        }
      }

      if ( isset($relations['has_many']) )
      {
        foreach ($relations['has_many'] as $relation => $objectList)
        {
          //print_r($objectList);
          /*
          $id_list_key = strtolower( $relation ) . '_ids';
          $idList = array();
          foreach ($objectList as $objInfo)
          {
            array_push($idList, $objInfo['id']);
          }*/
          //print_r($idList);
          //$root->{$className}[$id_list_key] = $idList;
          $root->{strtolower( Inflector::pluralize($relation) )} = $objectList;
        }
      }

      if ( isset($relations['many_many']) )
      {
        foreach ($relations['many_many'] as $relation => $objectList)
        {
          $root->{strtolower( Inflector::pluralize($relation) )} = $objectList;
        }
      }

      if ( isset($relations['belongs_many_many']) )
      {
        foreach ($relations['belongs_many_many'] as $relation => $objectList)
        {
          $root->{strtolower( Inflector::pluralize($relation) )} = $objectList;
        }
      }
      
    }
    //print_r($root);
    return $root;
  }

  /**
   * Parse DataObject attributes for emberdata
   * converts keys to underscored_names
   * add relations ids list
   * and foreign keys to Int
   *
   * @param DataObject $obj The DataObject to parse
   *
   * @return Array The parsed DataObject
   */
  function parseObject($obj)
  {
    if( method_exists($obj, 'onBeforeSerializeObject') )
    {
      $obj->onBeforeSerializeObject();
    }

    $objMap = $obj->toMap();
    $newObj = array();

    $has_one           = Config::inst()->get( $objMap['ClassName'], 'has_one', Config::INHERITED );
    $has_many          = Config::inst()->get( $objMap['ClassName'], 'has_many', Config::INHERITED );
    $many_many         = Config::inst()->get( $objMap['ClassName'], 'many_many', Config::INHERITED );
    $belongs_many_many = Config::inst()->get( $objMap['ClassName'], 'belongs_many_many', Config::INHERITED );
    
    $embeddedOptions = $this::$embeddedRecords;

    //attributes / has_ones
    foreach ($objMap as $key => $value)
    {
      $newKey = str_replace('ID', 'Id', $key);
      $newKey = Inflector::underscore($newKey);

      //remove foreign keys trailing ID
      $has_one_key = preg_replace ( '/ID$/', '', $key);

      //foreign keys to int OR embedding  
      if ( array_key_exists( $has_one_key, $has_one ) )
      {
        //convert to integer
        $value = intVal( $value );

        //if set and embeddable
        if ( $this->isRelationEmbeddable($obj, $has_one_key) && $value !== 0 )
        {
          $embeddedObject = $obj->{$has_one_key}();
          if ( $embeddableObject )
          {
            $value = $this->parseObject($embeddedObject);
          }
        }

        //remove undefined has_one relations
        if ( $value === 0 )
        {
          $value = null;
        }
      }

      if ( $value !== null )
      {
        $newObj[$newKey] = $value;
      }
    }
    
    //has_many + many_many + $belongs_many_many
    //i.e. "comment_ids": [1, 2, 3] OR "comments": [{obj}, {obj}]
    $many_relation = array();
    if ( is_array($has_many) )          $many_relation = array_merge($many_relation, $has_many);
    if ( is_array($many_many) )         $many_relation = array_merge($many_relation, $many_many);
    if ( is_array($belongs_many_many) ) $many_relation = array_merge($many_relation, $belongs_many_many);
    
    foreach ($many_relation as $relationName => $relationClassname)
    {
      $has_many_objects = $obj->{$relationName}();
      //if there actually are objects in the relation
      if ( $has_many_objects->count() )
      {
        //if embeddable
        if ( $this->isRelationEmbeddable($obj, $relationName) )
        {
          $newKey = Inflector::underscore( Inflector::pluralize($relationName) );
          $newObj[$newKey] = array();
          foreach ($has_many_objects as $embeddedObject) {
            array_push(
              $newObj[$newKey],
              $this->parseObject($embeddedObject)
            );
          }
        }
        else{
          //ID list only
          $newKey = Inflector::underscore( Inflector::singularize($relationName) );
          $idList = $has_many_objects->map('ID', 'ID')->keys();
          $newObj[$newKey.'_ids'] = $idList;
        }
      }
    } 

    return $newObj;
  }

  /**
   * Load all of an object's relations
   */
  function loadObjectRelations($obj)
  {    
    $embeddableRelations = $this::$embeddedRecords[$obj->ClassName];
    $relations           = array();

    $has_one           = Config::inst()->get( $obj->ClassName, 'has_one', Config::INHERITED );
    $has_many          = Config::inst()->get( $obj->ClassName, 'has_many', Config::INHERITED );
    $many_many         = Config::inst()->get( $obj->ClassName, 'many_many', Config::INHERITED );
    $belongs_many_many = Config::inst()->get( $obj->ClassName, 'belongs_many_many', Config::INHERITED );

    //has_one
    foreach ($has_one as $name => $class)
    {
      if ( in_array($name, $embeddableRelations) )
      {
        $relationObj        = $obj->{$name}();
        $parsedRelationObj  = $this->parseObject($relationObj);
        $relations['has_one'][ Inflector::singularize($relationObj->ClassName) ] = $parsedRelationObj;
      }
    }

    //has_many
    foreach ($has_many as $relationName => $relationClass)
    {
      if ( in_array($relationName, $embeddableRelations) )
      {

        $has_many_objects = $obj->{$relationName}();
        $className        = Inflector::singularize( $has_many_objects->dataClass );
        $relations['has_many'][$className]  = array();

        foreach ($has_many_objects as $relationObject)
        {
          $parsedObject = $this->parseObject($relationObject);
          array_push($relations['has_many'][$className], $parsedObject);
        }

      }      
    }

    //many_many
    foreach ($many_many as $relationName => $relationClass)
    {
      if ( in_array($relationName, $embeddableRelations) )
      {

        $has_many_objects = $obj->{$relationName}();
        $className        = Inflector::singularize( $has_many_objects->dataClass );
        //$relationKey      = strtolower($relationName);

        $relations['many_many'][$className]  = array();
        //$relations['many_many'][$relationKey]  = array();

        foreach ($has_many_objects as $relationObject)
        {
          $parsedObject = $this->parseObject($relationObject);
          array_push($relations['many_many'][$className], $parsedObject);
          //array_push($relations['many_many'][$relationKey], $parsedObject);
        }

      }      
    }

    //belongs_many_many
    foreach ($belongs_many_many as $relationName => $relationClass)
    {
      if ( in_array($relationName, $embeddableRelations) )
      {

        $has_many_objects = $obj->{$relationName}();
        $className        = Inflector::singularize( $has_many_objects->dataClass );

        $relations['belongs_many_many'][$className]  = array();

        foreach ($has_many_objects as $relationObject)
        {
          $parsedObject = $this->parseObject($relationObject);
          array_push($relations['belongs_many_many'][$className], $parsedObject);
        }

      }      
    }

    return $relations;
  }


  /**
   * Checks if an Object's relation record(s) should be embedded or not
   *
   * @see APIController::$embeddedRecords
   * @param $obj DataObject Object to check the options againsts
   * @param $relationName String The name of the relation
   * @return boolean whether or not this relation's record(s) should be embedded
   */
  function isRelationEmbeddable($obj, $relationName)
  {
    $embedOptions = $this::$embeddedRecords;

    //if the Class has embedding options
    if ( array_key_exists( $obj->className, $embedOptions) )
    {
      //if the relation is embeddable
      if ( in_array($relationName, $embedOptions[$obj->className]) )
      {
        return true;
      }
    }

    return false;
  }

  /**
   * Decode the Payload sent through a PUT request
   * into an associative array with all attributes case converted
   */
  function decodePayload( $payloadBody )
  {
    $payload = json_decode( $payloadBody, true );

    if ( $payload )
    {
      $payload = $this->underscoredToCamelised( $payload );
      $payload = $this->upperCaseIDs( $payload );
    }
    else{
      return false;
    }

    return $payload;
  }

  /**
   * Convert an array's keys from underscored
   * to upper case first and camalized keys
   * @param array $map array to convert 
   * @return array converted array
   */
  function underscoredToCamelised( $map )
  {
    foreach ($map as $key => $value)
    {
      $newKey = ucfirst( Inflector::camelize($key) );

      // Change key if needed
      if ($newKey != $key)
      {
        unset($map[$key]);
        $map[$newKey] = $value;
      }

      // Handle nested arrays
      if (is_array($value))
      {
        $map[$newKey] = $this->underscoredToCamelised( $map[$newKey] );
      }
    }

    return $map;
  }


  /**
   * Changes 'id' suffix to upper case and remove trailing 's', good for (foreign)keys
   * 
   * @param  string $column The column name to fix
   * @return string         Fixed column name
   */
  function ucIDKeys( $column )
  {
    return preg_replace( '/(.*)ID(s)?$/i', '$1ID', $column);
  }


  /**
   * Fixes all ID and foreignKeyIDs to be uppercase
   * 
   * @param array $map array to convert 
   * @return array converted array
   */
  function upperCaseIDs( $map )
  {
    foreach ($map as $key => $value)
    {
      $newKey = $this->ucIDKeys( $key );

      // Change key if needed
      if ($newKey != $key)
      {
        unset($map[$key]);
        $map[$newKey] = $value;
      }

      // Handle nested arrays
      if (is_array($value))
      {
        $map[$newKey] = $this->upperCaseIDs( $map[$newKey] );
      }
    }

    return $map;
  }
  
  /**
   * Changes all object's property to CamelCase
   * @return stdClass converted object
   */
  function camelizeObjectAttributes($obj)
  {
    if ( !is_array($obj) )
    {
      $obj = $obj->toMap();
    }    
    $newObj = new stdClass();
    $has_one = Config::inst()->get( $obj['ClassName'], 'has_one', Config::INHERITED );  

    foreach ($obj as $key => $value)
    {
      $newKey = str_replace('ID', 'Id', $key);
      $newKey = lcfirst( Inflector::camelize($newKey) );

      //foreign keys to int
      $has_one_key = preg_replace( '/ID$/', '', $key);
      if ( array_key_exists( $has_one_key, $has_one ) )
      {
        $value = intVal( $value );
      }

      $newObj->{$newKey} = $value;
    }

    return $newObj;
  }

}