<?php

class User extends Controller {

	public function __construct() {

		/**
		 * List of required parameters and permissions for each API endpoint
		 * also indicates the parameter type
		 */
		$this->endpoints = array(
			'login' => array(
				'required_role' => self::PUBLIC_ACCESS,
				'params' => array(
					'username' => 'required',
					'password' => 'required',
					'remember_me' => 'in[1,0,]' //(1 or 0 or empty space) boolean?
				)
			),
			'updatePassword' => array(
				'required_role' => self::PUBLIC_ACCESS,
				'params' => array(
					'user_id' => 'required',
					'hash' => 'required',
					'password' => array('min-6', 'max-20', 'strong-password'),
					'repeat_password' => 'matches[password]'
				)
			),
			'logout' => array(
				'required_role' => self::PUBLIC_ACCESS
			),
			'isLoggedIn' => array(
				'required_role' => self::PUBLIC_ACCESS
			),
			'signup' => array(
				'required_role' => self::PUBLIC_ACCESS,
				'params' => array(
					'username' => array('min-6', 'max-20', 'valid-characters', 'unique[username]'),
					'email' => array('valid-email', 'unique[email]'),
					'password' => array('min-6', 'max-20', 'strong-password'),
					'repeat_password' => 'matches[password]',
					'birthday' => 'date',
					'gender' => 'in[M,F]',
					'captcha' => 'matches-captcha'
				)
			),
			'resendUserActivation' => array(
				'required_role' => self::PUBLIC_ACCESS,
				'params' => array(
					'email' => array('required', 'valid-email')
				)
			),
			'getUser' => array(
				'required_role' => self::LOGGED_IN_USER,
				'params' => array(
					'id' => array('required', 'int')
				)
			),
			'updateUser' => array(
				'required_role' => self::LOGGED_IN_USER,
				'params' => array(
					'avatar' => array('optional', 'valid-file-extensions[png,jpg,jpeg]', 'max-file-size-1000'),
					'password' => array('optional', 'min-6', 'max-20', 'strong-password'),
					'repeat_password' => 'matches[password]',
					'location' => array('optional', 'max-100'),
					'occupation' => array('optional', 'max-200'),
					'web' => array('optional', 'max-200'),
					'about_me' => array('optional', 'max-500'),
					'instrument' => array('optional', 'max-500'),
					'favourite_bands' => array('optional', 'max-500')
				)
			),
			'search' => array(
				'required_role' => self::LOGGED_IN_USER,
				'params' => array(
					'keyword' => array('required', 'min-3', 'max-50'),
					'limit' => 'int',
					'offset' => 'int'
				)
			)
		);

		#request params
		$this->params = $this->checkRequest();
	}

	public function index() {
		
	}

	/**
	 * Checks if the username and password credentials match and starts the session
	 */
	public function login() {
		$user_model = $this->load_model('UserModel');
		$data = $user_model->checkLogin($this->params['username'], $this->params['password']);

		if ($data === false) {
			$this->sendResponse(0, array('field' => 'password', 'error_code' => ErrorCodes::INVALID_LOGIN));
		} else {

			//if the remember me option is set to true - keep the user session for 90 days	
			if (isset($this->params['remember_me']) && $this->params['remember_me']) {
				setcookie(session_name(), session_id(), array(
					'expires' => strtotime('+90 days'),
					'path' => '/',
					'samesite' => 'Lax'
				));
			}
			//otherwise keep until the browser is closed (default)
			else {
				setcookie(session_name(), session_id(), array(
					'expires' => 0,
					'path' => '/',
					'samesite' => 'Lax'
				));
			}

			$_SESSION['user'] = $data;
			$this->sendResponse(1, $data);
		}
	}
	
	/**
	 * Changes the user password if the provided userId/hash combination is valid
	 */
	public function updatePassword() {
		$password_reset_model = $this->load_model('PasswordResetModel');
		
		//check the hash
		if($password_reset_model->checkHash($this->params['user_id'], $this->params['hash'])){
			$user_model = $this->load_model('UserModel');
			
			if($user_model->updatePassword($this->params['user_id'], $this->params['password'])){
				//delete all password reset hashes related to the user_id
				$password_reset_model->deleteHash($this->params['user_id']);
				$this->sendResponse(1, true);
			}else{
				$this->sendResponse(0, ErrorCodes::DB_ERROR);
			}
		}else{
			$this->sendResponse(0, array('field' => 'hash', 'error_code' => ErrorCodes::INVALID_OR_EXPIRED_TOKEN));
		}
	}

	/**
	 * Logs out the user
	 */
	public function logout() {
		session_destroy();
		unset($_SESSION['user']);
		$this->sendResponse(1, true);
	}

	/**
	 * Checks if the user session is set
	 * Also updates the last_active date/time of the user
	 */
	public function isLoggedIn() {
		if (isset($_SESSION['user'])) {
			$user_model = $this->load_model('UserModel');
			$user_model->updateActivity($_SESSION['user']['ID']);
			$this->sendResponse(1, array('logged_in' => true, 'user' => $_SESSION['user']));
		} else {
			$this->sendResponse(1, array('logged_in' => false));
		}
	}

	/**
	 * New user signup
	 */
	public function signup() {
		$user_model = $this->load_model('UserModel');
		
		//set the correct default avatar
		if($this->params['gender'] === 'M'){
			$avatar = 'default-m.jpg';
		}else{
			$avatar = 'default-f.jpg';
		}
		
		$user_id = $user_model->insertUser($this->params['username'], $this->params['password'], $this->params['email'], $this->params['birthday'], $this->params['gender'], $avatar, 'user');

		if($user_id !== null){
			$activation = $this->generateActivationLink($user_id, $this->params['email']);

			//insert the activation data into the database
			$user_activation_model = $this->load_model('UserActivationModel');
			$user_activation_model->insertHash($user_id, $activation['hash']);
			
			//send the confirmation email
			if (Utils::sendConfirmationEmail($this->params['username'], $this->params['email'], $activation['link'])) {
				$this->sendResponse(1, array('success' => true));
			} else {
				$this->sendResponse(0, ErrorCodes::EMAIL_ERROR);
			}
		}else{
			$this->sendResponse(0, ErrorCodes::DB_ERROR);
		}
	}

	/**
	 * Resends the user activation email
	 */
	public function resendUserActivation() {
		$user_model = $this->load_model('UserModel');
		$user_data = $user_model->getUserByEmail($this->params['email'], false);

		if($user_data !== null){
			if ($user_data['activated'] === 1) {
				$this->sendResponse(0, array('field' => 'email', 'error_code' => ErrorCodes::EMAIL_ALREADY_ACTIVATED));
			} else {
				$activation = $this->generateActivationLink($user_data['ID'], $this->params['email']);

				//insert the activation data into the database
				$user_activation_model = $this->load_model('UserActivationModel');
				$user_activation_model->insertHash($user_data['ID'], $activation['hash']);

				//send the confirmation email
				if (Utils::sendConfirmationEmail($user_data['username'], $this->params['email'], $activation['link'])) {
					$this->sendResponse(1, array('success' => true));
				} else {
					$this->sendResponse(0, ErrorCodes::EMAIL_ERROR);
				}
			}
			
		}else{
			$this->sendResponse(0, array('field' => 'email', 'error_code' => ErrorCodes::EMAIL_NOT_FOUND));
		}
	}
	
	/**
	 * Generates an activation link
	 * @param int $user_id
	 * @param string $email
	 * @return array
	 */
	private function generateActivationLink($user_id, $email){
		$domain = Config::DOMAIN;

		$hash = Utils::generateRandomToken($email);
		$link = "http://$domain/activate/$user_id/$hash";

		return array(
			'link' => $link,
			'hash' => $hash
		);
	}

	/**
	 * Returns the specified user data
	 */
	public function getUser() {
		$user_model = $this->load_model('UserModel');
		$data = $user_model->getUser($this->params['id']);

		$this->sendResponse(1, $data);
	}

	/**
	 * Updates the user's data
	 */
	public function updateUser() {
		$user_model = $this->load_model('UserModel');
		$password = isset($this->params['password']) ? $this->params['password'] : '';
		$location = isset($this->params['location']) ? $this->params['location'] : '';
		$occupation = isset($this->params['occupation']) ? $this->params['occupation'] : '';
		$web = isset($this->params['web']) ? $this->params['web'] : '';
		$about_me = isset($this->params['about_me']) ? $this->params['about_me'] : '';
		$instrument = isset($this->params['instrument']) ? $this->params['instrument'] : '';
		$favourite_bands = isset($this->params['favourite_bands']) ? $this->params['favourite_bands'] : '';
		$new_avatar = '';

		#if there is a submited avatar
		if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== 4) {
			$new_avatar = $this->uploadUserAvatar('avatar', $_SESSION['user']['ID'], $_SESSION['user']['photo']);
		}

		#update the user data and reload the $_SESSION user
		if ($user_model->updateUser($_SESSION['user']['ID'], $password, $location, $occupation, $web, $about_me, $instrument, $favourite_bands, $new_avatar)) {
			$_SESSION['user'] = $user_model->getUser($_SESSION['user']['ID']);
			$this->sendResponse(1, array('success' => true));
		}
	}

	/**
	 * Helper function that uploads the new avatar
	 * returns the new avatar file name
	 * @param string $field_name
	 * @param int $user_id
	 * @param string $current_avatar
	 * @return string
	 */
	private function uploadUserAvatar($field_name, $user_id, $current_avatar) {
		$avatars_dir = Config::AVATARS_DIR;

		preg_match('/\.([^\.]+?)$/', $_FILES[$field_name]['name'], $matches);
		$extension = strtolower($matches[1]);
		$extension = '.' . $extension;

		#delete the old avatar
		if (file_exists($avatars_dir . $current_avatar) && !preg_match('/default/is', $current_avatar)) {
			unlink($avatars_dir . $current_avatar);
		}

		#upload the file to the server
		move_uploaded_file($_FILES[$field_name]['tmp_name'], $avatars_dir . '/avatar-' . $user_id . $extension);
		$avatar = 'avatar-' . $user_id . $extension;

		return $avatar;
	}

	/**
	 * Searches for users using the provided keyword
	 */
	public function search() {
		$user_model = $this->load_model('UserModel');
		$users = $user_model->search($this->params['keyword'], $this->params['limit'], $this->params['offset']);
		$total = $user_model->getTotalSearchResults($this->params['keyword']);

		$this->sendResponse(1, array('results' => $users, 'total' => $total));
	}
}
