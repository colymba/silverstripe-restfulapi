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
	 * Convert data into a JSON string
	 * 
	 * @param  mixed  $data Data to convert
	 * @return string       JSON data
	 */
	public function jsonify($data)
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
		$formattedData = false;

		if ( $data instanceof DataObject )
		{
			$formattedData = $this->formatDataObject( $data );
		}
		else if ( $data instanceof DataList )
		{
			$formattedData = $this->formatDataList( $data );
		}

		if ( $formattedData )
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
	private function formatDataObject(DataObject $dataObject)
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

    // iterate $db fields and $has_one realtions
    foreach ($dataObjectMap as $columnName => $value)
    {
    	$hasOneColumnName = preg_replace( '/ID$/i', '', $columnName );
    	$columnName = $this->serializeColumnName( $columnName );

    	// if this column is a has_one relation
    	if ( array_key_exists( $hasOneColumnName, $has_one ) )
    	{
    		// convert value to integer
        $value = intVal( $value );

        // skip
        if ( $value === 0 ) continue;

        // remove ID suffix from realation name
        $columnName = $this->serializeColumnName( $hasOneColumnName );
    	}

    	// save formatted data
    	$formattedDataObjectMap[$columnName] = $value;
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
        // set column value to ID list
        $idList = $dataList->map('ID', 'ID')->keys();

        $columnName = $this->serializeColumnName( $relationName );
        $formattedDataObjectMap[$columnName] = $idList;
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
	private function formatDataList(DataList $dataList)
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
	public function formatName(string $name)
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
	private function serializeColumnName(string $name)
	{
		//remove trailing ID from has_one
		$name = preg_replace( '/(.+)ID$/', '$1', $name);

		return $name;
	}
}