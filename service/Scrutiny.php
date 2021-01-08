<?php

namespace Elyzin\Service;

use Helper;
use Config;
use File;
use \Lang;
/*
Sample Rule:
$rules = [
	[
		"name" => "email", // Required, identifier
		"required" => true, // Define if mandatory, if not defined empty values will pass
		"sanitize" => "upper", // Sanitizes user input as per defined criteria, if not false. Falls back to basic sanitization
		"pattern" => "email", // "rx:[.*?]"
		"min" => "cf:settings|minunamelen"
	]
]
*/

class Scrutiny
{
	private static $result = ['valid' => [], 'bypass' => [], 'invalid' => [], 'error' => []];
	private static $regex = [
		"indpan" => "[A-Z]{3}[PCHFATBLJG]{1}[A-Z]{1}[0-9]{4}[A-Z]{1}"
	];

	public static function validate($data = array(), $rules = array())
	{
		if (is_string($rules)){ // Preset Rules
			$rules = File::read(Config::path('presets')."validate.".$rules.".php");
		}

		if (empty($data)) {
			if (defined('POST')) {
				$data = $_POST;
				if (!empty($_FILES)) {
					array_merge($data, $_FILES); // RECHECK DEPTH
				}
			}
		}
		self::$result['bypass'] = $data;

		do {
			$rule = array_pop($rules);
			if (array_key_exists($rule['name'], self::$result['bypass'])) { // Consider only available inputs, ignore non-applicable rules
				$value = self::$result['bypass'][$rule['name']];
				unset(self::$result['bypass'][$rule['name']]);

				if (!array_key_exists("sanitize", $rule)) $rule["sanitize"] = true; // If not defined perform basic sanitization
				if ($rule["sanitize"] !== false) { // Sanitization required
					$value = self::sanitize($value, $rule["sanitize"]);
				}

				$process = false;
				if (array_key_exists('required', $rule) && $rule['required'] === true) {
					if (empty($value)) {
						$feedback = "required"; // Required field, set error
					} else {
						$process = true;
					}
				} else if (empty($value)) {
					$feedback = ""; // Empty input for non-required field. Pass
				} else {
					$process = true;
				}

				if ($process) {
					$feedback = (is_callable([get_called_class(), "validate_".$rule["pattern"]])) ? $rule["pattern"] : "regex";
					$feedback = self::{"validate_".$feedback}($value, $rule);
				}
				self::conclude($rule['name'], $value, $feedback);
			}
		} while (!empty($rules));

		return self::$result;
	}

	private static function validate_email($value, $rule)
	{
		if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
			// Check Blacklisted
			// Check Database existance

			return; // Validation passed
		  }
		  return "invalid";
	}

	private static function validate_regex($value, $rule)
	{
		if(array_key_exists($rule["pattern"], self::$regex)){
			$pattern =  self::$regex[$rule["pattern"]];
		} else if (substr( $rule["pattern"], 0, 3 ) === "rx:"){ // Defined regex pattern
			$pattern = ltrim($rule["pattern"], "rx:");
		} else {
			trigger_error("Invalid validation pattern for ".$rule['name'], E_USER_ERROR);
			die();
		}

		if(!preg_match('/^'.$pattern.'$/iD', $value)) {
			return "invalid";
		}
		return;
	}

	private static function validate_num($value, $rule)
	{
		if (is_numeric($value)) {
			// Value is numeric, perform other related conditions
			if (isset($rule['min']) && is_numeric($rule['min'])) { // Mimimum is set
				if (isset($rule['max']) && is_numeric($rule['max']) && ($rule['max'] >= $rule['min'])) { // Both set

				} else {
				}
			} else if (isset($rule['max']) && is_numeric($rule['max'])) { // Only Maximum set

			}
			return; // Validation passed
		}
		return "notnum";
	}

	/**
	 * Sanitizes the input value received from user
	 * @param mixed $value The input value received
	 * @param mixed $rule The defined rules based on which sanitization will be done, defaults to basic sanitization
	 * @return mixed Sanitized value
	 */
	public static function sanitize($value, $rule = true)
	{
		if (empty($value)) return "";
		$value = htmlspecialchars(strip_tags(trim($value))); // Basic sanitization
		//$value = ​filter_var(trim($value), FILTER_SANITIZE_STRING); // Basic sanitization

		if (!is_bool($rule)) {
			if (is_string($rule)) $rule = explode(",", $rule);

			if (in_array('upper', $rule)) $value = strtoupper($value);
			if (in_array('space', $rule)) $value = preg_replace('/\s+/', '', $value);
			if (in_array('num', $rule)) $value = preg_replace('/\d+/', '', $value);
			if (in_array('nonsym', $rule)) $value = preg_replace('/\w+/', '', $value);
		}

		return $value;
	}

	/**
	 * Fetch database / config depended values as per expression and return real values
	 * @param string $name // Value to fetch for
	 * @param mixed $express // Expression of the value, array not expected
	 * @return mixed // Real value
	 */
	private static function fetchval($name, $express)
	{
		if(is_array($express)) return $express; // Return as is if the expression is array

		$expression_invalid = false;
		$express = explode("|", $express);

		switch ($name) {
			case 'range': // Numeric couple expected
				if(count($express )== 2 && is_numeric($express[0]) && is_numeric($express[1])) // Proper value expression
				return array_map("intval", $express);
				break;
			
			case 'min':
			case 'max':
				if(count($express )== 1 && is_numeric($express[0])) // Proper value expression
				return (int)$express[0];
				break;
			
			case 'oneof':
				if(count($express )== 1) // CSV
				return explode(",", $express[0]);
				break;

			
			default:
				# Invalid name...
				break;
		}

		if(count($express ) == 2 && (substr( $express[0], 0, 3 ) === "cf:")){ // Config dependent
			self::fetchval($name, Config::fetch(ltrim($express[0], "cf:"), $express[1]));
		} else {
			$expression_invalid = true;
		}

		if($expression_invalid) trigger_error("Invalid " . $name . " expression.", E_USER_ERROR);
	}

	/**
	 * After validation, assigns the input to applicable return group alongwith message in case of an error
	 * @param string $name Name (raw) of the input
	 * @param mixed $value Value of the input for validation
	 * @param string $phrase The error phrase. This should be empty if the validation suceedes / input is valid
	 * @return void
	 */
	private static function conclude($name, $value, $phrase = "")
	{
		if(!empty($phrase)) {
			self::$result['invalid'][$name] = $value;
			self::$result['error'][] = Lang("scrutiny_".$phrase, [Lang("label_" . $name)]);
		} else {
			self::$result['valid'][$name] = $value;
		}
	}
}
