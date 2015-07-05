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
`autoRefreshLifetime` | `boolean` | Whether or not token lifetime should be updated with every request | false
`refreshTokenColumn` | `string` | Column name that contains the refresh-token. Only modify this if you use different column names for the `RESTfulAPI_TokenAuthExtension` (eg. by using a custom extension) | 'ApiRefreshToken'

## Making use of the refresh-token

A successful login request will also return a `refreshtoken`. This is a token that can be used to refresh the user-token as long as the token has not expired. After a successful refresh, both API-token and refresh-token will be reset and the API-token will get a new lifetime.

Typically a client-application will perform login by supplying user-credentials. The client-application then has to perform a token refresh before the access-token is about to expire.

To perform a token-refresh, use the API endpoint:

    api/auth/refreshToken?refreshtoken=<token>;

Or pass the parameter in a JSON body. **NOTE:** This is a request that requires authentication, so you have to pass the existing (non-expired) API-token with the request as well.

When making use of the refresh-token, it's advised to set token lifetime (`tokenLife`) to a lower value than the default (which is 3 hours). A sensible value would be `900` or `1800`. Also do not enable `autoRefreshLifetime` as this would defeat the purpose of the refresh-token.

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