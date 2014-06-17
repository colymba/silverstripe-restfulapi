# RESTfulAPI_TokenAuthenticator

This component takes care of authenticating all API requests against a token stored in a HTTP header or a query var as fallback.

The authentication token is returned by the `login` function. Also available, a `logout` function and `lostpassword` function that will email a password reset link to the user.

The token can also be retrieved with an `RESTfulAPI_TokenAuthenticator` instance calling the method `getToken()` and it can be reset via `resetToken()`.

The `RESTfulAPI_TokenAuthExtension` `DataExtension` must be applied to a `DataObject` and the `tokenOwnerClass` config updated with the correct classname.

Config | Type | Info | Default
--- | :---: | --- | ---
`tokenLife` | `integer` | Authentication token life in seconds | 10800
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