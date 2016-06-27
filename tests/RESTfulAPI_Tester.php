<?php
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
class RESTfulAPI_Tester extends SapphireTest
{
    public function generateDBEntries()
    {
        $peter = ApiTest_Author::create(array(
            'Name' => 'Peter',
            'IsMan' => true
        ));
        $marie = ApiTest_Author::create(array(
            'Name' => 'Marie',
            'IsMan' => false
        ));

        $bible = ApiTest_Book::create(array(
            'Title' => 'The Bible',
            'Pages' => 2000
        ));
        $kamasutra = ApiTest_Book::create(array(
            'Title' => 'Kama Sutra',
            'Pages' => 1000
        ));

        $helsinki = ApiTest_Library::create(array(
            'Name'    => 'Helsinki'
        ));
        $paris = ApiTest_Library::create(array(
            'Name'    => 'Paris'
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
        $ext = new RESTfulAPI_GroupExtension();
        $ext->requireDefaultRecords();
    }

    public function setDefaultApiConfig()
    {
        Config::inst()->update('RESTfulAPI', 'access_control_policy', 'ACL_CHECK_CONFIG_ONLY');

        Config::inst()->update('RESTfulAPI', 'dependencies', array(
            'authenticator' => '%$RESTfulAPI_TokenAuthenticator',
            'authority'     => '%$RESTfulAPI_DefaultPermissionManager',
            'queryHandler'  => '%$RESTfulAPI_DefaultQueryHandler',
            'serializer'    => '%$RESTfulAPI_BasicSerializer'
        ));

        Config::inst()->update('RESTfulAPI', 'cors', array(
            'Enabled'       => true,
            'Allow-Origin'  => '*',
            'Allow-Headers' => '*',
            'Allow-Methods' => 'OPTIONS, POST, GET, PUT, DELETE',
            'Max-Age'       => 86400
        ));

        Config::inst()->update('RESTfulAPI_DefaultQueryHandler', 'dependencies', array(
            'deSerializer' => '%$RESTfulAPI_BasicDeSerializer'
        ));
    }

    public function getOPTIONSHeaders($method = 'GET', $site = null)
    {
        if (!$site) {
            $site = Director::absoluteBaseURL();
        }
        $host = parse_url($site, PHP_URL_HOST);

        return array(
            'Accept'                         => '*/*',
            'Accept-Encoding'                => 'gzip,deflate,sdch',
            'Accept-Language'                => 'en-GB,fr;q=0.8,en-US;q=0.6,en;q=0.4',
            'Access-Control-Request-Headers' => 'accept, x-silverstripe-apitoken',
            'Access-Control-Request-Method'  => $method,
            'Cache-Control'                  => 'no-cache',
            'Connection'                     => 'keep-alive',
            'Host'                           => $host,
            'Origin'                         => 'http://'.$host,
            'Pragma'                         => 'no-cache',
            'Referer'                        => 'http://'.$host.'/',
            'User-Agent'                     => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36'
        );
    }

    public function getRequestHeaders($site = null)
    {
        if (!$site) {
            $site = Director::absoluteBaseURL();
        }
        $host = parse_url($site, PHP_URL_HOST);

        return array(
            'Accept'                         => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Encoding'                => 'gzip,deflate,sdch',
            'Accept-Language'                => 'en-GB,fr;q=0.8,en-US;q=0.6,en;q=0.4',
            'Cache-Control'                  => 'no-cache',
            'Connection'                     => 'keep-alive',
            'Host'                           => $host,
            'Origin'                         => 'http://'.$host,
            'Pragma'                         => 'no-cache',
            'Referer'                        => 'http://'.$host.'/',
            'User-Agent'                     => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36',
            'X-Silverstripe-Apitoken'        => 'secret key'
        );
    }

    public function setUpOnce()
    {
        parent::setUpOnce();

        if ($this->extraDataObjects) {
            $this->generateDBEntries();
        }

        Config::inst()->update('Director', 'alternate_base_url', 'http://mysite.com/');
    }

    public function setUp()
    {
        parent::setUp();
    
        $this->setDefaultApiConfig();
    }
}
