<?php

namespace colymba\RESTfulAPI\QueryHandlers;

use colymba\RESTfulAPI\QueryHandlers\RESTfulAPIQueryHandler;
use colymba\RESTfulAPI\RESTfulAPI;
use colymba\RESTfulAPI\RESTfulAPIError;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ValidationException;

/**
 * Default RESTfulAPI Query handler
 * handles models request etc...
 *
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 *
 * @package RESTfulAPI
 * @subpackage QueryHandler
 */
class RESTfulAPIDefaultQueryHandler implements RESTfulAPIQueryHandler
{

    /**
     * Current deSerializer instance
     *
     * @var RESTfulAPIDeSerializer
     */
    public $deSerializer;

    /**
     * Injector dependencies
     * Override in configuration to use your custom classes
     *
     * @var array
     * @config
     */
    private static $dependencies = array(
        'deSerializer' => '%$colymba\RESTfulAPI\Serializers\Basic\RESTfulAPIBasicDeSerializer',
    );

    /**
     * Search Filter Modifiers Separator used in the query var
     * i.e. ?column__EndsWith=value
     *
     * @var string
     * @config
     */
    private static $searchFilterModifiersSeparator = '__';

    /**
     * Query vars to skip (uppercased)
     *
     * @var array
     * @config
     */
    private static $skipedQueryParameters = array('URL', 'FLUSH', 'FLUSHTOKEN', 'TOKEN');

    /**
     * Set a maximum numbers of records returned by the API.
     * Only affectects "GET All". Useful to avoid returning millions of records at once.
     *
     * Set to -1 to disable.
     *
     * @var integer
     * @config
     */
    private static $max_records_limit = 100;

    /**
     * Map of model references from URL to class names for exposed models
     *
     * @var array
     * @config
     */
    private static $models = [];

    /**
     * Stores the currently requested data
     *
     * @var array
     */
    public $requestedData = array(
        'model' => null,
        'id' => null,
        'params' => null,
    );

    /**
     * Return current RESTfulAPI DeSerializer instance
     *
     * @return RESTfulAPIDeSerializer DeSerializer instance
     */
    public function getdeSerializer()
    {
        return $this->deSerializer;
    }

    /**
     * All requests pass through here and are redirected depending on HTTP verb and params
     *
     * @param  HTTPRequest        $request    HTTP request
     * @return DataObjec|DataList                DataObject/DataList result or stdClass on error
     */
    public function handleQuery(HTTPRequest $request)
    {
        //get requested model(s) details
        $model = $request->param('ModelReference');

        $modelMap = Config::inst()->get(self::class, 'models');

        if (array_key_exists($model, $modelMap)) {
            $model = $modelMap[$model];
        }

        $id = $request->param('ID');
        $response = false;
        $queryParams = $this->parseQueryParameters($request->getVars());

        //validate Model name + store
        if ($model) {
            $model = $this->deSerializer->unformatName($model);
            if (!class_exists($model)) {
                return new RESTfulAPIError(400,
                    "Model does not exist. Received '$model'."
                );
            } else {
                //store requested model data and query data
                $this->requestedData['model'] = $model;
            }
        } else {
            //if model missing, stop + return blank object
            return new RESTfulAPIError(400,
                "Missing Model parameter."
            );
        }

        //check API access rules on model
        if (!RESTfulAPI::api_access_control($model, $request->httpMethod())) {
            return new RESTfulAPIError(403,
                "API access denied."
            );
        }

        //validate ID + store
        if (($request->isPUT() || $request->isDELETE()) && !is_numeric($id)) {
            return new RESTfulAPIError(400,
                "Invalid or missing ID. Received '$id'."
            );
        } elseif ($id !== null && !is_numeric($id)) {
            return new RESTfulAPIError(400,
                "Invalid ID. Received '$id'."
            );
        } else {
            $this->requestedData['id'] = $id;
        }

        //store query parameters
        if ($queryParams) {
            $this->requestedData['params'] = $queryParams;
        }

        //map HTTP word to module method
        switch ($request->httpMethod()) {
            case 'GET':
                return $this->findModel($model, $id, $queryParams, $request);
                break;
            case 'POST':
                return $this->createModel($model, $request);
                break;
            case 'PUT':
                return $this->updateModel($model, $id, $request);
                break;
            case 'DELETE':
                return $this->deleteModel($model, $id, $request);
                break;
            default:
                return new RESTfulAPIError(403,
                    "HTTP method mismatch."
                );
                break;
        }
    }

    /**
     * Parse the query parameters to appropriate Column, Value, Search Filter Modifiers
     * array(
     *   array(
     *     'Column'   => ColumnName,
     *     'Value'    => ColumnValue,
     *     'Modifier' => ModifierType
     *   )
     * )
     *
     * @param  array  $params raw GET vars array
     * @return array          formatted query parameters
     */
    public function parseQueryParameters(array $params)
    {
        $parsedParams = array();
        $searchFilterModifiersSeparator = Config::inst()->get(self::class, 'searchFilterModifiersSeparator');

        foreach ($params as $key__mod => $value) {
            // skip url, flush, flushtoken
            if (in_array(strtoupper($key__mod), Config::inst()->get(self::class, 'skipedQueryParameters'))) {
                continue;
            }

            $param = array();

            $key__mod = explode(
                $searchFilterModifiersSeparator,
                $key__mod
            );

            $param['Column'] = $this->deSerializer->unformatName($key__mod[0]);

            $param['Value'] = $value;

            if (isset($key__mod[1])) {
                $param['Modifier'] = $key__mod[1];
            } else {
                $param['Modifier'] = null;
            }

            array_push($parsedParams, $param);
        }

        return $parsedParams;
    }

    /**
     * Finds 1 or more objects of class $model
     *
     * Handles column modifiers: :StartsWith, :EndsWith,
     * :PartialMatch, :GreaterThan, :LessThan, :Negation
     * and query modifiers: sort, rand, limit
     *
     * @param  string                 $model          Model(s) class to find
     * @param  boolean|integr         $id             The ID of the model to find or false
     * @param  array                  $queryParams    Query parameters and modifiers
     * @param  HTTPRequest         $request        The original HTTP request
     * @return DataObject|DataList                    Result of the search (note: DataList can be empty)
     */
    public function findModel($model, $id = false, $queryParams, HTTPRequest $request)
    {
        if ($id) {
            $return = DataObject::get_by_id($model, $id);

            if (!$return) {
                return new RESTfulAPIError(404,
                    "Model $id of $model not found."
                );
            } elseif (!RESTfulAPI::api_access_control($return, $request->httpMethod())) {
                return new RESTfulAPIError(403,
                    "API access denied."
                );
            }
        } else {
            $return = DataList::create($model);
            $singletonModel = singleton($model);

            if (count($queryParams) > 0) {
                foreach ($queryParams as $param) {
                    if ($param['Column'] && $singletonModel->hasDatabaseField($param['Column'])) {
                        // handle sorting by column
                        if ($param['Modifier'] === 'sort') {
                            $return = $return->sort(array(
                                $param['Column'] => $param['Value'],
                            ));
                        }
                        // normal modifiers / search filters
                        elseif ($param['Modifier']) {
                            $return = $return->filter(array(
                                $param['Column'] . ':' . $param['Modifier'] => $param['Value'],
                            ));
                        }
                        // no modifier / search filter
                        else {
                            $return = $return->filter(array(
                                $param['Column'] => $param['Value'],
                            ));
                        }
                    } else {
                        // random
                        if ($param['Modifier'] === 'rand') {
                            // rand + seed
                            if ($param['Value']) {
                                $return = $return->sort('RAND(' . $param['Value'] . ')');
                            }
                            // rand only >> FIX: gen seed to avoid random result on relations
                            else {
                                $return = $return->sort('RAND(' . time() . ')');
                            }
                        }
                        // limits
                        elseif ($param['Modifier'] === 'limit') {
                            // range + offset
                            if (is_array($param['Value'])) {
                                $return = $return->limit($param['Value'][0], $param['Value'][1]);
                            }
                            // range only
                            else {
                                $return = $return->limit($param['Value']);
                            }
                        }
                    }
                }
            }

            //sets default limit if none given
            $limits = $return->dataQuery()->query()->getLimit();
            $limitConfig = Config::inst()->get(self::class, 'max_records_limit');

            if (is_array($limits) && !array_key_exists('limit', $limits) && $limitConfig >= 0) {
                $return = $return->limit($limitConfig);
            }
        }

        return $return;
    }

    /**
     * Create object of class $model
     *
     * @param  string         $model
     * @param  HTTPRequest $request
     * @return DataObject
     */
    public function createModel($model, HTTPRequest $request)
    {
        if (!RESTfulAPI::api_access_control($model, $request->httpMethod())) {
            return new RESTfulAPIError(403,
                "API access denied."
            );
        }

        $newModel = Injector::inst()->create($model);

        return $this->updateModel($newModel, $newModel->ID, $request);
    }

    /**
     * Update databse record or $model
     *
     * @param String|DataObject $model the model or class to update
     * @param Integer $id The ID of the model to update
     * @param HTTPRequest the original request
     *
     * @return DataObject The updated model
     */
    public function updateModel($model, $id, $request)
    {
        if (is_string($model)) {
            $model = DataObject::get_by_id($model, $id);
        }

        if (!$model) {
            return new RESTfulAPIError(404,
                "Record not found."
            );
        }

        if (!RESTfulAPI::api_access_control($model, $request->httpMethod())) {
            return new RESTfulAPIError(403,
                "API access denied."
            );
        }

        $rawJson = $request->getBody();

        // Before deserialize hook
        if (method_exists($model, 'onBeforeDeserialize')) {
            $model->onBeforeDeserialize($rawJson);
        }
        $model->extend('onBeforeDeserialize', $rawJson);

        $payload = $this->deSerializer->deserialize($rawJson);
        if ($payload instanceof RESTfulAPIError) {
            return $payload;
        }

        // After deserialize hook
        if (method_exists($model, 'onAfterDeserialize')) {
            $model->onAfterDeserialize($payload);
        }
        $model->extend('onAfterDeserialize', $payload);

        if ($model && $payload) {
            $has_one = Config::inst()->get(get_class($model), 'has_one');
            $has_many = Config::inst()->get(get_class($model), 'has_many');
            $many_many = Config::inst()->get(get_class($model), 'many_many');
            $belongs_many_many = Config::inst()->get(get_class($model), 'belongs_many_many');

            $many_many_extraFields = array();

            if (isset($payload['ManyManyExtraFields'])) {
                $many_many_extraFields = $payload['ManyManyExtraFields'];
                unset($payload['ManyManyExtraFields']);
            }

            $hasChanges = false;
            $hasRelationChanges = false;

            foreach ($payload as $attribute => $value) {
                if (!is_array($value)) {
                    if (is_array($has_one) && array_key_exists($attribute, $has_one)) {
                        $relation = $attribute . 'ID';
                        $model->$relation = $value;
                        $hasChanges = true;
                    } elseif ($model->{$attribute} != $value) {
                        $model->{$attribute} = $value;
                        $hasChanges = true;
                    }
                } else {
                    //has_many, many_many or $belong_many_many
                    if ((is_array($has_many) && array_key_exists($attribute, $has_many))
                        || (is_array($many_many) && array_key_exists($attribute, $many_many))
                        || (is_array($belongs_many_many) && array_key_exists($attribute, $belongs_many_many))
                    ) {
                        $hasRelationChanges = true;
                        $ssList = $model->{$attribute}();
                        $ssList->removeAll(); //reset list
                        foreach ($value as $id) {
                            // check if there is extraFields
                            if (array_key_exists($attribute, $many_many_extraFields)) {
                                if (isset($many_many_extraFields[$attribute][$id])) {
                                    $ssList->add($id, $many_many_extraFields[$attribute][$id]);
                                    continue;
                                }
                            }

                            $ssList->add($id);
                        }
                    }
                }
            }

            if ($hasChanges || !$model->ID) {
                try {
                    $id = $model->write(false, false, false, $hasRelationChanges);
                } catch (ValidationException $exception) {
                    $error = $exception->getResult();
                    return new RESTfulAPIError(400,
                        $error->message()
                    );
                }

                if (!$id) {
                    return new RESTfulAPIError(500,
                        "Error writting data."
                    );
                } else {
                    return DataObject::get_by_id($model->ClassName, $id);
                }
            } else {
                return $model;
            }
        } else {
            return new RESTfulAPIError(400,
                "Missing model or payload."
            );
        }
    }

    /**
     * Delete object of Class $model and ID $id
     *
     * @todo  Respond with a 204 status message on success?
     *
     * @param  string          $model     Model class
     * @param  integer                  $id        Model ID
     * @param  HTTPRequest  $request   Model ID
     * @return NULL|array                 NULL if successful or array with error detail
     */
    public function deleteModel($model, $id, HTTPRequest $request)
    {
        if ($id) {
            $object = DataObject::get_by_id($model, $id);

            if ($object) {
                if (!RESTfulAPI::api_access_control($object, $request->httpMethod())) {
                    return new RESTfulAPIError(403,
                        "API access denied."
                    );
                }

                $object->delete();
            } else {
                return new RESTfulAPIError(404,
                    "Record not found."
                );
            }
        } else {
            //shouldn't happen but just in case
            return new RESTfulAPIError(400,
                "Invalid or missing ID. Received '$id'."
            );
        }

        return null;
    }
}
