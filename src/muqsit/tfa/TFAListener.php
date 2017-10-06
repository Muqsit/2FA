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

use muqsit\tfa\provider\Handler;

use pocketmine\event\{EventPriority, Listener};
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\{PlayerCommandPreprocessEvent, PlayerDataSaveEvent, PlayerInteractEvent, PlayerLoginEvent, PlayerMoveEvent, PlayerQuitEvent};
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\Player;
use pocketmine\plugin\MethodEventExecutor;
use pocketmine\utils\TextFormat;

class TFAListener implements Listener{

	/** @var TFA */
	private $plugin;

	public function __construct(TFA $plugin){
		$this->plugin = $plugin;

		$this->registerHandler(InventoryTransactionEvent::class, "onInventoryTransaction", EventPriority::HIGH, false);
		$this->registerHandler(PlayerCommandPreprocessEvent::class, "onPlayerCommandPreprocess", EventPriority::HIGH, false);

		if($plugin->getServer()->shouldSavePlayerData()){
			$this->registerHandler(PlayerDataSaveEvent::class, "onPlayerDataSave", EventPriority::HIGH, false);
		}

		$this->registerHandler(PlayerInteractEvent::class, "onPlayerInteract", EventPriority::HIGH, false);
		$this->registerHandler(PlayerLoginEvent::class, "onPlayerLogin", EventPriority::HIGH, false);

		if($plugin->shouldCancelRotation()){
			$this->registerHandler(PlayerMoveEvent::class, "onPlayerMove", EventPriority::HIGH, false);
		}

		$this->registerHandler(PlayerQuitEvent::class, "onPlayerQuit", EventPriority::HIGH, false);
	}

	private function registerHandler(string $event, string $method, int $priority, bool $ignoreCancelled){
		assert(is_callable([$this, $method]), "Attempt to register nonexistent event handler ".static::class."::$method");
		$this->plugin->getServer()->getPluginManager()->registerEvent($event, $this, $priority, new MethodEventExecutor($method), $this->plugin, $ignoreCancelled);
	}

	public function onPlayerDataSave(PlayerDataSaveEvent $event){
		$player = $event->getPlayer();
		if($player instanceof Player){//$player is OfflinePlayer when the event is called while player is joining the server.
			if($this->plugin->getProvider()->getHandler()->isProcessing($this->plugin->getTFAPlayer($player))){
				$nbt = $event->getSaveData();
				if(isset($nbt->Inventory)){
					foreach($nbt->Inventory as $i => $item){
						if(isset($item->tag->tfa)){
							unset($nbt->Inventory->{$i});
							$event->setSaveData($nbt);
							break;
						}
					}
				}
			}
		}
	}

	public function onPlayerMove(PlayerMoveEvent $event){
		$from = $event->getFrom();
		$to = $event->getTo();
		if($from->yaw != $to->yaw || $from->pitch != $to->pitch){
			$event->setCancelled($this->plugin->getTFAPlayer($event->getPlayer())->isLocked());
		}
	}

	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event){
		$player = $event->getPlayer();
		$tfaplayer = $this->plugin->getTFAPlayer($player);
		if($tfaplayer->isLocked()){
			$event->setCancelled();
			if($this->plugin->getProvider()->verify($tfaplayer, $event->getMessage())){
				$tfaplayer->tfaLock(false);
				$player->sendMessage(TextFormat::GREEN."2FA code has been successfully verified.");
				return;
			}
			$player->sendMessage("\n".TextFormat::RED."Please enter the code in your Google Authenticator app. You can alternatively use your recovery codes if you do not have access to your 2FA device.\n ");
		}
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		$event->setCancelled(isset($event->getItem()->getNamedTag()->tfa));
	}

	public function onPlayerLogin(PlayerLoginEvent $event){
		$this->plugin->addTFAPlayer($event->getPlayer());
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		$this->plugin->removeTFAPlayer($event->getPlayer());
	}

	public function onInventoryTransaction(InventoryTransactionEvent $event){
		$transaction = $event->getTransaction();
		$player = $transaction->getSource();
		if($player !== null){
			$tfaplayer = $this->plugin->getTFAPlayer($player);
			if($tfaplayer->isLocked()){
				$event->setCancelled();
				return;
			}
			if($this->plugin->getProvider()->getHandler()->isProcessing($tfaplayer)){
				foreach($transaction->getActions() as $action){
					if($action instanceof SlotChangeAction && $action->getSlot() === Handler::ITEM_HOTBAR_SLOT){
						$player->sendMessage(TextFormat::RED."You cannot interact with this during the 2FA process. Use /2fa cancel to cancel the process.");
						$event->setCancelled();
						break;
					}
				}
			}
		}
	}
}
