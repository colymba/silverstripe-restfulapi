<?php
/**
 * Basic RESTfulAPI Model Serializer
 * handles DataObject, DataList etc.. JSON serialization and de-serialization
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Serializer
 */
class RESTfulAPI_BasicSerializer implements RESTfulAPI_Serializer
{

	/**
	 * Content-type header definition for this Serializer
	 * Used by RESTfulAPI in the response
	 * 
	 * @var string
	 */
	private $contentType = 'application/json; charset=utf-8';

	
	/**
	 * Return Content-type header definition
	 * to be used in the API response
	 * 
	 * @return string Content-type
	 */
	public function getcontentType()
	{
		return $this->contentType;
	}


  /**
   * Stores the current $embedded_records @config
   * Config set on {@link RESTfulAPI}
   * 
   * @var array
   */
  protected $embeddedRecords;


  /**
   * Construct and set current config
   */
  public function __construct()
  {
    $embedded_records = Config::inst()->get('RESTfulAPI', 'embedded_records');
    if ( is_array($embedded_records) )
    {
      $this->embeddedRecords = $embedded_records;
    }
    else{
      $this->embeddedRecords = array();
    }
  }


	/**
	 * Convert data into a JSON string
	 * 
	 * @param  mixed  $data Data to convert
	 * @return string       JSON data
	 */
	protected function jsonify($data)
	{
		$json = json_encode($data, JSON_NUMERIC_CHECK);
		
		//catch JSON parsing error
		$error = RESTfulAPI_Error::get_json_error();
		if ( $error !== false )
		{
			return new RESTfulAPI_Error(400, $error);
		}

		return $json;
	}


	/**
	 * Convert raw data (DataObject or DataList) to JSON
	 * ready to be consumed by the client API
	 * 
	 * @param  mixed   $data  Data to serialize
	 * @return string         JSON representation of data
	 */
	public function serialize($data)
	{
		$json = '';
		$formattedData = null;

		if ( $data instanceof DataObject )
		{
			$formattedData = $this->formatDataObject( $data );
		}
		else if ( $data instanceof DataList )
		{
			$formattedData = $this->formatDataList( $data );
		}

		if ( $formattedData !== null )
		{
			$json = $this->jsonify($formattedData);
		}
		else{
			//fallback: convert non array to object then encode
			if ( !is_array($data) )
			{
				$data = (object) $data;
			}
			$json = $this->jsonify($data);
		}

		return $json;
	}


	/**
	 * Format a DataObject keys and values
	 * ready to be turned into JSON
	 * 
	 * @param  DataObject $dataObject The data object to format
	 * @return array                  The formatted array map representation of the DataObject
	 */
	protected function formatDataObject(DataObject $dataObject)
	{
		if( method_exists($dataObject, 'onBeforeSerialize') )
    {
      $dataObject->onBeforeSerialize();
    }

    // setup
    $formattedDataObjectMap = array();
    $dataObjectMap          = $dataObject->toMap();

    // get DataObject realtions config
    $has_one           = Config::inst()->get( $dataObject->ClassName, 'has_one',           Config::INHERITED );
    $has_many          = Config::inst()->get( $dataObject->ClassName, 'has_many',          Config::INHERITED );
    $many_many         = Config::inst()->get( $dataObject->ClassName, 'many_many',         Config::INHERITED );
    $belongs_many_many = Config::inst()->get( $dataObject->ClassName, 'belongs_many_many', Config::INHERITED );

    // iterate $db fields and $has_one relations
    foreach ($dataObjectMap as $columnName => $value)
    {
    	$columnName = $this->serializeColumnName( $columnName );

    	// if NOT has_one relation
    	if ( !array_key_exists( $columnName, $has_one ) )
    	{
    		// straight copy
    		$formattedDataObjectMap[$columnName] = $value;
    	}
    	else{
    		// convert foreign ID to integer
        $value = intVal( $value );
        // skip empty relations
        if ( $value === 0 ) continue;

        // check if this should be embedded
        if ( $this->isEmbeddable($dataObject->ClassName, $columnName) && RESTfulAPI::isAPIEnabled($has_one[$columnName]) )
        {
        	// get the relation's record ready to embed
	      	$embedData = $this->getEmbedData($dataObject, $columnName);
	      	// embed the data if any
	      	if ( $embedData !== null )
	      	{	      		
	      		$formattedDataObjectMap[$columnName] = $embedData;
	      	}
        }
        else{
        	// save formatted data
    			$formattedDataObjectMap[$columnName] = $value;
        }        
    	}    	
    }

    // combine defined '_many' relations into 1 array
    $many_relations = array();
    if ( is_array($has_many) )          $many_relations = array_merge($many_relations, $has_many);
    if ( is_array($many_many) )         $many_relations = array_merge($many_relations, $many_many);
    if ( is_array($belongs_many_many) ) $many_relations = array_merge($many_relations, $belongs_many_many);
    
    // iterate '_many' relations
    foreach ($many_relations as $relationName => $relationClassname)
    {
    	//get the DataList for this realtion's name
      $dataList = $dataObject->{$relationName}();

      //if there actually are objects in the relation
      if ( $dataList->count() )
      {
      	// check if this relation should be embedded
      	if ( $this->isEmbeddable($dataObject->ClassName, $relationName) && RESTfulAPI::isAPIEnabled($relationClassname) )
	      {
	      	// get the relation's record(s) ready to embed
	      	$embedData = $this->getEmbedData($dataObject, $relationName);
	      	// embed the data if any
	      	if ( $embedData !== null )
	      	{
	      		$columnName = $this->serializeColumnName( $relationName );
	      		$formattedDataObjectMap[$columnName] = $embedData;
	      	}
	      }
	      else{
	      	// set column value to ID list
	        $idList = $dataList->map('ID', 'ID')->keys();

	        $columnName = $this->serializeColumnName( $relationName );
	        $formattedDataObjectMap[$columnName] = $idList;
	      }
      }
    }

    if( method_exists($dataObject, 'onAfterSerialize') )
    {
      $formattedDataObjectMap = $dataObject->onAfterSerialize( $formattedDataObjectMap );
    }

    return $formattedDataObjectMap;
	}


	/**
	 * Format a DataList into a formatted array
	 * ready to be turned into JSON
	 * 
	 * @param  DataList  $dataList  The DataList to format
	 * @return array                The formatted array representation of the DataList
	 */
	protected function formatDataList(DataList $dataList)
	{
		$formattedDataListMap = array();

		foreach ($dataList as $dataObject)
    {
      $formattedDataObjectMap = $this->formatDataObject( $dataObject );
      array_push($formattedDataListMap, $formattedDataObjectMap);
    }

    return $formattedDataListMap;
	}


	/**
	 * Format a SilverStripe ClassName or Field name
	 * to be used by the client API
	 * 
	 * @param  string $name ClassName of DBField name
	 * @return string       Formatted name
	 */
	public function formatName($name)
	{
		return $name;
	}


	/**
	 * Format a DB Column name or Field name
	 * to be used by the client API
	 * 
	 * @param  string $name Field name
	 * @return string       Formatted name
	 */
	protected function serializeColumnName($name)
	{
		//remove trailing ID from has_one
		$name = preg_replace( '/(.+)ID$/', '$1', $name);

		return $name;
	}


  /**
   * Returns a DataObject relation's data
   * formatted and ready to embed.
   * 
   * @param  DataObject $record       The DataObject to get the data from
   * @param  string     $relationName The name of the relation
   * @return array|null               Formatted DataObject or RelationList ready to embed or null if nothing to embed
   */
  protected function getEmbedData(DataObject $record, $relationName)
  {
  	if ( $record->hasMethod($relationName) )
    {
      $relationData = $record->$relationName();
      if ( $relationData instanceof RelationList )
      {
        return $this->formatDataList($relationData);
      }
      else{
        return $this->formatDataObject($relationData);
      }        
    }

    return null;
  }


  /**
   * Checks if a speicific model's relation
   * should have its records embedded.
   * 
   * @param  string  $model    Model's classname
   * @param  string  $relation Relation name
   * @return boolean           [description]
   */
  protected function isEmbeddable($model, $relation)
  {
    if ( array_key_exists($model, $this->embeddedRecords) )
    {
    	return is_array($this->embeddedRecords[$model]) && in_array($relation, $this->embeddedRecords[$model]);
    }

    return false;
  }
}