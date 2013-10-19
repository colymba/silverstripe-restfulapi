# SilverStripe RESTful API

This module implements a RESTful API for read/write access to your SilverStripe Models. Comes bundled with a default Query Handler and JSON Serializer, but can be extended to return XML or other content type via custom Serializers.

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

`Models` being the pluralized calss name of the model you are querying.

## Requirements
* [SilverStripe Framework 3.1+](https://github.com/silverstripe/silverstripe-framework)

## Note
Originally made for use with EmberJS/Ember Data DS.RESTAdapter. This RESTful API with the default JSON Serializer should work out of the box with the latest version of Ember Data.

## Links
* [JSON API](http://jsonapi.org)
* [Ember JS](https://github.com/emberjs/ember.js)
* [Ember Data](https://github.com/emberjs/data)

## Todo
* Authentication, Serializer & QueryHandler help
* Implement API's PermissionProvider
* Default YAML config

## License (BSD Simplified)

Copyright (c) 2013, Thierry Francois (colymba)

All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * Neither the name of Thierry Francois, colymba nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.