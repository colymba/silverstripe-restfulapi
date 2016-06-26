<?php
/**
 * Basic RESTfulAPI Model Serializer
 * handles DataObject, DataList etc.. JSON serialization and de-serialization
 *
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 *
 * @package RESTfulAPI
 * @subpackage Serializer
 */
class RESTfulAPI_BasicSerializer implements RESTfulAPI_Serializer
{
    /**
     * Content-type header definition for this Serializer
     * Used by RESTfulAPI in the response
     *
     * @var string
     */
    private $contentType = 'application/json; charset=utf-8';

    /**
     * Return Content-type header definition
     * to be used in the API response
     *
     * @return string Content-type
     */
    public function getcontentType()
    {
        return $this->contentType;
    }

    /**
     * Stores the current $embedded_records @config
     * Config set on {@link RESTfulAPI}
     *
     * @var array
     */
    protected $embeddedRecords;

    /**
     * Construct and set current config
     */
    public function __construct()
    {
        $embedded_records = Config::inst()->get('RESTfulAPI', 'embedded_records');
        if (is_array($embedded_records)) {
            $this->embeddedRecords = $embedded_records;
        } else {
            $this->embeddedRecords = array();
        }
    }


    /**
     * Convert data into a JSON string
     *
     * @param  mixed  $data Data to convert
     * @return string       JSON data
     */
    protected function jsonify($data)
    {
        // JSON_NUMERIC_CHECK removes leading zeros
        // which is an issue in cases like postcode e.g. 00160
        // see https://bugs.php.net/bug.php?id=64695
        $json = json_encode($data);

        //catch JSON parsing error
        $error = RESTfulAPI_Error::get_json_error();
        if ($error !== false) {
            return new RESTfulAPI_Error(400, $error);
        }

        return $json;
    }


    /**
     * Convert raw data (DataObject or DataList) to JSON
     * ready to be consumed by the client API
     *
     * @param  mixed   $data  Data to serialize
     * @return string         JSON representation of data
     */
    public function serialize($data)
    {
        $json = '';
        $formattedData = null;

        if ($data instanceof DataObject) {
            $formattedData = $this->formatDataObject($data);
        } elseif ($data instanceof DataList) {
            $formattedData = $this->formatDataList($data);
        }

        if ($formattedData !== null) {
            $json = $this->jsonify($formattedData);
        } else {
            //fallback: convert non array to object then encode
            if (!is_array($data)) {
                $data = (object) $data;
            }
            $json = $this->jsonify($data);
        }

        return $json;
    }


    /**
     * Format a DataObject keys and values
     * ready to be turned into JSON
     *
     * @param  DataObject $dataObject The data object to format
     * @return array|null             The formatted array map representation of the DataObject or null
     *                                is permission denied
     */
    protected function formatDataObject(DataObject $dataObject)
    {
        // api access control
        if (!RESTfulAPI::api_access_control($dataObject, 'GET')) {
            return null;
        }

        if (method_exists($dataObject, 'onBeforeSerialize')) {
            $dataObject->onBeforeSerialize();
        }
        $dataObject->extend('onBeforeSerialize');

        // setup
        $formattedDataObjectMap = array();

        // get DataObject config
        $db                    = Config::inst()->get($dataObject->ClassName, 'db');
        $has_one               = Config::inst()->get($dataObject->ClassName, 'has_one');
        $has_many              = Config::inst()->get($dataObject->ClassName, 'has_many');
        $many_many             = Config::inst()->get($dataObject->ClassName, 'many_many');
        $belongs_many_many     = Config::inst()->get($dataObject->ClassName, 'belongs_many_many');

        // Get a possibly defined list of "api_fields" for this DataObject. If defined, they will be the only fields
        // for this DataObject that will be returned, including related models.
        $apiFields = (array) Config::inst()->get($dataObject->ClassName, 'api_fields');

        //$many_many_extraFields = $dataObject->many_many_extraFields();
        $many_many_extraFields = $dataObject->stat('many_many_extraFields');

        // setup ID (not included in $db!!)
        $serializedColumnName = $this->serializeColumnName('ID');
        $formattedDataObjectMap[$serializedColumnName] = $dataObject->getField('ID');

        // iterate over simple DB fields
        if (!$db) {
            $db = array();
        }

        foreach ($db as $columnName => $fieldClassName) {
            // Check whether this field has been specified as allowed via api_fields
            if (!empty($apiFields) && !in_array($columnName, $apiFields)) {
                continue;
            }

            $serializedColumnName = $this->serializeColumnName($columnName);
            $formattedDataObjectMap[$serializedColumnName] = $dataObject->getField($columnName);
        }

        // iterate over has_one relations
        if (!$has_one) {
            $has_one = array();
        }
        foreach ($has_one as $columnName => $fieldClassName) {
            // Skip if api_fields is set for the parent, and this column is not in it
            if (!empty($apiFields) && !in_array($fieldClassName, $apiFields)) {
                continue;
            }

            $serializedColumnName = $this->serializeColumnName($columnName);

            // convert foreign ID to integer
            $relationID = intVal($dataObject->{$columnName.'ID'});
            // skip empty relations
            if ($relationID === 0) {
                continue;
            }

            // check if this should be embedded
            if ($this->isEmbeddable($dataObject->ClassName, $columnName)) {
                // get the relation's record ready to embed
                $embedData = $this->getEmbedData($dataObject, $columnName);
                // embed the data if any
                if ($embedData !== null) {
                    $formattedDataObjectMap[$serializedColumnName] = $embedData;
                }
            } else {
                // save foreign ID
                $formattedDataObjectMap[$serializedColumnName] = $relationID;
            }
        }

        // combine defined '_many' relations into 1 array
        $many_relations = array();
        if (is_array($has_many)) {
            $many_relations = array_merge($many_relations, $has_many);
        }
        if (is_array($many_many)) {
            $many_relations = array_merge($many_relations, $many_many);
        }
        if (is_array($belongs_many_many)) {
            $many_relations = array_merge($many_relations, $belongs_many_many);
        }

        // iterate '_many' relations
        foreach ($many_relations as $relationName => $relationClassname) {
            // Skip if api_fields is set for the parent, and this column is not in it
            if (!empty($apiFields) && !in_array($relationName, $apiFields)) {
                continue;
            }

            //get the DataList for this realtion's name
            $dataList = $dataObject->{$relationName}();

            //if there actually are objects in the relation
            if ($dataList->count()) {
                // check if this relation should be embedded
                if ($this->isEmbeddable($dataObject->ClassName, $relationName)) {
                    // get the relation's record(s) ready to embed
                    $embedData = $this->getEmbedData($dataObject, $relationName);
                    // embed the data if any
                    if ($embedData !== null) {
                        $serializedColumnName = $this->serializeColumnName($relationName);
                        $formattedDataObjectMap[$serializedColumnName] = $embedData;
                    }
                } else {
                    // set column value to ID list
                    $idList = $dataList->map('ID', 'ID')->keys();

                    $serializedColumnName = $this->serializeColumnName($relationName);
                    $formattedDataObjectMap[$serializedColumnName] = $idList;
                }
            }
        }

        if ($many_many_extraFields) {
            $extraFieldsData = array();

            // loop through extra fields config
            foreach ($many_many_extraFields as $relation => $fields) {
                $manyManyDataObjects = $dataObject->$relation();
                $relationData = array();

                // get the extra data for each object in the relation
                foreach ($manyManyDataObjects as $manyManyDataObject) {
                    $data = $manyManyDataObjects->getExtraData($relation, $manyManyDataObject->ID);

                    // format data
                    foreach ($data as $key => $value) {
                        // clear empty data
                        if (!$value) {
                            unset($data[$key]);
                            continue;
                        }

                        $newKey = $this->serializeColumnName($key);
                        if ($newKey != $key) {
                            unset($data[$key]);
                            $data[$newKey] = $value;
                        }
                    }

                    // store if there is any real data
                    if ($data) {
                        $relationData[$manyManyDataObject->ID] = $data;
                    }
                }

                // add individual DO extra data to the relation's extra data
                if ($relationData) {
                    $key = $this->serializeColumnName($relation);
                    $extraFieldsData[$key] = $relationData;
                }
            }

            // save the extrafields data
            if ($extraFieldsData) {
                $key = $this->serializeColumnName('ManyManyExtraFields');
                $formattedDataObjectMap[$key] = $extraFieldsData;
            }
        }

        if (method_exists($dataObject, 'onAfterSerialize')) {
            $dataObject->onAfterSerialize($formattedDataObjectMap);
        }
        $dataObject->extend('onAfterSerialize', $formattedDataObjectMap);

        return $formattedDataObjectMap;
    }

    /**
     * Format a DataList into a formatted array ready to be turned into JSON
     *
     * @param  DataList $dataList The DataList to format
     * @return array              The formatted array representation of the DataList
     */
    protected function formatDataList(DataList $dataList)
    {
        $formattedDataListMap = array();

        foreach ($dataList as $dataObject) {
            $formattedDataObjectMap = $this->formatDataObject($dataObject);
            if ($formattedDataObjectMap) {
                array_push($formattedDataListMap, $formattedDataObjectMap);
            }
        }

        return $formattedDataListMap;
    }


    /**
     * Format a SilverStripe ClassName or Field name to be used by the client API
     *
     * @param  string $name ClassName of DBField name
     * @return string       Formatted name
     */
    public function formatName($name)
    {
        return $name;
    }


    /**
     * Format a DB Column name or Field name to be used by the client API
     *
     * @param  string $name Field name
     * @return string       Formatted name
     */
    protected function serializeColumnName($name)
    {
        return $name;
    }


    /**
     * Returns a DataObject relation's data formatted and ready to embed.
     *
     * @param  DataObject $record       The DataObject to get the data from
     * @param  string     $relationName The name of the relation
     * @return array|null               Formatted DataObject or RelationList ready to embed or null if nothing to embed
     */
    protected function getEmbedData(DataObject $record, $relationName)
    {
        if ($record->hasMethod($relationName)) {
            $relationData = $record->$relationName();
            if ($relationData instanceof RelationList) {
                return $this->formatDataList($relationData);
            } else {
                return $this->formatDataObject($relationData);
            }
        }

        return null;
    }


    /**
     * Checks if a speicific model's relation should have its records embedded.
     *
     * @param  string  $model    Model's classname
     * @param  string  $relation Relation name
     * @return boolean           Trus if the relation should be embedded
     */
    protected function isEmbeddable($model, $relation)
    {
        if (array_key_exists($model, $this->embeddedRecords)) {
            return is_array($this->embeddedRecords[$model]) && in_array($relation, $this->embeddedRecords[$model]);
        }

        return false;
    }
}
