<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\profile;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use function array_map;
use function implode;
use function array_slice;
use function strtolower;
use function trim;
use function date;
use function number_format;

/** /bwseason info | new [name] - staff tools for the ranked seasons (ranks reset when a new season starts). */
final class SeasonCommand extends Command{

    public function __construct(){
        parent::__construct("bwseason", "Manage BedWars ranked seasons", "/bwseason <info|new> [name]", []);
        $this->setPermission("bedwars.admin");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if(!$this->testPermission($sender)){
            return;
        }
        $svc = BedWarsCore::getInstance()->getProfileService();
        $sub = strtolower($args[0] ?? "info");

        if($sub === "info"){
            $sizes = $svc->sizes();
            $ends = $svc->getSeasonEnds();
            $sender->sendMessage(TF::LIGHT_PURPLE . "Season: " . TF::WHITE . $svc->getSeasonLabel() . TF::GRAY . " (id " . $svc->getSeasonId() . ")");
            $sender->sendMessage(TF::GRAY . "Ends: " . TF::WHITE . ($ends > 0 ? date("Y-m-d H:i", $ends) : "manual (/bwseason new)"));
            $sender->sendMessage(TF::GRAY . "RP per division (Bronze..Grandmaster): " . TF::WHITE . implode(", ", array_map(static fn(int $s) : string => number_format($s), $sizes)));
            return;
        }

        if($sub === "new"){
            $name = trim(implode(" ", array_slice($args, 1)));
            $sender->sendMessage(TF::YELLOW . "Starting a new season - every rank is archived and reset...");
            $svc->startNewSeason($name !== "" ? $name : null, static function(bool $ok, int $id) use ($sender) : void{
                $sender->sendMessage($ok ? TF::GREEN . "Season " . $id . " started. All ranks were reset." : TF::RED . "Could not start a new season (another server may have just done it).");
            });
            return;
        }

        $sender->sendMessage(TF::YELLOW . "Usage: /bwseason info | /bwseason new [name]");
    }
}
