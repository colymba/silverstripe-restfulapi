<?php
/**
 * RESTfulAPI Token authentication data extension
 * Add to any DataObject that will store the authentication token
 * e.g. Member
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Authentication
 */
class RESTfulAPI_TokenAuthExtension extends DataExtension
{
	private static $db = array(
    'ApiToken'        => 'Varchar(160)',
    'ApiRefreshToken' => 'Varchar(161)',
    'ApiTokenExpire'  => 'Int'
	);

  // Add a index to the API token and refresh token.
  // Should increase lookup performance.
  // Cannot use unique constraint because MSSQL doesn't allow multiple null values:
  // https://github.com/silverstripe/silverstripe-mssql/issues/24
  // TODO: Move to unique indexes, when it's properly supported by all Database bindings for SilverStripe
  private static $indexes = array(
    'ApiToken' => true,
    'ApiRefreshToken' => true
  );

	function updateCMSFields(FieldList $fields)
	{
	  $fields->removeByName('ApiToken');
	  $fields->removeByName('ApiRefreshToken');
	  $fields->removeByName('ApiTokenExpire');
	}
}