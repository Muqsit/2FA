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
use pocketmine\utils\{TextFormat, UUID};

class UpdatePlayerTFATask extends DatabaseTask{

	/** @var TFAPlayer */
	private $tfaplayer;

	/** @var TFAPlayerData */
	private $data;

	public function __construct(TFAPlayer $player, TFAPlayerData $data){
		$this->tfaplayer = $player;
		$this->data = $data;
		parent::__construct();
	}

	public function onRun(){
		$data = new TFAPlayerData();
		if($this->getDataHolder()->getType() === DataHolder::TYPE_MYSQL){
			$mysql = $this->getMysqli();

			$uuid = UUID::fromBinary($this->tfaplayer->getUUID());

			if($this->data->exists()){
				$secret = $this->data->getSecret();
				$recovery = json_encode($this->data->getRecoveryCodes());

				$stmt = $mysql->prepare("INSERT INTO 2fa(uuid, secret, recovery) VALUES(?, ?, ?) ON DUPLICATE KEY UPDATE secret=VALUES(secret), recovery=VALUES(recovery)");
				$stmt->bind_param("sss", $uuid, $secret, $recovery);
			}else{
				$stmt = $mysql->prepare("DELETE FROM 2fa WHERE uuid=?");
				$stmt->bind_param("s", $uuid);
			}
			$stmt->execute();
			$stmt->close();
		}else{
			$path = $this->getDataHolder()->getFolderPath().DIRECTORY_SEPARATOR.UUID::fromBinary($this->tfaplayer->getUUID());
			switch($this->getDataHolder()->getType()){
				case DataHolder::TYPE_YML:
					$path .= ".yml";
					if($this->data->exists()){
						yaml_emit_file($path, [
							"secret" => $this->data->getSecret(),
							"recovery" => $this->data->getRecoveryCodes()
						]);
					}elseif(file_exists($path)){
						unlink($path);
					}
					break;
				case DataHolder::TYPE_JSON:
					$path .= ".json";
					if($this->data->exists()){
						file_put_contents($path, json_encode([
							"secret" => $this->data->getSecret(),
							"recovery" => $this->data->getRecoveryCodes()
						]));
					}elseif(file_exists($path)){
						unlink($path);
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

			$message = [
				' ',
				TextFormat::GREEN.'Two-Factor Authentication setup successfully!',
				TextFormat::GREEN.'Thank you for keeping your account safe!',
				' ',
				TextFormat::GRAY.'Please keep the following recovery codes in a safe place:',
			];

			foreach($this->data->getRecoveryCodes() as $code){
				$message[] = TextFormat::WHITE.TextFormat::BOLD.'* '.TextFormat::RESET.TextFormat::WHITE.$code;
			}

			$message[] = TextFormat::RED."If you lose your 2FA device and don't have access to these codes your account will be ".TextFormat::UNDERLINE.'LOCKED FOREVER.'.TextFormat::RESET;
			$message[] = " ";

			$player->getPlayer()->sendMessage(implode("\n", $message));
		}
	}
}