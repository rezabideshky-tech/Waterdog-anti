<?php

declare(strict_types=1);

namespace sergittos\bedwars\gui;

use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\sound\NoteInstrument;
use pocketmine\world\sound\NoteSound;
use sergittos\bedwars\game\shop\Product;
use sergittos\bedwars\session\Session;

final class ShopPurchaseHelper{

    public static function attemptPurchase(Player $player, Session $session, Product $product) : bool{
        if(!$product->canBePurchased($session)){
            $session->message("§c§lPurchase Failed§r §7- You already own this.");
            $player->broadcastSound(new NoteSound(NoteInstrument::BASS_DRUM(), 10), [$player]);
            return false;
        }

        $ore = $product->getOre();
        $inv = $player->getInventory();

        $required = $ore->getCount();
        $have = 0;
        foreach($inv->all($ore) as $it){
            $have += $it->getCount();
        }

        if($have < $required){
            $missing = $required - $have;
            $session->message("§c§lNot Enough Resources§r §7- Missing §e{$missing} §7" . self::oreLabel($ore) . ".");
            $player->broadcastSound(new NoteSound(NoteInstrument::BASS_DRUM(), 10), [$player]);
            return false;
        }

        if(!$product->onPurchase($session)){
            $session->message("§c§lPurchase Failed§r §7- Inventory full.");
            $player->broadcastSound(new NoteSound(NoteInstrument::BASS_DRUM(), 10), [$player]);
            return false;
        }

        $inv->removeItem($ore);

        $session->message("§a§lPurchased§r §7- §b" . TF::clean($product->getName()) . "§7.");
        $player->broadcastSound(new NoteSound(NoteInstrument::PLING(), 10), [$player]);

        return true;
    }

    private static function oreLabel(Item $ore) : string{
        return match($ore->getTypeId()){
            ItemTypeIds::IRON_INGOT => "Iron",
            ItemTypeIds::GOLD_INGOT => "Gold",
            ItemTypeIds::DIAMOND => "Diamond",
            ItemTypeIds::EMERALD => "Emerald",
            default => "Resource",
        };
    }
}