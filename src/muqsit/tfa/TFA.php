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

namespace muqsit\tfa;

use muqsit\tfa\player\TFAPlayer;
use muqsit\tfa\provider\{Handler, Provider};

use pocketmine\command\{Command, CommandSender};
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class TFA extends PluginBase{

	/** @var Provider */
	private $provider;

	/** @var TFAPlayer[] */
	private $players = [];

	/** @var bool */
	private $cancelRotation;

	public function onEnable(){
		try{
			if(!$this->getServer()->requiresAuthentication()){
				$this->getServer()->getLogger()->warning("2FA requires 'xbox-auth' to be enabled in server.properties. Disabling 2FA...");
				$this->getServer()->getPluginManager()->disablePlugin($this);
				return;
			}

			if(!extension_loaded("gd")){
				throw new \Error("Unable to find the 'GD' extension.");
			}
			if(!function_exists("imagepng")){
				throw new \Error("Unable to find the 'png' extension.");
			}

			$this->saveResource("config.yml");
			$this->saveResource("font.ttf");

			$tfapi = new Handler($this->getDataFolder()."font.ttf");

			$settings = yaml_parse_file($this->getDataFolder()."config.yml");

			if(!isset($settings["Database"])){
				$this->saveResource("config.yml", true);
			}

			$this->cancelRotation = $settings["CancelRotation"] ?? false;

			$this->provider = new Provider($tfapi, $settings["Database"]);

			new TFAListener($this);
		}catch(\Exception $e){
			throw $e;
		}
		foreach([
			" ____  _____ _",
			"|___ \|  ___/ \\",
			"  __) | |_ / _ \\",
			" / __/|  _/ ___ \\",
			"|_____|_|/_/   \_\\",
			" ",
			"2FA has been successfully enabled."
		] as $log){
			$this->getServer()->getLogger()->notice($log);
		}
	}

	public function shouldCancelRotation() : bool{
		return $this->cancelRotation;
	}

	public function getProvider() : Provider{
		return $this->provider;
	}

	public function addTFAPlayer(Player $player){
		$this->players[$player->getRawUniqueId()] = new TFAPlayer($this, $player);
	}

	public function getTFAPlayer(Player $player) : ?TFAPlayer{
		return $this->players[$player->getRawUniqueId()] ?? null;
	}

	public function getTFAPlayerInstance(TFAPlayer $player) : ?TFAPlayer{
		return $this->players[$player->getUUID()] ?? null;
	}

	public function removeTFAPlayer(Player $player){
		unset($this->players[$player->getRawUniqueId()]);
	}

	public function onCommand(CommandSender $issuer, Command $cmd, string $label, array $args) : bool{
		if(!($issuer instanceof Player)){
			$issuer->sendMessage(TextFormat::RED."This command can only be used in-game.");
			return false;
		}

		$player = $this->getTFAPlayer($issuer);
		if($player === null){
			$issuer->sendMessage(TextFormat::RED."An error occurred while fetching your TFA data, try rejoining the server!");
			return false;
		}

		if(isset($args[0])){
			switch($args[0]){
				case "remove":
					if (!$player->has2FA()) {
						$issuer->sendMessage(implode("\n", [
							TextFormat::RED."You do not have Two-Factor Authentication enabled!",
							TextFormat::GREEN."Enable Two-Factor Authentication with ".TextFormat::UNDERLINE."/2fa!".TextFormat::RESET
						]));
						return false;
					}
					$this->getProvider()->remove2FA($player);
					$issuer->sendMessage(implode("\n", [
						TextFormat::RED."Two-Factor Authentication has been ".TextFormat::UNDERLINE."disabled".TextFormat::RESET.TextFormat::RED."!",
						TextFormat::GRAY."Please note your account is now less secure."
					]));
					return true;
				case "cancel":
					if(!$this->provider->getHandler()->isProcessing($player)){
						$issuer->sendMessage(TextFormat::RED."You don't have an ongoing 2FA verification process.");
						return false;
					}
					$this->getProvider()->getHandler()->onCancelProcess($player);
					return true;
				default:
					if($this->provider->getHandler()->isProcessing($player)){
						if(!$this->getProvider()->verify($player, $args[0])){
							$issuer->sendMessage(TextFormat::RED."Invalid authentication entered! Please try again.");
							return false;
						}
						$this->getProvider()->getHandler()->onVerify($player);
						return true;
					}
					$issuer->sendMessage(implode("\n", [
						TextFormat::RED."You have not configured your 2fa authenticator!",
						TextFormat::GRAY."Use ".TF::UNDERLINE."/2fa".TextFormat::RESET.TextFormat::GRAY." to enable it!"
					]));
					return false;
			}
		}

		if($player->has2FA()){
			$issuer->sendMessage(TextFormat::RED."You already have two factor auth enabled, use /2fa remove to remove it.");
			return false;
		}

		$this->getProvider()->getHandler()->onTFARequest($player);
		return true;
	}
}
