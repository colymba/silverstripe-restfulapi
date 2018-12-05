<?php

namespace Colymba\RESTfulAPI\Tests;

use Colymba\RESTfulAPI\Extensions\GroupExtension;
use Colymba\RESTfulAPI\QueryHandlers\DefaultQueryHandler;
use Colymba\RESTfulAPI\RESTfulAPI;
use Colymba\RESTfulAPI\Tests\Fixtures\ApiTestAuthor;
use Colymba\RESTfulAPI\Tests\Fixtures\ApiTestBook;
use Colymba\RESTfulAPI\Tests\Fixtures\ApiTestLibrary;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;



/**
 * RESTfulAPI Test suite common methods and tools
 *
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 *
 * @package RESTfulAPI
 * @subpackage Tests
 */
class RESTfulAPITester extends SapphireTest
{
    public static function generateDBEntries()
    {
        $peter = ApiTestAuthor::create(array(
            'Name' => 'Peter',
            'IsMan' => true,
        ));
        $marie = ApiTestAuthor::create(array(
            'Name' => 'Marie',
            'IsMan' => false,
        ));

        $bible = ApiTestBook::create(array(
            'Title' => 'The Bible',
            'Pages' => 60,
        ));
        $kamasutra = ApiTestBook::create(array(
            'Title' => 'Kama Sutra',
            'Pages' => 70,
        ));

        $helsinki = ApiTestLibrary::create(array(
            'Name' => 'Helsinki',
        ));
        $paris = ApiTestLibrary::create(array(
            'Name' => 'Paris',
        ));

        // write to DB
        $peter->write();
        $marie->write();
        $bible->write();
        $kamasutra->write();
        $helsinki->write();
        $paris->write();

        // relations
        $peter->Books()->add($bible);
        $marie->Books()->add($kamasutra);

        $helsinki->Books()->add($bible);
        $helsinki->Books()->add($kamasutra);
        $paris->Books()->add($kamasutra);

        // since it doesn't seem to be called automatically
        $ext = new GroupExtension();
        $ext->requireDefaultRecords();
    }

    public function setDefaultApiConfig()
    {
        Config::inst()->update(RESTfulAPI::class, 'access_control_policy', 'ACL_CHECK_CONFIG_ONLY');

        Config::inst()->update(RESTfulAPI::class, 'dependencies', array(
            'authenticator' => '%$Colymba\RESTfulAPI\Authenticators\TokenAuthenticator',
            'authority' => '%$Colymba\RESTfulAPI\PermissionManagers\DefaultPermissionManager',
            'queryHandler' => '%$Colymba\RESTfulAPI\QueryHandlers\DefaultQueryHandler',
            'serializer' => '%$Colymba\RESTfulAPI\Serializers\DefaultSerializer',
        ));

        Config::inst()->update(RESTfulAPI::class, 'cors', array(
            'Enabled' => true,
            'Allow-Origin' => '*',
            'Allow-Headers' => '*',
            'Allow-Methods' => 'OPTIONS, POST, GET, PUT, DELETE',
            'Max-Age' => 86400,
        ));

        Config::inst()->update(DefaultQueryHandler::class, 'dependencies', array(
            'deSerializer' => '%$Colymba\RESTfulAPI\Serializers\DefaultDeSerializer'
        ));

        Config::inst()->update(DefaultQueryHandler::class, 'models', array(
                'apitestauthor'  => 'Colymba\RESTfulAPI\Tests\Fixtures\ApiTestAuthor',
                'apitestlibrary' => 'Colymba\RESTfulAPI\Tests\Fixtures\ApiTestLibrary',
            )
        );
    }

    public function getOPTIONSHeaders($method = 'GET', $site = null)
    {
        if (!$site) {
            $site = Director::absoluteBaseURL();
        }
        $host = parse_url($site, PHP_URL_HOST);

        return array(
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip,deflate,sdch',
            'Accept-Language' => 'en-GB,fr;q=0.8,en-US;q=0.6,en;q=0.4',
            'Access-Control-Request-Headers' => 'accept, x-silverstripe-apitoken',
            'Access-Control-Request-Method' => $method,
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Host' => $host,
            'Origin' => 'http://' . $host,
            'Pragma' => 'no-cache',
            'Referer' => 'http://' . $host . '/',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36',
        );
    }

    public function getRequestHeaders($site = null)
    {
        if (!$site) {
            $site = Director::absoluteBaseURL();
        }
        $host = parse_url($site, PHP_URL_HOST);

        return array(
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Encoding' => 'gzip,deflate,sdch',
            'Accept-Language' => 'en-GB,fr;q=0.8,en-US;q=0.6,en;q=0.4',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Host' => $host,
            'Origin' => 'http://' . $host,
            'Pragma' => 'no-cache',
            'Referer' => 'http://' . $host . '/',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36',
            'X-Silverstripe-Apitoken' => 'secret key',
        );
    }

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        if (self::getExtraDataobjects()) {
            self::generateDBEntries();
        }
    }

    public function setUp()
    {
        parent::setUp();

        $this->setDefaultApiConfig();

        Config::inst()->update(Director::class, 'alternate_base_url', 'http://mysite.com/');
    }
}
