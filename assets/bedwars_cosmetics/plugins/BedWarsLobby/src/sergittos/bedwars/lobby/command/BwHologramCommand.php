<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\lobby\BedWarsLobby;

class BwHologramCommand extends Command {

    private const VALID_STATS = ["kills", "wins", "beds_broken", "final_kills", "level"];

    public function __construct(private BedWarsLobby $plugin) {
        parent::__construct("bwhologram", "Manage leaderboard holograms", "/bwhologram <create|remove> <stat> [x] [y] [z]");
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

        $sub = strtolower($args[0] ?? "");
        $stat = strtolower($args[1] ?? "");

        if ($sub === "create") {
            if (!in_array($stat, self::VALID_STATS, true)) {
                $sender->sendMessage(TF::RED . "Invalid stat. Valid: " . implode(", ", self::VALID_STATS));
                return;
            }
            $pos = $sender->getPosition();
            $this->plugin->getLeaderboardManager()->setPosition($stat, $pos, $sender->getWorld()->getFolderName());
            $sender->sendMessage(TF::GREEN . "Leaderboard hologram '$stat' created at your position.");
            return;
        }

        if ($sub === "remove") {
            if (!in_array($stat, self::VALID_STATS, true)) {
                $sender->sendMessage(TF::RED . "Invalid stat. Valid: " . implode(", ", self::VALID_STATS));
                return;
            }
            $this->plugin->getLeaderboardManager()->removePosition($stat);
            $sender->sendMessage(TF::GREEN . "Leaderboard hologram '$stat' removed.");
            return;
        }

        $sender->sendMessage(
            TF::GOLD . "Usage:\n" .
            TF::YELLOW . "/bwhologram create <stat>\n" .
            TF::YELLOW . "/bwhologram remove <stat>\n" .
            TF::GRAY   . "Stats: " . implode(", ", self::VALID_STATS)
        );
    }
}
