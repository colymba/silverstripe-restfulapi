<?php

namespace colymba\RESTfulAPI\Tests\Fixtures;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use colymba\RESTfulAPI\Tests\Fixtures\ApiTestAuthor;
use colymba\RESTfulAPI\Tests\Fixtures\ApiTestLibrary;




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

class ApiTestBook extends DataObject
{
    private static $db = array(
        'Title' => 'Varchar(255)',
        'Pages' => 'Int',
    );

    private static $has_one = array(
        'Author' => ApiTestAuthor::class,
    );

    private static $belongs_many_many = array(
        'Libraries' => ApiTestLibrary::class,
    );

    public function validate()
    {
        if ($this->pages > 100) {
            $result = ValidationResult::create(false, 'Too many pages');
        } else {
            $result = ValidationResult::create(true);
        }

        return $result;
    }
}
