<?php
/**
 * Default API Query handler
 * handles models request etc...
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package SS_JSONAPI
 * @subpackage QueryHandler
 */
class JSONAPI_DefaultQueryHandler implements JSONAPI_QueryHandler
{

	/**
	 * Stores current JSONAPI instance
   * 
	 * @var JSONAPI
	 */
	private $api = null;


	/**
	 * Stores current JSONAPI Serializer instance
   * 
	 * @var JSONAPI
	 */
	private $serializer = null;


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
   * @todo embeded records are deprecated (for now)
   */
  private static $embeddedRecords = array();	


  /**
   * Sideloaded records settings
   * 
   * @todo not implemented
   * 
   * @var array
   */
  private static $sideloadedRecords = array();


  /**
   * Search Filter Modifiers Separator used in the query var
   * i.e. ?column__EndsWith=value
   * 
   * @var string
   */
  private static $searchFilterModifiersSeparator = '__';


  /**
   * Stores the currently requested data
   * 
   * @var array
   */
  public $requestedData = array(
    'model'  => null,
    'id'     => null,
    'params' => null
  );


  /**
	 * Return current JSONAPI instance
   * 
	 * @return JSONAPI JSONAPI instance
	 */
	public function getapi()
	{
		return $this->api;
	}


	/**
	 * Return current JSONAPI Serializer instance
   * 
	 * @return JSONAPI_Serializer Serializer instance
	 */
	public function getserializer()
	{
		return $this->serializer;
	}


	/**
	 * Create instance and saves current api reference
   * 
	 * @param JSONAPI $api current JSONAPI instance
	 */
	public function __construct(JSONAPI $api)
	{
		if ( $api instanceof JSONAPI )
		{
			$this->api = $api;
			$this->serializer = $api->getserializer();
		}
		else{
			user_error("JSONAPI_DefaultQueryHandler __constuct requires a JSONAPI instance as argument.", E_USER_ERROR);
		}		
	}

	
  /**
   * All requests pass through here and are redirected depending on HTTP verb and params
   * 
   * @param  SS_HTTPRequest                 $request    HTTP request
   * @return DataObjec|DataList|stdClass                DataObject/DataList result or stdClass on error
   */
  public function handleQuery(SS_HTTPRequest $request)
  { 
  	//get requested model(s) details
    $model       = $request->param('ClassName');
    $id          = $request->param('ID');
    $response    = false;
    $queryParams = $this->parseQueryParameters( $request->getVars() );

    //convert model name to SS conventions
    if ($model)
    {
      $model = $this->serializer->unformatName( $model );
    }
    else{
      //if model missing, stop + return blank object
      return false;
    }

    //store requested model data and query data
    $this->requestedData['model'] = $model;
    if ($id)
    {
      $this->requestedData['id'] = $id;
    }
    if ($queryParams)
    {
      $this->requestedData['params'] = $queryParams;
    }

    //map HTTP word to module method
    if ( $request->isGET() )
    {
      $result = $this->findModel($model, $id, $queryParams, $request);
    }
    elseif ( $request->isPOST() )
    {
      $result = $this->createModel($model, $request);
    }
    elseif ( $request->isPUT() )
    {
      $result = $this->updateModel($model, $id, $request);
    }
    elseif ( $request->isDELETE() )
    {
      $result = $this->deleteModel($model, $id, $request);
    }
    else{
    	$result = false;
    }
    
    return $result;
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
  function parseQueryParameters(array $params)
  {
    $parsedParams = array();
    $searchFilterModifiersSeparator = Config::inst()->get( 'JSONAPI_DefaultQueryHandler', 'searchFilterModifiersSeparator', Config::INHERITED );

    foreach ($params as $key__mod => $value)
    {
      //if ( strtoupper($key__mod) === 'URL' ) continue;
      // skip ul, flush, flushtoken
      if ( in_array(strtoupper($key__mod), array('URL', 'FLUSH', 'FLUSHTOKEN')) ) continue;

      $param = array();

      $key__mod = explode(
        $searchFilterModifiersSeparator,
        $key__mod
      );

      $param['Column'] = $this->serializer->unformatName( $key__mod[0] );

      $param['Value'] = $value;

      if ( isset($key__mod[1]) )
    	{
    		$param['Modifier'] = $key__mod[1];
    	}
      else{
      	$param['Modifier'] = null;
    	}

      array_push($parsedParams, $param);
    }

    return $parsedParams;
  }


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
          	//@delete ?
            //$param['Column'] = Inflector::camelize( $param['Column'] );

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
              // rand + seed
              if ( $param['Value'] )
              {
                $return = $return->sort('RAND('.$param['Value'].')');
              }
              // rand only
              else{
                $return = $return->sort('RAND()');
              }
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
   * 
   * @todo not implemented
   * @param  string         $model
   * @param  SS_HTTPRequest $request
   * @return DataObject
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
   * @todo embeded records are deprecated so relations updating probably doesn't work anymore
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
          //@todo check/test
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
   * Delete object of Class $model and ID $id
   * 
   * @todo not implemented
   * @param  string         $model   Model class
   * @param  Integer 				$id      Model ID
   * @return Boolean                 true if successful or false if failed              
   */
  function deleteModel($model, $id)
  {

  }
}