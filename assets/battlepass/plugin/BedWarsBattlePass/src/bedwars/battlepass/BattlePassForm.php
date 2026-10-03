<?php
declare(strict_types=1);

namespace bedwars\battlepass;

use pocketmine\form\Form;
use pocketmine\player\Player;

final class BattlePassForm implements Form{
	public function jsonSerialize() : array{
		return [
			"type" => "form",
			"title" => "§l§cBed§9Wars §6Battle Pass §7- Season 1",
			"content" => "§eUnlock exclusive cosmetics by playing BedWars!\n§7Win games and break beds to earn XP.",
			"buttons" => [
				["text" => "§l§aFree Rewards\n§r§7Tier 1 - 30"],
				["text" => "§l§6Premium Pass\n§r§7Kill effects, bed skins, titles"],
				["text" => "§l§bDaily Quests\n§r§7Earn bonus XP"],
				["text" => "§cClose"],
			],
		];
	}

	public function handleResponse(Player $player, $data) : void{
		if(!is_int($data) || $data === 3) return;
		$player->sendMessage(match($data){
			0 => "§aFree rewards: coming soon!",
			1 => "§6Get the Premium Pass at our store!",
			default => "§bDaily quests: coming soon!",
		});
	}
}
