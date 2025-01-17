<?php

require_once 'api/models/UserModel.php';

/**
 * Validator class used for user input validations
 */
class Validator {
		
	/**
	 * Checks if the passed value meets matches the rule's requirements
	 * @param string $field
	 * @param string $value
	 * @param array $rules
	 * @param array $params
	 * @return boolean
	 */
	public static function checkParam($field, $value, $rules, $params) {
		$result = true;
		
		foreach ($rules as $rule) {
			$rule = trim($rule);

			#required rule
			#also checks for submitted files
			if ($rule == 'required') {
				if ((!isset($value) || mb_strlen($value) === 0) && (!isset($_FILES[$field]) || $_FILES[$field]['error'] === 4)) {
					return array('field' => $field, 'error_code' => ErrorCodes::EMPTY_FIELD);
				}
			}
			#optional rule (used together with other rules. if the field is not set all other rules will be skipped. however if the field is set the rest of the validations will be run)
			#it also checks for submitted files
			#example: optional, max-10 (the field is not required, but if its set it must be less than 10 characters long) 
			elseif ($rule == 'optional') {
				if ((!isset($value) || mb_strlen($value) === 0) && (!isset($_FILES[$field]) || $_FILES[$field]['error'] === 4)) {
					break;
				}
			}
			#required[] rule (at least one of the fields must be set)
			elseif (preg_match('/required\[(.+?)\]/i', $rule, $matches)) {
				$valid = false;
				$list = $matches[1];
				$list = explode(',', $list);

				foreach ($list as $param) {
					if (isset($params[$param]) && mb_strlen($params[$param]) > 0) {
						$valid = true;
						break;
					}
				}

				if (!$valid) {
					return array('field' => $field, 'error_code' => ErrorCodes::AT_LEAST_ONE_FIELD_REQUIRED);
				}
			}
			#integer rule
			elseif ($rule == 'int') {
				if (!ctype_digit($value)) {
					return array('field' => $field, 'error_code' => ErrorCodes::INVALID_INT);
				}
			}
			#date rule
			elseif ($rule == 'date') {
				if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
					return array('field' => $field, 'error_code' => ErrorCodes::INVALID_DATE);
				}
			}
			#datetime rule
			elseif ($rule == 'datetime') {
				if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}\:\d{2}:\d{2}$/', $value)) {
					return array('field' => $field, 'error_code' => ErrorCodes::INVALID_DATE);
				}
			}
			#max-characters rule
			elseif (preg_match('/max-(\d+)/i', $rule, $matches)) {
				$max_length = $matches[1];
				if (mb_strlen($value) > $max_length) {
					return array('field' => $field, 'error_code' => ErrorCodes::EXCEEDS_CHARACTERS_ . $max_length);
				}
			}
			#min-characters rule
			elseif (preg_match('/min-(\d+)/i', $rule, $matches)) {
				$min_length = $matches[1];
				if (mb_strlen($value) < $min_length) {
					return array('field' => $field, 'error_code' => ErrorCodes::BELOW_CHARACTERS_ . $min_length);
				}
			}
			#unique[] field rule
			elseif (preg_match('/unique\[(.+?)\]/i', $rule, $matches)) {
				$unique_field = $matches[1];

				$user_model = new UserModel();
				$result = $user_model->isUnique($unique_field, $value);

				if ($user_model->isUnique($unique_field, $value) === false) {
					return array('field' => $field, 'error_code' => $unique_field . ErrorCodes::_IN_USE);
				}
			}
			#valid-email rule
			elseif ($rule == 'valid-email') {
				if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
					return array('field' => $field, 'error_code' => ErrorCodes::INVALID_EMAIL);
				}
			} elseif ($rule == 'valid-url') {
				if (!filter_var($value, FILTER_VALIDATE_URL)) {
					return array('field' => $field, 'error_code' => ErrorCodes::INVALID_URL);
				}
			}
			#strong-passworld rule (at least 6 characters with 1 or more numbers)
			elseif ($rule == 'strong-password') {
				if (mb_strlen($value) < 6 || preg_match('/\d+/', $value) == false || preg_match('/[a-z]+/i', $value) == false) {
					return array('field' => $field, 'error_code' => ErrorCodes::WEAK_PASSWORD);
				}
			}
			#valid-characters rule (can contain only letters, digits, underscores and dashes)
			elseif ($rule == 'valid-characters') {
				if (preg_match('/[^\w\d_-]/', $value)) {
					return array('field' => $field, 'error_code' => ErrorCodes::INVALID_CHARACTERS);
				}
			}
			#checks if the two fields are equal
			elseif (preg_match('/matches\[(.+?)\]/i', $rule, $matches)) {
				$match_field = $matches[1];

				if ((isset($params[$match_field]) && $value !== $params[$match_field])) {
					return array('field' => $field, 'error_code' => ErrorCodes::NO_MATCH);
				}
			}
			#in[] rule
			elseif (preg_match('/in\[(.+?)\]/i', $rule, $matches)) {
				$list = $matches[1];
				$list = explode(',', $list);

				if (in_array($value, $list) === false) {
					return array('field' => $field, 'error_code' => ErrorCodes::NOT_IN_LIST);
				}
			}
			#matches-captcha rule
			elseif ($rule == 'matches-captcha') {
				if (strtolower($value) !== strtolower($_SESSION['captcha']['code'])) {
					return array('field' => $field, 'error_code' => ErrorCodes::INVALID_CAPTCHA);
				}
			}
			#max-file-size-kilobytes rule (checks if the uploaded file exceeds the max file size specified in kilobytes)
			elseif (preg_match('/max-file-size-(\d+)/i', $rule, $matches)) {
				$max_size = $matches[1];
				
				$post_size = (int) $_SERVER['CONTENT_LENGTH'];
				if ((($post_size / 1024) > $max_size + 800) || ($_FILES[$field]['size'] / 1024) > $max_size) {
					return array('field' => $field, 'error_code' => ErrorCodes::EXCEEDS_MAX_FILE_SIZE);
				}
				
			}
			#valid-file-extensions[] rule (the uploaded file must match one of the provided extensions)
			elseif (preg_match('/valid-file-extensions\[(.+?)\]/i', $rule, $matches)) {
				$list = $matches[1];
				$list = explode(',', $list);
				
				preg_match('/\.([^\.]+?)$/', $_FILES[$field]['name'], $matches);
				$extension = strtolower($matches[1]);
				
				if (in_array($extension, $list) === false) {
					return array('field' => $field, 'error_code' => ErrorCodes::INVALID_FILE_EXTENSION);
				}
				
			}
		}

		return $result;
	}

}
