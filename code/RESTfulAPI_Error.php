<?php
/**
 * Stores an API errors. And a library of static methods.
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Error
 */
class RESTfulAPI_Error
{

	/**
	 * Error HTTP status code
	 * 
	 * @var integer
	 */
	public $code;


	/**
	 * Error message
	 * 
	 * @var string
	 */
	public $message;


	/**
	 * Error response body
	 * to be serialized
	 * 
	 * @var mixed
	 */
	public $body;


	/**
	 * Creates the error object and sets properties
	 * 
	 * @param integer $code    HTTP status code
	 * @param string  $message Error message
	 */
	function __construct($code, $message, $body = null)
	{
    $this->code    = $code;
    $this->message = $message;

    if ( $body !== null )
    {
      $this->body = $body;
    }
    else{
      $this->body = array(
        'code'    => $code,
        'message' => $message
      );
    }
	}


	/**
	 * Check for the latest JSON parsing error
	 * and return the message if any
	 *
	 * More available for PHP >= 5.3.3
	 * http://www.php.net/manual/en/function.json-last-error.php
	 * 
	 * @return false|string Returns false if no error or a string with the error detail.
	 */
	public static function get_json_error()
	{
		$error = 'JSON - ';

		switch (json_last_error())
		{
			case JSON_ERROR_NONE:
				$error = false;
				break;

			case JSON_ERROR_DEPTH:
				$error .= 'The maximum stack depth has been exceeded.';
				break;

			case JSON_ERROR_STATE_MISMATCH:
				$error .= 'Invalid or malformed JSON.';
				break;

			case JSON_ERROR_CTRL_CHAR:
				$error .= 'Control character error, possibly incorrectly encoded.';
				break;

			case JSON_ERROR_SYNTAX:
				$error .= 'Syntax error.';
				break;

			default:
				$error .= 'Unknown error ('.json_last_error().').';
				break;
		}

		return $error;
	}
}