<?php

namespace Colymba\RESTfulAPI\Tests\Serializers;

use Colymba\RESTfulAPI\Serializers\DefaultDeSerializer;
use SilverStripe\Core\Injector\Injector;
use Colymba\RESTfulAPI\Tests\Fixtures\ApiTestAuthor;
use Colymba\RESTfulAPI\Tests\Fixtures\ApiTestBook;
use Colymba\RESTfulAPI\Tests\Fixtures\ApiTestLibrary;
use Colymba\RESTfulAPI\Tests\RESTfulAPITester;



/**
 * Default DeSerializer Test suite
 *
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 *
 * @package RESTfulAPI
 * @subpackage Tests
 */
class DefaultDeSerializerTest extends RESTfulAPITester
{
    protected static $extra_dataobjects = array(
        ApiTestAuthor::class,
        ApiTestBook::class,
        ApiTestLibrary::class,
    );

    protected function getDeSerializer()
    {
        $injector = new Injector();
        $deserializer = new DefaultDeSerializer();

        $injector->inject($deserializer);

        return $deserializer;
    }

    /* **********************************************************
     * TESTS
     * */

    /**
     * Checks payload deserialization
     */
    public function testDeserialize()
    {
        $deserializer = $this->getDeSerializer();
        $json = json_encode(array('Name' => 'Some name'));
        $result = $deserializer->deserialize($json);

        $this->assertTrue(
            is_array($result),
            "Default DeSerialize should return an array"
        );

        $this->assertEquals(
            "Some name",
            $result['Name'],
            "Default DeSerialize should not change values"
        );
    }

    /**
     * Checks payload column/class names unformatting
     */
    public function testUnformatName()
    {
        $deserializer = $this->getDeSerializer();

        $column = 'Name';
        $class = 'Colymba\RESTfulAPI\Tests\Fixtures\ApiTestAuthor';

        $this->assertEquals(
            $column,
            $deserializer->unformatName($column),
            "Default DeSerialize should not change name formatting"
        );

        $this->assertEquals(
            ApiTestAuthor::class,
            $deserializer->unformatName($class),
            "Default DeSerialize should return ucfirst class name"
        );
    }
}
