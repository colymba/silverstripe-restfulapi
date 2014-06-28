# SilverStripe RESTful API

[![Build Status](https://travis-ci.org/colymba/silverstripe-restfulapi.png?branch=master)](https://travis-ci.org/colymba/silverstripe-restfulapi)

This module implements a RESTful API for read/write access to your SilverStripe Models. It comes bundled with a default Token Authenticator, Query Handler and JSON Serializers, and can be extended to your need and to return XML or other content type via custom components.


## API URL structure

| Action                | HTTP Verb | URL                                     |
| :-------------------- | :-------- | :-------------------------------------- |
| Find 1 record         | `GET`     | `api/Model/ID`                          |
| Find multiple records | `GET`     | `api/Model?param=val&__rand=1234`       |
| Update a record       | `PUT`     | `api/Model/ID`                          |
| Create a record       | `POST`    | `api/Model`                             |
| Delete a record       | `DELETE`  | `api/Model/ID`                          |
| -                     | -         | -                                       |
| Login & get token     | n/a       | `api/auth/login?email=***&pwd=***`      |
| Logout                | n/a       | `api/auth/logout`                       |
| Password reset email  | n/a       | `api/auth/lostPassword?email=***`       |
| -                     | -         | -                                       |
| Custom ACL methods    | n/a       | `api/acl/YOURMETHOD`                    |

`Model` being the class name of the model you are querying (*name formatting may vary depending on DeSerializer used*). For example with a model class named `Book` URLs would look like:
* `api/Book/33`
* `api/Book?title=Henry`
* `api/Book?title__StartsWith=Henry`
* `api/Book?title__StartsWith=Henry&__rand=123456&__limit=1`
* `api/Book?title__StartsWith=Henry&__rand=123456&__limit[]=10&__limit[]=5`

The allowed `/auth/$Action` must be defined on the used `RESTfulAPI_Authenticator` class via the `$allowed_actions` config.


## Requirements
* [SilverStripe Framework 3.1+](https://github.com/silverstripe/silverstripe-framework)


## Quick features highlight
* [Configurable components](#components)
* [CORS enabled](doc/RESTfulAPI.md#cors)
* [Embedded records](doc/RESTfulAPI.md#embedded-records)
* [Sideloaded records (EmberDataSerializer)](doc/EmberDataSerializer.md#sideloaded-records)
* [Authentication](doc/TokenAuthenticator.md)
* [DataObject & Config level api access control](doc/RESTfulAPI.md#authentication-and-api-access-control)
* [Search filter modifiers](doc/DefaultQueryHandler.md#search-filter-modifiers)


## What's all this?
### RESTfulAPI
This is the main API Controller that receives all the requests, checks if authentication is needed and passing control to the authenticator if true, the resquest is then passed on to the QueryHandler, which uses the DeSerializer to figure out model & column names and decode the eventual payload from the client, the query result is then passed to the Serializer to be formatted and then returned to the client.

If CORS are enabled (true by default), the right headers are taken care of too.


### Components
The `RESTfulAPI` uses 4 types of components, each implementing a different interface:
* Authetication (`RESTfulAPI_Authenticator`)
* Permission Management (`RESTfulAPI_PermissionManager`)
* Query Handler (`RESTfulAPI_QueryHandler`)
* Serializer (`RESTfulAPI_Serializer`)


### Default components
This API comes with defaults for each of those components:
* `RESTfulAPI_TokenAuthenticator` handles authentication via a token in an HTTP header or variable
* `RESTfulAPI_DefaultPermissionManager` handles DataObject permission checks depending on the HTTP request
* `RESTfulAPI_DefaultQueryHandler` handles all find, edit, create or delete for models
* `RESTfulAPI_BasicSerializer` / `RESTfulAPI_BasicDeSerializer` serialize query results into JSON and deserialize client payloads
* `RESTfulAPI_EmberDataSerializer` / `RESTfulAPI_EmberDataDeSerializer` same as the `Basic` version but with specific fomatting fo Ember Data.

You can create you own classes by implementing the right interface or extending the existing components. When creating you own components, any error should be return as a `RESTfulAPI_Error` object to the `RESTfulAPI`.


### Token Authentication Extension
When using `RESTfulAPI_TokenAuthenticator` you must add the `RESTfulAPI_TokenAuthExtension` `DataExtension` to a `DataObject` and setup `RESTfulAPI_TokenAuthenticator` with the right config.

**By default, API authentication is disabled.**


### Permissions management
DataObject API access control can be managed in 2 ways. Through the `api_access` [YML config](doc/RESTfulAPI.md#authentication-and-api-access-control) allowing for simple configurations, or via [DataObject permissions](http://doc.silverstripe.org/framework/en/reference/dataobject#permissions) through a `RESTfulAPI_PermissionManager` component.

A sample `Group` extension `RESTfulAPI_GroupExtension` is also available with a basic set of dedicated API permissions. This can be enabled via [config](code/_config/config.yml#L11) or you can create your own.

**By default, the API only performs access control against the `api_access` YML config.**


### Config
See individual component configuration file for mode details
* [RESTfulAPI](doc/RESTfulAPI.md) the root of the api
* [TokenAuthenticator](doc/TokenAuthenticator.md) handles query authentication via token
* [DefaultPermissionManager](doc/DefaultPermissionManager.md) handles DataObject level permissions check
* [DefaultQueryHandler](doc/DefaultQueryHandler.md) where most of the logic happens
* [BasicSerializer](doc/BasicSerializer.md) BasicSerializer and DeSerializer for everyday use
* [EmberDataSerializer](doc/EmberDataSerializer.md) EmberDataSerializer and DeSerializer speicifrcally design for use with Ember Data and application/vnd.api+json

Here is what a site's `config.yml` file could look like:
```yaml
---
Name: mysite
After: 'framework/*','cms/*'
---
# API access
Artwork:
  api_access: true
Author:
  api_access: true
Category:
  api_access: true
Magazine:
  api_access: true
Tag:
  api_access: 'GET,POST'
Visual:
  api_access: true
Image:
  api_access: true
File:
  api_access: true
Page:
  api_access: false
# RestfulAPI config
RESTfulAPI:
  authentication_policy: true
  access_control_policy: 'ACL_CHECK_CONFIG_AND_MODEL'
  dependencies:
    authenticator: '%$RESTfulAPI_TokenAuthenticator'
    authority: '%$RESTfulAPI_DefaultPermissionManager'
    queryHandler: '%$RESTfulAPI_DefaultQueryHandler'
    serializer: '%$RESTfulAPI_EmberDataSerializer'
  cors:
    Enabled: true
    Allow-Origin: 'http://mydomain.com'
    Allow-Headers: '*'
    Allow-Methods: 'OPTIONS, GET'
    Max-Age: 86400
# Components config
RESTfulAPI_DefaultQueryHandler:
  dependencies:
    deSerializer: '%$RESTfulAPI_EmberDataDeSerializer'
RESTfulAPI_EmberDataSerializer:
  sideloaded_records:
    Artwork:
      - 'Visuals'
      - 'Authors'
```


## In the wild
* [hesainprint.com](http://hesainprint.com)


## Todo
* API access IP throttling (limit request per minute for each IP or token)
* Check components interface implementation 


## License (BSD Simplified)

Copyright (c) 2013, Thierry Francois (colymba)

All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * Neither the name of Thierry Francois, colymba nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
