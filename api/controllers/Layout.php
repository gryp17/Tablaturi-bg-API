<?php

class Layout extends Controller {

	public function index() {
		//$this->load_view('index');
		$this->sendResponse(1, 'Tablaturi-BG API');
	}

}
