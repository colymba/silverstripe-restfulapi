# RESTfulAPI_DefaultPermissionManager

This component will check access permission against a DataObject for a given Member. The request HTTP method, will be match against a DataObject's `can()` method. 

Config | Type | Info | Default
--- | :---: | --- | ---
`n/a` | `n/a` | n/a | n/a


Permission checks should be implemented on your DataObject with the `canView`, `canCreate`, `canEdit`, `canDelete` methods. See SilverStripe [documentation](http://doc.silverstripe.org/framework/en/reference/dataobject#permissions) for more information.