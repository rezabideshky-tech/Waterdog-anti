<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\friends;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use sergittos\bedwars\lobby\BedWarsLobby;
use function count;
use function date;
use function is_array;
use function trim;

final class FriendsMenu{

    private static function ready(Player $player): bool{
        if(!class_exists(SimpleForm::class) || !class_exists(CustomForm::class)){
            $player->sendMessage("§c§lFriends Unavailable§r§7 - FormAPI is required.");
            return false;
        }
        return true;
    }

    private static function mgr(): FriendManager{
        return BedWarsLobby::getInstance()->getFriendManager();
    }

    public static function openMain(Player $player): void{
        if(!self::ready($player)){
            return;
        }

        $mgr = self::mgr();
        $data = $mgr->get($player);

        $friends = is_array($data["friends"] ?? null) ? $data["friends"] : [];
        $incoming = is_array($data["incoming"] ?? null) ? $data["incoming"] : [];
        $outgoing = is_array($data["outgoing"] ?? null) ? $data["outgoing"] : [];

        $online = 0;
        foreach($friends as $row){
            $name = (string) ($row["name"] ?? "");
            if($name !== "" && $mgr->isOnlineName($name)){
                $online++;
            }
        }

        $content =
            "§b|\n" .
            "§b| §7Friends §f» §a" . count($friends) . " §7(§b" . $online . " §7Online)\n" .
            "§b| §7Inbox §f» §e" . count($incoming) . " §7Outgoing §f» §7" . count($outgoing);

        $form = new SimpleForm(function(Player $player, ?int $data): void{
            if($data === null){
                return;
            }

            match($data){
                0 => self::openProfile($player),
                1 => self::openFriendList($player),
                2 => self::openAddFriend($player),
                3 => self::openInbox($player),
                default => null
            };
        });

        $form->setTitle("§l§bFRIENDS");
        $form->setContent($content);

        $form->addButton("§b§lMy Profile\n§7Overview and settings", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/icon_profile");
        $form->addButton("§a§lFriend List\n§7See who's with you", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/icon_steve");
        $form->addButton("§e§lAdd Friend\n§7Send a request", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/icon_import");
        $form->addButton("§d§lInbox\n§7" . count($incoming) . " request(s) waiting", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/inbox");

        $player->sendForm($form);
    }

    public static function openProfile(Player $player): void{
        if(!self::ready($player)){
            return;
        }

        $mgr = self::mgr();
        $data = $mgr->get($player);

        $friends = is_array($data["friends"] ?? null) ? $data["friends"] : [];
        $incoming = is_array($data["incoming"] ?? null) ? $data["incoming"] : [];
        $outgoing = is_array($data["outgoing"] ?? null) ? $data["outgoing"] : [];

        $allow = (bool) ($data["settings"]["allow_requests"] ?? true);
        $seen = (int) ($data["last_seen"] ?? 0);

        $content =
            "§7Profile\n\n" .
            "§fName: §b" . $player->getName() . "\n" .
            "§fFriends: §a" . count($friends) . "\n" .
            "§fInbox: §e" . count($incoming) . "\n" .
            "§fOutgoing: §7" . count($outgoing) . "\n" .
            "§fRequests: " . ($allow ? "§aEnabled" : "§cDisabled") . "\n" .
            "§fLast Seen: §7" . ($seen > 0 ? date("Y-m-d H:i", $seen) : "-");

        $form = new SimpleForm(function(Player $player, ?int $data): void{
            if($data === null){
                return;
            }

            match($data){
                0 => self::toggleRequests($player),
                1 => self::openOutgoing($player),
                2 => self::openMain($player),
                default => null
            };
        });

        $form->setTitle("§l§bMy Profile");
        $form->setContent($content);
        $form->addButton("§l§eToggle Requests\n§7Enable or disable", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/icon_setting");
        $form->addButton("§l§7Outgoing\n§7Requests you sent", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/icon_send");
        $form->addButton("§l§cBack", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/undo");

        $player->sendForm($form);
    }

    private static function toggleRequests(Player $player): void{
        $mgr = self::mgr();
        $data = $mgr->get($player);
        $allow = (bool) ($data["settings"]["allow_requests"] ?? true);

        $mgr->setAllowRequests($player, !$allow);
        $player->sendMessage(!$allow ? "§a§lFriends Updated§r§7 - Requests enabled." : "§c§lFriends Updated§r§7 - Requests disabled.");
        self::openProfile($player);
    }

    public static function openOutgoing(Player $player): void{
        if(!self::ready($player)){
            return;
        }

        $mgr = self::mgr();
        $data = $mgr->get($player);
        $outgoing = is_array($data["outgoing"] ?? null) ? $data["outgoing"] : [];

        $rows = [];
        foreach($outgoing as $id => $row){
            if(!is_array($row)){
                continue;
            }
            $name = (string) ($row["name"] ?? "");
            if($name === ""){
                continue;
            }
            $rows[] = ["id" => (string) $id, "name" => $name, "time" => (int) ($row["time"] ?? 0)];
        }

        $form = new SimpleForm(function(Player $player, ?int $index) use ($rows): void{
            if($index === null){
                return;
            }
            if($index === count($rows)){
                self::openProfile($player);
            }
        });

        $form->setTitle("§l§7Outgoing");
        $form->setContent("§7Requests you have sent.");

        foreach($rows as $row){
            $t = $row["time"] > 0 ? date("m/d H:i", $row["time"]) : "-";
            $form->addButton("§f" . $row["name"] . "\n§7Sent: " . $t, SimpleForm::IMAGE_TYPE_PATH, "textures/ui/icon_send");
        }

        $form->addButton("§l§cBack", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/undo");
        $player->sendForm($form);
    }

    public static function openFriendList(Player $player): void{
        if(!self::ready($player)){
            return;
        }

        $mgr = self::mgr();
        $data = $mgr->get($player);
        $friends = is_array($data["friends"] ?? null) ? $data["friends"] : [];

        $list = [];
        foreach($friends as $id => $row){
            if(!is_array($row)){
                continue;
            }
            $name = (string) ($row["name"] ?? "");
            if($name === ""){
                continue;
            }
            $list[] = ["id" => (string) $id, "name" => $name, "since" => (int) ($row["since"] ?? 0)];
        }

        $form = new SimpleForm(function(Player $player, ?int $index) use ($list): void{
            if($index === null){
                return;
            }

            if(!isset($list[$index])){
                self::openMain($player);
                return;
            }

            self::openFriendActions($player, $list[$index]["id"], $list[$index]["name"], (int) $list[$index]["since"]);
        });

        $form->setTitle("§l§aFriend List");

        if($list === []){
            $form->setContent("§7No friends yet.\n§7Use §eAdd Friend §7to send a request.");
            $form->addButton("§l§cBack", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/undo");
            $player->sendForm($form);
            return;
        }

        $form->setContent("§7Select a friend.");

        foreach($list as $row){
            $online = $mgr->isOnlineName($row["name"]);
            $status = $online ? "§aOnline" : "§7Offline";
            $since = $row["since"] > 0 ? date("m/d/y", $row["since"]) : "-";
            $form->addButton("§f" . $row["name"] . "\n" . $status . " §8| §7Since: " . $since, SimpleForm::IMAGE_TYPE_PATH, $online ? "textures/ui/icon_steve" : "textures/ui/icon_alex");
        }

        $player->sendForm($form);
    }

    private static function openFriendActions(Player $player, string $friendId, string $friendName, int $since): void{
        if(!self::ready($player)){
            return;
        }

        $mgr = self::mgr();
        $online = $mgr->isOnlineName($friendName);

        $content =
            "§7Friend\n\n" .
            "§fName: §b" . $friendName . "\n" .
            "§fStatus: " . ($online ? "§aOnline" : "§7Offline") . "\n" .
            "§fSince: §7" . ($since > 0 ? date("Y-m-d", $since) : "-");

        $form = new SimpleForm(function(Player $player, ?int $index) use ($friendId, $friendName): void{
            if($index === null){
                return;
            }

            if($index === 0){
                $mgr = self::mgr();
                $ok = $mgr->removeFriendByIds($mgr->id($player), $player->getName(), $friendId, $friendName);
                $player->sendMessage($ok ? "§a§lFriends Updated§r§7 - Friend removed." : "§c§lAction Failed§r§7 - Friend not found.");
                self::openFriendList($player);
                return;
            }

            self::openFriendList($player);
        });

        $form->setTitle("§l§b" . $friendName);
        $form->setContent($content);
        $form->addButton("§l§cRemove Friend\n§7Delete from your list", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/trash");
        $form->addButton("§l§7Back", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/undo");

        $player->sendForm($form);
    }

    public static function openAddFriend(Player $player): void{
        if(!self::ready($player)){
            return;
        }

        $form = new CustomForm(function(Player $player, ?array $data): void{
            if($data === null){
                self::openMain($player);
                return;
            }

            $name = trim((string) ($data[1] ?? ""));
            $mgr = self::mgr();

            [$ok, $msg] = $mgr->sendRequest($player, $name);
            $player->sendMessage($msg);

            self::openMain($player);
        });

        $form->setTitle("§l§eAdd Friend");
        $form->addLabel("§7Send a friend request by username.");
        $form->addInput("Player name", "Steve");

        $player->sendForm($form);
    }

    public static function openInbox(Player $player): void{
        if(!self::ready($player)){
            return;
        }

        $mgr = self::mgr();
        $data = $mgr->get($player);
        $incoming = is_array($data["incoming"] ?? null) ? $data["incoming"] : [];

        $list = [];
        foreach($incoming as $id => $row){
            if(!is_array($row)){
                continue;
            }
            $name = (string) ($row["name"] ?? "");
            if($name === ""){
                continue;
            }
            $list[] = ["id" => (string) $id, "name" => $name, "time" => (int) ($row["time"] ?? 0)];
        }

        $form = new SimpleForm(function(Player $player, ?int $index) use ($list): void{
            if($index === null){
                return;
            }

            if(!isset($list[$index])){
                self::openMain($player);
                return;
            }

            self::openRequestActions($player, $list[$index]["id"], $list[$index]["name"], (int) $list[$index]["time"]);
        });

        $form->setTitle("§l§dInbox");

        if($list === []){
            $form->setContent("§7No friend requests right now.");
            $form->addButton("§l§cBack", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/undo");
            $player->sendForm($form);
            return;
        }

        $form->setContent("§7Select a request.");

        foreach($list as $row){
            $timeText = $row["time"] > 0 ? date("m/d H:i", $row["time"]) : "-";
            $form->addButton("§f" . $row["name"] . "\n§7Received: " . $timeText, SimpleForm::IMAGE_TYPE_PATH, "textures/ui/inbox");
        }

        $player->sendForm($form);
    }

    private static function openRequestActions(Player $player, string $senderId, string $senderName, int $timeSent): void{
        if(!self::ready($player)){
            return;
        }

        $content =
            "§7Friend Request\n\n" .
            "§fFrom: §b" . $senderName . "\n" .
            "§fSent: §7" . ($timeSent > 0 ? date("Y-m-d H:i", $timeSent) : "-");

        $form = new SimpleForm(function(Player $player, ?int $index) use ($senderId, $senderName): void{
            if($index === null){
                return;
            }

            $mgr = self::mgr();

            if($index === 0){
                $ok = $mgr->acceptRequestByIds($mgr->id($player), $player->getName(), $senderId, $senderName);
                $player->sendMessage($ok ? "§a§lFriends Updated§r§7 - Friend added." : "§c§lAction Failed§r§7 - Request not found.");
                self::openInbox($player);
                return;
            }

            if($index === 1){
                $ok = $mgr->denyRequestByIds($mgr->id($player), $player->getName(), $senderId, $senderName);
                $player->sendMessage($ok ? "§e§lFriends Updated§r§7 - Request denied." : "§c§lAction Failed§r§7 - Request not found.");
                self::openInbox($player);
                return;
            }

            self::openInbox($player);
        });

        $form->setTitle("§l§b" . $senderName);
        $form->setContent($content);
        $form->addButton("§l§aAccept\n§7Add as friend", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/realms_green_check");
        $form->addButton("§l§cDeny\n§7Delete request", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/redX1");
        $form->addButton("§l§7Back", SimpleForm::IMAGE_TYPE_PATH, "textures/ui/undo");

        $player->sendForm($form);
    }
}