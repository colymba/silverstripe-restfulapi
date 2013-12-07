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
class RESTfulAPI_DefaultDeSerializer implements RESTfulAPI_DeSerializer
{

	/**
	 * Convert client JSON data to an array of data
	 * ready to be consumed by SilverStripe
	 *
	 * Expects payload to be formatted:
	 * {
	 *   "className": {
	 *     "fieldName": "Field value",
	 *     "relations": [1]
	 *   }
	 * }
	 * 
	 * @param  string        $data   JSON to be converted to data ready to be consumed by SilverStripe
	 * @return array|false           Formatted array representation of the JSON data or false if failed
	 */
	public function deserialize(string $json)
	{
		$data = json_decode( $json, true );

    if ( $data )
    {    	
    	$data = array_shift($data);

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
}