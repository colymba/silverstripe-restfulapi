# RESTfulAPI_BasicSerializer & RESTfulAPI_BasicDeSerializer

This component will serialize the data returned by the QueryHandler into JSON. No special formatting is performed on the JSON output (column names are returned as is), DataObject are returns as objects {} and DataLists as array or objects [{},{}].

Config | Type | Info | Default
--- | :---: | --- | ---
`n/a` | `n/a` | n/a | n/a


## Embedded records

This serializer will use the `RESTfulAPI` `embedded_records` config.


## Hooks

You can define an `onBeforeSerialize()` function on your model to add/remove field to your model before being serialized (i.e. remove Password from Member).

## Specifying fields to use

You can specify which fields you'd like included in the API output for a DataObject:

```yaml
Book:
  api_fields:
    - Title
    - Pages
    - Author # a related model
Author:
  api_fields:
    - Name
```

In the above example, if you requested a Book you would receive it's Title, Pages and related Author object (a
`has_one` relation). The Author returned would have a Name. Entity IDs will remain in place as well.

If you don't specify anything for a DataObject's `api_fields` configuration setting, the standard dataset will be
returned.

It's also important to note that in the above example, API requests for Author would only ever return the Name field.
If you wanted it to be only for requests for a book, you can use the `onBeforeSerialize()` extension method to set
the config dynamically:

```yaml
Book:
  extensions:
    - BookAuthorApiExtension
```

```php
class BookAuthorApiExtension extends DataExtension
{
    /**
     * Only return the Author's Name when it's accessed through a Book.
     */
    public function onBeforeSerialize()
    {
        Config::inst()->update('Author', 'api_fields', array('Name'));
    }
}
```
