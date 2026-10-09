<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\entity\PlayBedwarsEntity;
use sergittos\bedwars\utils\GameUtils;

/**
 * دستور حذف NPC های "ورود به مود" (Solo/Double/Triple/Squad) که با
 * SpawnJoinEntityCommand توی لابی اسپان شدن.
 *
 * /deletejoinentity nearest        -> نزدیک‌ترین NPC به خودِ پلیر رو حذف می‌کنه
 * /deletejoinentity <mode>         -> همه‌ی NPC های اون مود توی دنیای فعلی رو حذف می‌کنه
 * /deletejoinentity all            -> همه‌ی NPC های ورود به مود رو توی دنیای فعلی حذف می‌کنه
 * /deletejoinentity list           -> لیست NPC های موجود توی دنیای فعلی رو نشون می‌ده
 */
class DeleteJoinEntityCommand extends Command {

    private const MODES = ["solo" => 1, "double" => 2, "triple" => 3, "squad" => 4];

    public function __construct() {
        parent::__construct(
            "deletejoinentity",
            "Remove join-mode NPCs spawned in the lobby",
            "/deletejoinentity <nearest|list|all|solo|double|triple|squad>",
            ["removejoinentity", "deljoinentity"]
        );
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

        $target = strtolower($args[0] ?? "nearest");
        $world = $sender->getWorld();

        /** @var PlayBedwarsEntity[] $npcs */
        $npcs = [];
        foreach ($world->getEntities() as $entity) {
            if ($entity instanceof PlayBedwarsEntity) {
                $npcs[] = $entity;
            }
        }

        if (empty($npcs)) {
            $sender->sendMessage(TF::YELLOW . "There are no join-mode NPCs in this world.");
            return;
        }

        if ($target === "list") {
            $sender->sendMessage(TF::AQUA . "Join NPCs in this world (" . count($npcs) . "):");
            foreach ($npcs as $npc) {
                $pos = $npc->getPosition();
                $sender->sendMessage(TF::GRAY . "- " . GameUtils::getMode($npc->getPlayersPerTeam()) .
                    TF::GRAY . " @ " . round($pos->getX(), 1) . ", " . round($pos->getY(), 1) . ", " . round($pos->getZ(), 1));
            }
            return;
        }

        if ($target === "all") {
            $count = count($npcs);
            foreach ($npcs as $npc) {
                $npc->flagForDespawn();
            }
            $sender->sendMessage(TF::GREEN . "Removed " . $count . " join-mode NPC(s) from this world.");
            return;
        }

        if ($target === "nearest") {
            $closest = null;
            $closestDistance = null;
            foreach ($npcs as $npc) {
                $distance = $npc->getPosition()->distance($sender->getPosition());
                if ($closestDistance === null || $distance < $closestDistance) {
                    $closestDistance = $distance;
                    $closest = $npc;
                }
            }
            if ($closest === null) {
                $sender->sendMessage(TF::YELLOW . "There are no join-mode NPCs in this world.");
                return;
            }
            $mode = GameUtils::getMode($closest->getPlayersPerTeam());
            $closest->flagForDespawn();
            $sender->sendMessage(TF::GREEN . "Removed the nearest join-mode NPC (" . $mode . TF::GREEN . ").");
            return;
        }

        if (isset(self::MODES[$target])) {
            $playersPerTeam = self::MODES[$target];
            $count = 0;
            foreach ($npcs as $npc) {
                if ($npc->getPlayersPerTeam() === $playersPerTeam) {
                    $npc->flagForDespawn();
                    $count++;
                }
            }
            if ($count === 0) {
                $sender->sendMessage(TF::YELLOW . "No '" . $target . "' join-mode NPCs found in this world.");
                return;
            }
            $sender->sendMessage(TF::GREEN . "Removed " . $count . " '" . $target . "' join-mode NPC(s).");
            return;
        }

        $sender->sendMessage(TF::RED . "Usage: /deletejoinentity <nearest|list|all|solo|double|triple|squad>");
    }
}
