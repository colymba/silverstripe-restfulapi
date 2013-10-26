# API components configuration

API and Component settings can be updated via the config API (use `config.yml`).


## Main REST API `RESTfulAPI.`
This handles/redirect all api request. The API is accesses via the `api/` url (can be changed with a `Director` rule). The `api/auth/ACTION` request will need the Authentication component to have the *ACTION* defined.

Config | Type | Info | Default
--- | :---: | --- | ---
`authenticator` | `boolean` | If true, the API will use authentication | false
`authenticatorClass` | `string` | The Authenticator class to use for all authentication requests | 'RESTfulAPI_TokenAuthenticator'
`queryHandlerClass` | `string` | The QueryHandler class that will handle the model/database queries (find, create, edit, delete....) | 'RESTfulAPI_DefaultQueryHandler'
`serializerClass` | `string` | The Serializer class that will handle models/data serialization before returning the data to the client (e.g. convert data into JSON) | 'RESTfulAPI_DefaultSerializer'
- | - | - | -
`cors` | `array` | Cross-Origin Resource Sharing (CORS) API settings | 
`cors.Enabled` | `boolean` | If true the API will add CORS HTTP headers to the response | true
`cors.Allow-Origin` | `string` or `array` | '\*' allows all, 'http://domain.com' allows a specific domain, array('http://domain.com', 'http://site.com') allows a list of domains | '\*'
`cors.Allow-Headers` | `string` | '\*' allows all, 'header1, header2' coman separated list allows a list of headers | '\*'
`cors.Allow-Methods` | `string` | 'HTTPMETHODE1, HTTPMETHODE12' coma separated list of HTTP methodes to allow | 'OPTIONS, POST, GET, PUT, DELETE'
`cors.Max-Age` | `integer` | Preflight/OPTIONS request caching time in seconds | 86400


## Token Authenticator `RESTfulAPI_TokenAuthenticator.`
This component takes care of authenticating all API requests against a token stored in a HTTP header or query var as fallback.

The authentication token is returned by the `login` function. Also available, a `logout` function and `lostpassword` function that will email a password reset link to the user.

The token can also be retrieved with an `RESTfulAPI_TokenAuthenticator` instance calling `getToken()` and it can be reset via `resetToken()`.

The `RESTfulAPI_TokenAuthExtension` `DataExtension` must be applied to a `DataObject` and the `tokenOwnerClass` config updated with the correct classname.

Config | Type | Info | Default
--- | :---: | --- | ---
`tokenLife` | `integer` | Authentication token life in ms | 10800000
`tokenHeader` | `string` | Custom HTTP header storing the token | 'X-Silverstripe-Apitoken'
`tokenQueryVar` | `string` | Fallback GET/POST HTTP query var storing the token | 'token'
`tokenOwnerClass` | `string` | DataObject class name for the token's owner | 'Member'


## Token Authentication Data Extension `RESTfulAPI_TokenAuthExtension`
This extension **MUST** be applied to a `DataObject` to use `RESTfulAPI_TokenAuthenticator` and update the `tokenOwnerClass` config accordingly. e.g.
```yaml
Member:
  extensions:
    - RESTfulAPI_TokenAuthExtension
```
```yaml
ApiUser:
  extensions:
    - RESTfulAPI_TokenAuthExtension
RESTfulAPI_TokenAuthenticator:
  tokenOwnerClass: 'ApiUser'
```

The `$db` keys can be changed to anything you want but keep the types to `Varchar(160)` and `Int`.


## Default QueryHandler `RESTfulAPI_DefaultQueryHandler.`
This component handles database queries and return the data to the API. This also accept search filter modifiers in HTTP variables (see [Search Filter Modifiers](http://doc.silverstripe.org/framework/en/topics/datamodel#search-filter-modifiers)) as well as 2 special modifiers (rand=seed and limit=count).

Config | Type | Info | Default
--- | :---: | --- | ---
`embeddedRecords` | `array` | Defines which classes to embed into relations. NOT IMPLEMENTED | n/a
`sideloadedRecords` | `array` | Defines which classes to load into the response. NOT IMPLEMENTED | n/a
`searchFilterModifiersSeparator` | `string` | Separator used in HTTP params between the column name and the search filter modifier (e.g. ?name__StartsWith=Henry will find models with the column name that starts with 'Henry'. ORM equivalent *->filter(array('name::StartsWith' => 'Henry'))* ) | '__'


## Default Serializer `RESTfulAPI_DefaultSerializer.`
This component will serialize the data into JSON with the following conventions:
* SilverStripe Classes and fields name are UpperCamelCase
* The client api uses lowerCamelCase variable.
* Results are returned in a JSON root with the requested model as key (plurialized when returning mulitple results)

You can define an `onBeforeSerialize()` function on your model to add/remove field to your model before being serialized (e.g. remove Password from Member).

Config | Type | Info | Default
--- | :---: | --- | ---
`n/a` | `n/a` | n/a | n/a