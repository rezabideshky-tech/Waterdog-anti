<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\battlepass\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use sergittos\bedwars\lobby\battlepass\data\PlayerBattlePassDataManager;
use sergittos\bedwars\lobby\battlepass\registry\BattlePassRegistry;
use sergittos\bedwars\lobby\battlepass\util\TimeFormatter;
use function array_slice;
use function count;
use function implode;
use function is_numeric;
use function number_format;
use function strtolower;

final class BattlePassAdminCommand extends Command{

    public function __construct(
        private BattlePassRegistry $registry,
        private PlayerBattlePassDataManager $data
    ){
        parent::__construct("bpadmin", "Manage the Battle Pass", "/bpadmin <reload|info|list|setseason|settierreward|cleartierreward|grantpremium|revokepremium|addxp>");
        $this->setPermission("battlepass.admin");
    }

    public function execute(CommandSender $sender, string $label, array $args): void{
        if(!$this->testPermission($sender)){
            return;
        }

        $sub = strtolower((string) ($args[0] ?? ""));
        if($sub === ""){
            $sender->sendMessage("§cUsage: /bpadmin <reload|info|list|setseason|settierreward|cleartierreward|grantpremium|revokepremium|addxp>");
            return;
        }

        if($sub === "reload"){
            $this->registry->reload();
            $sender->sendMessage("§aBattle Pass reloaded.");
            return;
        }

        if($sub === "info"){
            $sender->sendMessage("§d§lBattle Pass §r§8- §f" . $this->registry->getSeasonName());
            $sender->sendMessage("§7Season ID: §f" . $this->registry->getSeasonId());
            $sender->sendMessage("§7Tiers: §f" . $this->registry->getTierCount() . " §8| §7XP/Tier: §f" . number_format($this->registry->getXpPerTier()));
            $sender->sendMessage("§7Time left: §b" . TimeFormatter::left($this->registry->getEnd()));
            return;
        }

        if($sub === "list"){
            foreach($this->registry->getTiers() as $tier => $def){
                $sender->sendMessage("§7Tier §f" . $tier . " §8| §7Free: " . $def->getFreeRewardPreview() . " §8| §7Premium: " . $def->getPremiumRewardPreview());
            }
            return;
        }

        if($sub === "setseason"){
            if(count($args) < 5){
                $sender->sendMessage("§cUsage: /bpadmin setseason <name> <durationDays> <xpPerTier> <tierCount>");
                return;
            }

            $durationDays = $args[count($args) - 3];
            $xpPerTier = $args[count($args) - 2];
            $tierCount = $args[count($args) - 1];
            $name = implode(" ", array_slice($args, 1, count($args) - 4));

            if($name === "" || !is_numeric($durationDays) || !is_numeric($xpPerTier) || !is_numeric($tierCount)){
                $sender->sendMessage("§cInvalid input.");
                return;
            }

            $this->registry->setSeason($name, (int) $durationDays, (int) $xpPerTier, (int) $tierCount);
            $sender->sendMessage("§aNew season started: §f" . $name);
            return;
        }

        if($sub === "settierreward"){
            if(count($args) < 4){
                $sender->sendMessage("§cUsage: /bpadmin settierreward <tier> <free|premium> <coins|command|cosmetic> <...>");
                return;
            }

            $tier = (int) $args[1];
            $track = strtolower((string) $args[2]);
            $rewardType = strtolower((string) $args[3]);

            if($track !== "free" && $track !== "premium"){
                $sender->sendMessage("§cTrack must be 'free' or 'premium'.");
                return;
            }
            $premium = $track === "premium";

            if($rewardType === "coins"){
                if(!isset($args[4]) || !is_numeric($args[4])){
                    $sender->sendMessage("§cUsage: /bpadmin settierreward <tier> <free|premium> coins <amount>");
                    return;
                }
                $ok = $this->registry->setTierReward($tier, $premium, ["type" => "coins", "amount" => (int) $args[4]]);
                $sender->sendMessage($ok ? "§aReward added to tier §f{$tier} §8({$track})" : "§cTier not found.");
                return;
            }

            if($rewardType === "command"){
                if(!isset($args[4])){
                    $sender->sendMessage("§cUsage: /bpadmin settierreward <tier> <free|premium> command <command...>");
                    return;
                }
                $cmd = implode(" ", array_slice($args, 4));
                $ok = $this->registry->setTierReward($tier, $premium, ["type" => "command", "command" => $cmd]);
                $sender->sendMessage($ok ? "§aCommand reward added to tier §f{$tier} §8({$track})" : "§cTier not found.");
                return;
            }

            if($rewardType === "cosmetic"){
                if(!isset($args[5])){
                    $sender->sendMessage("§cUsage: /bpadmin settierreward <tier> <free|premium> cosmetic <category> <key> [equip]");
                    return;
                }
                $category = (string) $args[4];
                $key = (string) $args[5];
                $equip = isset($args[6]) ? strtolower((string) $args[6]) !== "false" : false;

                $ok = $this->registry->setTierReward($tier, $premium, [
                    "type" => "cosmetic",
                    "category" => $category,
                    "key" => $key,
                    "equip" => $equip
                ]);
                $sender->sendMessage($ok ? "§aCosmetic reward added to tier §f{$tier} §8({$track})" : "§cTier not found.");
                return;
            }

            $sender->sendMessage("§cUnknown reward type. Use coins, command or cosmetic.");
            return;
        }

        if($sub === "cleartierreward"){
            if(count($args) < 3){
                $sender->sendMessage("§cUsage: /bpadmin cleartierreward <tier> <free|premium>");
                return;
            }
            $tier = (int) $args[1];
            $track = strtolower((string) $args[2]);
            if($track !== "free" && $track !== "premium"){
                $sender->sendMessage("§cTrack must be 'free' or 'premium'.");
                return;
            }
            $ok = $this->registry->clearTierRewards($tier, $track === "premium");
            $sender->sendMessage($ok ? "§aCleared rewards on tier §f{$tier} §8({$track})" : "§cTier not found.");
            return;
        }

        if($sub === "grantpremium"){
            if(count($args) < 2){
                $sender->sendMessage("§cUsage: /bpadmin grantpremium <player> [days]");
                return;
            }
            $name = (string) $args[1];
            $days = isset($args[2]) && is_numeric($args[2]) ? (int) $args[2] : null;

            $target = Server::getInstance()->getPlayerByPrefix($name);
            if($target === null){
                $sender->sendMessage("§cPlayer not found or offline.");
                return;
            }

            $this->data->load($target, function(array $row, array $state) use ($target, $days, $sender): void{
                $row = $this->data->grantPremium($row, $days);
                $this->data->save($target, $row, $state);
                $sender->sendMessage("§aGranted Premium Pass to §f" . $target->getName() . ($days === null ? " §8(lifetime)" : " §8({$days}d)"));
                $target->sendMessage("§d§l§oBattle Pass §r§7- You have been granted the §dPremium Pass§7!");
            });
            return;
        }

        if($sub === "revokepremium"){
            if(count($args) < 2){
                $sender->sendMessage("§cUsage: /bpadmin revokepremium <player>");
                return;
            }
            $name = (string) $args[1];
            $target = Server::getInstance()->getPlayerByPrefix($name);
            if($target === null){
                $sender->sendMessage("§cPlayer not found or offline.");
                return;
            }

            $this->data->load($target, function(array $row, array $state) use ($target, $sender): void{
                $row["premium_until"] = null;
                $this->data->save($target, $row, $state);
                $sender->sendMessage("§aRevoked Premium Pass from §f" . $target->getName());
            });
            return;
        }

        if($sub === "addxp"){
            if(count($args) < 3 || !is_numeric($args[2])){
                $sender->sendMessage("§cUsage: /bpadmin addxp <player> <amount>");
                return;
            }
            $name = (string) $args[1];
            $amount = (int) $args[2];

            $target = Server::getInstance()->getPlayerByPrefix($name);
            if($target === null){
                $sender->sendMessage("§cPlayer not found or offline.");
                return;
            }

            $registry = $this->registry;
            $this->data->load($target, function(array $row, array $state) use ($target, $amount, $registry, $sender): void{
                $row = $this->data->applySeasonResetIfNeeded($row, $registry);
                $row = $this->data->addTestXp($registry, $row, $amount);
                $this->data->save($target, $row, $state);
                $sender->sendMessage("§aGave §f" . $amount . " §aBattle Pass XP to §f" . $target->getName());
                $target->sendMessage("§d§l§oBattle Pass §r§7- §a+" . $amount . " XP §7(Admin Grant)");
            });
            return;
        }

        $sender->sendMessage("§cUnknown subcommand.");
    }
}
