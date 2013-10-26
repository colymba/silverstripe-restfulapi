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

The allowed `/auth/$Action` must be defined on the `RESTfulAPI_Authenticator` class used via `$allowed_actions` config.

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

### Token Authentication Extension
When using `RESTfulAPI_TokenAuthenticator` you must add the `RESTfulAPI_TokenAuthExtension` `DataExtension` to a `DataObject` and setup `RESTfulAPI_TokenAuthenticator` with the right classname.

** `WARNING` By default API authentication is disabled, exposing your whole database. Make sure to enable it via config. **

### Config

See [CONFIG.md](CONFIG.md) for individual component configuration options.

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
* Authentication requirements levels (none vs all vs read vs write)
* Implement API's PermissionProvider
* JSON ActiveModel Serializer component
* Check components interface implementation 

## License (BSD Simplified)

Copyright (c) 2013, Thierry Francois (colymba)

All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * Neither the name of Thierry Francois, colymba nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
