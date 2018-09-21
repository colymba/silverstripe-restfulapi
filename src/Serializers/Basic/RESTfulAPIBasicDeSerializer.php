<?php

namespace colymba\RESTfulAPI\Serializers\Basic;

use colymba\RESTfulAPI\RESTfulAPIError;
use colymba\RESTfulAPI\Serializers\RESTfulAPIDeSerializer;
use SilverStripe\Core\ClassInfo;

/**
 * Basic RESTfulAPI Model DeSerializer
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
class RESTfulAPIBasicDeSerializer implements RESTfulAPIDeSerializer
{

    /**
     * Convert client JSON data to an array of data
     * ready to be consumed by SilverStripe
     *
     * Expects payload to be formatted:
     * {
     *   "FieldName": "Field value",
     *   "Relations": [1]
     * }
     *
     * @param  string        $data   JSON to be converted to data ready to be consumed by SilverStripe
     * @return array|false           Formatted array representation of the JSON data or false if failed
     */
    public function deserialize($json)
    {
        $data = json_decode($json, true);

        //catch JSON parsing error
        $error = RESTfulAPIError::get_json_error();
        if ($error !== false) {
            return new RESTfulAPIError(400, $error);
        }

        if ($data) {
            $data = $this->unformatPayloadData($data);
        } else {
            return new RESTfulAPIError(400,
                "No data received."
            );
        }

        return $data;
    }

    /**
     * Process payload data from client
     * and unformats columns/values recursively
     *
     * @param  array  $data Payload data (decoded JSON)
     * @return array        Paylaod data with all keys/values unformatted
     */
    protected function unformatPayloadData(array $data)
    {
        $unformattedData = array();

        foreach ($data as $key => $value) {
            $newKey = $this->deserializeColumnName($key);

            if (is_array($value)) {
                $newValue = $this->unformatPayloadData($value);
            } else {
                $newValue = $value;
            }

            $unformattedData[$newKey] = $newValue;
        }

        return $unformattedData;
    }

    /**
     * Format a ClassName or Field name sent by client API
     * to be used by SilverStripe
     *
     * @param  string $name ClassName of Field name
     * @return string       Formatted name
     */
    public function unformatName($name)
    {
        $class = ucfirst($name);
        if (ClassInfo::exists($class)) {
            return $class;
        } else {
            return $name;
        }
    }

    /**
     * Format a DB Column name or Field name
     * sent from client API to be used by SilverStripe
     *
     * @param  string $name Field name
     * @return string       Formatted name
     */
    private function deserializeColumnName($name)
    {
        return $name;
    }
}
