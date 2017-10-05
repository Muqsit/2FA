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

namespace muqsit\tfa\provider;

use muqsit\tfa\provider\tasks\DatabaseTask;

class DataHolder{

	const DEFAULT_TYPE = self::TYPE_YML;

	const TYPE_YML = "YAML";
	const TYPE_JSON = "JSON";
	const TYPE_MYSQL = "MySQL";

	/** @var string */
	private $type;

	/** @var array|null */
	private $mysqldata;

	/** @var string|null */
	private $folderpath;

	/** @var Provider */
	private static $instance;

	public function __construct(string $type, ?string $folderpath = null){
		if(self::$instance instanceof $this){
			throw new \Error("Attempted to create a second instance of DataHolder. There can only exist 1 instance.");
		}
		self::$instance = $this;

		$this->type = $type;
		$this->folderpath = $folderpath;
	}

	public static function getInstance() : self{
		return self::$instance;
	}

	public function setType(string $type){
		$this->type = $type;
	}

	public function setFolderPath(string $path){
		if(!is_dir($path)){
			mkdir($path, 0755, true);
		}
		$this->folderpath = $path;
	}

	public function setMysqlData(array $credentials){
		$this->mysqldata = $credentials;
		DatabaseTask::init($this);
	}

	public function getMysqlDbName() : ?string{
		return $this->mysqldata[3] ?? null;
	}

	public function getType() : string{
		return $this->type;
	}

	public function getFolderPath() : ?string{
		return $this->folderpath;
	}

	public function getMysqlData() : ?array{
		return $this->mysqldata;
	}
}