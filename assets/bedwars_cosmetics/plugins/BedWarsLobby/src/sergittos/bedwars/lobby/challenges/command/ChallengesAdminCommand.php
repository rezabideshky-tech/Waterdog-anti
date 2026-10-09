<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\challenges\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use sergittos\bedwars\lobby\challenges\model\RequirementType;
use sergittos\bedwars\lobby\challenges\registry\ChallengeRegistry;
use sergittos\bedwars\lobby\challenges\util\TimeFormatter;
use function array_slice;
use function count;
use function implode;
use function is_numeric;
use function number_format;
use function str_replace;
use function strtolower;
use function time;
use function ucfirst;

final class ChallengesAdminCommand extends Command{

    public function __construct(private ChallengeRegistry $registry){
        parent::__construct("chadmin", "Manage Challenges", "/chadmin <create|settime|rewardcoins|rewardcommand|rewardcosmetic|delete|reload|list>");
        $this->setPermission("challenges.admin");
    }

    public function execute(CommandSender $sender, string $label, array $args): void{
        if(!$this->testPermission($sender)){
            return;
        }

        $sub = strtolower((string)($args[0] ?? ""));
        if($sub === ""){
            $sender->sendMessage("§cUsage: /chadmin <create|settime|rewardcoins|rewardcommand|rewardcosmetic|delete|reload|list>");
            return;
        }

        if($sub === "reload"){
            $this->registry->reload();
            $sender->sendMessage("§aChallenges reloaded.");
            return;
        }

        if($sub === "list"){
            $all = $this->registry->getAll();
            if($all === []){
                $sender->sendMessage("§eNo challenges found.");
                return;
            }
            $sender->sendMessage("§bChallenges:");
            foreach($all as $c){
                $sender->sendMessage("§7- §f" . $c->getId() . " §8| §e" . $c->getNamePlain() . " §8| §b" . TimeFormatter::range($c->getStart(), $c->getEnd()));
            }
            return;
        }

        if($sub === "delete"){
            $id = (string)($args[1] ?? "");
            if($id === ""){
                $sender->sendMessage("§cUsage: /chadmin delete <id>");
                return;
            }
            $ok = $this->registry->delete($id);
            $sender->sendMessage($ok ? "§aDeleted: §f{$id}" : "§cChallenge not found.");
            return;
        }

        if($sub === "create"){
            if(count($args) < 6){
                $sender->sendMessage("§cUsage: /chadmin create <id> <requirement> <goal> <durationHours> <rewardCoins>");
                $sender->sendMessage("§7Requirements: coins_earned, kills, final_kills, beds_broken, wins");
                return;
            }

            $id = (string)$args[1];
            $req = strtolower((string)$args[2]);

            if(!RequirementType::isValid($req)){
                $sender->sendMessage("§cInvalid requirement.");
                return;
            }

            if(!is_numeric($args[3]) || !is_numeric($args[4]) || !is_numeric($args[5])){
                $sender->sendMessage("§cGoal, durationHours and rewardCoins must be numbers.");
                return;
            }

            $goal = (int)$args[3];
            $durationHours = (int)$args[4];
            $rewardCoins = (int)$args[5];

            if($goal <= 0 || $durationHours <= 0 || $rewardCoins <= 0){
                $sender->sendMessage("§cValues must be greater than 0.");
                return;
            }

            $start = time();
            $end = $start + ($durationHours * 3600);

            $ok = $this->registry->upsert([
                "id" => $id,
                "name" => "§l§b" . ucfirst(str_replace("_", " ", $id)) . " §r§7Challenge",
                "icon" => "textures/items/book",
                "requirement" => $req,
                "goal" => $goal,
                "start" => $start,
                "end" => $end,
                "claim_grace_hours" => 72,
                "rewards" => [
                    ["type" => "coins", "amount" => $rewardCoins]
                ]
            ]);

            if(!$ok){
                $sender->sendMessage("§cFailed to create challenge.");
                return;
            }

            $sender->sendMessage("§aCreated: §f{$id} §8| §e" . number_format($rewardCoins) . " coins §8| §b" . TimeFormatter::left($end));
            return;
        }

        if($sub === "settime"){
            if(count($args) < 4){
                $sender->sendMessage("§cUsage: /chadmin settime <id> <startUnix> <endUnix>");
                return;
            }
            $id = (string)$args[1];
            if($id === "" || !is_numeric($args[2]) || !is_numeric($args[3])){
                $sender->sendMessage("§cInvalid input.");
                return;
            }
            $start = (int)$args[2];
            $end = (int)$args[3];
            if($end <= $start){
                $sender->sendMessage("§cEnd must be greater than start.");
                return;
            }
            $ok = $this->registry->setTime($id, $start, $end);
            $sender->sendMessage($ok ? "§aTime updated: §f{$id} §8| §b" . TimeFormatter::range($start, $end) : "§cChallenge not found.");
            return;
        }

        if($sub === "rewardcoins"){
            if(count($args) < 3){
                $sender->sendMessage("§cUsage: /chadmin rewardcoins <id> <amount>");
                return;
            }
            $id = (string)$args[1];
            if($id === "" || !is_numeric($args[2])){
                $sender->sendMessage("§cInvalid input.");
                return;
            }
            $amount = (int)$args[2];
            if($amount <= 0){
                $sender->sendMessage("§cAmount must be greater than 0.");
                return;
            }
            $ok = $this->registry->addReward($id, ["type" => "coins", "amount" => $amount]);
            $sender->sendMessage($ok ? "§aReward added: §e" . number_format($amount) . " coins §7→ §f{$id}" : "§cChallenge not found.");
            return;
        }

        if($sub === "rewardcommand"){
            if(count($args) < 3){
                $sender->sendMessage("§cUsage: /chadmin rewardcommand <id> <command...>");
                return;
            }
            $id = (string)$args[1];
            $cmd = implode(" ", array_slice($args, 2));
            if($id === "" || $cmd === ""){
                $sender->sendMessage("§cInvalid input.");
                return;
            }
            $ok = $this->registry->addReward($id, ["type" => "command", "command" => $cmd]);
            $sender->sendMessage($ok ? "§aCommand reward added to §f{$id}" : "§cChallenge not found.");
            return;
        }

        if($sub === "rewardcosmetic"){
            if(count($args) < 4){
                $sender->sendMessage("§cUsage: /chadmin rewardcosmetic <id> <category> <key> [equip=true|false]");
                return;
            }
            $id = (string)$args[1];
            $category = (string)$args[2];
            $key = (string)$args[3];
            $equip = isset($args[4]) ? strtolower((string)$args[4]) !== "false" : true;

            if($id === "" || $category === "" || $key === ""){
                $sender->sendMessage("§cInvalid input.");
                return;
            }

            $ok = $this->registry->addReward($id, [
                "type" => "cosmetic",
                "category" => $category,
                "key" => $key,
                "equip" => $equip
            ]);

            $sender->sendMessage($ok ? "§aCosmetic reward added to §f{$id}" : "§cChallenge not found.");
            return;
        }

        $sender->sendMessage("§cUnknown subcommand.");
    }
}