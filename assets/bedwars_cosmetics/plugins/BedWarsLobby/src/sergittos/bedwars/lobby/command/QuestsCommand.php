<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use sergittos\bedwars\lobby\quests\data\PlayerQuestDataManager;
use sergittos\bedwars\lobby\quests\menu\QuestsMenu;

final class QuestsCommand extends Command{

    public function __construct(private PlayerQuestDataManager $data){
        parent::__construct("quest", "Open BedWars Quests", "/quest", ["quests"]);
        $this->setPermission("bedwars.play");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if(!$sender instanceof Player){
            return;
        }
        if(!$this->testPermission($sender)){
            return;
        }
        QuestsMenu::openMain($sender, $this->data);
    }
}