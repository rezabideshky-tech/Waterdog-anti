<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\entity\PlayBedwarsEntity;

class SpawnJoinEntityCommand extends Command {

    private const MODES = [
        "solo"   => 1,
        "double" => 2,
        "triple" => 3,
        "squad"  => 4,
    ];

    public function __construct() {
        parent::__construct("spawnjoinentity", "Spawn a PlayBedwarsEntity", "/spawnjoinentity <solo|double|triple|squad>");
        $this->setPermission("bedwars.admin");
    }

    public function execute(CommandSender $sender, string $label, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . "Use in-game only.");
            return;
        }
        if (!$sender->hasPermission("bedwars.admin")) {
            $sender->sendMessage(TF::RED . "No permission.");
            return;
        }

        $mode = strtolower($args[0] ?? "");
        if (!isset(self::MODES[$mode])) {
            $sender->sendMessage(TF::RED . "Invalid mode. Valid: solo, double, triple, squad");
            return;
        }

        $playersPerTeam = self::MODES[$mode];

        // از موقعیت + جهت دقیق پلیر استفاده می‌کنیم (yaw/pitch واقعی)، نه 0,0
        $loc = $sender->getLocation();
        $location = new Location($loc->getX(), $loc->getY(), $loc->getZ(), $sender->getWorld(), $loc->getYaw(), $loc->getPitch());

        $nbt = \pocketmine\nbt\tag\CompoundTag::create()
            ->setInt("players_per_team", $playersPerTeam)
            ->setString("CustomName", "");

        // استفاده از Skin بازیکن (به جای CompoundTag خالی)
        $entity = new PlayBedwarsEntity($location, $sender->getSkin(), $nbt);
        $entity->spawnToAll();

        $sender->sendMessage(TF::GREEN . "PlayEntity spawned with mode '$mode'.");
    }
}