<?php

namespace SupperSync\database;

use pocketmine\utils\Config;

class PConfig{
	
	public function __construct($file){
		$this->conf = new Config($file."Config.yml", Config::PROPERTIES, array(
			"url"=>"http://huishao.iego.net",
			"api"=>"api.php"
		));
	}
	public function getall(){
		return $this->conf->getall();
	}
}