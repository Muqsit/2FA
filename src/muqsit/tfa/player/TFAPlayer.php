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

namespace muqsit\tfa\player;

use muqsit\tfa\TFA;

use pocketmine\{Player, Server};

class TFAPlayer{

	/** @var string */
	private $username;

	/** @var int */
	private $entityRuntimeId;

	/** @var string */
	private $uuid;

	/** @var bool */
	private $has2FA = false;

	/** @var string|null */
	private $tfasecret;

	/** @var int[]|null */
	private $tfarecovery;

	/** @var bool */
	private $tfaLock = false;

	public function __construct(TFA $tfa, Player $player){
		$this->username = $player->getName();
		$this->uuid = $player->getRawUniqueId();
		$this->entityRuntimeId = $player->getId();
		$tfa->getProvider()->loadPlayer($this);
	}

	public function getId() : int{
		return $this->entityRuntimeId;
	}

	public function getUUID() : string{
		return $this->uuid;
	}

	public function getName() : string{
		return $this->username;
	}

	public function has2FA() : bool{
		return $this->has2FA;
	}

	public function getSecret() : ?string{
		return $this->tfasecret;
	}

	public function isLocked(){
		return $this->tfaLock;
	}

	public function isRecoveryCode($code) : bool{
		return $this->tfarecovery !== null && is_numeric($code) && ceil(log10($code)) === 8.0 && in_array($code, $this->tfarecovery);
	}

	public function set2FA(TFAPlayerData $data){
		if($this->has2FA = $data->exists()){
			$this->tfasecret = $data->getSecret();
			$this->tfarecovery = $data->getRecoveryCodes();
			$this->tfaLock(true);
		}
	}

	public function tfaLock(bool $value){
		$player = $this->getPlayer();
		if($player !== null){
			if(!$value){//hack to not let player get kicked for flying as soon as they authenticate.
				$player->resetFallDistance();
			}
			$player->setImmobile($this->tfaLock = $value);
		}
	}

	public function getPlayer() : ?Player{
		return Server::getInstance()->findEntity($this->entityRuntimeId);
	}
}