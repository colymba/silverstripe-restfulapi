<?php

namespace colymba\RESTfulAPI\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

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
class RESTfulAPITokenAuthExtension extends DataExtension
{

    private static $db = array(
        'ApiToken' => 'Varchar(160)',
        'ApiTokenExpire' => 'Int',
    );

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('ApiToken');
        $fields->removeByName('ApiTokenExpire');
    }
}
