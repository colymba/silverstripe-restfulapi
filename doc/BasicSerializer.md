# RESTfulAPI_BasicSerializer & RESTfulAPI_BasicDeSerializer

This component will serialize the data returned by the QueryHandler into JSON. No special formatting is performed on the JSON output (column names are returned as is), DataObject are returns as objects {} and DataLists as array or objects [{},{}].

Config | Type | Info | Default
--- | :---: | --- | ---
`n/a` | `n/a` | n/a | n/a


## Embedded records

This serializer will use the `RESTfulAPI` `embedded_records` config.


## Hooks

You can define an `onBeforeSerialize()` function on your model to add/remove field to your model before being serialized (i.e. remove Password from Member).