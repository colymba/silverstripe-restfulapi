# SilverStripe RESTful API

This module implements a RESTful API for read/write access to your SilverStripe Models. Comes bundled with a default Token Authenticator, Query Handler and JSON Serializer, but can be extended to return XML or other content type via custom Serializers.

## API URL structure

| Action                | Verb      | URL                                     |
| :-------------------- | :-------- | :-------------------------------------- |
| Find 1 record         | `GET`     | `api/Models/ID`                         |
| Find multiple records | `GET`     | `api/Models?param=val&anotherParam=val` |
| Update a record       | `PUT`     | `api/Models/ID`                         |
| Create a record       | `POST`    | `api/Models`                            |
| Delete a record       | `DELETE`  | `api/Models/ID`                         |
| -                     | -         | -                                       |
| Login & get token     | `*`       | `api/auth/login?email=***&pwd=***`      |
| Logout                | `*`       | `api/auth/logout`                       |
| Password reset email  | `*`       | `api/auth/lostPassword?email=***`       |

`Models` being the pluralized calss name of the model you are querying. For example with model class named `Book` URLs would look like:
* `api/Books/33`
* `api/Books?title=Henry`
* `api/Books?title__StartsWith=Henry`
* `api/Books?title__StartsWith=Henry&__rand=123456&__limit=1`
* `api/Books?title__StartsWith=Henry&__rand=123456&__limit[]=10&__limit[]=5`

## Requirements
* [SilverStripe Framework 3.1+](https://github.com/silverstripe/silverstripe-framework)

## What's all this?
### RESTfulAPI
This is the main API Controller that receives all the requests, passes them on to the right component and return the response to the client.

If CORS are enabled (by default), this takes care of the right headers too.

### Components
The `RESTfulAPI` Controller uses three types of components, each implementing a different interface:
* Authetication (`RESTfulAPI_Authenticator`)
* Query Handler (`RESTfulAPI_QueryHandler`)
* Serializer (`RESTfulAPI_Serializer`)

### Default components
This API comes with defaults for each of those components:
* `RESTfulAPI_TokenAuthenticator` handles authentication via a token in an HTTP header or variable
* `RESTfulAPI_DefaultQueryHandler` handles all find, edit, create or delete for models
* `RESTfulAPI_DefaultSerializer` serialize query results into JSON

You can create you own classes by implementing the right interface or extending the existing components.

API and Component settings can be updated via the config API (use `config.yml`).

### Config

#### RESTfulAPI `RESTfulAPI.`
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


#### RESTfulAPI_TokenAuthenticator `RESTfulAPI_TokenAuthenticator.`
This component takes care of authenticating all API requests against a token stored in a HTTP header or query var as fallback. The authentication token is returned by the `login` function. Also available, a `logout` function and `lostpassword` function that will email a password rest link to the user.
You can define a `onBeforeSerialize()` function on your model to add/remove field to your model before being serialized.

Config | Type | Info | Default
--- | :---: | --- | ---
`tokenLife` | `integer` | Authentication token life in ms | 10800000


#### RESTfulAPI_DefaultQueryHandler `RESTfulAPI_DefaultQueryHandler.`
This component handles database queries and return the data to the API. This also accept search filter modifiers in HTTP variables (see [Search Filter Modifiers](http://doc.silverstripe.org/framework/en/topics/datamodel#search-filter-modifiers)) as well as 2 special modifiers (rand=seed and limit=count).

Config | Type | Info | Default
--- | :---: | --- | ---
`embeddedRecords` | `array` | Defines which classes to embed into relations. NOT IMPLEMENTED | n/a
`sideloadedRecords` | `array` | Defines which classes to load into the response. NOT IMPLEMENTED | n/a
`searchFilterModifiersSeparator` | `string` | Separator used in HTTP params between the column name and the search filter modifier (e.g. ?name__StartsWith=Henry will find models with the column name that starts with 'Henry'. ORM equivalent *->filter(array('name::StartsWith' => 'Henry'))* ) | '__'


#### RESTfulAPI_DefaultSerializer `RESTfulAPI_DefaultSerializer.`
This component will serialize the data into JSON with the following conventions:
* SilverStripe Classes and fields name are UpperCamelCase
* The client api uses lowerCamelCase variable.
* Results are returned in a JSON root with the requested model as key (plurialized when returning mulitple results)

Config | Type | Info | Default
--- | :---: | --- | ---
`n/a` | `n/a` | n/a | n/a


## API Documentation
Full API documentation (generated with each releases) available in the [doc subfolder](https://github.com/colymba/silverstripe-restfulapi/tree/master/doc)

## Note
Originally made for use with EmberJS/Ember Data DS.RESTAdapter. This RESTful API with the default JSON Serializer should work out of the box with the latest version of Ember Data.

## Links
* [JSON API](http://jsonapi.org)
* [Ember JS](https://github.com/emberjs/ember.js)
* [Ember Data](https://github.com/emberjs/data)
* [Using CORS](http://www.html5rocks.com/en/tutorials/cors/)

## Todo
* RESTfulAPI_TokenAuthenticator configurable token header/var name
* RESTfulAPI_TokenAuthenticator : validateAPIToken should have configurable class to check the token against (e.g. other than Member) + configurable token column name
* RESTfulAPI_Authenticator interface should not have login or logout
* Implement API's PermissionProvider
* JSON ActiveModel Serializer
* Check components interface implementation 

## License (BSD Simplified)

Copyright (c) 2013, Thierry Francois (colymba)

All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * Neither the name of Thierry Francois, colymba nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
