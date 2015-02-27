<?php
/**
 * Default RESTfulAPI Query handler
 * handles models request etc...
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage QueryHandler
 */
class RESTfulAPI_DefaultQueryHandler implements RESTfulAPI_QueryHandler
{

  /**
   * Current deSerializer instance
   * 
   * @var RESTfulAPI_DeSerializer
   */
  public $deSerializer;


  /**
   * Injector dependencies
   * Override in configuration to use your custom classes
   * 
   * @var array
   * @config
   */
  private static $dependencies = array(
    'deSerializer' => '%$RESTfulAPI_BasicDeSerializer'
  );


  /**
   * Search Filter Modifiers Separator used in the query var
   * i.e. ?column__EndsWith=value
   * 
   * @var string
   * @config
   */
  private static $searchFilterModifiersSeparator = '__';


  /**
   * Query vars to skip (uppercased)
   * 
   * @var array
   * @config
   */
  private static $skipedQueryParameters = array('URL', 'FLUSH', 'FLUSHTOKEN');


  /**
   * Set a maximum numbers of records returned by the API.
   * Only affectects "GET All". Useful to avoid returning millions of records at once.
   * 
   * Set to -1 to disable.
   * 
   * @var integer
   * @config
   */
  private static $max_records_limit = 100;


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
	 * Return current RESTfulAPI DeSerializer instance
   * 
	 * @return RESTfulAPI_DeSerializer DeSerializer instance
	 */
	public function getdeSerializer()
	{
		return $this->deSerializer;
	}

	
  /**
   * All requests pass through here and are redirected depending on HTTP verb and params
   * 
   * @param  SS_HTTPRequest        $request    HTTP request
   * @return DataObjec|DataList                DataObject/DataList result or stdClass on error
   */
  public function handleQuery(SS_HTTPRequest $request)
  { 
    //get requested model(s) details
    $model       = $request->param('ClassName');
    $id          = $request->param('ID');
    $response    = false;
    $queryParams = $this->parseQueryParameters( $request->getVars() );

    //validate Model name + store
    if ($model)
    {
      $model = $this->deSerializer->unformatName( $model );
      if ( !class_exists($model) )
      {
        return new RESTfulAPI_Error(400,
          "Model does not exist. Received '$model'."
        );
      }
      else{
        //store requested model data and query data
        $this->requestedData['model'] = $model;
      }
    }
    else{
      //if model missing, stop + return blank object
      return new RESTfulAPI_Error(400,
        "Missing Model parameter."
      );
    }
    
    //validate ID + store
    if ( ($request->isPUT() || $request->isDELETE()) && !is_numeric($id) )
    {
      return new RESTfulAPI_Error(400,
        "Invalid or missing ID. Received '$id'."
      );
    }
    else if ( $id !== NULL && !is_numeric($id) )
    {
      return new RESTfulAPI_Error(400,
        "Invalid ID. Received '$id'."
      );
    }
    else{
      $this->requestedData['id'] = $id;
    }

    //store query parameters
    if ($queryParams)
    {
      $this->requestedData['params'] = $queryParams;
    }

    //check API access rules on model
    if ( !RESTfulAPI::api_access_control($model, $request->httpMethod()) )
    {
      return new RESTfulAPI_Error(403,
        "API access denied."
      );
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
    	return new RESTfulAPI_Error(403,
        "HTTP method mismatch."
      );
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
    $searchFilterModifiersSeparator = Config::inst()->get('RESTfulAPI_DefaultQueryHandler', 'searchFilterModifiersSeparator');

    foreach ($params as $key__mod => $value)
    {
      // skip url, flush, flushtoken
      if ( in_array(strtoupper($key__mod), Config::inst()->get('RESTfulAPI_DefaultQueryHandler', 'skipedQueryParameters')) ) continue;

      $param = array();

      $key__mod = explode(
        $searchFilterModifiersSeparator,
        $key__mod
      );

      $param['Column'] = $this->deSerializer->unformatName( $key__mod[0] );

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
   * Handles column modifiers: :StartsWith, :EndsWith,
   * :PartialMatch, :GreaterThan, :LessThan, :Negation
   * and query modifiers: sort, rand, limit
   *
   * @param  string                 $model          Model(s) class to find
   * @param  boolean|integr         $id             The ID of the model to find or false
   * @param  array                  $queryParams    Query parameters and modifiers
   * @param  SS_HTTPRequest         $request        The original HTTP request
   * @return DataObject|DataList                    Result of the search (note: DataList can be empty) 
   */
  function findModel($model, $id = false, $queryParams, SS_HTTPRequest $request)
  {
    if ($id)
    {
      $return = DataObject::get_by_id($model, $id);
      
      if ( !$return )
      {
        return new RESTfulAPI_Error(404,
          "Model $id of $model not found."
        );
      }
      else if ( !RESTfulAPI::api_access_control($return, $request->httpMethod()) )
      {
        return new RESTfulAPI_Error(403,
          "API access denied."
        );
      }
    }
    else{
      $return = DataList::create($model);

      if ( count($queryParams) > 0 )
      {
        foreach ($queryParams as $param)
        {

          if ( $param['Column'] )
          {
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
              // rand only >> FIX: gen seed to avoid random result on relations
              else{                
                $return = $return->sort('RAND('.time().')');
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

      //sets default limit if none given
      $limits = $return->dataQuery()->query()->getLimit();
      $limitConfig = Config::inst()->get('RESTfulAPI_DefaultQueryHandler', 'max_records_limit');

      if ( is_array($limits) && !array_key_exists('limit', $limits) && $limitConfig >= 0 )
      {
        $return = $return->limit($limitConfig);
      }
    }

    return $return;
  }


  /**
   * Create object of class $model
   * 
   * @param  string         $model
   * @param  SS_HTTPRequest $request
   * @return DataObject
   */
  function createModel($model, SS_HTTPRequest $request)
  {
    if ( !RESTfulAPI::api_access_control($model, $request->httpMethod()) )
    {
      return new RESTfulAPI_Error(403,
        "API access denied."
      );
    }

    $newModel = Injector::inst()->create($model);
    $newModel->write();

    return $this->updateModel($model, $newModel->ID, $request);
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
    if ( !$model )
    {
      return new RESTfulAPI_Error(404,
        "Record not found."
      );
    }

    if ( !RESTfulAPI::api_access_control($model, $request->httpMethod()) )
    {
      return new RESTfulAPI_Error(403,
        "API access denied."
      );
    }

    $payload = $this->deSerializer->deserialize( $request->getBody() );
    if ( $payload instanceof RESTfulAPI_Error )
    {
      return $payload;
    }

    if ( $model && $payload )
    {
      $has_one           = Config::inst()->get( $model->ClassName, 'has_one' );
      $has_many          = Config::inst()->get( $model->ClassName, 'has_many' );
      $many_many         = Config::inst()->get( $model->ClassName, 'many_many' );
      $belongs_many_many = Config::inst()->get( $model->ClassName, 'belongs_many_many' );

      $many_many_extraFields = array();

      if ( isset($payload['ManyManyExtraFields']) )
      {
        $many_many_extraFields = $payload['ManyManyExtraFields'];
        unset($payload['ManyManyExtraFields']);
      }

      $hasChanges         = false;
      $hasRelationChanges = false;

      foreach ($payload as $attribute => $value)
      {
        if ( !is_array($value) )
        {
          if ( is_array($has_one) && array_key_exists($attribute, $has_one) )
          {
            $relation         = $attribute . 'ID';
            $model->$relation = $value;
            $hasChanges       = true;
          }
          else if ( $model->{$attribute} != $value )
          {
            $model->{$attribute} = $value;
            $hasChanges          = true;
          }
        }
        else{
          //has_many, many_many or $belong_many_many
          if ( (is_array($has_many) && array_key_exists($attribute, $has_many))
               || (is_array($many_many) && array_key_exists($attribute, $many_many))
               || (is_array($belongs_many_many) && array_key_exists($attribute, $belongs_many_many))
          ) {
            $hasRelationChanges = true;
            $ssList = $model->{$attribute}();            
            $ssList->removeAll(); //reset list
            foreach ($value as $id)
            {
              // check if there is extraFields
              if ( array_key_exists($attribute, $many_many_extraFields) )
              {
                if ( isset($many_many_extraFields[$attribute][$id]) )
                {
                  $ssList->add( $id, $many_many_extraFields[$attribute][$id] );
                  continue;
                }                
              }

              $ssList->add( $id );
            }
          }
        }
      }

      if ( $hasChanges || $hasRelationChanges )
      {
        $model->write(false, false, false, $hasRelationChanges);
      }
    }

    return DataObject::get_by_id($model->ClassName, $model->ID);
  }


  /**
   * Delete object of Class $model and ID $id
   *
   * @todo  Respond with a 204 status message on success?
   * 
   * @param  string          $model     Model class
   * @param  integer 				 $id        Model ID
   * @param  SS_HTTPRequest  $request   Model ID
   * @return NULL|array                 NULL if successful or array with error detail              
   */
  function deleteModel($model, $id, SS_HTTPRequest $request)
  {
    if ( $id )
    {
      $object = DataObject::get_by_id($model, $id);

      if ( $object )
      {
        if ( !RESTfulAPI::api_access_control($object, $request->httpMethod()) )
        {
          return new RESTfulAPI_Error(403,
            "API access denied."
          );
        }
        
        $object->delete();
      }
      else{
        return new RESTfulAPI_Error(404,
          "Record not found."
        );
      }
    }
    else{
      //shouldn't happen but just in case
      return new RESTfulAPI_Error(400,
        "Invalid or missing ID. Received '$id'."
      );
    }
    
    return NULL;
  }
}
