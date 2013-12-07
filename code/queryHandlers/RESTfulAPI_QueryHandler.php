<?php
/**
 * RESTfulAPI Query handlers definition
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage QueryHandler
 */
interface RESTfulAPI_QueryHandler
{

	/**
	 * Return current RESTfulAPI instance
	 * 
	 * @return RESTfulAPI RESTfulAPI instance
	 */
	public function getapi();


	/**
	 * Return current RESTfulAPI DeSerializer instance
	 * 
	 * @return RESTfulAPI_DeSerializer DeSerializer instance
	 */
	public function getdeSerializer();


	/**
	 * Create instance and saves current api reference
	 * 
	 * @param RESTfulAPI $api current RESTfulAPI instance
	 */
	public function __construct(RESTfulAPI $api);


	/**
   * All requests pass through here and are redirected depending on HTTP verb and params
   * 
   * @param  SS_HTTPRequest   $request    HTTP request
   * @return array                        DataObjec/DataList array map
   */
  public function handleQuery(SS_HTTPRequest $request);
}