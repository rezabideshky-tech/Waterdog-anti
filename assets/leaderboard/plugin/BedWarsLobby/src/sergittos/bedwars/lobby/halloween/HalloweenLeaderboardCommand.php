<?php
declare(strict_types=1);

namespace sergittos\bedwars\lobby\halloween;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\lobby\BedWarsLobby;

/**
 * /bwhalloween on|off|respawn|info — مدیریت دکور هالووینی لیدربوردها
 *   on/off   : روشن/خاموش کردن پایه‌ها (توی config.yml پلاگین ذخیره می‌شه)
 *   respawn  : همه‌ی پایه‌های ثبت‌شده را از نو می‌سازه
 *   info     : تعداد پایه‌های فعال + لیست استت‌ها
 */
final class HalloweenLeaderboardCommand extends Command {

    public function __construct(private BedWarsLobby $plugin) {
        parent::__construct(
            "bwhalloween",
            "Manage the Halloween leaderboard pedestals",
            "/bwhalloween <on|off|respawn|info>"
        );
        $this->setPermission("bedwars.admin");
        $this->setAliases(["bwlbdecor", "lbcrown"]);
    }

    public function execute(CommandSender $sender, string $label, array $args) : void{
        $manager = $this->plugin->getLobbyManager()->getPedestals();
        $sub = strtolower($args[0] ?? "info");

        switch($sub) {
            case "on":
                $manager->setActive(true);
                $sender->sendMessage(TF::GREEN . "دکور هالووینی لیدربوردها روشن شد.");
                break;
            case "off":
                $manager->setActive(false);
                $sender->sendMessage(TF::YELLOW . "دکور هالووینی خاموش شد (متن لیدربوردها دست‌نخورده می‌مونه).");
                break;
            case "respawn":
                $n = $manager->respawnAll();
                $sender->sendMessage(TF::GREEN . "دوباره ساخته شد: $n پایه.");
                break;
            default:
                $sender->sendMessage(TF::GOLD . "Halloween leaderboard decor");
                $sender->sendMessage(TF::GRAY . "- enabled: " . ($manager->isEnabled() ? TF::GREEN . "yes" : TF::RED . "no"));
                $sender->sendMessage(TF::GRAY . "- pedestals spawned: " . TF::AQUA . count($manager->getSpawned()));
                $sender->sendMessage(TF::GRAY . "- variants: " . implode(", ", array_keys(HalloweenLeaderboardManager::VARIANTS)));
                if($sender instanceof Player) {
                    $sender->sendMessage(TF::GRAY . "- Y_OFFSET: " . HalloweenPedestal::Y_OFFSET . " (پایه "
                        . HalloweenPedestal::Y_OFFSET . " بلاک زیر نقطه‌ی /lbspawn)");
                }
                break;
        }
    }
}
