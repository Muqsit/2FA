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

use muqsit\tfa\player\TFAPlayer;
use muqsit\tfa\provider\Handler;

use pocketmine\item\Item;
use pocketmine\nbt\tag\{ByteTag, LongTag};
use pocketmine\network\mcpe\protocol\{BatchPacket, ClientboundMapItemDataPacket};
use pocketmine\{Player, Server};
use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\{Color, TextFormat};

class RequestTFATask extends AsyncTask {

	const PACKET_COMPRESSION_LEVEL = 9;

	const COLOUR_BACKGROUND = 0;
	const COLOUR_BARCODE = 1;
	const COLOUR_TEXT = 2;

	const COLOUR_PALETTE = [
		self::COLOUR_BACKGROUND => [175, 175, 175],
		self::COLOUR_BARCODE => [60, 35, 5],
		self::COLOUR_TEXT => [72, 72, 72]
	];

	const WIDTH = 128;
	const HEIGHT = 128;

	const EC_LEVEL_L = "L";
	const EC_LEVEL_M = "M";
	const EC_LEVEL_Q = "Q";
	const EC_LEVEL_H = "H";

	const BARCODE_ERROR_CORRECTION_LEVEL = self::EC_LEVEL_M;//https://developers.google.com/chart/infographics/docs/qr_codes
	const BARCODE_MARGIN = 3;

	/** @var int */
	private static $iuuid;

	/** @var TFAPlayer */
	private $player;

	/** @var int */
	private $uuid;

	/** @var Handler */
	private $tfhandler;

	/** @var string */
	private $fontpath;

	/** @var string */
	private $secret;

	/** @var string */
	private static $title;

	public static function init(string $title){
		self::$title = $title;
		self::$iuuid = mt_rand();
	}

	public function __construct(TFAPlayer $player, Handler $handler){
		$this->player = $player;
		$this->uuid = ++self::$iuuid;
		$this->tfhandler = $handler;
		$this->fontpath = Handler::getFont();
	}

	public function getSecret() : string{
		return $this->secret ?? $this->secret = $this->tfhandler->createSecret();
	}

	private function getURL(?string $title = null) : string{
		$urlencoded = urlencode("otpauth://totp/".$this->player->getName()."?secret=".$this->secret);

		if($title !== null){
			$urlencoded .= urlencode("&issuer=".urlencode($title));
		}

		return "https://chart.googleapis.com/chart?chs=".self::WIDTH."x".self::HEIGHT."&chld=".self::BARCODE_ERROR_CORRECTION_LEVEL."|".self::BARCODE_MARGIN."&cht=qr&chl=".$urlencoded;
	}

	private function sendItem(Player $player){
		$item = Item::get(Item::FILLED_MAP);
		$item->setCustomName(TextFormat::RESET."TFA Barcode");
		$nbt = $item->getNamedTag();
		$nbt->map_uuid = new LongTag("map_uuid", $this->uuid);
		$nbt->tfa = new ByteTag('tfa', 1);
		$item->setNamedTag($nbt);
		$player->getInventory()->setItem(Handler::ITEM_HOTBAR_SLOT, $item);
	}

	private function createMap(array $colours) : ClientboundMapItemDataPacket{
		$packet = new ClientboundMapItemDataPacket();
		$packet->mapId = $this->uuid;
		$packet->type = ClientboundMapItemDataPacket::BITFLAG_TEXTURE_UPDATE;
		$packet->scale = 1;
		$packet->width = self::WIDTH;
		$packet->height = self::HEIGHT;
		$packet->colors = $colours;
		return $packet;
	}

	public function onRun(){
		$this->getSecret();

		$pk = new BatchPacket();

		$img = imagecreatefrompng($this->getURL(self::$title));

		imagettftext($img, 6, 0, 18, 18, imagecolorallocate($img, ...self::COLOUR_PALETTE[self::COLOUR_TEXT]), $this->fontpath, self::$title." 2FA QR Code");
		imagettftext($img, 6, 0, 4, 114, imagecolorallocate($img, ...self::COLOUR_PALETTE[self::COLOUR_TEXT]), $this->fontpath, "Secret Key:");
		imagettftext($img, 6, 0, 4, 124, imagecolorallocate($img, ...self::COLOUR_PALETTE[self::COLOUR_TEXT]), $this->fontpath, $this->secret);

		$palette = [
			self::COLOUR_BACKGROUND => new Color(...self::COLOUR_PALETTE[self::COLOUR_BACKGROUND]),
			self::COLOUR_BARCODE => new Color(...self::COLOUR_PALETTE[self::COLOUR_BARCODE]),
			self::COLOUR_TEXT => new Color(...self::COLOUR_PALETTE[self::COLOUR_TEXT])
		];

		for($y = 0; $y < self::HEIGHT; ++$y){
			for($x = 0; $x < self::WIDTH; ++$x){
				$rgb = imagecolorat($img, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				if($b === 255){
					$colours[$y][$x] = $palette[self::COLOUR_BACKGROUND];
				}elseif($b === 0){
					$colours[$y][$x] = $palette[self::COLOUR_BARCODE];
				}else{
					$colours[$y][$x] = $palette[self::COLOUR_TEXT];
				}
			}
		}
		imagedestroy($img);

		$pk->addPacket($this->createMap($colours));

		$pk->setCompressionLevel(self::PACKET_COMPRESSION_LEVEL);
		$pk->encode();
		$this->setResult($pk->buffer, false);
	}

	public function onCompletion(Server $server){
		$player = $server->getPluginManager()->getPlugin("2FA")->getTFAPlayerInstance($this->player);
		if($player !== null){
			$player = $player->getPlayer();
			if($player !== null){
				$pk = new BatchPacket($this->getResult());
				$pk->isEncoded = true;

				$player->dataPacket($pk);
				$player->sendMessage(implode("\n", [
					TextFormat::GREEN."We have sent you a map with your secret info.",
					TextFormat::GREEN."Enter (or scan) it into your authenticator",
					TextFormat::GREEN."application and then run ".TextFormat::GRAY."/2fa <code>"
				]));
				$this->sendItem($player);
			}
		}
	}
}
