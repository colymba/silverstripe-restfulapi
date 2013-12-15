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

		if ( $formattedData )
		{
			$root = new stdClass();
	    $root->{$className} = $formattedData;

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
}