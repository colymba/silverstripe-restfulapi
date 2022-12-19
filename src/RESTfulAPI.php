<?php

namespace Colymba\RESTfulAPI;

use Colymba\RESTfulAPI\Authenticators\Authenticator;
use Colymba\RESTfulAPI\PermissionManagers\PermissionManager;
use Colymba\RESTfulAPI\QueryHandlers\QueryHandler;
use Colymba\RESTfulAPI\Serializers\Serializer;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * SilverStripe 3 RESTful API
 *
 * This module implements a RESTful API
 * with flexible configuration for model querying and response serialization
 * through independent components.
 *
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 *
 * @package RESTfulAPI
 */
class RESTfulAPI extends Controller
{

    /**
     * Lets you select if the API requires authentication for access
     * null|false = no authentication required
     * true  = authentication required for all HTTP methods
     * array = authentication required for selected HTTP methods e.g. array('POST', 'PUT', 'DELETE')
     *
     * @var boolean|array
     * @config
     */
    private static $authentication_policy;

    const ACL_CHECK_CONFIG_ONLY = 'config';
    const ACL_CHECK_MODEL_ONLY = 'model';
    const ACL_CHECK_CONFIG_AND_MODEL = 'both';

    /**
     * Lets you select if the API will perform access control checks.
     * false, no ACL required (everything is accessible to anyone)
     * string, 1 of the 3 ACL policy constants:
     * - 'ACL_CHECK_CONFIG_ONLY'      : Check against class api_access config only
     * - 'ACL_CHECK_MODEL_ONLY'       : Check against DataObject permissions (canView... etc.)
     * - 'ACL_CHECK_CONFIG_AND_MODEL' : Check against both api_access and DataObject permissions
     *
     * Default to check config only.
     *
     * @var boolean|string
     * @config
     */
    private static $access_control_policy = 'ACL_CHECK_CONFIG_ONLY';

    /**
     * Current Authenticator instance
     *
     * @var Authenticator
     */
    public $authenticator;

    /**
     * Current Permission Manager instance
     *
     * @var PermissionManager
     */
    public $authority;

    /**
     * Current QueryHandler instance
     *
     * @var QueryHandler
     */
    public $queryHandler;

    /**
     * Current serializer instance
     *
     * @var Serializer
     */
    public $serializer;

    /**
     * Injector dependencies
     * Override in configuration to use your custom classes
     *
     * @var array
     * @config
     */
    private static $dependencies = array(
        'authenticator' => '%$Colymba\RESTfulAPI\Authenticators\TokenAuthenticator',
        'authority' => '%$Colymba\RESTfulAPI\PermissionManagers\DefaultPermissionManager',
        'queryHandler' => '%$Colymba\RESTfulAPI\QueryHandlers\DefaultQueryHandler',
        'serializer' => '%$Colymba\RESTfulAPI\Serializers\DefaultSerializer',
    );

    /**
     * Embedded records setting
     * Specify which relation ($has_one, $has_many, $many_many) model data should be embedded into the response
     *
     * Map of relations to embed for specific record classname
     * 'RequestedClass' => array('RelationNameToEmbed', 'Another')
     *
     * Non embedded response:
     * {
     *   'member': {
     *     'name': 'John',
     *     'favourites': [1, 2]
     *   }
     * }
     *
     * Response with embedded record:
     * {
     *   'member': {
     *     'name': 'John',
     *     'favourites': [{
     *         'id': 1,
     *         'name': 'Mark'
     *      },{
     *         'id': 2,
     *         'name': 'Maggie'
     *      }]
     *    }
     * }
     *
     * @var array
     * @config
     */
    private static $embedded_records;

    /**
     * Cross-Origin Resource Sharing (CORS)
     * API settings for cross domain XMLHTTPRequest
     *
     * Enabled        true|false      enable/disable CORS
     * Allow-Origin   String|Array    '*' to allow all, 'http://domain.com' to allow single domain, array('http://domain.com', 'http://site.com') to allow multiple domains
     * Allow-Headers  String          '*' to allow all or comma separated list of headers
     * Allow-Methods  String          comma separated list of allowed methods
     * Max-Age        Integer         Preflight/OPTIONS request caching time in seconds (NOTE has no effect if Authentification is enabled => custom header = always preflight)
     *
     * @var array
     * @config
     */
    private static $cors = array(
        'Enabled' => true,
        'Allow-Origin' => '*',
        'Allow-Headers' => '*',
        'Allow-Methods' => 'OPTIONS, POST, GET, PUT, DELETE',
        'Max-Age' => 86400,
    );

    /**
     * URL handler allowed actions
     *
     * @var array
     */
    private static $allowed_actions = array(
        'index',
        'auth',
        'acl',
    );

    /**
     * URL handler definition
     *
     * @var array
     */
    private static $url_handlers = array(
        'auth/$Action' => 'auth',
        'acl/$Action' => 'acl',
        '$ModelReference/$ID' => 'index',
    );

    /**
     * Returns current query handler instance
     *
     * @return QueryHandler QueryHandler instance
     */
    public function getqueryHandler()
    {
        return $this->queryHandler;
    }

    /**
     * Returns current serializer instance
     *
     * @return Serializer Serializer instance
     */
    public function getserializer()
    {
        return $this->serializer;
    }

    /**
     * Current RESTfulAPI instance
     *
     * @var RESTfulAPI
     */
    protected static $instance;

    /**
     * Constructor....
     */
    public function __construct()
    {
        parent::__construct();

        //save current instance in static var
        self::$instance = $this;
    }

    /**
     * Controller inititalisation
     * Catches CORS preflight request marked with HTTPMethod 'OPTIONS'
     */
    public function init()
    {
        parent::init();

        //catch preflight request
        if ($this->request->httpMethod() === 'OPTIONS') {
            $answer = $this->answer(null, true);
            $answer->output();
            exit;
        }
    }

    /**
     * Handles authentications methods
     * get response from API Authenticator
     * then passes it on to $answer()
     *
     * @param HTTPRequest $request HTTP request
     * @return HTTPResponse
     */
    public function auth(HTTPRequest $request)
    {
        $action = $request->param('Action');

        if ($this->authenticator) {
            $className = get_class($this->authenticator);
            $allowedActions = Config::inst()->get($className, 'allowed_actions');
            if (!$allowedActions) {
                $allowedActions = array();
            }

            if (in_array($action, $allowedActions)) {
                if (method_exists($this->authenticator, $action)) {
                    $response = $this->authenticator->$action($request);
                    $response = $this->serializer->serialize($response);
                    return $this->answer($response);
                } else {
                    //let's be shady here instead
                    return $this->error(new RESTfulAPIError(403,
                        "Action '$action' not allowed."
                    ));
                }
            } else {
                return $this->error(new RESTfulAPIError(403,
                    "Action '$action' not allowed."
                ));
            }
        }
    }

    /**
     * Handles Access Control methods
     * get response from API PermissionManager
     * then passes it on to $answer()
     *
     * @param HTTPRequest $request HTTP request
     * @return HTTPResponse
     */
    public function acl(HTTPRequest $request)
    {
        $action = $request->param('Action');

        if ($this->authority) {
            $className = get_class($this->authority);
            $allowedActions = Config::inst()->get($className, 'allowed_actions');
            if (!$allowedActions) {
                $allowedActions = array();
            }

            if (in_array($action, $allowedActions)) {
                if (method_exists($this->authority, $action)) {
                    $response = $this->authority->$action($request);
                    $response = $this->serializer->serialize($response);
                    return $this->answer($response);
                } else {
                    //let's be shady here instead
                    return $this->error(new RESTfulAPIError(403,
                        "Action '$action' not allowed."
                    ));
                }
            } else {
                return $this->error(new RESTfulAPIError(403,
                    "Action '$action' not allowed."
                ));
            }
        }
    }

    /**
     * Main API hub switch
     * All requests pass through here and are redirected depending on HTTP verb and params
     *
     * @todo move authentication check to another methode
     *
     * @param  HTTPRequest      $request    HTTP request
     * @return string                       json object of the models found
     */
    public function index(HTTPRequest $request)
    {
        //check authentication if enabled
        if ($this->authenticator) {
            $policy = $this->config()->authentication_policy;
            $authALL = $policy === true;
            $authMethod = is_array($policy) && in_array($request->httpMethod(), $policy);

            if ($authALL || $authMethod) {
                $authResult = $this->authenticator->authenticate($request);

                if ($authResult instanceof RESTfulAPIError) {
                    //Authentication failed return error to client
                    return $this->error($authResult);
                }
            }
        }

        //pass control to query handler
        $data = $this->queryHandler->handleQuery($request);
        //catch + return errors
        if ($data instanceof RESTfulAPIError) {
            return $this->error($data);
        }

        //serialize response
        $json = $this->serializer->serialize($data);
        //catch + return errors
        if ($json instanceof RESTfulAPIError) {
            return $this->error($json);
        }

        //all is good reply normally
        return $this->answer($json);
    }

    /**
     * Output the API response to client
     * then exit.
     *
     * @param  string $json Response body
     * @param  boolean $corsPreflight Set to true if this is a XHR preflight request answer. CORS shoud be enabled.
     * @return HTTPResponse
     */
    public function answer($json = null, $corsPreflight = false)
    {
        $answer = new HTTPResponse();

        //set response body
        if (!$corsPreflight) {
            $answer->setBody($json);
        }

        //set CORS if needed
        $answer = $this->setAnswerCORS($answer);

        $answer->addHeader('Content-Type', $this->serializer->getcontentType());

        // save controller's response then return/output
        $this->response = $answer;

        return $answer;
    }

    /**
     * Handles formatting and output error message
     * then exit.
     *
     * @param  RESTfulAPIError $error Error object to return
     * @return HTTPResponse
     */
    public function error(RESTfulAPIError $error)
    {
        $answer = new HTTPResponse();

        $body = $this->serializer->serialize($error->body);
        $answer->setBody($body);

        $answer->setStatusCode($error->code, $error->message);
        $answer->addHeader('Content-Type', $this->serializer->getcontentType());

        $answer = $this->setAnswerCORS($answer);

        // save controller's response then return/output
        $this->response = $answer;

        return $answer;
    }

    /**
     * Apply the proper CORS response heardes
     * to an HTTPResponse
     *
     * @param HTTPResponse $answer The updated response if CORS are neabled
     * @return HTTPResponse
     */
    private function setAnswerCORS(HTTPResponse $answer)
    {
        $cors = Config::inst()->get(self::class, 'cors');

        // skip if CORS is not enabled
        if (!$cors['Enabled']) {
            return $answer;
        }

        //check if Origin is allowed
        $allowedOrigin = $cors['Allow-Origin'];
        $requestOrigin = $this->request->getHeader('Origin');
        if ($requestOrigin) {
            if ($cors['Allow-Origin'] === '*') {
                $allowedOrigin = $requestOrigin;
            } elseif (is_array($cors['Allow-Origin'])) {
                if (in_array($requestOrigin, $cors['Allow-Origin'])) {
                    $allowedOrigin = $requestOrigin;
                }
            }
        }
        $answer->addHeader('Access-Control-Allow-Origin', $allowedOrigin);

        //allowed headers
        $allowedHeaders = '';
        $requestHeaders = $this->request->getHeader('Access-Control-Request-Headers');
        if ($cors['Allow-Headers'] === '*' && $requestHeaders != null) {
            $allowedHeaders = $requestHeaders;
        } else {
            $allowedHeaders = $cors['Allow-Headers'];
        }
        $answer->addHeader('Access-Control-Allow-Headers', $allowedHeaders);

        //allowed method
        $answer->addHeader('Access-Control-Allow-Methods', $cors['Allow-Methods']);

        //max age
        $answer->addHeader('Access-Control-Max-Age', $cors['Max-Age']);

        return $answer;
    }

    /**
     * Checks a class or model api access
     * depending on access_control_policy and the provided model.
     * - 1st config check
     * - 2nd permission check if config access passes
     *
     * @param  string|DataObject  $model      Model's classname or DataObject
     * @param  string             $httpMethod API request HTTP method
     * @return boolean                        true if access is granted, false otherwise
     */
    public static function api_access_control($model, $httpMethod = 'GET')
    {
        $policy = self::config()->access_control_policy;
        if ($policy === false) {
            return true;
        } // if access control is disabled, skip
        else {
            $policy = constant('self::' . $policy);
        }

        if ($policy === self::ACL_CHECK_MODEL_ONLY) {
            $access = true;
        } else {
            $access = false;
        }

        if ($policy === self::ACL_CHECK_CONFIG_ONLY || $policy === self::ACL_CHECK_CONFIG_AND_MODEL) {
            if (!is_string($model)) {
                $className = get_class($model);
            } else {
                $className = $model;
            }

            $access = self::api_access_config_check($className, $httpMethod);
        }

        if ($policy === self::ACL_CHECK_MODEL_ONLY || $policy === self::ACL_CHECK_CONFIG_AND_MODEL) {
            if ($access) {
                $access = self::model_permission_check($model, $httpMethod);
            }
        }

        return $access;
    }

    /**
     * Checks a model's api_access config.
     * api_access config can be:
     * - unset|false, access is always denied
     * - true, access is always granted
     * - comma separated list of allowed HTTP methods
     *
     * @param  string      $className   Model's classname
     * @param  string      $httpMethod  API request HTTP method
     * @return boolean                  true if access is granted, false otherwise
     */
    private static function api_access_config_check($className, $httpMethod = 'GET')
    {
        $access = false;
        $api_access = singleton($className)->stat('api_access');

        if (is_string($api_access)) {
            $api_access = explode(',', strtoupper($api_access));
            if (in_array($httpMethod, $api_access)) {
                $access = true;
            } else {
                $access = false;
            }
        } elseif ($api_access === true) {
            $access = true;
        }

        return $access;
    }

    /**
     * Checks a Model's permission for the currently
     * authenticated user via the Permission Manager dependency.
     *
     * For permissions to actually be checked, this means the RESTfulAPI
     * must have both authenticator and authority dependencies defined.
     *
     * If the authenticator component does not return an instance of the Member
     * null will be passed to the authority component.
     *
     * This default to true.
     *
     * @param  string|DataObject   $model      Model's classname or DataObject to check permission for
     * @param  string              $httpMethod API request HTTP method
     * @return boolean                         true if access is granted, false otherwise
     */
    private static function model_permission_check($model, $httpMethod = 'GET')
    {
        $access = true;
        $apiInstance = self::$instance;

        if ($apiInstance->authenticator && $apiInstance->authority) {
            $request = $apiInstance->request;
            $member = $apiInstance->authenticator->getOwner($request);

            if (!$member instanceof Member) {
                $member = null;
            }

            $access = $apiInstance->authority->checkPermission($model, $member, $httpMethod);
            if (!is_bool($access)) {
                $access = true;
            }
        }

        return $access;
    }
}
