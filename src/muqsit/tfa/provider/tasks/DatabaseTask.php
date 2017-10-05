<?php

/**
 *   ____  _____ _
 *  |___ \|  ___/ \
 *    __) | |_ / _ \
 *   / __/|  _/ ___ \
 *  |_____|_|/_/   \_\
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Muqsit
 * @link   http://github.com/Muqsit
 *
*/

namespace muqsit\tfa\provider\tasks;

use muqsit\tfa\provider\DataHolder;

use pocketmine\scheduler\AsyncTask;

abstract class DatabaseTask extends AsyncTask{

	const MYSQLI_KEY = "TFA.Database.MySQL.Async.MySQLi";

	/** @var DataHolder|null */
	private $dataholder;

	public function __construct(){
		$this->dataholder = DataHolder::getInstance();
	}

	public static function init(DataHolder $holder){
		$mysql = new \mysqli(...$holder->getMysqlData());
		$mysql->query("CREATE TABLE IF NOT EXISTS 2fa(uuid CHAR(36) NOT NULL PRIMARY KEY, secret VARCHAR(16), recovery JSON)");
		$mysql->close();
	}

	protected function getDataHolder() : ?DataHolder{
		return $this->dataholder;
	}

	public function __destruct(){
		if($this->getDataHolder() !== null){
			$this->getMysqli()->close();
		}
	}

	protected function getMysqli() : \mysqli{
		$mysqli = $this->getFromThreadStore(self::MYSQLI_KEY);
		if($mysqli !== null){
			return $mysqli;
		}
		$mysqli = new \mysqli(...$this->getDataHolder()->getMysqlData());
		$this->saveToThreadStore(self::MYSQLI_KEY, $mysqli);
		return $mysqli;
	}
}