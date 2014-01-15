<?php
/**
 * Defines requirements for RESTfulAPI DeSerializer
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Serializer
 */
interface RESTfulAPI_DeSerializer
{
	
	/**
	 * Convert client JSON data to an array of data
	 * ready to be consumed by SilverStripe
	 * 
	 * @param  string  $data   JSON to be converted to data ready to be consumed by SilverStripe
	 * @return array           Formatted array representation of the JSON data
	 */
	public function deserialize($json);


	/**
	 * Format a ClassName of DBField name sent by client API
	 * to be used by SilverStripe
	 * 
	 * @param  string $name ClassName of DBField name
	 * @return string       Formatted name
	 */
	public function unformatName($name);
}