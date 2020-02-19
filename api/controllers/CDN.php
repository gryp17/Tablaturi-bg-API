<?php

class CDN extends Controller {

	public function __construct() {

		/**
		 * List of required parameters and permissions for each API endpoint
		 * also indicates the parameter type
		 */
		$this->endpoints = array(
			'file' => array(
				'required_role' => self::PUBLIC_ACCESS,
				'params' => array(
					'type' => 'in[downloads,avatars,articles]',
					'file' => 'required'
				)
			)
		);

		#request params
		$this->params = $this->checkRequest();
	}

	public function index() {
		
	}

	/**
	 * Returns the requested static resource
	 */
	public function file() {
		$folders_map = array(
			'downloads' => Config::DOWNLOADS_DIR,
			'articles' => Config::ARTICLES_DIR,
			'avatars' => Config::AVATARS_DIR
		);

		$folder = $folders_map[$this->params['type']];
		$file = $this->params['file'];
		$path = $folder.$file;

		if (file_exists($path)) {
			$content_type = mime_content_type($path);
			$content = file_get_contents($path);
			$this->sendFileResponse($content_type, $file, $content);
		} else {
			$this->sendResponse(0, ErrorCodes::NOT_FOUND);
		}
	}

}
