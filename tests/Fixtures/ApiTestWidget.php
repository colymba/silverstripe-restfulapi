<?php

use SilverStripe\ORM\DataObject;

/**
 * RESTfulAPI Test suite DataObjects
 *
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 *
 * @package RESTfulAPI
 * @subpackage Tests
 */

class ApiTestWidget extends DataObject
{
    private static $table_name = 'ApiTestWidget';

    private static $db = array(
        'Name' => 'Varchar(255)',
    );
}
