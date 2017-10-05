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

use muqsit\tfa\player\{TFAPlayer, TFAPlayerData};
use muqsit\tfa\provider\DataHolder;

use pocketmine\Server;
use pocketmine\utils\UUID;

class LoadPlayerTask extends DatabaseTask{

	/** @var TFAPlayer */
	private $tfaplayer;

	public function __construct(TFAPlayer $player){
		$this->tfaplayer = $player;
		parent::__construct();
	}

	public function onRun(){
		$data = new TFAPlayerData();
		if($this->getDataHolder()->getType() === DataHolder::TYPE_MYSQL){
			$mysql = $this->getMysqli();

			$uuid = UUID::fromBinary($this->tfaplayer->getUUID());

			$stmt = $mysql->prepare("SELECT secret, recovery FROM 2fa WHERE uuid=?");
			$stmt->bind_param("s", $uuid);
			$stmt->bind_result($secret, $recovery);
			$stmt->execute();

			if($stmt->fetch()){
				$data->setSecret($secret);
				$data->setRecoveryCodes(json_decode($recovery, true));
			}

			$stmt->close();
		}else{
			$path = $this->getDataHolder()->getFolderPath().DIRECTORY_SEPARATOR.UUID::fromBinary($this->tfaplayer->getUUID());
			switch($this->getDataHolder()->getType()){
				case DataHolder::TYPE_YML:
					$path .= ".yml";
					if(file_exists($path)){
						$settings = yaml_parse_file($path);
						if(isset($settings["secret"])){
							$data->setSecret($settings["secret"]);
						}
						if(isset($settings["recovery"])){
							$data->setRecoveryCodes($settings["recovery"]);
						}
					}
					break;
				case DataHolder::TYPE_JSON:
					$path .= ".json";
					if(file_exists($path)){
						$settings = json_decode(file_get_contents($path));
						if(isset($settings->secret)){
							$data->setSecret($settings->secret);
						}
						if(isset($settings->recovery)){
							$data->setRecoveryCodes($settings->recovery);
						}
					}
					break;
			}
		}
		$this->setResult($data);
	}

	public function onCompletion(Server $server){
		$player = $server->getPluginManager()->getPlugin("2FA")->getTFAPlayerInstance($this->tfaplayer);
		if($player !== null){
			$player->set2FA($this->getResult());
		}
	}
}