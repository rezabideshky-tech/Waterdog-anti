<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\battlepass\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use sergittos\bedwars\lobby\battlepass\data\PlayerBattlePassDataManager;
use sergittos\bedwars\lobby\battlepass\menu\BattlePassMenu;
use sergittos\bedwars\lobby\battlepass\registry\BattlePassRegistry;

final class BattlePassCommand extends Command{

    public function __construct(
        private BattlePassRegistry $registry,
        private PlayerBattlePassDataManager $data
    ){
        parent::__construct("battlepass", "Open the Battle Pass", "/battlepass", ["bp", "pass"]);
        $this->setPermission("battlepass.use");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void{
        if(!$sender instanceof Player){
            return;
        }
        BattlePassMenu::openMain($sender, $this->registry, $this->data);
    }
}
