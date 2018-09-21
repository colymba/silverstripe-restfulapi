<?php

namespace colymba\RESTfulAPI\Tests\Serializers\Basic;

use colymba\RESTfulAPI\QueryHandlers\RESTfulAPIBasicDeSerializer;
use SilverStripe\Core\Injector\Injector;
use colymba\RESTfulAPI\Tests\Fixtures\ApiTestAuthor;
use colymba\RESTfulAPI\Tests\Fixtures\ApiTestBook;
use colymba\RESTfulAPI\Tests\Fixtures\ApiTestLibrary;
use colymba\RESTfulAPI\Tests\RESTfulAPITester;



/**
 * Basic DeSerializer Test suite
 *
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 *
 * @package RESTfulAPI
 * @subpackage Tests
 */
class RESTfulAPIBasicDeSerializerTest extends RESTfulAPITester
{
    protected static $extra_dataobjects = array(
        ApiTestAuthor::class,
        ApiTestBook::class,
        ApiTestLibrary::class,
    );

    protected function getDeSerializer()
    {
        $injector = new Injector();
        $deserializer = new RESTfulAPIBasicDeSerializer();

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
            "Basic DeSerialize should return an array"
        );

        $this->assertEquals(
            "Some name",
            $result['Name'],
            "Basic DeSerialize should not change values"
        );
    }

    /**
     * Checks payload column/class names unformatting
     */
    public function testUnformatName()
    {
        $deserializer = $this->getDeSerializer();

        $column = 'Name';
        $class = 'apiTest_Author';

        $this->assertEquals(
            $column,
            $deserializer->unformatName($column),
            "Basic DeSerialize should not change name formatting"
        );

        $this->assertEquals(
            ApiTestAuthor::class,
            $deserializer->unformatName($class),
            "Basic DeSerialize should return ucfirst class name"
        );
    }
}
