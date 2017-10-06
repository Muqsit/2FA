<?php

/**
 *   ____  _____ _
 *  |___ \|  ___/ \
 *    __) | |_ / _ \
 *   / __/|  _/ ___ \
 *  |_____|_|/_/   \_\
 *
 *
 * Functions from this class may subjected to copyright.
 * This class is a modification of PHPGangsta/GoogleAuthenticator
 * @link https://github.com/PHPGangsta/GoogleAuthenticator
 * Read EXTENDEDLICENSE.md for more.
 *
*/

namespace muqsit\tfa\provider;

use muqsit\tfa\player\{TFAPlayer, TFAPlayerData};
use muqsit\tfa\provider\tasks\{RequestTFATask, UpdatePlayerTFATask};

use pocketmine\Player;
use pocketmine\utils\TextFormat;

class Handler{

	const ITEM_HOTBAR_SLOT = 3;

	const CODE_LENGTH = 6;

	const BASE32_LOOKUP_TABLE = [
		'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', //  7
		'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 15
		'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 23
		'Y', 'Z', '2', '3', '4', '5', '6', '7', // 31
		'=',  // padding char
	];

	/** @var string */
	private static $fontpath;

	/** @var int[] */
	private $requests = [];

	public function __construct(string $path){
		self::$fontpath = $path;
	}

	public static function getFont() : string{
		return self::$fontpath;
	}

	public function isProcessing(TFAPlayer $player) : bool{
		if($player instanceof Player || $player instanceof TFAPlayer){
			return isset($this->requests[$player->getId()]);
		}
		throw new \InvalidArgumentException("Argument 1 in ".get_class($this)."::getProcessSecret() must be an instance of Player or TFAPlayer, ".get_class($player)." given.");
	}

	public function getProcessSecret($player) : ?string{
		if($player instanceof Player || $player instanceof TFAPlayer){
			return $this->requests[$player->getId()] ?? null;
		}
		throw new \InvalidArgumentException("Argument 1 in ".get_class($this)."::getProcessSecret() must be an instance of Player or TFAPlayer, ".get_class($player)." given.");
	}

	public function onTFARequest(TFAPlayer $player) : bool{
		if($this->isProcessing($player)){
			$player->getPlayer()->sendMessage(TextFormat::RED."You have already started 2fa process, please enter the code given on your Google Authenticator app to finish.");
			return false;
		}

		$pmplayer = $player->getPlayer();

		if(!$pmplayer->getInventory()->getItem(self::ITEM_HOTBAR_SLOT)->isNull()){
			$pmplayer->sendMessage(TextFormat::RED."Please empty your hotbar slot #".(self::ITEM_HOTBAR_SLOT + 1)." to continue.");
			return false;
		}

		$pmplayer->getServer()->getScheduler()->scheduleAsyncTask($task = new RequestTFATask($player, $this));
		$this->requests[$player->getId()] = $task->getSecret();
		return true;
	}

	public function onCancelProcess($player){
		if($player instanceof Player || $player instanceof TFAPlayer){
			unset($this->requests[$player->getId()]);
			$player = $player->getPlayer();
			if($player !== null){
				$player->sendMessage(TextFormat::YELLOW."You have cancelled the 2FA process. You can always set it back up using /2fa.");
				$player->getInventory()->clear(self::ITEM_HOTBAR_SLOT);
			}
			return;
		}
		throw new \InvalidArgumentException("Argument 1 in ".get_class($this)."::getProcessSecret() must be an instance of Player or TFAPlayer, ".get_class($player)." given.");
	}

	public function onVerify(TFAPlayer $player){
		if(!$this->isProcessing($player)){
			return;
		}

		$pmplayer = $player->getPlayer();
		if($pmplayer !== null){
			$secret = $this->getProcessSecret($player);

			$recoveryCodes = [];
			while(!isset($recoveryCodes[4])){
				$recoveryCodes[] = random_int(10000000, 99999999);
			}

			$data = new TFAPlayerData($secret, $recoveryCodes);
			$pmplayer->getServer()->getScheduler()->scheduleAsyncTask(new UpdatePlayerTFATask($player, $data));
			$pmplayer->getInventory()->clear(self::ITEM_HOTBAR_SLOT);
			unset($this->requests[$player->getId()]);
		}
	}

	public function check(Player $player){
		$player->getServer()->getScheduler()->scheduleAsyncTask(new TFACheckTask($player));
	}

	/**
	 * Please read EXTENDEDLICENSE.md before making
	 * any changes to this function.
	 *
	 * @return string
	 */
	public function createSecret(int $secretLength = 16) : string{
		$validChars = self::BASE32_LOOKUP_TABLE;

		if($secretLength < 16 || $secretLength > 128){
			throw new \Exception('Bad secret length');
		}
		$secret = "";
		$rnd = false;
		if(function_exists('random_bytes')){
			$rnd = random_bytes($secretLength);
		}elseif(function_exists('mcrypt_create_iv')){
			$rnd = mcrypt_create_iv($secretLength, MCRYPT_DEV_URANDOM);
		}elseif(function_exists('openssl_random_pseudo_bytes')){
			$rnd = openssl_random_pseudo_bytes($secretLength, $cryptoStrong);
			if (!$cryptoStrong) {
				$rnd = false;
			}
		}
		if($rnd !== false){
			for($i = 0; $i < $secretLength; ++$i){
				$secret .= $validChars[ord($rnd[$i]) & 31];
			}
		}else{
			throw new \Exception('No source of secure random');
		}

		return $secret;
	}

	/**
	 * Please read EXTENDEDLICENSE.md before making
	 * any changes to this function.
	 *
	 * @return string
	 */
	public function getCode(string $secret, float $timeSlice = null) : string{
		if($timeSlice === null){
			$timeSlice = floor(time() / 30);
		}

		$secretkey = $this->_base32Decode($secret);

		$time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
		$hm = hash_hmac('SHA1', $time, $secretkey, true);
		$offset = ord(substr($hm, -1)) & 0x0F;
		$hashpart = substr($hm, $offset, 4);

		$value = unpack('N', $hashpart);
		$value = $value[1];
		$value = $value & 0x7FFFFFFF;

		$modulo = pow(10, self::CODE_LENGTH);

		return str_pad($value % $modulo, self::CODE_LENGTH, '0', STR_PAD_LEFT);
	}

	/**
	 * Please read EXTENDEDLICENSE.md before making
	 * any changes to this function.
	 *
	 * @return bool
	 */
	public function verifyCode(string $secret, string $code, int $discrepancy = 1, float $currentTimeSlice = null) : bool{
		if($currentTimeSlice === null){
			$currentTimeSlice = floor(time() / 30);
		}

		if(strlen($code) !== self::CODE_LENGTH){
			return false;
		}

		for($i = -$discrepancy; $i <= $discrepancy; ++$i){
			$calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
			if($this->timingSafeEquals($calculatedCode, $code)){
				return true;
			}
		}

		return false;
	}

	/**
	 * Please read EXTENDEDLICENSE.md before making
	 * any changes to this function.
	 *
	 * @return string|bool
	 */
	private function _base32Decode(string $secret){
		if(empty($secret)){
			return "";
		}

		$base32chars = self::BASE32_LOOKUP_TABLE;
		$base32charsFlipped = array_flip($base32chars);

		$paddingCharCount = substr_count($secret, $base32chars[32]);
		$allowedValues = [6, 4, 3, 1, 0];
		if(!in_array($paddingCharCount, $allowedValues)){
			return false;
		}
		for($i = 0; $i < 4; ++$i){
			if($paddingCharCount == $allowedValues[$i] &&
				substr($secret, -($allowedValues[$i])) != str_repeat($base32chars[32], $allowedValues[$i])){
				return false;
			}
		}
		$secret = str_replace('=', "", $secret);
		$secret = str_split($secret);
		$binaryString = "";
		for($i = 0; $i < count($secret); $i += 8){
			$x = "";
			if(!in_array($secret[$i], $base32chars)){
				return false;
			}
			for($j = 0; $j < 8; ++$j){
				$x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
			}
			$eightBits = str_split($x, 8);
			for($z = 0; $z < count($eightBits); ++$z){
				$binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : "";
			}
		}

		return $binaryString;
	}

	/**
	 * Please read EXTENDEDLICENSE.md before making
	 * any changes to this function.
	 *
	 * @return bool
	 */
	private function timingSafeEquals(string $safeString, string $userString) : bool{
		if(function_exists('hash_equals')){
			return hash_equals($safeString, $userString);
		}
		$safeLen = strlen($safeString);
		$userLen = strlen($userString);

		if($userLen != $safeLen){
			return false;
		}

		$result = 0;

		for($i = 0; $i < $userLen; ++$i){
			$result |= (ord($safeString[$i]) ^ ord($userString[$i]));
		}

		return $result === 0;
	}
}
