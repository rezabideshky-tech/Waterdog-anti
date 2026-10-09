<?php

declare(strict_types=1);

namespace sergittos\bedwars\gui;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\game\stage\StartingStage;
use sergittos\bedwars\game\stage\WaitingStage;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\game\team\TeamSelectionManager;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\SessionFactory;
use function count;
use function implode;
use function min;
use function strtolower;
use function strtoupper;
use function ucfirst;

final class TeamSelectorGui{

    private const TEAM_SLOTS = [10, 12, 14, 16, 19, 21, 23, 25];

    public function open(Player $player) : void{
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        $game = $session->getGame();

        if($game === null || !$session->isPlaying()){
            $session->message("{RED}{BOLD}Team Selector Locked{RESET}{GRAY} - Join a match first.");
            return;
        }

        $stage = $game->getStage();
        if(!($stage instanceof WaitingStage) && !($stage instanceof StartingStage)){
            $session->message("{RED}{BOLD}Team Selector Locked{RESET}{GRAY} - The match is already in progress.");
            return;
        }

        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
        $menu->setName(TF::BOLD . TF::AQUA . "Team Selector");

        $inv = $menu->getInventory();

        $filler = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY())->asItem();
        $filler->setCustomName(" ");
        for($i = 0; $i < 27; $i++){
            $inv->setItem($i, $filler);
        }

        $inv->setItem(4, $this->makeRandomButton());

        $playersPerTeam = $game->getMap()->getPlayersPerTeam();
        $mySelected = TeamSelectionManager::get($session);

        $slotTeams = [];
        $teams = $game->getTeams();

        foreach($teams as $index => $team){
            $slot = self::TEAM_SLOTS[$index] ?? null;
            if($slot === null){
                continue;
            }

            $count = TeamSelectionManager::countSelected($game, $team->getName());
            $isMine = $mySelected !== null && strtolower($mySelected) === strtolower($team->getName());

            $icon = VanillaBlocks::WOOL()->setColor($team->getDyeColor())->asItem();
            $icon->setCustomName($team->getColor() . $team->getName() . TF::WHITE . " (" . TF::YELLOW . $count . TF::WHITE . "/" . TF::YELLOW . $playersPerTeam . TF::WHITE . ")");

            $list = TeamSelectionManager::listSelected($game, $team->getName());
            $names = $this->formatSelectedNames($list, $playersPerTeam);

            $lore = [];
            $lore[] = TF::GRAY . ($names === "" ? "Nobody" : $names);
            $lore[] = "";
            if($count >= $playersPerTeam && !$isMine){
                $lore[] = TF::RED . "Team is full";
            }else{
                $lore[] = $isMine ? (TF::GREEN . "Selected") : (TF::YELLOW . "Click to select");
            }

            $icon->setLore($lore);
            $icon->getNamedTag()->setString("bw_team_pick", $team->getName());

            $inv->setItem($slot, $icon);
            $slotTeams[$slot] = $team;
        }

        $menu->setListener(function(InvMenuTransaction $tx) use ($session, $player, $slotTeams, $playersPerTeam) : InvMenuTransactionResult{
            $item = $tx->getItemClicked();
            $tag = $item->getNamedTag();

            if($tag->getTag("bw_team_random") !== null){
                TeamSelectionManager::set($session, null);
                $this->applySelectorItem($session, null);
                $this->applyWaitingNametag($session, null);
                $session->message("{GREEN}{BOLD}Selection Cleared{RESET}{GRAY} - You will be assigned automatically.");
                $player->removeCurrentWindow();
                $this->open($player);
                return $tx->discard();
            }

            if($tag->getTag("bw_team_pick") === null){
                return $tx->discard();
            }

            $teamName = $tag->getString("bw_team_pick");
            $team = null;
            foreach($slotTeams as $t){
                if(strtolower($t->getName()) === strtolower($teamName)){
                    $team = $t;
                    break;
                }
            }

            if($team === null){
                $session->message("{RED}{BOLD}Selection Failed{RESET}{GRAY} - Team not found.");
                return $tx->discard();
            }

            $count = TeamSelectionManager::countSelected($session->getGame(), $team->getName());
            $mine = TeamSelectionManager::get($session);
            $isMine = $mine !== null && strtolower($mine) === strtolower($team->getName());

            if($count >= $playersPerTeam && !$isMine){
                $session->message("{RED}{BOLD}Team Full{RESET}{GRAY} - Choose another team.");
                return $tx->discard();
            }

            TeamSelectionManager::set($session, $team->getName());
            $this->applySelectorItem($session, $team);
            $this->applyWaitingNametag($session, $team);

            $session->message("{GREEN}{BOLD}Team Selected{RESET}{GRAY} - You selected " . $team->getColoredName() . "{GRAY}.");
            $player->removeCurrentWindow();
            $this->open($player);

            return $tx->discard();
        });

        $menu->send($player);
    }

    private function makeRandomButton(){
        $item = VanillaItems::NETHER_STAR();
        $item->setCustomName(TF::GREEN . TF::BOLD . "Random Team");
        $item->setLore([
            TF::GRAY . "Clear your selection",
            "",
            TF::YELLOW . "Click to clear"
        ]);
        $item->getNamedTag()->setByte("bw_team_random", 1);
        return $item;
    }

    private function applySelectorItem(Session $session, ?Team $team) : void{
        $inv = $session->getPlayer()->getInventory();

        if($team === null){
            $inv->setItem(0, BedwarsItems::TEAM_SELECTOR()->setColor(DyeColor::WHITE())->asItem());
            return;
        }

        $inv->setItem(0, BedwarsItems::TEAM_SELECTOR()->setColor($team->getDyeColor())->asItem());
    }

    private function applyWaitingNametag(Session $session, ?Team $team): void{
        $player = $session->getPlayer();

        if($team === null){
            $session->updateNametag();
            return;
        }

        $player->setNameTag($team->getFormattedNametag($session));
        $player->setNameTagAlwaysVisible();
    }

    private function formatSelectedNames(array $list, int $limit) : string{
        if(empty($list)){
            return "";
        }

        $names = [];
        $max = min(count($list), $limit);
        for($i = 0; $i < $max; $i++){
            $names[] = ucfirst((string) $list[$i]);
        }

        return implode(", ", $names);
    }
}