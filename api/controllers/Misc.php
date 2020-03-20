<?php

class Misc extends Controller {

	public function __construct() {

		/**
		 * List of required parameters and permissions for each API endpoint
		 * also indicates the parameter type
		 */
		$this->endpoints = array(
			'generateCaptcha' => array(
				'required_role' => self::PUBLIC_ACCESS
			),
			'contactUs' => array(
				'required_role' => self::PUBLIC_ACCESS,
				'params' => array(
					'username' => array('min-3', 'max-20'),
					'email' => 'valid-email',
					'message' => 'required',
					'captcha' => 'matches-captcha'
				)
			),
			'getErrorCodes' => array(
				'required_role' => self::PUBLIC_ACCESS
			)
		);

		#request params
		$this->params = $this->checkRequest();
	}

	public function index() {
		
	}

	/**
	 * Generates new captcha image
	 */
	public function generateCaptcha() {
		#captcha code
		$_SESSION['captcha'] = simplePHPCaptcha();
		sendCaptcha();
	}

	/**
	 * Sends contact us email 
	 */
	public function contactUs() {
		if (Utils::sendContactUsEmail($this->params['username'], $this->params['email'], $this->params['message'])) {
			$this->sendResponse(1, array('success' => true));
		} else {
			$this->sendResponse(0, ErrorCodes::EMAIL_ERROR);
		}
	}

	/**
	 * Returns the error codes mapping
	 */
	public function getErrorCodes() {
		$codes = ErrorCodes::getConstants();
		$this->sendResponse(1, $codes);
	}

}
