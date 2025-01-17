<?php

class UserComment extends Controller {

	public function __construct() {

		/**
		 * List of required parameters and permissions for each API endpoint
		 * also indicates the parameter type
		 */
		$this->endpoints = array(
			'getUserComments' => array(
				'required_role' => self::LOGGED_IN_USER,
				'params' => array(
					'user_id' => 'int',
					'limit' => 'int',
					'offset' => 'int'
				)
			),
			'addUserComment' => array(
				'required_role' => self::LOGGED_IN_USER,
				'params' => array(
					'user_id' => 'int',
					'content' => array('required', 'max-500')
				)
			)
		);
		
		#request params
		$this->params = $this->checkRequest();
	}

	public function index() {
		
	}

	/**
	 * Returns all article comments for the specified user id
	 */
	public function getUserComments() {
		$user_comment_model = $this->load_model('UserCommentModel');
		$comments = $user_comment_model->getUserComments($this->params['user_id'], $this->params['limit'], $this->params['offset']);
		$total = $user_comment_model->getTotalUserComments($this->params['user_id']);

		$this->sendResponse(1, array('results' => $comments, 'total' => $total));
	}
	
	/**
	 * Adds new user comment
	 */
	public function addUserComment() {
		$user_comment_model = $this->load_model('UserCommentModel');
		$result = $user_comment_model->addUserComment($this->params['user_id'], $_SESSION['user']['ID'], $this->sanitize($this->params['content']));

		if ($result === true) {
			
			//give 1 reputation
			$user_model = $this->load_model('UserModel');
			$user_model->giveReputation($_SESSION['user']['ID'], 1);
			
			//send notification email
			if((int) $this->params['user_id'] !== $_SESSION['user']['ID']){
				$recipient = $user_model->getUser($this->params['user_id']);			
				if($recipient !== null){
					Utils::sendProfileCommentEmail($recipient, $_SESSION['user'], $this->params['content']);
				}
			}
			
			$this->sendResponse(1, array('success' => true));
		} else {
			$this->sendResponse(0, ErrorCodes::DB_ERROR);
		}
	}

}
