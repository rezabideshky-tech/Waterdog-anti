<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\challenges\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use sergittos\bedwars\lobby\challenges\data\PlayerChallengeDataManager;
use sergittos\bedwars\lobby\challenges\menu\ChallengesMenu;
use sergittos\bedwars\lobby\challenges\registry\ChallengeRegistry;

final class ChallengesCommand extends Command{

    public function __construct(
        private ChallengeRegistry $registry,
        private PlayerChallengeDataManager $data
    ){
        parent::__construct("challenges", "Open Challenges", "/challenges", ["ch"]);
        $this->setPermission("challenges.use");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void{
        if(!$sender instanceof Player){
            return;
        }
        ChallengesMenu::openMain($sender, $this->registry, $this->data);
    }
}