<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\friends;

use pocketmine\player\Player;
use pocketmine\Server;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function strtolower;
use function time;
use function trim;

final class FriendManager{

    private string $folder;

    public function __construct(string $dataFolder){
        $this->folder = rtrim($dataFolder, "/") . "/friends/players/";
        if(!is_dir($this->folder)){
            mkdir($this->folder, 0777, true);
        }
    }

    public function id(Player $player): string{
        $xuid = $player->getXuid();
        return $xuid !== "" ? $xuid : strtolower($player->getName());
    }

    public function idByName(string $name): string{
        return strtolower(trim($name));
    }

    private function path(string $id): string{
        return $this->folder . $id . ".json";
    }

    public function getById(string $id, string $nameIfNew = ""): array{
        $id = trim($id);
        if($id === ""){
            $id = "unknown";
        }

        $data = [
            "name" => $nameIfNew !== "" ? $nameIfNew : $id,
            "friends" => [],
            "incoming" => [],
            "outgoing" => [],
            "settings" => ["allow_requests" => true],
            "last_seen" => 0
        ];

        $file = $this->path($id);
        if(is_file($file)){
            $decoded = json_decode((string) @file_get_contents($file), true);
            if(is_array($decoded)){
                $data["name"] = (string) ($decoded["name"] ?? $data["name"]);
                $data["friends"] = is_array($decoded["friends"] ?? null) ? $decoded["friends"] : [];
                $data["incoming"] = is_array($decoded["incoming"] ?? null) ? $decoded["incoming"] : [];
                $data["outgoing"] = is_array($decoded["outgoing"] ?? null) ? $decoded["outgoing"] : [];
                $data["settings"] = is_array($decoded["settings"] ?? null) ? $decoded["settings"] : $data["settings"];
                $data["last_seen"] = (int) ($decoded["last_seen"] ?? 0);
            }
        }

        if($nameIfNew !== "" && $data["name"] !== $nameIfNew){
            $data["name"] = $nameIfNew;
            $this->saveById($id, $data);
        }

        return $data;
    }

    public function saveById(string $id, array $data): void{
        file_put_contents(
            $this->path($id),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    public function get(Player $player): array{
        return $this->getById($this->id($player), $player->getName());
    }

    public function setAllowRequests(Player $player, bool $value): void{
        $id = $this->id($player);
        $data = $this->getById($id, $player->getName());
        $data["settings"]["allow_requests"] = $value;
        $this->saveById($id, $data);
    }

    public function canReceiveRequests(string $targetId, string $targetName): bool{
        $t = $this->getById($targetId, $targetName);
        return (bool) ($t["settings"]["allow_requests"] ?? true);
    }

    public function removeFriendByIds(string $aId, string $aName, string $bId, string $bName): bool{
        $a = $this->getById($aId, $aName);
        $b = $this->getById($bId, $bName);

        $changed = false;

        if(isset($a["friends"][$bId])){
            unset($a["friends"][$bId]);
            $changed = true;
        }
        if(isset($b["friends"][$aId])){
            unset($b["friends"][$aId]);
            $changed = true;
        }

        if($changed){
            $this->saveById($aId, $a);
            $this->saveById($bId, $b);
        }

        return $changed;
    }

    public function denyRequestByIds(string $receiverId, string $receiverName, string $senderId, string $senderName): bool{
        $r = $this->getById($receiverId, $receiverName);
        $s = $this->getById($senderId, $senderName);

        $changed = false;

        if(isset($r["incoming"][$senderId])){
            unset($r["incoming"][$senderId]);
            $changed = true;
        }
        if(isset($s["outgoing"][$receiverId])){
            unset($s["outgoing"][$receiverId]);
            $changed = true;
        }

        if($changed){
            $this->saveById($receiverId, $r);
            $this->saveById($senderId, $s);
        }

        return $changed;
    }

    public function acceptRequestByIds(string $receiverId, string $receiverName, string $senderId, string $senderName): bool{
        $r = $this->getById($receiverId, $receiverName);
        $s = $this->getById($senderId, $senderName);

        if(!isset($r["incoming"][$senderId])){
            return false;
        }

        unset($r["incoming"][$senderId]);
        unset($s["outgoing"][$receiverId]);

        $now = time();
        $r["friends"][$senderId] = ["name" => $senderName, "since" => $now];
        $s["friends"][$receiverId] = ["name" => $receiverName, "since" => $now];

        $this->saveById($receiverId, $r);
        $this->saveById($senderId, $s);

        return true;
    }

    public function sendRequest(Player $from, string $targetName): array{
        $targetName = trim($targetName);
        if($targetName === ""){
            return [false, "§c§lRequest Failed§r§7 - Enter a valid player name."];
        }

        if(strtolower($targetName) === strtolower($from->getName())){
            return [false, "§c§lRequest Failed§r§7 - You cannot add yourself."];
        }

        $fromId = $this->id($from);
        $fromName = $from->getName();

        $targetPlayer = Server::getInstance()->getPlayerExact($targetName);
        $targetId = $targetPlayer !== null ? $this->id($targetPlayer) : $this->idByName($targetName);
        $targetShownName = $targetPlayer !== null ? $targetPlayer->getName() : $targetName;

        if(!$this->canReceiveRequests($targetId, $targetShownName)){
            return [false, "§c§lRequest Failed§r§7 - This player is not accepting requests."];
        }

        $a = $this->getById($fromId, $fromName);
        $b = $this->getById($targetId, $targetShownName);

        if(isset($a["friends"][$targetId])){
            return [false, "§e§lAlready Friends§r§7 - You are already friends with §f{$targetShownName}§7."];
        }

        if(isset($a["outgoing"][$targetId])){
            return [false, "§e§lAlready Sent§r§7 - Request already sent to §f{$targetShownName}§7."];
        }

        if(isset($a["incoming"][$targetId])){
            $ok = $this->acceptRequestByIds($fromId, $fromName, $targetId, $targetShownName);
            return $ok
                ? [true, "§a§lFriends Updated§r§7 - Request accepted."]
                : [false, "§c§lRequest Failed§r§7 - Request not found."];
        }

        $now = time();
        $a["outgoing"][$targetId] = ["name" => $targetShownName, "time" => $now];
        $b["incoming"][$fromId] = ["name" => $fromName, "time" => $now];

        $this->saveById($fromId, $a);
        $this->saveById($targetId, $b);

        if($targetPlayer !== null && $targetPlayer->isConnected()){
            $targetPlayer->sendMessage("§b§lFriend Request§r§7 - From §f{$fromName}§7. Open §aFriends §7to respond.");
        }

        return [true, "§a§lRequest Sent§r§7 - Sent to §f{$targetShownName}§7."];
    }

    public function isOnlineName(string $name): bool{
        return Server::getInstance()->getPlayerExact($name) !== null;
    }

    public function touchLastSeen(Player $player): void{
        $id = $this->id($player);
        $data = $this->getById($id, $player->getName());
        $data["last_seen"] = time();
        $this->saveById($id, $data);
    }
}