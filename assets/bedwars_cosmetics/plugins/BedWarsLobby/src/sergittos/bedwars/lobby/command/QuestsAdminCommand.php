<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\lobby\BedWarsLobby;
use function array_values;

class QuestsAdminCommand extends Command {

    private const STATS = ["kills", "wins", "beds_broken", "final_kills", "deaths", "coins", "level"];

    public function __construct(private BedWarsLobby $plugin) {
        parent::__construct("questsadmin", "Manage quests", "/questsadmin");
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

        $this->openMainMenu($sender);
    }

    private function openMainMenu(Player $player): void {
        $form = new SimpleForm(function(Player $player, ?int $data): void {
            if ($data === null) return;
            if ($data === 0) {
                $this->openCreateForm($player);
                return;
            }
            $this->openManageList($player);
        });
        $form->setTitle("Quests Admin");
        $form->setContent("What would you like to do?");
        $form->addButton("§aCreate a new quest");
        $form->addButton("§eManage existing quests");
        $player->sendForm($form);
    }

    private function openCreateForm(Player $player): void {
        $form = new CustomForm(function(Player $player, ?array $data): void {
            if ($data === null) return;

            [$name, $description, $rewardCoins, $rewardXp] = [$data[0], $data[1], (int) $data[2], (int) $data[3]];
            $requirements = [];
            foreach (self::STATS as $i => $stat) {
                $value = (int) $data[4 + $i];
                if ($value > 0) {
                    $requirements[$stat] = $value;
                }
            }

            if ($name === "" || empty($requirements)) {
                $player->sendMessage(TF::RED . "You must set a name and at least one requirement > 0.");
                return;
            }

            BedWarsCore::getInstance()->getProvider()->addQuest($name, $description, $requirements, $rewardCoins, $rewardXp, function() use ($player, $name): void {
                $player->sendMessage(TF::GREEN . "Quest '$name' created!");
                $this->plugin->getQuestManager()->reloadQuests();
            });
        });
        $form->setTitle("Create Quest");
        $form->addInput("Quest name", "e.g. Weekend Warrior");
        $form->addInput("Description", "e.g. Win 4 games this weekend");
        $form->addInput("Reward coins", "e.g. 50", "0");
        $form->addInput("Reward XP", "e.g. 100", "0");
        foreach (self::STATS as $stat) {
            $form->addInput("Required $stat (0 = not required)", "0", "0");
        }
        $player->sendForm($form);
    }

    private function openManageList(Player $player): void {
        BedWarsCore::getInstance()->getProvider()->getAllQuests(function(array $rows) use ($player): void {
            $rows = array_values($rows);
            $form = new SimpleForm(function(Player $player, ?int $data) use ($rows): void {
                if ($data === null || !isset($rows[$data])) return;
                $this->openQuestActions($player, $rows[$data]);
            });
            $form->setTitle("Manage Quests");

            if (empty($rows)) {
                $form->setContent("§7No quests created yet.");
            } else {
                $form->setContent("§7Select a quest:");
                foreach ($rows as $row) {
                    $status = ((int) $row["enabled"]) === 1 ? "§aEnabled" : "§cDisabled";
                    $form->addButton($row["name"] . "\n" . $status);
                }
            }
            $player->sendForm($form);
        });
    }

    private function openQuestActions(Player $player, array $quest): void {
        $enabled = ((int) $quest["enabled"]) === 1;
        $form = new SimpleForm(function(Player $player, ?int $data) use ($quest, $enabled): void {
            if ($data === null) return;
            $provider = BedWarsCore::getInstance()->getProvider();
            if ($data === 0) {
                $provider->setQuestEnabled((int) $quest["id"], !$enabled, function() use ($player, $enabled): void {
                    $player->sendMessage(TF::GREEN . "Quest " . ($enabled ? "disabled" : "enabled") . ".");
                    $this->plugin->getQuestManager()->reloadQuests();
                });
            } elseif ($data === 1) {
                $provider->removeQuest((int) $quest["id"], function() use ($player): void {
                    $player->sendMessage(TF::GREEN . "Quest deleted.");
                    $this->plugin->getQuestManager()->reloadQuests();
                });
            }
        });
        $form->setTitle($quest["name"]);
        $form->setContent(
            "§7" . $quest["description"] . "\n\n" .
            "§7Requirements: §f" . $quest["requirements"] . "\n" .
            "§7Reward: §6" . $quest["reward_coins"] . " coins§7, §b" . $quest["reward_xp"] . " XP"
        );
        $form->addButton($enabled ? "§cDisable" : "§aEnable");
        $form->addButton("§4Delete");
        $player->sendForm($form);
    }

}
