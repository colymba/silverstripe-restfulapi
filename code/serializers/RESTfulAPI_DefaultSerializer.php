<?php
/**
 * Default RESTfulAPI Model Serializer
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
class RESTfulAPI_DefaultSerializer implements RESTfulAPI_Serializer
{

	/**
	 * Content-type header definition for this Serializer
	 * Used by RESTfulAPI in the response
	 * 
	 * @var string
	 */
	private $contentType = 'application/json; charset=utf-8';

	
	/**
	 * Stores current RESTfulAPI instance
	 * 
	 * @var RESTfulAPI
	 */
	private $api = null;
	

	/**
	 * Stores current RESTfulAPI Query Handler
	 * 
	 * @var RESTfulAPI_QueryHandler
	 */
	private $queryHandler = null;


  /**
	 * Return current RESTfulAPI instance
	 * 
	 * @return RESTfulAPI RESTfulAPI instance
	 */
	public function getapi()
	{
		return $this->api;
	}


	/**
	 * Return current RESTfulAPI Query Handler instance
	 * 
	 * @return RESTfulAPI_QueryHandler QueryHandler instance
	 */
	public function getserializer()
	{
		return $this->queryHandler;
	}


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
	 * Create instance and saves current api reference
	 * 
	 * @param RESTfulAPI $api current RESTfulAPI instance
	 */
	public function __construct(RESTfulAPI $api)
	{
		if ( $api instanceof RESTfulAPI )
		{
			$this->api = $api;
			$this->queryHandler = $api->getqueryHandler();
		}
		else{
			user_error("RESTfulAPI_DefaultSerializer __constuct requires a RESTfulAPI instance as argument.", E_USER_ERROR);
		}		
	}


	/**
	 * Convert raw data (DataObject or DataList) to JSON
	 * ready to be consumed by the client API
	 * 
	 * @param  DataObject|DataList  $data  Data to serialize
	 * @return string                      JSON representation of data
	 */
	public function serialize($data)
	{
		$json = '';
		$formattedData = false;

		if ( $data instanceof DataObject )
		{
			$className = $this->formatName( $data->ClassName );
			$formattedData = $this->formatDataObject( $data );
		}
		else if ( $data instanceof DataList )
		{
			$className = $this->formatName( $data->dataClass );
			$className = Inflector::pluralize( $className );
			$formattedData = $this->formatDataList( $data );
		}
		else if ( is_array($data) )
		{
			$json = Convert::raw2json($data);
		}
		else{
			//no usable $data -> empty response
      $json = '';
		}

		if ( $formattedData )
		{
			$root = new stdClass();
	    $root->{$className} = $formattedData;

			$json = Convert::raw2json($root);
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
		if ( ClassInfo::exists($name) )
		{
			$name = Inflector::singularize( $name );
			$name = lcfirst( $name );
		}
		else{
			$name = $this->serializeColumnName( $name );
		}

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
		$name = str_replace('ID', 'Id', $name);
		$name = lcfirst($name);

		return $name;
	}




	/* ************************************************************************************
	 * DESERIALIZE ************************************************************************
	 * ************************************************************************************/




	/**
	 * Convert client JSON data to an array of data
	 * ready to be consumed by SilverStripe
	 * 
	 * @param  string        $data   JSON to be converted to data ready to be consumed by SilverStripe
	 * @return array|false           Formatted array representation of the JSON data or false if failed
	 */
	public function deserialize(string $json)
	{
		$data = json_decode( $json, true );

    if ( $data )
    {
      foreach ($data as $column => $value)
      {
      	$newColumn = $this->deserializeColumnName( $column );
      	if ( $newColumn !== $column )
      	{
      		unset($data[$column]);
        	$data[$newColumn] = $value;
      	}
      }
    }
    else{
      return false;
    }

		return $data;
	}


	/**
	 * Format a ClassName or Field name sent by client API
	 * to be used by SilverStripe
	 * 
	 * @param  string $name ClassName of Field name
	 * @return string       Formatted name
	 */
	public function unformatName(string $name)
	{
		$class = Inflector::singularize( $name );
		$class = ucfirst( $class );

		if ( ClassInfo::exists($class) )
		{
			return $class;
		}
		else{
			$name = $this->deserializeColumnName( $name );
		}

		return $name;
	}


	/**
	 * Format a DB Column name or Field name
	 * sent from client API to be used by SilverStripe
	 * 
	 * @param  string $name Field name
	 * @return string       Formatted name
	 */
	private function deserializeColumnName(string $name)
	{
		$name = preg_replace( '/(.*)ID(s)?$/i', '$1ID', $name);
		$name = ucfirst($name);

		return $name;
	}




	/* ************************************************************************************
	 * UTILITIES LEGACY *******************************************************************
	 * ************************************************************************************/




	/**
   * Changes 'id' suffix to upper case and remove trailing 's', good for (foreign)keys
   * 
   * @param  string $column The column name to fix
   * @return string         Fixed column name
   */
  private function ucIDKeys( $column )
  {
    return preg_replace( '/(.*)ID(s)?$/i', '$1ID', $column);
  }


  /**
   * Convert an array's keys from underscored
   * to upper case first and camalized keys
   * 
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
        $map[$newKey] = $this->ucfirstCamelcaseKeys( $map[$newKey] );
      }
    }

    return $map;
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
}