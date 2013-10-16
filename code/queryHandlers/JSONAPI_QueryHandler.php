<?php
/**
 * JSON API Query handlers definition
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package SS_JSONAPI
 * @subpackage QueryHandler
 */
interface JSONAPI_QueryHandler
{

	/**
	 * Return current JSONAPI instance
	 * 
	 * @return JSONAPI JSONAPI instance
	 */
	public function getapi();


	/**
	 * Return current JSONAPI Serializer instance
	 * 
	 * @return JSONAPI_Serializer Serializer instance
	 */
	public function getserializer();


	/**
	 * Create instance and saves current api reference
	 * 
	 * @param JSONAPI $api current JSONAPI instance
	 */
	public function __construct(JSONAPI $api);


	/**
   * All requests pass through here and are redirected depending on HTTP verb and params
   * 
   * @param  SS_HTTPRequest   $request    HTTP request
   * @return array                        DataObjec/DataList array map
   */
  public function handleQuery(SS_HTTPRequest $request);
}