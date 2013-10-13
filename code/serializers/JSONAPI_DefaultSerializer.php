<?php
/**
 * Default API Model Serializer
 * handles DataObject, DataList etc.. JSON serialization and de-serialization
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package SS_JSONAPI
 * @subpackage Serializer
 */
class JSONAPI_DefaultSerializer implements JSONAPI_Serializer
{
	/**
	 * Store current JSONAPI instance
	 * @var JSONAPI
	 */
	private $api = null;
	
	/**
	 * Create instance and saves current api reference
	 * @param JSONAPI $api current JSONAPI instance
	 */
	public function __construct(JSONAPI $api)
	{
		if ( $api instanceof JSONAPI )
		{
			$this->api = $api;	
		}
		else{
			user_error("JSONAPI_DefaultSerializer __constuct requires a JSONAPI instance as argument.", E_USER_ERROR);
		}		
	}


	/**
   * Parse DataList/DataObject into root JSON 
   * ready to be returned to client
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
      //$className = strtolower( Inflector::underscore( Inflector::singularize($className) ) );
      $className = lcfirst( Inflector::singularize($className) );


      $root = new stdClass();
      $root->{$className} = new stdClass();

      return Convert::raw2json($root);
    }

    //multiple results to parse
    if ( $data instanceof DataList )
    {

      $className  = $data->dataClass;
      //$className  = strtolower( Inflector::underscore( Inflector::pluralize($className) ) );
      $className  = lcfirst( Inflector::singularize($className) );

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
      //$className = strtolower( Inflector::underscore( Inflector::singularize($className) ) );
      $className = lcfirst( Inflector::singularize($className) );
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
   * Parse DataObject attributes
   * converts keys to lowercase-camelized
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
      //$newKey = Inflector::underscore($newKey);
      $newKey = lcfirst($newKey);

      //remove foreign keys trailing ID
      $has_one_key = preg_replace ( '/ID$/', '', $key);

      //foreign keys to int OR embedding  
      if ( array_key_exists( $has_one_key, $has_one ) )
      {
        //convert to integer
        $value = intVal( $value );

        //if set and embeddable
        //@todo embeded records are deprecated (for now)
        if ( $this->isRelationEmbeddable($obj, $has_one_key) && $value !== 0 )
        {
          $embeddedObject = $obj->{$has_one_key}();
          if ( $embeddableObject )
          {
            $value = $this->parseObject($embeddedObject);
          }
        }

        //use non IDed key for has_one
        $newKey = lcfirst($has_one_key);

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
    //comments: [1, 2, 3]
    $many_relations = array();
    if ( is_array($has_many) )          $many_relations = array_merge($many_relations, $has_many);
    if ( is_array($many_many) )         $many_relations = array_merge($many_relations, $many_many);
    if ( is_array($belongs_many_many) ) $many_relations = array_merge($many_relations, $belongs_many_many);
    
    foreach ($many_relations as $relationName => $relationClassname)
    {
      //get the DataList for this realtion's name
      $many_objects = $obj->{$relationName}();

      //if there actually are objects in the relation
      if ( $many_objects->count() )
      {
        //if embeddable
        //@todo embeded records are deprecated (for now)
        if ( $this->isRelationEmbeddable($obj, $relationName) )
        {
          $newKey = Inflector::underscore( Inflector::pluralize($relationName) );
          $newObj[$newKey] = array();
          foreach ($many_objects as $embeddedObject) {
            array_push(
              $newObj[$newKey],
              $this->parseObject($embeddedObject)
            );
          }
        }
        else{
          //ID list only
          //$newKey = Inflector::underscore( Inflector::singularize($relationName) );
          $newKey = lcfirst($relationName);
          $idList = $many_objects->map('ID', 'ID')->keys();
          //$newObj[$newKey.'_ids'] = $idList;
          $newObj[$newKey] = $idList;
        }
      }
    } 

    return $newObj;
  }

  /**
   * Load all of an object's relations
   * Used for side loaded records
   *
   * @todo  should implement and async config check, only sideload is not async   * 
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
   * @todo embeded records are deprecated (for now)
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
      //$payload = $this->underscoredToCamelised( $payload );
      $payload = $this->ucfirstCamelcaseKeys( $payload );
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
  function ucfirstCamelcaseKeys( $map )
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
   * Convert an array's keys from underscored
   * to upper case first and camalized keys
   * @param array $map array to convert 
   * @return array converted array
   */
  /*
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
  }*/

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
   * Changes all object's property to CamelCase
   * @return stdClass converted object
   */
  /*
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
  */
}