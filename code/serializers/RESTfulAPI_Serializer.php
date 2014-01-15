<?php
/**
 * Defines requirements for RESTfulAPI Serializer
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Serializer
 */
interface RESTfulAPI_Serializer
{

	/**
	 * Return Content-type header definition
	 * to be used in the API response
	 * 
	 * @return string Content-type
	 */
	public function getcontentType();


	/**
	 * Convert raw data (DataObject, DataList or Array) to JSON
	 * ready to be consumed by the client API
	 * 
	 * @param  DataObject|DataList|Array  $data  data to serialize
	 * @return string                            JSON representation of data
	 */
	public function serialize($data);


	/**
	 * Format a SilverStripe ClassName of DBField
	 * to be used by the client API
	 * 
	 * @param  string $name ClassName of DBField name
	 * @return string       Formatted name
	 */
	public function formatName($name);
}