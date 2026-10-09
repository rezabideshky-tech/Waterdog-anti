<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\profile;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function trim;

/** /profile [player] - opens your BedWars profile (or somebody else's). */
final class ProfileCommand extends Command{

    public function __construct(){
        parent::__construct("profile", "Open your BedWars profile, rank, medals and achievements", "/profile [player]", ["bwprofile", "pf"]);
        $this->setPermission("bedwars.play");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if(!$sender instanceof Player){
            $sender->sendMessage("This command can only be used in game.");
            return;
        }
        if(!$this->testPermission($sender)){
            return;
        }
        $name = trim($args[0] ?? "");
        if($name === ""){
            ProfileMenu::open($sender, $sender->getName());
            return;
        }
        $online = $sender->getServer()->getPlayerByPrefix($name);
        ProfileMenu::open($sender, $online !== null ? $online->getName() : $name);
    }
}
