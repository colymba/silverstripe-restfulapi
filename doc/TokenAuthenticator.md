# RESTfulAPITokenAuthenticator

This component takes care of authenticating all API requests against a token stored in a HTTP header or a query var as fallback.

The authentication token is returned by the `login` function. Also available, a `logout` function and `lostpassword` function that will email a password reset link to the user.

The token can also be retrieved with an `RESTfulAPITokenAuthenticator` instance calling the method `getToken()` and it can be reset via `resetToken()`.

The `RESTfulAPITokenAuthExtension` `DataExtension` must be applied to a `DataObject` and the `tokenOwnerClass` config updated with the correct classname.

Config | Type | Info | Default
--- | :---: | --- | ---
`tokenLife` | `integer` | Authentication token life in seconds | 10800
`tokenHeader` | `string` | Custom HTTP header storing the token | 'X-Silverstripe-Apitoken'
`tokenQueryVar` | `string` | Fallback GET/POST HTTP query var storing the token | 'token'
`tokenOwnerClass` | `string` | DataObject class name for the token's owner | 'Member'
`autoRefreshLifetime` | `boolean` | Whether or not token lifetime should be updated with every request | false


## Token Authentication Data Extension `RESTfulAPITokenAuthExtension`
This extension **MUST** be applied to a `DataObject` to use `RESTfulAPITokenAuthenticator` and update the `tokenOwnerClass` config accordingly. e.g.
```yaml
Member:
  extensions:
    - RESTfulAPITokenAuthExtension
```
```yaml
ApiUser:
  extensions:
    - RESTfulAPITokenAuthExtension
RESTfulAPITokenAuthenticator:
  tokenOwnerClass: 'ApiUser'
```

The `$db` keys can be changed to anything you want but keep the types to `Varchar(160)` and `Int`.