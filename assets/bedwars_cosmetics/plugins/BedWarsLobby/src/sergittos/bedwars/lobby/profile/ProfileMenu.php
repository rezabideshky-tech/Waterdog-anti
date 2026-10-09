<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\profile;

use jojoe77777\FormAPI\CustomForm;
use pocketmine\player\Player;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\lobby\profile\ui\ProfileForm;
use sergittos\bedwars\profile\PlayerProfile;
use sergittos\bedwars\profile\ProfileCatalog;
use function ceil;
use function in_array;
use function count;
use function is_array;
use function max;
use function min;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Entry point of the profile UI: loads the numbers from the database (async), then sends the full-screen form
 * that the "BedWars Profile UI" resource pack draws.
 */
final class ProfileMenu{

    public const MEDALS_PER_PAGE = 8;
    public const ACHIEVEMENTS_PER_PAGE = 8;
    public const HISTORY_PER_PAGE = 6;
    public const HISTORY_MAX = 60;

    public const TABS = ["home", "stats", "rank", "medals", "ach", "history"];

    /** Opens a player's profile (yours when $target is your own name). */
    public static function open(Player $viewer, string $target, string $tab = "home", int $page = 0) : void{
        if(!in_array($tab, self::TABS, true)){
            $tab = "home";
        }
        $page = max(0, $page);
        $core = BedWarsCore::getInstance();
        $repo = $core->getProfileService()->getRepository();
        $self = strtolower($target) === strtolower($viewer->getName());

        $repo->loadProfile($target, function(?PlayerProfile $p) use ($viewer, $tab, $page, $self, $repo) : void{
            if(!$viewer->isConnected()){
                return;
            }
            if($p === null){
                $viewer->sendMessage("§c§lPROFILE §r§8| §cThe profile could not be loaded right now. Please try again in a moment.");
                return;
            }
            if(!$p->exists){
                $viewer->sendMessage("§c§lPROFILE §r§8| §7No BedWars profile was found for §f" . $p->username . "§7.");
                return;
            }

            $send = function(array $extra, int $pages, int $shownPage) use ($viewer, $p, $tab, $self) : void{
                if(!$viewer->isConnected()){
                    return;
                }
                $viewer->sendForm(new ProfileForm(new ProfileView($p, $tab, $shownPage, $pages, self::entityIdFor($viewer, $p->username, $self), $self, $viewer->getName(), $extra)));
            };

            switch($tab){
                case "stats":
                    $send([], 1, 0);
                    return;

                case "rank":
                    $repo->loadSeasonResults($p->username, 4, function(?array $rows) use ($send) : void{
                        $send(["seasons" => $rows ?? []], 1, 0);
                    });
                    return;

                case "history":
                    self::loadHistory($p, $page, false, $send);
                    return;

                default: // home, medals, ach need the medal counts
                    $repo->loadMedals($p->username, function(?array $medals) use ($p, $tab, $page, $send) : void{
                        $p->medals = $medals ?? [];
                        if($tab === "medals"){
                            $pages = max(1, (int) ceil(count(ProfileCatalog::MEDALS) / self::MEDALS_PER_PAGE));
                            $send([], $pages, min($page, $pages - 1));
                        }elseif($tab === "ach"){
                            $pages = max(1, (int) ceil(count(ProfileCatalog::ACHIEVEMENTS) / self::ACHIEVEMENTS_PER_PAGE));
                            $send([], $pages, min($page, $pages - 1));
                        }else{
                            $send([], 1, 0);
                        }
                    });
            }
        });
    }

    private static function loadHistory(PlayerProfile $p, int $page, bool $retried, callable $send) : void{
        $repo = BedWarsCore::getInstance()->getProfileService()->getRepository();
        $repo->loadHistory($p->username, self::HISTORY_PER_PAGE, $page * self::HISTORY_PER_PAGE, function(?array $res) use ($p, $page, $retried, $send) : void{
            if($res === null){
                $send(["history" => [], "historyTotal" => 0], 1, 0);
                return;
            }
            $total = min(self::HISTORY_MAX, $res["total"]);
            $pages = max(1, (int) ceil($total / self::HISTORY_PER_PAGE));
            if($res["rows"] === [] && $res["total"] > 0 && !$retried){
                self::loadHistory($p, $pages - 1, true, $send);
                return;
            }
            $send(["history" => $res["rows"], "historyTotal" => $res["total"]], $pages, min($page, $pages - 1));
        });
    }

    /** The "Top Ranked" board (current season). The left card always shows the viewer. */
    public static function openBoard(Player $viewer) : void{
        $svc = BedWarsCore::getInstance()->getProfileService();
        $repo = $svc->getRepository();
        $size = max(1, min(10, $svc->getConfig()->int("leaderboard.size", 10)));
        $repo->loadProfile($viewer->getName(), function(?PlayerProfile $p) use ($viewer, $repo, $size) : void{
            if(!$viewer->isConnected()){
                return;
            }
            if($p === null){
                $viewer->sendMessage("§c§lPROFILE §r§8| §cThe leaderboard could not be loaded right now. Please try again in a moment.");
                return;
            }
            $repo->loadTop($size, function(?array $rows) use ($viewer, $p) : void{
                if(!$viewer->isConnected()){
                    return;
                }
                $viewer->sendForm(new ProfileForm(new ProfileView($p, "board", 0, 1, $viewer->getId(), true, $viewer->getName(), ["top" => $rows ?? []])));
            });
        });
    }

    /** Small vanilla input form asking for the name to look up. */
    public static function openSearch(Player $viewer) : void{
        $form = new CustomForm(function(Player $player, $data) : void{
            if(!is_array($data)){
                return;
            }
            $name = trim((string) ($data[1] ?? ""));
            if($name === "" || !preg_match('/^[A-Za-z0-9_ ]{1,32}$/', $name)){
                $player->sendMessage("§c§lPROFILE §r§8| §7Please enter a valid player name.");
                return;
            }
            $online = $player->getServer()->getPlayerByPrefix($name);
            self::open($player, $online !== null ? $online->getName() : $name);
        });
        $form->setTitle("§d§lSearch Player");
        $form->addLabel("§7Look up any player's BedWars profile, rank, medals and match history.");
        $form->addInput("§fPlayer name", "Steve");
        $form->sendToPlayer($viewer);
    }

    private static function entityIdFor(Player $viewer, string $username, bool $self) : int{
        if($self){
            return $viewer->getId();
        }
        $online = $viewer->getServer()->getPlayerExact($username);
        return $online !== null ? $online->getId() : 0;
    }
}
