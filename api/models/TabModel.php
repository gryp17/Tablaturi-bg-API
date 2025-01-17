<?php

class TabModel {

	private $connection;

	public function __construct() {
		$this->connection = DB::getInstance()->connection;
	}

	/**
	 * Returns the total number of guitar pro and text tabs
	 * @return array
	 */
	public function getTabsCount() {
		$data = array();

		#get the total number of tabs
		$query = $this->connection->prepare('SELECT COUNT(ID) FROM tab');
		$query->execute();
		$total = (int) $query->fetch()[0];

		#get only the guitar pro tabs
		$query = $this->connection->prepare('SELECT COUNT(ID) FROM tab WHERE type = "gp"');
		$query->execute();
		$gp = (int) $query->fetch()[0];

		$data = array(
			'gp' => $gp,
			'text' => $total - $gp
		);

		return $data;
	}

	/**
	 * Returns the most popular, liked, latest or commented tabs
	 * @param string $type
	 * @param int $limit
	 */
	public function getMost($type, $limit) {
		$data = array();

		switch ($type) {
			case 'popular' :
				$query = $this->connection->prepare('SELECT ID, band, song, downloads FROM tab ORDER BY downloads DESC LIMIT :limit');
				break;
			case 'liked' :
				$query = $this->connection->prepare('SELECT ID, band, song, rating FROM tab ORDER BY rating DESC, band ASC LIMIT :limit');
				break;
			case 'latest' :
				$query = $this->connection->prepare('SELECT ID, band, song, upload_date FROM tab ORDER BY upload_date DESC LIMIT :limit');
				break;
			case 'commented' :
				$query = $this->connection->prepare('SELECT tab.ID, tab.band, tab.song , count( tab_comment.ID ) AS comments FROM tab, tab_comment WHERE tab.ID = tab_comment.tab_ID GROUP BY tab.ID ORDER BY comments DESC LIMIT :limit');
				break;
		}

		$params = array('limit' => $limit);
		$query->execute($params);

		while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
			//convert the date to javascript friendly format
			if (isset($row['upload_date'])) {
				$row['upload_date'] = Utils::formatDate($row['upload_date']);
			}
			$data[] = $row;
		}

		return $data;
	}

	/**
	 * Returns all band/song names that contain the provided search term
	 * @param string $type
	 * @param string $term
	 * @param string $band
	 * @return array
	 */
	public function getAutocompleteResults($type, $term, $band) {
		$data = array();

		$params = array('term' => '%' . $term . '%');
		
		if ($type == 'band') {
			$query = $this->connection->prepare('SELECT DISTINCT(band) AS term FROM tab WHERE band LIKE :term LIMIT 10');
		} else {
			#if the band is set search only for songs from that band
			if(isset($band) && mb_strlen($band) > 0){
				$query = 'SELECT DISTINCT(song) AS term FROM tab WHERE band = :band AND song LIKE :term LIMIT 10';
				$params['band'] = $band;
			}else{
				$query = 'SELECT DISTINCT(song) AS term FROM tab WHERE song LIKE :term LIMIT 10';
			}
			
			$query = $this->connection->prepare($query);
		}

		$query->execute($params);

		while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
			$data[] = array(
				'id' => $row['term'],
				'label' => $row['term'],
				'value' => $row['term']
			);
		}

		return $data;
	}

	/**
	 * Returns the search results for the specified type/band/song
	 * @param string $type
	 * @param string $band
	 * @param string $song
	 * @param int $limit
	 * @param int $offset
	 */
	public function search($type, $band, $song, $limit, $offset) {
		$data = array();

		$search = $this->generateSearchQuery($type, $band, $song, $limit, $offset);

		$query = $this->connection->prepare($search['query']);
		$query->execute($search['params']);

		while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
			$row['upload_date'] = Utils::formatDate($row['upload_date']);
			$row['modified_date'] = Utils::formatDate($row['modified_date']);
			$data[] = $row;
		}

		return $data;
	}
	
	/**
	 * Returns the total number of records that match the specified type/band/song criterias
	 * @param string $type
	 * @param string $band
	 * @param string $song
	 * @return int
	 */
	public function getSearchTotal($type, $band, $song){
		$search = $this->generateSearchQuery($type, $band, $song, null, null);
		
		$query = $this->connection->prepare($search['count_query']);
		$query->execute($search['count_params']);
		
		$result = $query->fetch(PDO::FETCH_ASSOC);
		
		return (int) $result['total'];
	}

	/**
	 * Builds the correct query depending on the provided type/band/song
	 * Also returns the "count" query that is used in the pagination
	 * @param string $type
	 * @param string $band
	 * @param string $song
	 * @param int $limit
	 * @param int $offset
	 * @return array
	 */
	private function generateSearchQuery($type, $band, $song, $limit, $offset) {
		$result = array();
		
		if($type == 'all'){
			$type = '';
		}

		#band and song search
		if (mb_strlen($band) > 0 && mb_strlen($song) > 0) {

			#search query
			$result['query'] = 'SELECT * FROM tab '
				. 'WHERE type LIKE :type AND band LIKE :band AND song LIKE :song '
				. 'ORDER BY band, song, rating DESC, type, downloads DESC '
				. 'LIMIT :limit OFFSET :offset';

			$result['params'] = array(
				'type' => '%' . $type . '%',
				'band' => '%' . $band . '%',
				'song' => '%' . $song . '%',
				'limit' => $limit,
				'offset' => $offset
			);

			#count query
			$result['count_query'] = 'SELECT COUNT(ID) AS total FROM tab '
				. 'WHERE type LIKE :type AND band LIKE :band AND song LIKE :song';

			$result['count_params'] = array(
				'type' => '%' . $type . '%',
				'band' => '%' . $band . '%',
				'song' => '%' . $song . '%'
			);
		}
		#band search
		elseif(mb_strlen($band) > 0){
			
			$result['query'] = 'SELECT * FROM tab '
				. 'WHERE type LIKE :type AND band LIKE :band '
				. 'ORDER BY band, song, rating DESC, type, downloads DESC '
				. 'LIMIT :limit OFFSET :offset';
			
			$result['params'] = array(
				'type' => '%' . $type . '%',
				'band' => '%' . $band . '%',
				'limit' => $limit,
				'offset' => $offset
			);
			
			$result['count_query'] = 'SELECT COUNT(ID) AS total FROM tab '
				. 'WHERE type LIKE :type AND band LIKE :band';
			
			$result['count_params'] = array(
				'type' => '%' . $type . '%',
				'band' => '%' . $band . '%'
			);
		}
		#song search
		else{
			
			$result['query'] = 'SELECT * FROM tab '
				. 'WHERE type LIKE :type AND song LIKE :song '
				. 'ORDER BY band, song, rating DESC, type, downloads DESC '
				. 'LIMIT :limit OFFSET :offset';

			$result['params'] = array(
				'type' => '%' . $type . '%',
				'song' => '%' . $song . '%',
				'limit' => $limit,
				'offset' => $offset
			);
			
			$result['count_query'] = 'SELECT COUNT(ID) AS total FROM tab '
				. 'WHERE type LIKE :type AND song LIKE :song';

			$result['count_params'] = array(
				'type' => '%' . $type . '%',
				'song' => '%' . $song . '%'
			);
		}
		
		return $result;
	}
	
	/**
	 * Returns all tabs that were uploaded by the specified user id
	 * @param int $uploader_id
	 * @param int $limit
	 * @param int $offset
	 * @return array
	 */
	public function getTabsByUploader($uploader_id, $limit, $offset){
		$data = array();

		$query = $this->connection->prepare('SELECT * FROM tab WHERE uploader_ID = :uploader_id ORDER BY upload_date DESC LIMIT :limit OFFSET :offset');
		$params = array('uploader_id' => $uploader_id ,'limit' => $limit, 'offset' => $offset);

		$query->execute($params);

		while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
			//convert the date to javascript friendly format
			$row['upload_date'] = Utils::formatDate($row['upload_date']);
			$row['modified_date'] = Utils::formatDate($row['modified_date']);
			$data[] = $row;
		}

		return $data;
	}
	
	/**
	 * Returns the total number of user tabs
	 * @param int $uploader_id
	 * @return array
	 */
	public function getTotalTabsByUploader($uploader_id){
		$query = $this->connection->prepare('SELECT COUNT(ID) AS total FROM tab WHERE uploader_ID = :uploader_id');
		$query->execute(array('uploader_id' => $uploader_id));
		
		$result = $query->fetch(PDO::FETCH_ASSOC);
		
		return $result['total'];
	}
	
	/**
	 * Returns the tab data
	 * @param int $id
	 * @return array
	 */
	public function getTab($id){
		$query = $this->connection->prepare('SELECT '
				. 'tab.ID, '
				. 'tab.type, '
				. 'tab.path, '
				. 'tab.band, '
				. 'tab.song, '
				. 'tab.version, '
				. 'tab.tab_type, '
				. 'tab.content, '
				. 'tab.rating, '
				. 'tab.downloads, '
				. 'tab.upload_date, '
				. 'tab.modified_date, '
				. 'tab.tunning, '
				. 'tab.difficulty, '
				. 'tab.uploader_ID, '
				. 'user.username '
				. 'FROM tab, user '
				. 'WHERE tab.uploader_ID = user.ID AND tab.ID = :id');
		$query->execute(array('id' => $id));
		
		$row = $query->fetch(PDO::FETCH_ASSOC);
		if($row){
			//convert the date to javascript friendly format
			$row['upload_date'] = Utils::formatDate($row['upload_date']);
			$row['modified_date'] = Utils::formatDate($row['modified_date']);
			
			return $row;
		}else{
			return null;
		}
	}
	
	/**
	 * Updates the tab views/downloads
	 * @param int $id
	 * @return boolean
	 */
	public function addTabView($id){
		$query = $this->connection->prepare('UPDATE tab SET downloads = downloads + 1 WHERE ID = :tab_id');
		return $query->execute(array('tab_id' => $id));
	}
	
	/**
	 * Inserts new tab rating and calculates the average tab rating
	 * @param int $user_id
	 * @param int $tab_id
	 * @param int $rating
	 * @return boolean
	 */
	public function rateTab($user_id, $tab_id, $rating){
		if($this->tabIsRated($user_id, $tab_id)){
			return false;
		}else{
			$query = $this->connection->prepare('INSERT INTO tab_rating (tab_ID, user_ID, rating, date) VALUES (:tab_id, :user_id, :rating, now())');
			$query->execute(array('tab_id' => $tab_id, 'user_id' => $user_id, 'rating' => $rating));
						
			//calculate the new average tab rating
			$this->calculateTabRating($tab_id);
			
			return true;
		}

	}

	/**
	 * Checks if the tab has already been rated by the user
	 * @param int $user_id
	 * @param int $tab_id
	 * @return boolean
	 */
	public function tabIsRated($user_id, $tab_id){
		$check_query = $this->connection->prepare('SELECT * FROM tab_rating WHERE tab_ID = :tab_id AND user_ID = :user_id');
		$check_query->execute(array('tab_id' => $tab_id, 'user_id' => $user_id));
		$row = $check_query->fetch();
		
		if ($row) {
			return true;
		} else {
			return false;
		}
	}
	
	
	/**
	 * Calculates the current tab rating and updates the rating field of the tab
	 * @param int $tab_id
	 * @return boolean
	 */
	private function calculateTabRating($tab_id){
		$query = $this->connection->prepare('UPDATE tab SET rating = (SELECT AVG(rating) FROM tab_rating WHERE tab_ID = :tab_id) WHERE ID = :update_tab_id');
		return $query->execute(array('tab_id' => $tab_id, 'update_tab_id' => $tab_id));
	}
	
	
	/**
	 * Adds new tab
	 * @param string $type
	 * @param string $band
	 * @param string $song
	 * @param string $tab_type
	 * @param string $content
	 * @param string $path
	 * @param int $uploader_ID
	 * @param string $tunning
	 * @param string $difficulty
	 * @return boolean
	 */
	public function addTab($type, $band, $song, $tab_type, $content, $path, $uploader_ID, $tunning, $difficulty){
		$query = $this->connection->prepare('INSERT INTO tab '
				. '(type, band, song, tab_type, content, path, rating, downloads, upload_date, modified_date, uploader_ID, tunning, difficulty)'
				. ' VALUES '
				. '(:type, :band, :song, :tab_type, :content, :path, 0, 0, now(), now(), :uploader_ID, :tunning, :difficulty)');
		
		$params = array(
			'type' => $type,
			'band' => $band,
			'song' => $song,
			'tab_type' => $tab_type,
			'content' => $content,
			'path' => $path,
			'uploader_ID' => $uploader_ID,
			'tunning' => $tunning,
			'difficulty' => $difficulty
		);
		
		if($query->execute($params)){
			return $this->connection->lastInsertId();
		}else{
			return null;
		}
	}
	
	/**
	 * Updates the tab
	 * @param int $tab_id
	 * @param string $type
	 * @param string $band
	 * @param string $song
	 * @param string $tab_type
	 * @param string $content
	 * @param string $path
	 * @param string $tunning
	 * @param string $difficulty
	 * @return boolean
	 */
	public function updateTab($tab_id, $type, $band, $song, $tab_type, $content, $path, $tunning, $difficulty){
		$query = $this->connection->prepare('UPDATE tab SET '
				. 'type = :type, '
				. 'band = :band, '
				. 'song = :song, '
				. 'tab_type = :tab_type, '
				. 'content = :content, '
				. 'path = :path, '
				. 'modified_date = now(), '
				. 'tunning = :tunning, '
				. 'difficulty = :difficulty '
				. 'WHERE ID = :tab_id');
		
		$params = array(
			'tab_id' => $tab_id,
			'type' => $type,
			'band' => $band,
			'song' => $song,
			'tab_type' => $tab_type,
			'content' => $content,
			'path' => $path,
			'tunning' => $tunning,
			'difficulty' => $difficulty
		);
		
		return $query->execute($params);
	}
	
}
