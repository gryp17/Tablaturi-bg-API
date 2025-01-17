<?php

class BackingTrack extends Controller {

	public function __construct() {

		/**
		 * List of required parameters and permissions for each API endpoint
		 * also indicates the parameter type
		 */
		$this->endpoints = array(
			'search' => array(
				'required_role' => self::PUBLIC_ACCESS,
				'params' => array(
					'band' => 'required[band,song]',
					'song' => 'required[band,song]'
				)
			),
			'getMP3' => array(
				'required_role' => self::PUBLIC_ACCESS,
				'params' => array(
					'link' => 'valid-url'
				)
			)
		);

		#request params
		$this->params = $this->checkRequest();
	}

	public function index() {
		
	}

	/**
	 * Returns all backing tracks that match the specified search criterias
	 */
	public function search() {
		$bt_model = $this->load_model('BackingTrackModel');

		#if the band is set
		if (mb_strlen($this->params['band']) > 0) {
			$data = $bt_model->getBandTracks($this->params['band']);

			#if the song is set as well
			if (mb_strlen($this->params['song']) > 0) {
				#filter out the songs that don't match the song param
				$data = array_filter($data, function ($item) {
					return (strpos(strtolower($item['song']), strtolower($this->params['song'])) !== false);
				});

				#change the array to indexed (instead of associative)
				$data = array_values($data);
			}
		}
		#if only the song is set
		else if (mb_strlen($this->params['song']) > 0) {
			$data = $bt_model->getSongTracks($this->params['song']);
		}

		$this->sendResponse(1, $data);
	}

	/**
	 * Extracts the MP3 link from the provided backing track link
	 */
	public function getMP3() {
		$bt_model = $this->load_model('BackingTrackModel');
		
		$mp3_file = $bt_model->getMP3($this->params['link']);
		
		if ($mp3_file !== null) {
			$this->sendResponse(1, $mp3_file);
		} else {
			$this->sendResponse(0, ErrorCodes::NOT_FOUND);
		}
	}

}
