<?php

class UserFavourite extends Controller {

	public function __construct() {

		/**
		 * List of required parameters and permissions for each API endpoint
		 * also indicates the parameter type
		 */
		$this->endpoints = array(
			'getUserFavourites' => array(
				'required_role' => self::LOGGED_IN_USER,
				'params' => array(
					'user_id' => 'int',
					'limit' => 'int',
					'offset' => 'int'
				)
			),
			'deleteFavouriteTab' => array(
				'required_role' => self::LOGGED_IN_USER,
				'params' => array(
					'tab_id' => 'int'
				)
			),
			'addFavouriteTab' => array(
				'required_role' => self::LOGGED_IN_USER,
				'params' => array(
					'tab_id' => 'int'
				)
			),
			'isFavouriteTab' => array(
				'required_role' => self::LOGGED_IN_USER,
				'params' => array(
					'tab_id' => 'int'
				)
			)
		);
		
		#request params
		$this->params = $this->checkRequest();
	}

	public function index() {
		
	}

	/**
	 * Returns all favourite tabs for the specified user id
	 */
	public function getUserFavourites() {
		$user_favourite_model = $this->load_model('UserFavouriteModel');
		$tabs = $user_favourite_model->getUserFavourites($this->params['user_id'], $this->params['limit'], $this->params['offset']);
		$total = $user_favourite_model->getTotalUserFavourites($this->params['user_id']);

		$this->sendResponse(1, array('results' => $tabs, 'total' => $total));
	}
	
	/**
	 * Deletes the specified tab from the user's favourites list
	 */
	public function deleteFavouriteTab() {
		$user_favourite_model = $this->load_model('UserFavouriteModel');
		$user_favourite_model->deleteFavouriteTab($_SESSION['user']['ID'] ,$this->params['tab_id']);
		
		$this->sendResponse(1, array('success' => true));
	}
	
	/**
	 * Adds the specified tab to the user's favourites list
	 * returns true if the record was added and false if the tab already is in favourites
	 */
	public function addFavouriteTab() {
		$user_favourite_model = $this->load_model('UserFavouriteModel');
		$result = $user_favourite_model->addFavouriteTab($_SESSION['user']['ID'] ,$this->params['tab_id']);
		
		$this->sendResponse(1, $result);
	}

	/**
	 * Checks if the tab is in the favourite tabs list
	 */
	public function isFavouriteTab() {
		$user_favourite_model = $this->load_model('UserFavouriteModel');
		$tab = $user_favourite_model->getUserFavourite($_SESSION['user']['ID'] ,$this->params['tab_id']);
		$result = isset($tab);

		$this->sendResponse(1, $result);
	}
	

}
