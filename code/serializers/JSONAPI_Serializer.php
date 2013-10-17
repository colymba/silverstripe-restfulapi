<?php
/**
 * Defines requirements for JSONAPI Serializer
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package SS_JSONAPI
 * @subpackage Serializer
 */
interface JSONAPI_Serializer
{

	/**
	 * Return current JSONAPI instance
	 * 
	 * @return JSONAPI JSONAPI instance
	 */
	public function getapi();


	/**
	 * Return current JSONAPI Query Handler instance
	 * 
	 * @return JSONAPI_QueryHandler QueryHandler instance
	 */
	public function getserializer();


	/**
	 * Return Content-type header definition
	 * to be used in the API response
	 * 
	 * @return string Content-type
	 */
	public function getcontentType();


	/**
	 * Create instance and saves current api reference
	 * 
	 * @param JSONAPI $api current JSONAPI instance
	 */
	public function __construct(JSONAPI $api);


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
	public function formatName(string $name);


	/**
	 * Convert client JSON data to an array of data
	 * ready to be consumed by SilverStripe
	 * 
	 * @param  string  $data   JSON to be converted to data ready to be consumed by SilverStripe
	 * @return array           Formatted array representation of the JSON data
	 */
	public function deserialize(string $json);


	/**
	 * Format a ClassName of DBField name sent by client API
	 * to be used by SilverStripe
	 * 
	 * @param  string $name ClassName of DBField name
	 * @return string       Formatted name
	 */
	public function unformatName(string $name);
}