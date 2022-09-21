<?php

namespace Colymba\RESTfulAPI\Tests\PermissionManagers;

use Colymba\RESTfulAPI\RESTfulAPI;
use Colymba\RESTfulAPI\Extensions\TokenAuthExtension;
use Colymba\RESTfulAPI\Tests\RESTfulAPITester;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\Member;
use Colymba\RESTfulAPI\Tests\Fixtures\ApiTestLibrary;




/**
 * Default Permission Manager Test suite
 *
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 *
 * @package RESTfulAPI
 * @subpackage Tests
 */
class DefaultPermissionManagerTest extends RESTfulAPITester
{
    protected static $required_extensions = array(
        Member::class => array(TokenAuthExtension::class),
    );

    protected static $extra_dataobjects = array(
        ApiTestLibrary::class,
    );

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        Member::create(array(
            'Email' => 'admin@api.com',
            'Password' => 'Admin$password1',
        ))->write();

        $member = Member::get()->filter(array(
            'Email' => 'admin@api.com',
        ))->first();

        $member->addToGroupByCode('restfulapi-administrators');

        Member::create(array(
            'Email' => 'stranger@api.com',
            'Password' => 'Stranger$password1',
        ))->write();
    }

    protected function getAdminToken()
    {
        $response = Director::test('api/auth/login?email=admin@api.com&pwd=Admin$password1');
        $json = json_decode($response->getBody());
        return $json->token;
    }

    protected function getStrangerToken()
    {
        $response = Director::test('api/auth/login?email=stranger@api.com&pwd=Stranger$password1');
        $json = json_decode($response->getBody());
        return $json->token;
    }

    /* **********************************************************
     * TESTS
     * */

    /**
     * Test READ permissions are honoured
     */
    public function testReadPermissions()
    {
        Config::inst()->update(RESTfulAPI::class, 'access_control_policy', 'ACL_CHECK_MODEL_ONLY');
        Config::inst()->update(RESTfulAPI::class, 'cors', array(
            'Enabled' => false,
        ));

        // GET with permission = OK
        $requestHeaders = $this->getRequestHeaders();
        $requestHeaders['X-Silverstripe-Apitoken'] = $this->getAdminToken();
        $response = Director::test('api/apitestlibrary/1', null, null, 'GET', null, $requestHeaders);

        $this->assertEquals(
            200,
            $response->getStatusCode(),
            "Member of 'restfulapi-administrators' Group should be able to READ records."
        );

        // GET with NO Permission = BAD
        $requestHeaders = $this->getRequestHeaders();
        $requestHeaders['X-Silverstripe-Apitoken'] = $this->getStrangerToken();
        $response = Director::test('api/apitestlibrary/1', null, null, 'GET', null, $requestHeaders);

        $this->assertEquals(
            403,
            $response->getStatusCode(),
            "Member without permission should NOT be able to READ records."
        );
    }

    /**
     * Test EDIT permissions are honoured
     */
    public function testEditPermissions()
    {
        Config::inst()->update(RESTfulAPI::class, 'access_control_policy', 'ACL_CHECK_MODEL_ONLY');
        Config::inst()->update(RESTfulAPI::class, 'cors', array(
            'Enabled' => false,
        ));

        // PUT with permission = OK
        $requestHeaders = $this->getRequestHeaders();
        $requestHeaders['X-Silverstripe-Apitoken'] = $this->getAdminToken();
        $response = Director::test('api/apitestlibrary/1', null, null, 'PUT', '{"Name":"Api"}', $requestHeaders);

        $this->assertEquals(
            200,
            $response->getStatusCode(),
            "Member of 'restfulapi-administrators' Group should be able to EDIT records."
        );

        // PUT with NO Permission = BAD
        $requestHeaders = $this->getRequestHeaders();
        $requestHeaders['X-Silverstripe-Apitoken'] = $this->getStrangerToken();
        $response = Director::test('api/apitestlibrary/1', null, null, 'PUT', '{"Name":"Api"}', $requestHeaders);

        $this->assertEquals(
            403,
            $response->getStatusCode(),
            "Member without permission should NOT be able to EDIT records."
        );
    }

    /**
     * Test CREATE permissions are honoured
     */
    public function testCreatePermissions()
    {
        Config::inst()->update(RESTfulAPI::class, 'access_control_policy', 'ACL_CHECK_MODEL_ONLY');
        Config::inst()->update(RESTfulAPI::class, 'cors', array(
            'Enabled' => false,
        ));

        // POST with permission = OK
        $requestHeaders = $this->getRequestHeaders();
        $requestHeaders['X-Silverstripe-Apitoken'] = $this->getAdminToken();
        $response = Director::test('api/apitestlibrary', null, null, 'POST', '{"Name":"Api"}', $requestHeaders);

        $this->assertEquals(
            200,
            $response->getStatusCode(),
            "Member of 'restfulapi-administrators' Group should be able to CREATE records."
        );

        // POST with NO Permission = BAD
        $requestHeaders = $this->getRequestHeaders();
        $requestHeaders['X-Silverstripe-Apitoken'] = $this->getStrangerToken();
        $response = Director::test('api/apitestlibrary', null, null, 'POST', '{"Name":"Api"}', $requestHeaders);

        $this->assertEquals(
            403,
            $response->getStatusCode(),
            "Member without permission should NOT be able to CREATE records."
        );
    }

    /**
     * Test DELETE permissions are honoured
     */
    public function testDeletePermissions()
    {
        Config::inst()->update(RESTfulAPI::class, 'access_control_policy', 'ACL_CHECK_MODEL_ONLY');
        Config::inst()->update(RESTfulAPI::class, 'cors', array(
            'Enabled' => false,
        ));

        // DELETE with permission = OK
        $requestHeaders = $this->getRequestHeaders();
        $requestHeaders['X-Silverstripe-Apitoken'] = $this->getAdminToken();
        $response = Director::test('api/apitestlibrary/1', null, null, 'DELETE', null, $requestHeaders);

        $this->assertEquals(
            200,
            $response->getStatusCode(),
            "Member of 'restfulapi-administrators' Group should be able to DELETE records."
        );

        // DELETE with NO Permission = BAD
        $requestHeaders = $this->getRequestHeaders();
        $requestHeaders['X-Silverstripe-Apitoken'] = $this->getStrangerToken();
        $response = Director::test('api/apitestlibrary/1', null, null, 'DELETE', null, $requestHeaders);

        $this->assertEquals(
            403,
            $response->getStatusCode(),
            "Member without permission should NOT be able to DELETE records."
        );
    }
}
