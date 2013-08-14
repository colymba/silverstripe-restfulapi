<?php

class APIController extends Controller
{
  /**
   * @var Boolean $useTokenAuthentication If true 'all' requests will be checked for authentication with a token
   */
  private static $useTokenAuthentication = false;

  /**
   * @var Integer $tokenLife Authentication token life in ms
   */
  private static $tokenLife = 10800000;//3 * 60 * 60 * 1000;

  const AUTH_CODE_LOGGED_IN     = 0;
  const AUTH_CODE_LOGIN_FAIL    = 1;
  const AUTH_CODE_TOKEN_INVALID = 2;
  const AUTH_CODE_TOKEN_EXPIRED = 3;

  /**
   * @var Array $cors Cross-Origin Resource Sharing (CORS) API settings for cross domain XMLHTTPRequest 
   */
  private static $cors = array(
    'Enabled'       => true,
    'Allow-Origin'  => '*', // * OR Allowed Origin String 'http://localhost' OR Array list of accepted origins array('http://localhost', 'http://domain.com')
    'Allow-Headers' => '*', // * OR or comma separated values 'hearder-1, header-2'
    'Allow-Methods' => 'OPTIONS, POST, GET, PUT, DELETE',
    'Max-Age'       => 86400 //seconds = 1 day //@NOTE has no effect if Authentification is enabled => custom header = always preflight?
  );

  /**
   * @var Array $embeddedRecords Map of classes to embed for specific record
   * i.e. 'RequestedClass' => array('ClassToEmbed', 'Another')
   */
  private static $embeddedRecords = array(
    'Member' => array('Favourites')
  );

  //@TODO
  private static $sideloadedRecords = array(
  );

  /**
   * @var Array $allowed_actions URL handler allowed actions
   */
  private static $allowed_actions = array(
    'index',
    'login',
    'logout',
    'lostpassword'
  );

  /**
   * @var Array $url_handlers URL handler definition
   */
  public static $url_handlers = array(
    'login' => 'login',
    'logout' => 'logout',
    'lostpassword' => 'lostPassword',
    '$ClassName/$ID' => 'index'
  );

  /**
   * @var Array $requestData Stores the currently requested data (Model class + ID)
   */
  private $requestData = array(
    'model' => null,
    'id'    => null
  );

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
   * @param JSON|String $json the response body
   * @param Array|false $error use false if not an error otherwise pass array('code' => statusCode, 'description' => statusDescription)
   * @param Boolean $corsPreflight set to true if this is a XHR preflight request answer. CORS shoud be enabled.
   * @return JSON API response
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
   * @param SS_HTTPRequest $request HTTP request containing 'email' & 'pwd' vars
   * @return JSON Returns JSON object of the result (result, message, token, member)
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
   * @param SS_HTTPRequest $request HTTP request containing 'email' var
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
   * @param SS_HTTPRequest $request HTTP request containing 'email' vars
   * @return JSON Returns JSON 'email' = false if email fails (member desn't will not be reported)
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
   * Validate the API token from an HTTP Request
   *
   * @param SS_HTTPRequest $request HTTP request with API token header "X-Silverstripe-Apitoken"
   * @return array Returns an array with the result and eventual error message
   */
  function validateAPIToken(SS_HTTPRequest $request)
  {
    $response = array();
    $token = $request->getHeader("X-Silverstripe-Apitoken");
    if (!$token)
    {
      $token = $request->requestVar('token');
    }

    if ( $token )
    {
      $member = Member::get()->filter(array('ApiToken' => $token))->first();
      if ( $member )
      {
        $tokenExpire  = $member->ApiTokenExpire;
        $now          = time();
        $life         = Config::inst()->get( 'APIController', 'tokenLife', Config::INHERITED );

        if ( $tokenExpire > ($now - $life) )
        {
          $member->logIn();
          $response['valid'] = true;
        }
        else{
          $response['valid']   = false;
          $response['message'] = 'Token expired.';
          $response['code']    = self::AUTH_CODE_TOKEN_EXPIRED;
        }        
      }      
    }
    else{
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
   * @param SS_HTTPRequest $request HTTP request
   *
   * @return JSON Returns json object of the models found
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
    $model = $request->param('ClassName');
    $id = $request->param('ID');
    $response = false;

    //convert model name to SS conventions
    if ($model)
    {
      $model = ucfirst( Inflector::singularize($model) );
    }

    //store requested model data
    $this->requestData['model'] = $model;
    $this->requestData['id'] = $id;

    //map HTTP word to API method
    //@TODO handle error
    if ( $request->isGET() )
    {
      $response = $this->findModel($model, $id, $request);
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
   * Finds 1 or more objects of class $model
   *
   * @param String $model the model(s) class to find
   * @param false|Integer $id The ID of the model to find
   * @param SS_HTTPRequest the original request
   *
   * @return DataObject|DataList the result of the search (note: DataList can be empty)
   */
  function findModel($model, $id = false, $request)
  {    
    if ($id)
    {
      $return = DataObject::get_by_id($model, $id);
    }
    else{
      //?ids[]=1&ids[]=2
      $filters = $request->getVars();
      unset($filters['url']);

      $return = DataObject::get($model);

      if ( count($filters) > 0 )
      {
        foreach ($filters as $filter => $value)
        {
          if ( $filter === 'ids' || $filter === 'id' )
          {
            $filter = 'ID';
          }
          else{
            $filter = Inflector::camelize($filter);
          }
          $return = $return->filter(array($filter => $value));
        }
      }
    }

    return $return;
  }

  /**
   * Create object of class $model
   * @TODO
   */
  function createModel($model, $request)
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
      $className = strtolower( Inflector::singularize($className) );

      $root = new stdClass();
      $root->{$className} = new stdClass();

      return Convert::raw2json($root);
    }

    //multiple results to parse
    if ( $data instanceof DataList )
    {

      $className  = $data->dataClass;
      $className  = strtolower( Inflector::pluralize($className) );

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
      $className = strtolower( Inflector::singularize($className) );
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
   * Fixes all ID and foreignKeyIDs to be uppercase
   * @param array $map array to convert 
   * @return array converted array
   */
  function upperCaseIDs( $map )
  {
    foreach ($map as $key => $value)
    {
      $newKey = preg_replace( '/(.*)ID$/i', '$1ID', $key);

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