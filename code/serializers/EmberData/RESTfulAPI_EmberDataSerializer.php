<?php
/**
 * EmberData RESTfulAPI Model Serializer
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
class RESTfulAPI_EmberDataSerializer extends RESTfulAPI_BasicSerializer
{

	/**
	 * Content-type header definition for this Serializer
	 * Used by RESTfulAPI in the response
	 * 
	 * @var string
	 */
	private $contentType = 'application/vnd.api+json; charset=utf-8';


  /**
   * Sideloaded records settings
   * Specify which relation's records will be added to the JSON root:
   * 'RequestedClass' => array('RelationNameToSideLoad', 'Another')
   * 
   * Non sideloaded response:
   * {
   *   'member': {
   *     'name': 'John',
   *     'favourites': [1, 2]
   *   }
   * }
   *
   * Response with sideloaded records:
   * {
   *   'member': {
   *     'name': 'John',
   *     'favourites': [1, 2]
   *    },
   *
   * 		favourites': [{
   *       'id': 1,
   *       'name': 'Mark'
   *    },{
   *       'id': 2,
   *       'name': 'Maggie'
   *    }]
   * }
   *
   * Try not to use in conjunction with {@link RESTfulAPI} $embedded_records
   * with the same settings.
   * 
   * @var array
   * @config
   */
  private static $sideloaded_records;


  /**
   * Stores the current $sideloaded_records config
   * 
   * @var array
   */
  protected $sideloadedRecords;


  /**
   * Construct and set current config
   */
  public function __construct()
  {
  	parent::__construct();

    $sideloaded_records = Config::inst()->get('RESTfulAPI_EmberDataSerializer', 'sideloaded_records');
    if ( is_array($sideloaded_records) )
    {
      $this->sideloadedRecords = $sideloaded_records;
    }
    else{
      $this->sideloadedRecords = array();
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
			$dataClass = $data->ClassName;
			$rootClassName = $this->formatName( $data->ClassName );
			$formattedData = $this->formatDataObject( $data );
		}
		else if ( $data instanceof DataList )
		{
			$dataClass = $data->dataClass;
			$rootClassName = $this->formatName( $data->dataClass );
			$rootClassName = Inflector::pluralize( $rootClassName );
			$formattedData = $this->formatDataList( $data );
		}

		if ( $formattedData )
		{
			$root = new stdClass();
	    $root->{$rootClassName} = $formattedData;

	    // check if we should be sideloading some data
	    if ( $this->hasSideloadedRecords($dataClass) )
	    {
	    	$root = $this->insertSideloadData($root, $data);
	    }

			$json = $this->jsonify($root);
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
	protected function serializeColumnName(string $name)
	{
		$name = str_replace('ID', 'Id', $name);
		$name = lcfirst($name);

		return $name;
	}


	/**
	 * Check if a specific class requires data to be sideloaded.
	 * 
	 * @param  string  $classname Requested data classname
	 * @return boolean            True if some relations should be sideloaded
	 */
	protected function hasSideloadedRecords(string $classname)
	{
		return array_key_exists($classname, $this->sideloadedRecords);
	}


	/**
	 * Fetches and return all the data that need to be sideloaded
	 * for a specific source DataObject or DataList.
	 * 
	 * @param  DataObject|DataList  $dataSource The source data to fetch sideloaded records for
	 * @return array                            A map of relation names with their data
	 */
	protected function getSideloadData($dataSource)
	{
		$data = array();

		if ( $dataSource instanceof DataObject )
		{
			// if a single DataObject get the data for each relation
			foreach ($this->sideloadedRecords[$dataSource->ClassName] as $relationName)
			{
				$newData = $this->getEmbedData($dataSource, $relationName);
				// has_one are only simple array and we want arrays or array
	  		if ( in_array($relationName, $dataSource->stat('has_one')) )
				{
					$newData = array($newData);
				}
				//print_r($newData);
				$data[$relationName] = $newData;
			}
		}
		else if ( $dataSource instanceof DataList )
		{
			// if a list of DataObject, loop through each and merge all the data together
			foreach ($dataSource as $dataObjectSource)
			{
				$newData = $this->getSideloadData($dataObjectSource);
				$data = array_merge_recursive($data, $newData);
			}

			// remove duplicates
			foreach ($data as $relationName => $relationData)
			{
				$data[$relationName] = array_unique($relationData, SORT_REGULAR);
			}
		}		

		return $data;
	}


	/**
	 * Take a root object ready to be converted into JSON
	 * and an original data source (DataObject OR DataList)
	 * and insorts into the root object all relation records
	 * that should be sideloaded.
	 * 
	 * @param  stdClass              $root       Root object ready to become JSON
	 * @param  DataObject|DataList   $dataSource The original data set from the root object
	 * @return stdClass                          The updated root object sith the sideloaded data attached
	 */
	protected function insertSideloadData(stdClass $root, $dataSource)
	{
		if ( $dataSource instanceof DataObject )
		{
			$dataClass = $dataSource->ClassName;
		}
		else{
			$dataClass = $dataSource->dataClass;
		}

		// get the extra data
  	$sideloadData = $this->getSideloadData($dataSource);

  	// attached those to the root
  	foreach ($sideloadData as $relationName => $relationData)
  	{
  		$rootRelationName = $this->formatName( $relationName );
  		$rootRelationName = Inflector::pluralize( $rootRelationName );

  		// has_one are only simple array and we want arrays or array
  		/*
  		if ( in_array($relationName, singleton($dataClass)->stat('has_one')) )
			{
				$relationData = array($relationData);
			}*/

			// attach to root
			$root->{$rootRelationName} = $relationData;
  	}

  	return $root;
	}
}