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

use muqsit\tfa\player\TFAPlayer;
use muqsit\tfa\provider\tasks\{LoadPlayerTask, MySQLTask, RemoveTFATask, RequestTFATask};

use pocketmine\Server;
use pocketmine\utils\TextFormat;

class Provider{

	/** @var Handler */
	private $handler;

	/** @var Server */
	private $server;

	public function __construct(Handler $handler, array $settings){
		$data = new DataHolder($settings["DataType"]);

		$this->server = Server::getInstance();
		$this->handler = $handler;

		if($data->getType() !== DataHolder::TYPE_MYSQL){
			if($data->getType() !== DataHolder::TYPE_YML && $data->getType() !== DataHolder::TYPE_JSON){
				$this->server->getLogger()->notice("Invalid data type '".$data->getType()."' in config, switching to default: ".DataHolder::DEFAULT_TYPE);
				$data->setType(DataHolder::DEFAULT);
			}

			if(!isset($settings["FolderPath"])){
				throw new \Error("Poorly configured configuration detected. Missing 'FolderPath' entry in config.");
			}

			$data->setFolderPath($settings["FolderPath"]);
		}else{
			$mysql = $settings["Credentials"];
			$data->setMysqlData([$mysql["Host"], $mysql["Username"], $mysql["Password"], $mysql["Database"], $mysql["Port"]]);
		}

		RequestTFATask::init($settings["2FATitle"] ?? "PMMP");
	}

	public function getHandler() : Handler{
		return $this->handler;
	}

	public function loadPlayer(TFAPlayer $player){
		$this->server->getScheduler()->scheduleAsyncTask(new LoadPlayerTask($player));
	}

	public function remove2FA(TFAPlayer $player){
		$this->server->getScheduler()->scheduleAsyncTask(new RemoveTFATask($player));
	}

	public function verify(TFAPlayer $player, string $input) : bool{
		if($player->isRecoveryCode($input)){
			$this->remove2FA($player);
			$player = $player->getPlayer();
			if($player !== null){
				$player->sendMessage(TextFormat::YELLOW."2FA has been disabled from your account as you had used a recovery code. You can set up 2FA again using /2fa.");
			}
			return true;
		}
		return $this->handler->verifyCode($this->handler->getProcessSecret($player) ?? $player->getSecret(), $input);
	}
}