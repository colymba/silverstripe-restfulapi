# RESTfulAPI_DefaultQueryHandler

This component handles database queries, utilize the deserializer to figure out models and column names and returns the data to the RESTfulAPI.

Config | Type | Info | Default
--- | :---: | --- | ---
`dependencies` | `array` | key => value pair specifying which deserializer to use | 'deSerializer' => '%$RESTfulAPI_BasicDeSerializer'
`searchFilterModifiersSeparator` | `string` | Separator used in HTTP params between the column name and the search filter modifier (e.g. ?name__StartsWith=Henry will find models with the column name that starts with 'Henry'. ORM equivalent *->filter(array('name::StartsWith' => 'Henry'))* ) | '__'
`max_records_limit` | `int` | specify the maximum number of records to return by default (avoid the api returning millions...) | 100


## Search filter modifiers
This also accept search filter modifiers in HTTP variables (see [Search Filter Modifiers](http://doc.silverstripe.org/framework/en/topics/datamodel#search-filter-modifiers)) like:
* ?columnNAme__StartsWith=Ba

As well as special modifiers `sort`, `rand` and `limit` with these possible formatting:
* ?columnName__sort=ASC
* ?__rand
* ?__rand=seed
* ?__limit=count
* ?__limit[]=count&__limit[]=offset

Search filter modifiers are recognised/extracted thanks to the `searchFilterModifiersSeparator` config. The above examples assume the default `searchFilterModifiersSeparator` is in use.