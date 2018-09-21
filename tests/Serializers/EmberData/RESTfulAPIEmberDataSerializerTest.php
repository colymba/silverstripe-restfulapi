<?php

namespace colymba\RESTfulAPI\Tests\Serializers\EmberData;

use colymba\RESTfulAPI\Inflector;
use colymba\RESTfulAPI\Serializers\EmberData\RESTfulAPIEmberDataSerializer;
use colymba\RESTfulAPI\Tests\RESTfulAPITester;
use SilverStripe\Core\Injector\Injector;
use colymba\RESTfulAPI\Tests\Fixtures\ApiTestAuthor;
use colymba\RESTfulAPI\Tests\Fixtures\ApiTestBook;
use colymba\RESTfulAPI\Tests\Fixtures\ApiTestLibrary;
use SilverStripe\Core\Config\Config;



/**
 * EmberData Serializer Test suite
 *
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 *
 * @package RESTfulAPI
 * @subpackage Tests
 */
class RESTfulAPIEmberDataSerializerTest extends RESTfulAPITester
{
    protected static $extra_dataobjects = array(
        ApiTestAuthor::class,
        ApiTestBook::class,
        ApiTestLibrary::class,
    );

    protected function getSerializer()
    {
        $injector = new Injector();
        $serializer = new RESTfulAPIEmberDataSerializer();

        $injector->inject($serializer);

        return $serializer;
    }

    /* **********************************************************
     * TESTS
     * */

    /**
     * Checks serializer content type access
     */
    public function testContentType()
    {
        $serializer = $this->getSerializer();
        $contentType = $serializer->getcontentType();

        $this->assertTrue(
            is_string($contentType),
            'EmberData Serializer getcontentType() should return string'
        );
    }

    /**
     * Checks data serialization
     */
    public function testSerialize()
    {
        $serializer = $this->getSerializer();

        // test single dataObject serialization
        $dataObject = ApiTestAuthor::get()->filter(array('Name' => 'Peter'))->first();
        $jsonString = $serializer->serialize($dataObject);
        $jsonObject = json_decode($jsonString);

        $this->assertEquals(
            1,
            $jsonObject->apiTest_Author->id,
            "EmberData Serialize should wrap result in an object in JSON root"
        );
    }

    /**
     * Checks sideloading records config
     */
    public function testSideloadedRecords()
    {
        Config::inst()->update(RESTfulAPIEmberDataSerializer::class, 'sideloaded_records', array(
            'ApiTestLibrary' => array('Books'),
        ));

        Config::inst()->update(ApiTestBook::class, 'api_access', true);

        $serializer = $this->getSerializer();
        $dataObject = ApiTestLibrary::get()->filter(array('Name' => 'Helsinki'))->first();

        $jsonString = $serializer->serialize($dataObject);
        $jsonObject = json_decode($jsonString);

        $booksRoot = $serializer->formatName(ApiTestBook::class);
        $booksRoot = Inflector::pluralize($booksRoot);

        $this->assertFalse(
            is_null($jsonObject->$booksRoot),
            "EmberData Serialize should sideload records in an object in JSON root"
        );

        $this->assertTrue(
            is_array($jsonObject->$booksRoot),
            "EmberData Serialize should sideload records as array"
        );
    }

    /**
     * Checks column name formatting
     */
    public function testFormatName()
    {
        $serializer = $this->getSerializer();

        $column = 'UpperCamelCase';
        $class = ApiTestLibrary::class;

        $this->assertEquals(
            'upperCamelCase',
            $serializer->formatName($column),
            "EmberData Serializer should return lowerCamel case columns"
        );

        $this->assertEquals(
            'apiTest_Library',
            $serializer->formatName($class),
            "EmberData Serializer should return lowerCamel case class"
        );
    }
}
