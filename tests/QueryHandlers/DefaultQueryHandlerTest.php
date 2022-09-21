<?php

namespace Colymba\RESTfulAPI\Tests\QueryHandlers;

use Colymba\RESTfulAPI\QueryHandlers\DefaultQueryHandler;
use SilverStripe\Core\Injector\Injector;
use Colymba\RESTfulAPI\RESTfulAPIError;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use Colymba\RESTfulAPI\Tests\Fixtures\ApiTestAuthor;
use Colymba\RESTfulAPI\Tests\Fixtures\ApiTestBook;
use Colymba\RESTfulAPI\Tests\Fixtures\ApiTestLibrary;
use Colymba\RESTfulAPI\Tests\Fixtures\ApiTestProduct;
use ApiTestWidget;
use Colymba\RESTfulAPI\Tests\RESTfulAPITester;



/**
 * Default Query Handler Test suite
 *
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 *
 * @package RESTfulAPI
 * @subpackage Tests
 */
class DefaultQueryHandlerTest extends RESTfulAPITester
{
    protected static $extra_dataobjects = array(
        ApiTestAuthor::class,
        ApiTestBook::class,
        ApiTestLibrary::class,
        ApiTestProduct::class,
    );

    protected $url_pattern = 'api/$ModelReference/$ID';

    /**
     * Turn on API access for the book and widget fixtures by default
     */
    public function setUp(): void
    {
        parent::setUp();

        Config::inst()->update(ApiTestBook::class, 'api_access', true);
        Config::inst()->update(ApiTestWidget::class, 'api_access', true);

        $widget = ApiTestWidget::create(['Name' => 'TestWidget1']);
        $widget->write();
        $widget = ApiTestWidget::create(['Name' => 'TestWidget2']);
        $widget->write();
    }

    protected function getHTTPRequest($method = 'GET', $class = ApiTestBook::class, $id = '', $params = array())
    {
        $request = new HTTPRequest(
            $method,
            'api/' . $class . '/' . $id,
            $params
        );
        $request->match($this->url_pattern);
        $request->setRouteParams(array(
            'Controller' => 'RESTfulAPI',
        ));

        return $request;
    }

    protected function getQueryHandler()
    {
        $injector = new Injector();
        $qh = new DefaultQueryHandler();

        $injector->inject($qh);

        return $qh;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $product = ApiTestProduct::create(array(
            'Title' => 'Sold out product',
            'Soldout' => true,
        ));
        $product->write();
    }

    /* **********************************************************
     * TESTS
     * */

    /**
     * Checks that query parameters are parsed properly
     */
    public function testQueryParametersParsing()
    {
        $qh = $this->getQueryHandler();
        $request = $this->getHTTPRequest('GET', ApiTestBook::class, '1', array('Title__StartsWith' => 'K'));
        $params = $qh->parseQueryParameters($request->getVars());
        $params = array_shift($params);

        $this->assertEquals(
            $params['Column'],
            'Title',
            'Column parameter name mismatch'
        );
        $this->assertEquals(
            $params['Value'],
            'K',
            'Value parameter mismatch'
        );
        $this->assertEquals(
            $params['Modifier'],
            'StartsWith',
            'Modifier parameter mismatch'
        );
    }

    /**
     * Checks that access to DataObject with api_access config disabled return error
     */
    public function testAPIDisabled()
    {
        Config::inst()->update(ApiTestBook::class, 'api_access', false);

        $qh = $this->getQueryHandler();
        $request = $this->getHTTPRequest('GET', ApiTestBook::class, '1');
        $result = $qh->handleQuery($request);

        $this->assertContainsOnlyInstancesOf(
            RESTfulAPIError::class,
            array($result),
            'Request for DataObject with api_access set to false should return a RESTfulAPIError'
        );
    }

    /**
     * Checks single record requests
     */
    public function testFindSingleModel()
    {
        $qh = $this->getQueryHandler();
        $request = $this->getHTTPRequest('GET', ApiTestBook::class, '1');
        $result = $qh->handleQuery($request);

        $this->assertContainsOnlyInstancesOf(
            ApiTestBook::class,
            array($result),
            'Single model request should return a DataObject of class model'
        );
        $this->assertEquals(
            1,
            $result->ID,
            'IDs mismatch. DataObject is not the record requested'
        );
    }

    /**
     * Checks multiple records requests
     */
    public function testFindMultipleModels()
    {
        $qh = $this->getQueryHandler();
        $request = $this->getHTTPRequest('GET', ApiTestBook::class);
        $result = $qh->handleQuery($request);

        $this->assertContainsOnlyInstancesOf(
            DataList::class,
            array($result),
            'Request for multiple models should return a DataList'
        );

        $this->assertGreaterThan(
            1,
            $result->toArray(),
            'Request should return more than 1 result'
        );
    }

    /**
     * Checks fallback for models without explicit mapping
     */
    public function testModelMappingFallback()
    {
        $qh = $this->getQueryHandler();
        $request = $this->getHTTPRequest('GET', ApiTestWidget::class, '1');
        $result = $qh->handleQuery($request);

        $this->assertContainsOnlyInstancesOf(
            ApiTestWidget::class,
            array($result),
            'Unmapped model should fall back to standard mapping'
        );
    }

    /**
     * Checks max record limit config
     */
    public function testMaxRecordsLimit()
    {
        Config::inst()->update(DefaultQueryHandler::class, 'max_records_limit', 1);

        $qh = $this->getQueryHandler();
        $request = $this->getHTTPRequest('GET', ApiTestBook::class);
        $result = $qh->handleQuery($request);

        $this->assertCount(
            1,
            $result->toArray(),
            'Request for multiple models should implement limit set by max_records_limit config'
        );
    }

    /**
     * Checks new record creation
     */
    public function testCreateModel()
    {
        $existingRecords = ApiTestBook::get()->toArray();

        $qh = $this->getQueryHandler();
        $request = $this->getHTTPRequest('POST', ApiTestBook::class);

        $body = json_encode(array('Title' => 'New Test Book'));
        $request->setBody($body);

        $result = $qh->createModel(ApiTestBook::class, $request);
        $rewRecords = ApiTestBook::get()->toArray();

        $this->assertContainsOnlyInstancesOf(
            DataObject::class,
            array($result),
            'Create model should return a DataObject'
        );

        $this->assertEquals(
            count($existingRecords) + 1,
            count($rewRecords),
            'Create model should create a database entry'
        );

        $this->assertEquals(
            'New Test Book',
            $result->Title,
            "Created model title doesn't match"
        );

        // failing tests return error?
    }

    /**
     * Checks new record creation
     */
    public function testModelValidation()
    {
        $qh = $this->getQueryHandler();
        $request = $this->getHTTPRequest('POST', ApiTestBook::class);

        $body = json_encode(array('Title' => 'New Test Book', 'Pages' => 101));
        $request->setBody($body);

        $result = $qh->createModel(ApiTestBook::class, $request);

        $this->assertEquals(
            'Too many pages',
            $result->message,
            "Model with validation error should return the validation error"
        );
    }

    /**
     * Checks record update
     */
    public function testUpdateModel()
    {
        $firstRecord = ApiTestBook::get()->first();

        $qh = $this->getQueryHandler();
        $request = $this->getHTTPRequest('PUT', ApiTestBook::class);

        $newTitle = $firstRecord->Title . ' UPDATED';
        $body = json_encode(array('Title' => $newTitle));
        $request->setBody($body);

        $result = $qh->updateModel(ApiTestBook::class, $firstRecord->ID, $request);
        $updatedRecord = DataObject::get_by_id(ApiTestBook::class, $firstRecord->ID);

        $this->assertContainsOnlyInstancesOf(
            DataObject::class,
            array($result),
            'Update model should return a DataObject'
        );

        $this->assertEquals(
            $newTitle,
            $updatedRecord->Title,
            "Update model didn't update database record"
        );

        // failing tests return error?
    }

    /**
     * Checks record deletion
     */
    public function testDeleteModel()
    {
        $firstRecord = ApiTestBook::get()->first();

        $qh = $this->getQueryHandler();
        $request = $this->getHTTPRequest('DELETE', ApiTestBook::class);
        $result = $qh->deleteModel(ApiTestBook::class, $firstRecord->ID, $request);

        $deletedRecord = DataObject::get_by_id(ApiTestBook::class, $firstRecord->ID);

        $this->assertNull(
            $deletedRecord,
            'Delete model should delete a database record'
        );
    }

    public function testAfterDeserialize()
    {
        $product = ApiTestProduct::get()->first();
        $qh = $this->getQueryHandler();
        $request = $this->getHTTPRequest('PUT', ApiTestProduct::class, $product->ID);
        $body = json_encode(array(
            'Title' => 'Making product available',
            'Soldout' => false,
        ));
        $request->setBody($body);

        $updatedProduct = $qh->handleQuery($request);

        $this->assertContainsOnlyInstancesOf(
            DataObject::class,
            array($updatedProduct),
            'Update model should return a DataObject'
        );

        $this->assertEquals(
            ApiTestProduct::$rawJSON,
            $body,
            "Raw JSON passed into 'onBeforeDeserialize' should match request payload"
        );

        $this->assertTrue(
            $updatedProduct->Soldout == 1,
            "Product should still be sold out, because 'onAfterDeserialize' unset the data bafore writing"
        );
    }
}
