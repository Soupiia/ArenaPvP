<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace sys\arenapvp\utils;


use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\level\sound\LaunchSound;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;

/*
 * Message to all, this is here because Pocketmine doesn't support the Infinity enchantment.
 */
class CustomBow extends Tool {

	public function onReleaseUsing(Player $player) : bool{
		/** @var Item $arrow */
		$arrow = null;
		if(($index = $player->getInventory()->first(self::get(self::ARROW, -1, 1))) === -1){
			if($player->isSurvival()){
				return false;
			}else {
				$arrow = Item::get(Item::ARROW, 0, 1); //Default to a normal arrow for creative
			}
		}else{ //TODO: check offhand slot first (MCPE 1.1)
			$arrow = $player->getInventory()->getItem($index);
			$arrow->setCount(1);
		}

		//TODO: add effects for tipped arrows

		$vector = $player->getDirectionVector();

		$nbt = new CompoundTag("", [
			new ListTag("Pos", [
				new DoubleTag("", $player->x),
				new DoubleTag("", $player->y + $player->getEyeHeight() + 0.25),
				new DoubleTag("", $player->z)
			]),
			new ListTag("Motion", [
				new DoubleTag("", $vector->x),
				new DoubleTag("", $vector->y),
				new DoubleTag("", $vector->z)
			]),
			new ListTag("Rotation", [
				new FloatTag("", ($player->yaw > 180 ? 360 : 0) - $player->yaw),
				new FloatTag("", -$player->pitch)
			]),
			new ShortTag("Fire", $player->isOnFire() ? 45 * 60 : 0)
			//TODO: add Power and Flame enchantment effects
		]);

		$diff = $player->getItemUseDuration();
		$p = $diff / 20;
		$f = min((($p ** 2) + $p * 2) / 3, 1) * 2;
		$ev = new EntityShootBowEvent($player, $this, Entity::createEntity("Arrow", $player->getLevel(), $nbt, $player, $f == 2), $f);

		if($f < 0.05 or $diff < 7){
			$ev->setCancelled();
		}

		$player->getServer()->getPluginManager()->callEvent($ev);

		if($ev->isCancelled()){
			$ev->getProjectile()->close();
			return false;
		}else{
			$player->getLevel()->broadcastLevelSoundEvent($player, LevelSoundEventPacket::SOUND_BOW);
			$ev->getProjectile()->setMotion($ev->getProjectile()->getMotion()->multiply($ev->getForce()));
			if($player->isSurvival()) {
				if(!$this->hasEnchantment(Enchantment::INFINITY)) {
					$player->getInventory()->removeItem($arrow);
				}
				$this->meta++;
			}
			if($ev->getProjectile() instanceof Projectile){
				$player->getServer()->getPluginManager()->callEvent($projectileEv = new ProjectileLaunchEvent($ev->getProjectile()));
				if($projectileEv->isCancelled()){
					$ev->getProjectile()->close();
				}else{
					$ev->getProjectile()->spawnToAll();
					$player->getLevel()->addSound(new LaunchSound($player), $player->getViewers());
				}
			}else{
				$ev->getProjectile()->spawnToAll();
			}
		}

		return true;
	}
}