:warning: I haven't been able to give as much love as I would like to these repos as they deserve. If you have time and are interested to help maintain them, give me a shout. :rotating_light:

# SilverStripe RESTful API

[![Build Status](https://github.com/colymba/silverstripe-restfulapi/actions/workflows/ci.yml/badge.svg)](https://github.com/colymba/silverstripe-restfulapi/actions/workflows/ci.yml)

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

The allowed `/auth/$Action` must be defined on the used `Authenticator` class via the `$allowed_actions` config.


## Requirements
* [SilverStripe Framework 4+](https://github.com/silverstripe/silverstripe-framework)


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
* Authetication (`Authenticator`)
* Permission Management (`PermissionManager`)
* Query Handler (`QueryHandler`)
* Serializer (`Serializer`)


### Default components
This API comes with defaults for each of those components:
* `TokenAuthenticator` handles authentication via a token in an HTTP header or variable
* `DefaultPermissionManager` handles DataObject permission checks depending on the HTTP request
* `DefaultQueryHandler` handles all find, edit, create or delete for models
* `DefaultSerializer` / `DefaultDeSerializer` serialize query results into JSON and deserialize client payloads
* `EmberDataSerializer` / `EmberDataDeSerializer` same as the `Default` version but with specific fomatting fo Ember Data.

You can create you own classes by implementing the right interface or extending the existing components. When creating you own components, any error should be return as a `RESTfulAPIError` object to the `RESTfulAPI`.


### Token Authentication Extension
When using `TokenAuthenticator` you must add the `TokenAuthExtension` `DataExtension` to a `DataObject` and setup `TokenAuthenticator` with the right config.

**By default, API authentication is disabled.**


### Permissions management
DataObject API access control can be managed in 2 ways. Through the `api_access` [YML config](doc/RESTfulAPI.md#authentication-and-api-access-control) allowing for simple configurations, or via [DataObject permissions](http://doc.silverstripe.org/framework/en/reference/dataobject#permissions) through a `PermissionManager` component.

A sample `Group` extension `GroupExtension` is also available with a basic set of dedicated API permissions. This can be enabled via [config](code/_config/config.yml#L11) or you can create your own.

**By default, the API only performs access control against the `api_access` YML config.**


### Config
See individual component configuration file for mode details
* [RESTfulAPI](doc/RESTfulAPI.md) the root of the api
* [TokenAuthenticator](doc/TokenAuthenticator.md) handles query authentication via token
* [DefaultPermissionManager](doc/DefaultPermissionManager.md) handles DataObject level permissions check
* [DefaultQueryHandler](doc/DefaultQueryHandler.md) where most of the logic happens
* [DefaultSerializer](doc/DefaultSerializer.md) DefaultSerializer and DeSerializer for everyday use
* [EmberDataSerializer](doc/EmberDataSerializer.md) EmberDataSerializer and DeSerializer speicifrcally design for use with Ember Data and application/vnd.api+json

Here is what a site's `config.yml` file could look like:
```yaml
---
Name: mysite
After:
    - 'framework/*'
    - 'cms/*'
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
Colymba\RESTfulAPI\RESTfulAPI:
  authentication_policy: true
  access_control_policy: 'ACL_CHECK_CONFIG_AND_MODEL'
  dependencies:
    authenticator: '%$Colymba\RESTfulAPI\Authenticators\TokenAuthenticator'
    authority: '%$Colymba\RESTfulAPI\PermissionManagers\DefaultPermissionManager'
    queryHandler: '%$Colymba\RESTfulAPI\QueryHandlers\DefaultQueryHandler'
    serializer: '%$Colymba\RESTfulAPI\Serializers\EmberData\EmberDataSerializer'
  cors:
    Enabled: true
    Allow-Origin: 'http://mydomain.com'
    Allow-Headers: '*'
    Allow-Methods: 'OPTIONS, GET'
    Max-Age: 86400
# Components config
Colymba\RESTfulAPI\QueryHandlers\DefaultQueryHandler\DefaultQueryHandler:
  dependencies:
    deSerializer: '%$Colymba\RESTfulAPI\Serializers\EmberData\EmberDataDeSerializer'
Colymba\RESTfulAPI\Serializers\EmberData\EmberDataSerializer:
  sideloaded_records:
    Artwork:
      - 'Visuals'
      - 'Authors'
```


## Todo
* API access IP throttling (limit request per minute for each IP or token)
* Check components interface implementation


## License 
[BSD 3-clause license](LICENSE)

Copyright (c) 2018, Thierry Francois (colymba)
All rights reserved.
