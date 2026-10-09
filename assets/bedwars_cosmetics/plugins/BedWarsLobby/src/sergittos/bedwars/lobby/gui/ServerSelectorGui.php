<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\gui;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\network\ServerInfo;

class ServerSelectorGui {

    public function open(Player $player): void {
        $network = BedWarsCore::getInstance()->getNetworkManager();

        $form = new SimpleForm(function(Player $player, ?int $data) use ($network): void {
            if ($data === null) return;
            $types = ["solo", "double", "triple", "squad"];
            if (isset($types[$data])) {
                $this->connect($player, $types[$data], $network);
            }
        });

        $form->setTitle("§zSELECT MODE");

        $modes = [
            ["type" => "solo",   "label" => "§l§cSOLO",    "desc" => "1v1v1v1...", "team" => "1 player per team",  "icon" => "textures/items/iron_sword"],
            ["type" => "double", "label" => "§l§bDOUBLE",    "desc" => "2v2v2v2...", "team" => "2 players per team", "icon" => "textures/items/diamond_sword"],
            ["type" => "triple", "label" => "§l§aTRIPLES", "desc" => "3v3v3v3...", "team" => "3 players per team", "icon" => "textures/items/golden_sword"],
            ["type" => "squad",  "label" => "§l§6SQUADS",  "desc" => "4v4v4v4...", "team" => "4 players per team", "icon" => "textures/items/netherite_sword"],
        ];

        foreach ($modes as $mode) {
            $servers = $network->getServersByType($mode["type"]);
            $onlineServers = array_filter($servers, fn(ServerInfo $s) => $s->isOnline());
            $totalOnline = array_sum(array_map(fn(ServerInfo $s) => $s->online, $onlineServers));
            $available = count(array_filter($onlineServers, fn(ServerInfo $s) => !$s->isFull()));

            if (empty($onlineServers)) {
                $status = TF::RED . "§lOffline";
            } elseif ($available > 0) {
                $status = TF::YELLOW . (string) $totalOnline . TF::GRAY . " §r§7playing";
            } else {
                $status = TF::GOLD . "§lFull §7(" . TF::YELLOW . $totalOnline . TF::GOLD . " §7playing)";
            }

            $buttonText = $mode["label"] . " §7" . $mode["desc"] . "\n§7" . $mode["team"] . " §8| " . $status;

            $form->addButton($buttonText, 0, $mode["icon"]);
        }

        $form->sendToPlayer($player);
    }

    private function connect(Player $player, string $type, $network): void {
        $servers = $network->getServersByType($type);
        $available = array_filter($servers, fn(ServerInfo $s) => $s->isOnline() && !$s->isFull());

        if (empty($available)) {
            $player->sendMessage(TF::RED . "No available servers for $type right now.");
            return;
        }

        usort($available, fn(ServerInfo $a, ServerInfo $b) => $a->online <=> $b->online);
        $best = $available[0];

        $this->bringPartyAlong($player, $best->name);

        // Intentionally silent - no "Connecting to ..." chat message before
        // the transfer anymore, the client's own transfer/loading screen is
        // enough.
        $network->transferToServer($player, $best->name);
    }

    /**
     * اگه $player لیدر یک پارتی باشه، برای هر عضو آنلاینِ دیگه یک
     * party_follow ثبت می‌کنه و همه رو به همون سرور مقصد (نه سرور
     * جداگانه‌ی خودشون) ترنسفر می‌کنه. سرور Game با همین ردیف تشخیص
     * می‌ده که عضو باید مستقیم بره توی بازیِ لیدر، نه matchmaking جدا.
     */
    private function bringPartyAlong(Player $leader, string $targetServer): void {
        $core = BedWarsCore::getInstance();
        $partyManager = $core->getPartyManager();
        $leaderSession = $core->getSessionManager()->get($leader);
        if ($leaderSession === null) return;

        $party = $partyManager->getPartyOf($leaderSession);
        if ($party === null || !$party->isLeader($leaderSession)) return;

        $provider = $core->getProvider();
        foreach ($party->getMembers() as $member) {
            if ($member === $leaderSession) continue;
            $memberPlayer = $member->getPlayer();
            if (!$memberPlayer->isConnected()) continue;

            $provider->setPartyFollow($member->getUsername(), $leader->getName(), $targetServer);
            $memberPlayer->sendMessage(TF::AQUA . "Following your party leader to " . TF::YELLOW . $targetServer . TF::AQUA . "...");
            $core->getNetworkManager()->transferToServer($memberPlayer, $targetServer);
        }
    }
}