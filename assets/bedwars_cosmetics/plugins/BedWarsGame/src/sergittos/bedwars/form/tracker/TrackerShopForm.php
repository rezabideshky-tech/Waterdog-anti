<?php

declare(strict_types=1);

namespace sergittos\bedwars\form\tracker;

use pocketmine\player\Player;
use sergittos\bedwars\form\SimpleForm;
use sergittos\bedwars\game\shop\tracker\TrackerProduct;
use sergittos\bedwars\gui\ShopPurchaseHelper;
use sergittos\bedwars\libs\EasyUI\element\Button;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\SessionFactory;

final class TrackerShopForm extends SimpleForm{

    public function __construct(private Session $session){
        parent::__construct("TRACKER SHOP");
    }

    protected function onCreation(): void{
        $game = $this->session->getGame();
        $myTeam = $this->session->getTeam();

        if($game === null || $myTeam === null){
            return;
        }

        $this->setHeaderText("§b§lChoose a team to track §r§7- §e§l2 Emeralds§r§7 each");

        $anyAvailable = false;

        foreach($game->getTeams() as $team){
            if($team->getName() === $myTeam->getName()){
                continue;
            }

            // Skip teams that no longer exist in this match / have already
            // been eliminated - nothing to track there, and listing them just
            // clutters the menu with options that will fail on purchase.
            if(!$team->isAlive()){
                continue;
            }

            $anyAvailable = true;

            $color = $team->getColor();
            $bedStatus = $team->isBedDestroyed() ? "§c§lBed Destroyed" : "§a§lBed Intact";

            $product = new TrackerProduct($team->getName(), "Track Team " . $team->getName());
            $text = $color . "§l" . strtoupper($team->getName()) . " §r§7Team\n" .
                $bedStatus . "\n" .
                "§7Cost: §a§l" . $product->getPrice() . " Emeralds";

            $btn = new Button($text);
            $btn->setSubmitListener(function(Player $player) use ($product): void{
                if(!SessionFactory::hasSession($player)){
                    return;
                }
                $session = SessionFactory::getSession($player);
                ShopPurchaseHelper::attemptPurchase($player, $session, $product);
                $player->sendForm(new self($session));
            });

            $this->addButton($btn);
        }

        if(!$anyAvailable){
            $this->setHeaderText("§b§lTracker Shop\n§r§7No trackable teams remain right now.");
        }
    }
}