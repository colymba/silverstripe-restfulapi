<?php

namespace colymba\RESTfulAPI\Tests\Fixtures;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use colymba\RESTfulAPI\Tests\Fixtures\ApiTestBook;




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
class ApiTestLibrary extends DataObject
{
    private static $table_name = 'ApiTestLibrary';

    private static $db = array(
        'Name' => 'Varchar(255)',
    );

    private static $many_many = array(
        'Books' => ApiTestBook::class,
    );

    public function canView($member = null)
    {
        return Permission::check('RESTfulAPI_VIEW', 'any', $member);
    }

    public function canEdit($member = null)
    {
        return Permission::check('RESTfulAPI_EDIT', 'any', $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('RESTfulAPI_CREATE', 'any', $member);
    }

    public function canDelete($member = null)
    {
        return Permission::check('RESTfulAPI_DELETE', 'any', $member);
    }
}
