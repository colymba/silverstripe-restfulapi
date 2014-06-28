# RESTfulAPI

This handles/redirect all api request. The API is accessed via the `api/` url (can be changed with a `Director` rule). The `api/auth/ACTION` request will need the Authentication component to have the *ACTION* defined.

If `api/` isn't a suitable access point for the api, this can be changed via config:
```yaml
Director:
  rules:
    'restapi': 'RESTfulAPI'
```

Config | Type | Info | Default
--- | :---: | --- | ---
`authentication_policy` | `boolean`/`array` | If true, the API will use authentication, if false no authentication required. Or an array of HTTP methods that require authentication | false
`access_control_policy` | `boolean`/`string` | Lets you select which access control checks the API will perform or none at all. | 'ACL_CHECK_CONFIG_ONLY'
`dependencies` | `array` | key => value pairs sepcifying the components classes used for the `'authenticator'`, `'queryHandler'` and `'serializer'`  | 'authenticator' => '%$RESTfulAPI_TokenAuthenticator', 'queryHandler' => '%$RESTfulAPI_DefaultQueryHandler', 'serializer' => '%$RESTfulAPI_BasicSerializer'
`embedded_records` | `array` | key => value pairs sepcifying which relation names to embed in the response and for which model this applies (i.e. 'RequestedClass' => array('RelationNameToEmbed')) | n/a
- | - | - | -
`cors` | `array` | Cross-Origin Resource Sharing (CORS) API settings | 
`cors.Enabled` | `boolean` | If true the API will add CORS HTTP headers to the response | true
`cors.Allow-Origin` | `string` or `array` | '\*' allows all, 'http://domain.com' allows a specific domain, array('http://domain.com', 'http://site.com') allows a list of domains | '\*'
`cors.Allow-Headers` | `string` | '\*' allows all, 'header1, header2' coman separated list allows a list of headers | '\*'
`cors.Allow-Methods` | `string` | 'HTTPMETHODE1, HTTPMETHODE12' coma separated list of HTTP methodes to allow | 'OPTIONS, POST, GET, PUT, DELETE'
`cors.Max-Age` | `integer` | Preflight/OPTIONS request caching time in seconds | 86400


## CORS (Cross-Origin Resource Sharing)

This is nescassary if the api is access from a different domain. See [using CORS](http://www.html5rocks.com/en/tutorials/cors/) for more infos.


## Authentication and api access control
By default, the api will refuse access to any model/dataObject which doesn't have it's `api_access` config var explicitly enabled. So for generic use and just limiting which models are accessible, an authenticator component isn't nescessary.

Note that the DataObject's `api_access` config can either be:
* unset|false: all requests to this model will be rejected
* true: all requests will be allowed
* array of HTTP methods: only requests with the HTTP method in the config will be allowed (i.e. GET, POST)

If you require a more fined tune permission management, you can change the `access_control_policy` to perform model checks. This will be handled by the defined `authority` component like [DefaultPermissionManager](DefaultPermissionManager.md).

See the [api_access_control()](../code/RESTfulAPI.php#L519) function for more details.


### Access control considerations
The API (with default components) will call the `api_access_control` method (making any configured checks) for each `find`, `create`, `update`, `delete` operations as well as during serialization of the data. Using a `RESTfulAPI_PermissionManager` may impact performace and you should concider carefully your permission sets to avoid unexpected results.

Note that when coonfiguring `api_access_control` to do checks on the DataObject level via a `RESTfulAPI_PermissionManager`, the Member model passed to the Permission Manager is the one returned by the `RESTfulAPI_Authenticator` `getOwner()` method. If the returned owner isn't an instance of Member, `null` will be passed instead.

A sample `Group` extension `RESTfulAPI_GroupExtension` is also available with a basic set of dedicated API permissions and User Groups. This can be enabled via [config](../code/_config/config.yml#L11) or you can create your own.


## Embedded records
By default on the IDs of relations (has_one, has_many...) are returned to the client. To save HTTP request, these relation can be embedded into the payload, this is defined by the `embedded_records` config and used by the serializers.

For more details about embeded records, [see the source comment](../code/RESTfulAPI.php#L106) on the config var.