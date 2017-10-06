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

class TFAPlayerData{

	/** @var string|null */
	private $secret;

	/** @var array|null */
	private $recovery;

	public function __construct(?string $secret = null, ?array $recovery = null){
		$this->secret = $secret;
		$this->recovery = $recovery;
	}

	public function exists() : bool{
		return $this->secret !== null && $this->recovery !== null;
	}

	public function getSecret() : ?string{
		return $this->secret;
	}

	public function getRecoveryCodes() : ?array{
		return $this->recovery;
	}

	public function setSecret(string $secret){
		if(strlen($secret) !== 16){
			throw new \InvalidArgumentException("Secret must be 16-digit in length, got ".strlen($secret)." digit secret.");
		}
		$this->secret = $secret;
	}

	public function setRecoveryCodes(array $codes){
		foreach($codes as $code){
			if(ceil(log10($code)) !== 8.0){
				throw new \InvalidArgumentException("Recovery codes must be 8-digit in length, got ".ceil(log10($code))." digit code.");
			}
		}
		$this->recovery = $codes;
	}
}
