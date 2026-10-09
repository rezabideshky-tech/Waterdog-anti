<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\lobby\BedWarsLobby;

class LbSpawnCommand extends Command {

    private const VALID_STATS = ["kills", "wins", "finalkills", "deaths", "bedbroken", "level", "coins", "winstreak"];

    public function __construct(private BedWarsLobby $plugin) {
        parent::__construct("lbspawn", "Spawn a leaderboard hologram", "/lbspawn <kills|wins|finalkills|deaths|bedbroken|level|coins|winstreak>");
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

        $stat = strtolower($args[0] ?? "");
        if (!in_array($stat, self::VALID_STATS, true)) {
            $sender->sendMessage(TF::RED . "Invalid stat. Valid: " . implode(", ", self::VALID_STATS));
            return;
        }

        // تبدیل نام stat به نام ستون دیتابیس
        $column = match($stat) {
            "finalkills" => "final_kills",
            "bedbroken"  => "beds_broken",
            "winstreak"  => "win_streak",
            default      => $stat,
        };

        $this->plugin->getLobbyManager()->addLeaderboardHologram($sender, $column);
    }
}