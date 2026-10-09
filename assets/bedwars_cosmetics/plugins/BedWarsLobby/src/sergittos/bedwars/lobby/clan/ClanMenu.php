<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\clan;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\clan\Clan;
use sergittos\bedwars\clan\ClanColors;
use sergittos\bedwars\clan\ClanCrestRegistry;
use sergittos\bedwars\clan\ClanManager;
use sergittos\bedwars\clan\ClanRole;
use sergittos\bedwars\clan\data\PlayerClanDataManager;
use sergittos\bedwars\lobby\BedWarsLobby;
use sergittos\bedwars\lobby\LobbyItems;
use sergittos\bedwars\lobby\clan\ui\ClanForm;
use sergittos\bedwars\lobby\clan\ui\ClanLayouts;
use function array_keys;
use function array_search;
use function array_slice;
use function ceil;
use function count;
use function date;
use function is_array;
use function max;
use function mb_strlen;
use function mb_substr;
use function min;
use function str_replace;
use function strtolower;
use function strtoupper;
use function trim;
use function ucwords;
use function usort;

/**
 * Every clan menu screen. The list/confirm/review screens (plain button
 * choices - SimpleForm before this change) now render through ClanForm,
 * the red/gold "BedWars Clan UI" resource pack added alongside the purple
 * BedWars Profile UI (same trick, new "bwc" namespace - see
 * ui/bwp/clan.json and ClanLayouts).
 *
 * The screens that collect actual input (text fields, dropdowns, sliders,
 * toggles - CustomForm) are UNCHANGED and stay on vanilla Bedrock styling:
 * that custom-render trick only works for button-list "long forms" - there
 * is no equivalent hook for Bedrock's data-entry "custom forms", so
 * reskinning them isn't possible without a completely separate UI system.
 * That covers: promptApply, promptInvite, promptRejectReason,
 * openNotificationSettings, openDirectory (the actual search text box),
 * promptMetaDetails/promptSettingsDetails (name/tag/description/dropdowns/
 * slider) and promptCrest (Bedrock's own image-button grid - see
 * ClanCrestRegistry docblock; its icons come from a separate bundled
 * "ClanCrests" pack anyway, not this one), plus promptDeposit/
 * promptWithdraw and the staff creation-approval queue - all left exactly
 * as they were. Everything downstream of those inputs - openDirectoryResults
 * (the "search" screen, same 8-row+pagination layout as Members/Bank/etc.)
 * and promptCreateConfirm/promptSettingsConfirm (the "dialog" screen) - now
 * renders through ClanForm too.
 */
final class ClanMenu{

    /** Available clan tag colors, matches the palette already used for kill messages/ranks elsewhere in the plugin. */
    private const COLORS = ["WHITE", "AQUA", "GREEN", "YELLOW", "GOLD", "RED", "LIGHT_PURPLE", "BLUE", "DARK_GREEN", "DARK_AQUA"];

    private const TEX = "textures/bwc/";
    private const ROLE_ICON = [
        ClanRole::OWNER   => self::TEX . "ic_crown",
        ClanRole::OFFICER => self::TEX . "ic_officer",
        ClanRole::MEMBER  => self::TEX . "ic_member",
    ];

    private static function ready(Player $player): bool{
        if(!class_exists(SimpleForm::class) || !class_exists(CustomForm::class)){
            $player->sendMessage(TF::RED . TF::BOLD . "Clan Unavailable " . TF::RESET . TF::GRAY . "- FormAPI is required.");
            return false;
        }
        return true;
    }

    private static function mgr(): ClanManager{
        return BedWarsCore::getInstance()->getClanManager();
    }

    private static function chat(): ClanChatManager{
        return BedWarsLobby::getInstance()->getClanChatManager();
    }

    private static function session(Player $player){
        return BedWarsCore::getInstance()->getSessionManager()->get($player);
    }

    // ==========================================================================
    // Small helpers shared by every red/gold screen
    // ==========================================================================

    /**
     * Generic confirm/prompt/choice screen (up to 6 options), used for every
     * leave/disband/invite/mute/accept-reject style dialog.
     * @param list<array{label:string, action:(callable(Player):void)}> $options
     */
    private static function dialog(Player $player, string $icon, string $title, string $message, array $options): void{
        $values = ["icon" => $icon, "title" => $title, "message" => $message];
        $callbacks = [];
        foreach(array_slice($options, 0, 6) as $i => $opt){
            $values["opt" . $i] = $opt["label"];
            $callbacks["opt" . $i] = $opt["action"];
        }
        $form = new ClanForm("dialog", $values, function(Player $p, string $action) use ($callbacks): void{
            if(isset($callbacks[$action])){
                $callbacks[$action]($p);
            }
        });
        $player->sendForm($form);
    }

    /**
     * One row inside a dashboard's row list (icon + one line of text + an
     * optional trailing "manage" button that opens a follow-up screen).
     * @param array<string,string> $values written into, by reference
     * @param array<string,callable(Player):void> $callbacks written into, by reference
     */
    private static function row(array &$values, array &$callbacks, int $i, string $icon, string $text, ?callable $onOpen = null): void{
        $values["row{$i}_icon"] = $icon;
        $values["row{$i}_text"] = $text;
        if($onOpen !== null){
            $values["row{$i}_action"] = "btn";
            $callbacks["row{$i}_action"] = $onOpen;
        }
    }

    private static function dashboard(Player $player, string $screen, array $values, array $callbacks): void{
        $form = new ClanForm($screen, $values, function(Player $p, string $action) use ($callbacks): void{
            if(isset($callbacks[$action])){
                $callbacks[$action]($p);
            }
        });
        $player->sendForm($form);
    }

    /** Every dashboard shares the same tab bar - wired once, reused everywhere. */
    private static function tabCallbacks(Player $player, Clan $clan): array{
        return [
            "tab_home" => fn(Player $p) => self::openClanHome($p, $clan),
            "tab_members" => fn(Player $p) => self::openMembers($p, $clan),
            "tab_war" => fn(Player $p) => self::openWar($p),
            "tab_bank" => fn(Player $p) => self::openBank($p, $clan),
            "tab_apps" => fn(Player $p) => self::openApplications($p, $clan),
            "tab_settings" => fn(Player $p) => ClanRole::canManageSettings($clan->getRole($p->getName()) ?? ClanRole::MEMBER) ? self::openSettings($p, $clan) : self::openClanHome($p, $clan),
            "close" => fn(Player $p) => self::openClanHome($p, $clan),
        ];
    }

    private static function tabValues(string $activeTab): array{
        // Every tab button is always enabled - the active one is simply the
        // screen already on screen. Presence (non-empty) is all ClanForm needs.
        return ["tab_home" => "btn", "tab_members" => "btn", "tab_war" => "btn", "tab_bank" => "btn", "tab_apps" => "btn", "tab_settings" => "btn", "close" => "btn"];
    }

    // ==========================================================================
    // Main menu
    // ==========================================================================

    public static function openMain(Player $player): void{
        if(!self::ready($player)) return;

        $clan = self::mgr()->getClanOf($player->getName());

        if($clan !== null){
            self::openClanHome($player, $clan);
            return;
        }

        self::mgr()->getPendingCreationRequestFor($player->getName(), function(?object $request) use ($player): void{
            $message = TF::GRAY . "You're not in a clan yet. Create your own\nor join one from the directory.";
            if($request !== null){
                $message .= "\n\n" . TF::YELLOW . "Creation request \"" . $request->name . "\" is " . TF::GOLD . "awaiting staff review" . TF::YELLOW . ".";
            }

            $options = [];
            $options[] = ["label" => TF::GOLD . TF::BOLD . "Create Clan", "action" => fn(Player $p) => self::openCreateStart($p)];
            $options[] = ["label" => TF::AQUA . TF::BOLD . "Search", "action" => fn(Player $p) => self::openDirectory($p)];
            if($player->hasPermission("bedwars.clan.approve")){
                $options[] = ["label" => TF::LIGHT_PURPLE . TF::BOLD . "Review Requests", "action" => fn(Player $p) => self::openCreateApproval($p)];
            }

            self::dialog($player, self::TEX . "ic_clan", TF::GOLD . "CLAN", $message, $options);
        });
    }

    public static function openClanHome(Player $player, Clan $clan): void{
        if(!self::ready($player)) return;

        $role = $clan->getRole($player->getName()) ?? ClanRole::MEMBER;
        $config = self::mgr()->getConfig();
        $chatOn = self::chat()->isToggled($player->getName());

        $values = self::tabValues("home");
        $values["hero"] = ClanCrestRegistry::getTexture($clan->getCrestId());
        $values["line1"] = $clan->getColoredTag() . " " . TF::BOLD . $clan->getName();
        $values["line2"] = TF::GRAY . "Level " . $clan->getLevel() . "  -  " . $clan->getMemberCount() . "/" . $clan->getCapacity($config) . " members  -  Rank: " . ClanRole::displayName($role);

        $callbacks = self::tabCallbacks($player, $clan);

        self::row($values, $callbacks, 0, self::TEX . "ic_clan", TF::AQUA . "Clan Info" . TF::GRAY . "  -  public profile & stats", fn(Player $p) => self::openInfo($p, $clan));
        self::row($values, $callbacks, 1, self::TEX . "ic_members", TF::GREEN . "Members" . TF::GRAY . "  -  roster & management", fn(Player $p) => self::openMembers($p, $clan));
        self::row($values, $callbacks, 2, self::TEX . "ic_bank", TF::GOLD . "Bank" . TF::GRAY . "  -  " . $clan->getBankCoins() . " coins", fn(Player $p) => self::openBank($p, $clan));

        $nextRow = 3;
        if(ClanRole::canReviewApplications($role)){
            self::row($values, $callbacks, $nextRow++, self::TEX . "ic_apps", TF::YELLOW . "Applications" . TF::GRAY . "  -  review join requests", fn(Player $p) => self::openApplications($p, $clan));
        }
        if(ClanRole::canManageSettings($role)){
            self::row($values, $callbacks, $nextRow++, self::TEX . "ic_settings", TF::LIGHT_PURPLE . "Settings" . TF::GRAY . "  -  edit clan details", fn(Player $p) => self::openSettings($p, $clan));
        }
        self::row($values, $callbacks, $nextRow++, self::TEX . "ic_apps", TF::WHITE . "Notifications" . TF::GRAY . "  -  choose what to be told about", fn(Player $p) => self::openNotificationSettings($p));

        $values["primary"] = ($chatOn ? TF::GREEN : TF::GRAY) . "Clan Chat: " . ($chatOn ? "ON" : "OFF");
        $callbacks["primary"] = function(Player $p) use ($clan): void{
            self::toggleChat($p);
            self::openClanHome($p, $clan);
        };

        if($clan->isOwner($player->getName())){
            $values["secondary"] = TF::DARK_RED . "Disband Clan";
            $callbacks["secondary"] = fn(Player $p) => self::promptDisband($p, $clan);
        }else{
            $values["secondary"] = TF::RED . "Leave Clan";
            $callbacks["secondary"] = fn(Player $p) => self::promptLeave($p, $clan);
        }

        $values["tertiary"] = TF::DARK_GRAY . "Clan Wars";
        $callbacks["tertiary"] = fn(Player $p) => self::openWar($p);

        self::dashboard($player, "home", $values, $callbacks);
    }

    private static function toggleChat(Player $player): void{
        $chat = self::chat();
        $now = !$chat->isToggled($player->getName());
        $chat->setToggled($player->getName(), $now);
        $player->sendMessage(TF::GOLD . "[Clan] " . TF::GRAY . "Clan chat is now " . ($now ? TF::GREEN . "ON" : TF::RED . "OFF") . TF::GRAY . ". " . ($now ? "Everything you say goes to your clan." : "You're back on public chat."));
    }

    private static function openWar(Player $player): void{
        $clan = self::mgr()->getClanOf($player->getName());
        $values = $clan !== null ? self::tabValues("war") : [];
        $values["hero"] = self::TEX . "ic_war";
        $values["line1"] = TF::BOLD . "Clan Wars";
        $values["line2"] = TF::YELLOW . TF::BOLD . "Coming Soon";
        $callbacks = $clan !== null ? self::tabCallbacks($player, $clan) : [];

        self::row($values, $callbacks, 0, self::TEX . "ic_war", TF::GRAY . "Fight other clans for rewards and bragging rights.");
        self::row($values, $callbacks, 1, self::TEX . "ic_clock", TF::GRAY . "This feature is still being built - check back soon.");

        $values["primary"] = TF::GRAY . "Back to Clan Menu";
        $callbacks["primary"] = function(Player $p) use ($clan): void{
            $c = $clan ?? self::mgr()->getClanOf($p->getName());
            if($c !== null) self::openClanHome($p, $c);
        };

        self::dashboard($player, "war", $values, $callbacks);
    }

    private static function promptLeave(Player $player, Clan $clan): void{
        $isOwner = $clan->isOwner($player->getName());
        $warning = $isOwner && $clan->getMemberCount() > 1
            ? TF::YELLOW . "You're the owner - ownership will transfer to another member automatically."
            : ($isOwner ? TF::YELLOW . "You're the only member - the clan will be disbanded." : "");

        $message = TF::GRAY . "Are you sure you want to leave " . $clan->getColoredName() . TF::GRAY . "?\n\n" . $warning;

        self::dialog($player, self::TEX . "ic_leave", TF::RED . "LEAVE CLAN", $message, [
            ["label" => TF::GRAY . "Cancel", "action" => fn(Player $p) => self::openClanHome($p, $clan)],
            ["label" => TF::RED . TF::BOLD . "Leave Clan", "action" => function(Player $p) use ($clan): void{
                $session = self::session($p);
                if($session === null) return;
                self::mgr()->leaveClan($session, function(bool $ok, string $message) use ($p): void{
                    $p->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $message);
                    LobbyItems::refreshClanItem(self::session($p));
                });
            }],
        ]);
    }

    /** First step of the owner's disband flow - a second, harder confirm follows in promptDisbandConfirm(). */
    private static function promptDisband(Player $player, Clan $clan): void{
        $message =
            TF::GRAY . "Disbanding " . $clan->getColoredName() . TF::GRAY . " will permanently remove it for all " . TF::WHITE . $clan->getMemberCount() . TF::GRAY . " member(s).\n\n" .
            TF::YELLOW . TF::BOLD . "This cannot be undone.";

        self::dialog($player, self::TEX . "ic_disband", TF::DARK_RED . "DISBAND CLAN", $message, [
            ["label" => TF::GRAY . "Cancel", "action" => fn(Player $p) => self::openClanHome($p, $clan)],
            ["label" => TF::RED . TF::BOLD . "Continue", "action" => fn(Player $p) => self::promptDisbandConfirm($p, $clan)],
        ]);
    }

    private static function promptDisbandConfirm(Player $player, Clan $clan): void{
        $message = TF::RED . TF::BOLD . "Are you absolutely sure?\n" . TF::RESET . TF::GRAY . TF::BOLD . $clan->getName() . TF::RESET . TF::GRAY . " and everything in it will be gone forever.";

        self::dialog($player, self::TEX . "ic_disband", TF::DARK_RED . "FINAL CONFIRMATION", $message, [
            ["label" => TF::GRAY . "Cancel", "action" => fn(Player $p) => self::openClanHome($p, $clan)],
            ["label" => TF::DARK_RED . TF::BOLD . "Yes, Disband Permanently", "action" => function(Player $p) use ($clan): void{
                $session = self::session($p);
                if($session === null) return;
                self::mgr()->disbandClan($session, function(bool $ok, string $message) use ($p): void{
                    $p->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $message);
                    LobbyItems::refreshClanItem(self::session($p));
                });
            }],
        ]);
    }

    // ==========================================================================
    // Public clan profile
    // ==========================================================================

    public static function openInfo(Player $player, Clan $clan): void{
        if(!self::ready($player)) return;

        $config = self::mgr()->getConfig();
        $isMember = $clan->hasMember($player->getName());

        $values = [];
        $values["hero"] = ClanCrestRegistry::getTexture($clan->getCrestId());
        $values["line1"] = $clan->getColoredTag() . " " . TF::BOLD . $clan->getName();
        $values["line2"] = TF::GRAY . ($clan->getDescription() !== "" ? $clan->getDescription() : "No description set.");

        $callbacks = [];
        self::row($values, $callbacks, 0, self::TEX . "ic_star", TF::GRAY . "Level " . TF::WHITE . $clan->getLevel() . TF::GRAY . "   Members " . TF::WHITE . $clan->getMemberCount() . "/" . $clan->getCapacity($config));
        self::row($values, $callbacks, 1, self::TEX . "ic_lock", TF::GRAY . "Type: " . TF::WHITE . ($clan->isPublic() ? "Public" : "Invite Only") . TF::GRAY . "   Required Level: " . TF::WHITE . $clan->getRequiredLevel());
        $rowIndex = 2;
        if($clan->getLastWeekRank() > 0){
            self::row($values, $callbacks, $rowIndex++, self::TEX . "ic_war", TF::GRAY . "Last week rank: " . TF::WHITE . "#" . $clan->getLastWeekRank() . TF::GRAY . " (score " . $clan->getLastWeekScore() . ")");
        }
        if($clan->getBestWeekRank() > 0){
            self::row($values, $callbacks, $rowIndex++, self::TEX . "ic_top", TF::GRAY . "Best weekly rank: " . TF::WHITE . "#" . $clan->getBestWeekRank());
        }

        if($isMember){
            $values["primary"] = TF::GRAY . "Back to Clan Menu";
            $callbacks["primary"] = fn(Player $p) => self::openClanHome($p, $clan);
        }elseif($clan->isPublic()){
            $values["primary"] = TF::GREEN . TF::BOLD . "Join Clan";
            $callbacks["primary"] = fn(Player $p) => self::joinClanDirect($p, $clan);
        }else{
            $values["primary"] = TF::GREEN . TF::BOLD . "Request to Join";
            $callbacks["primary"] = fn(Player $p) => self::promptApply($p, $clan);
        }

        self::dashboard($player, "info", $values, $callbacks);
    }

    /** Public clans: no review needed - join instantly as long as nothing (level, cooldown, capacity) blocks it. */
    private static function joinClanDirect(Player $player, Clan $clan): void{
        $session = self::session($player);
        if($session === null) return;

        self::mgr()->joinPublicClan($session, $clan, function(bool $ok, string $msg) use ($player): void{
            $player->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $msg);
            LobbyItems::refreshClanItem(self::session($player));
            $joined = self::mgr()->getClanOf($player->getName());
            if($ok && $joined !== null){
                self::openClanHome($player, $joined);
            }
        });
    }

    private static function promptApply(Player $player, Clan $clan): void{
        $form = new CustomForm(function(Player $player, ?array $data) use ($clan): void{
            if($data === null) return;
            $session = self::session($player);
            if($session === null) return;

            $message = trim((string) ($data[0] ?? ""));
            self::mgr()->applyToJoin($session, $clan, $message, function(bool $ok, string $msg) use ($player): void{
                $player->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $msg);
            });
        });
        $form->setTitle(TF::BOLD . "" . TF::GREEN . "APPLY TO " . strtoupper($clan->getName()));
        $form->addInput("Tell them why you'd be a good fit", "e.g. 500 wins, active daily...", "");
        $player->sendForm($form);
    }

    // ==========================================================================
    // Members
    // ==========================================================================

    private const MEMBERS_PER_PAGE = 6;

    public static function openMembers(Player $player, Clan $clan, int $page = 0): void{
        if(!self::ready($player)) return;

        $role = $clan->getRole($player->getName()) ?? ClanRole::MEMBER;
        $canManage = ClanRole::canManageMembers($role);

        $usernames = array_keys($clan->getMembers());
        usort($usernames, function(string $a, string $b) use ($clan): int{
            $rank = fn(string $r) => match($r){ ClanRole::OWNER => 0, ClanRole::OFFICER => 1, default => 2 };
            $ra = $rank($clan->getRole($a)); $rb = $rank($clan->getRole($b));
            return $ra <=> $rb ?: strtolower($a) <=> strtolower($b);
        });

        $pages = max(1, (int) ceil(count($usernames) / self::MEMBERS_PER_PAGE));
        $page = max(0, min($page, $pages - 1));
        $slice = array_slice($usernames, $page * self::MEMBERS_PER_PAGE, self::MEMBERS_PER_PAGE);

        $config = self::mgr()->getConfig();
        $values = self::tabValues("members");
        $values["hero"] = ClanCrestRegistry::getTexture($clan->getCrestId());
        $values["line1"] = TF::BOLD . "Members";
        $values["line2"] = TF::GRAY . count($usernames) . "/" . $clan->getCapacity($config) . " members";
        $values["page"] = TF::GRAY . "Page " . ($page + 1) . " / " . $pages;

        $callbacks = self::tabCallbacks($player, $clan);
        foreach($slice as $i => $username){
            $memberRole = $clan->getRole($username) ?? ClanRole::MEMBER;
            $online = BedWarsCore::getInstance()->getSessionManager()->getByName($username) !== null;
            $icon = self::ROLE_ICON[$memberRole] ?? self::ROLE_ICON[ClanRole::MEMBER];
            $text = ($online ? TF::GREEN : TF::GRAY) . $username . "  " . ClanRole::color($memberRole) . ClanRole::displayName($memberRole);
            self::row($values, $callbacks, $i, $icon, $text, fn(Player $p) => self::openMemberActions($p, $clan, $username));
        }

        if($page > 0){
            $values["prev"] = "btn";
            $callbacks["prev"] = fn(Player $p) => self::openMembers($p, $clan, $page - 1);
        }
        if($page < $pages - 1){
            $values["next"] = "btn";
            $callbacks["next"] = fn(Player $p) => self::openMembers($p, $clan, $page + 1);
        }

        if($canManage){
            $values["primary"] = TF::BLUE . TF::BOLD . "Invite Player";
            $callbacks["primary"] = fn(Player $p) => self::promptInvite($p, $clan);
        }
        $values["secondary"] = TF::GRAY . "Back to Clan Menu";
        $callbacks["secondary"] = fn(Player $p) => self::openClanHome($p, $clan);

        self::dashboard($player, "members", $values, $callbacks);
    }

    /** Owner/officer flow: type a name, send the request, the target has to accept it before they join. */
    private static function promptInvite(Player $player, Clan $clan): void{
        $form = new CustomForm(function(Player $player, ?array $data) use ($clan): void{
            if($data === null) return;
            $target = trim((string) ($data[0] ?? ""));
            if($target === "") return;

            $session = self::session($player);
            if($session === null) return;

            self::mgr()->inviteMember($session, $clan, $target, function(bool $ok, string $msg) use ($player, $clan, $target): void{
                $player->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $msg);
                if($ok){
                    $targetSession = BedWarsCore::getInstance()->getSessionManager()->getByName($target);
                    if($targetSession !== null && $targetSession->getPlayer()->isOnline()){
                        self::sendInvitePrompt($targetSession->getPlayer(), $clan, $player->getName());
                    }
                }
            });
        });
        $form->setTitle(TF::BOLD . "" . TF::BLUE . "INVITE PLAYER");
        $form->addInput(TF::AQUA . "Player username", "Steve", "");
        $player->sendForm($form);
    }

    /** Sent to the invited player - they must accept or decline before anything changes. */
    private static function sendInvitePrompt(Player $target, Clan $clan, string $inviterName): void{
        $message =
            $clan->getColoredTag() . " " . TF::BOLD . $clan->getName() . TF::RESET . "\n" .
            TF::GRAY . "Invited by: " . TF::WHITE . $inviterName . "\n" .
            TF::GRAY . "Level: " . TF::WHITE . $clan->getLevel() . TF::GRAY . "  Members: " . TF::WHITE . $clan->getMemberCount() . "\n\n" .
            TF::YELLOW . "This invite expires in 60 seconds.";

        self::dialog($target, ClanCrestRegistry::getTexture($clan->getCrestId()), TF::BLUE . "CLAN INVITE", $message, [
            ["label" => TF::GREEN . TF::BOLD . "Accept", "action" => fn(Player $p) => self::respondInvite($p, true)],
            ["label" => TF::RED . TF::BOLD . "Decline", "action" => fn(Player $p) => self::respondInvite($p, false)],
        ]);
    }

    private static function respondInvite(Player $target, bool $accept): void{
        $session = self::session($target);
        if($session === null) return;
        self::mgr()->respondToInvite($session, $accept, function(bool $ok, string $msg) use ($target): void{
            $target->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $msg);
            LobbyItems::refreshClanItem(self::session($target));
            $joined = self::mgr()->getClanOf($target->getName());
            if($ok && $joined !== null){
                self::openClanHome($target, $joined);
            }
        });
    }

    private static function openMemberActions(Player $player, Clan $clan, string $targetUsername): void{
        $actorRole = $clan->getRole($player->getName()) ?? ClanRole::MEMBER;
        $targetRole = $clan->getRole($targetUsername) ?? ClanRole::MEMBER;
        $isSelf = strtolower($targetUsername) === strtolower($player->getName());
        $isOwner = $actorRole === ClanRole::OWNER;
        $canKick = !$isSelf && $targetRole !== ClanRole::OWNER
            && (($isOwner) || (ClanRole::canManageMembers($actorRole) && !($actorRole === ClanRole::OFFICER && $targetRole === ClanRole::OFFICER)));
        $canMute = !$isSelf && $targetRole !== ClanRole::OWNER && ClanRole::canManageMembers($actorRole);

        $wrap = function(callable $mutation): callable{
            return function(Player $player) use ($mutation): void{
                $session = self::session($player);
                if($session === null) return;
                $mutation($session, function(bool $ok, string $msg) use ($player): void{
                    $player->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $msg);
                    $current = self::mgr()->getClanOf($player->getName());
                    if($current !== null){
                        self::openMembers($player, $current);
                    }
                });
            };
        };

        $options = [];
        if($isOwner && !$isSelf){
            if($targetRole !== ClanRole::OFFICER){
                $options[] = ["label" => TF::GREEN . "Promote to Officer", "action" => $wrap(fn($session, $reply) => self::mgr()->setOfficerRole($session, $targetUsername, true, $reply))];
            }else{
                $options[] = ["label" => TF::YELLOW . "Demote to Member", "action" => $wrap(fn($session, $reply) => self::mgr()->setOfficerRole($session, $targetUsername, false, $reply))];
            }
            $options[] = ["label" => TF::GOLD . "Transfer Ownership", "action" => $wrap(fn($session, $reply) => self::mgr()->transferOwnership($session, $targetUsername, $reply))];
        }
        if($canMute){
            $clanId = $clan->getId();
            $isMuted = self::chat()->isMuted($clanId, $targetUsername);
            $options[] = [
                "label" => $isMuted ? TF::AQUA . "Unmute in Clan Chat" : TF::YELLOW . "Mute in Clan Chat",
                "action" => function(Player $player) use ($clanId, $targetUsername, $isMuted, $clan): void{
                    if($isMuted){
                        self::chat()->unmute($clanId, $targetUsername);
                        $player->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $targetUsername . " was unmuted in clan chat.");
                        self::openMembers($player, $clan);
                        return;
                    }
                    self::promptMuteDuration($player, $clan, $targetUsername);
                },
            ];
        }
        if($canKick){
            $options[] = ["label" => TF::RED . "Kick from Clan", "action" => $wrap(fn($session, $reply) => self::mgr()->kickMember($session, $targetUsername, $reply))];
        }
        $options[] = ["label" => TF::GRAY . "Back", "action" => fn(Player $player) => self::openMembers($player, $clan)];

        self::dialog(
            $player,
            self::ROLE_ICON[$targetRole] ?? self::ROLE_ICON[ClanRole::MEMBER],
            TF::WHITE . strtoupper($targetUsername),
            TF::GRAY . "Rank: " . TF::YELLOW . ClanRole::displayName($targetRole),
            $options
        );
    }

    private static function promptMuteDuration(Player $player, Clan $clan, string $targetUsername): void{
        $options = [
            ["10 minutes", 600],
            ["1 hour", 3600],
            ["24 hours", 86400],
            ["7 days", 604800],
        ];

        $dialogOptions = [];
        foreach($options as [$label, $seconds]){
            $dialogOptions[] = ["label" => TF::YELLOW . $label, "action" => function(Player $player) use ($clan, $targetUsername, $label, $seconds): void{
                self::chat()->mute($clan->getId(), $targetUsername, $seconds);
                $player->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $targetUsername . " was muted in clan chat for " . $label . ".");
                self::openMembers($player, $clan);
            }];
        }
        $dialogOptions[] = ["label" => TF::GRAY . "Cancel", "action" => fn(Player $p) => self::openMemberActions($p, $clan, $targetUsername)];

        self::dialog($player, self::TEX . "ic_clock", TF::YELLOW . "MUTE DURATION", TF::GRAY . "How long should " . $targetUsername . " be muted in clan chat?", $dialogOptions);
    }

    // ==========================================================================
    // Bank
    // ==========================================================================

    public static function openBank(Player $player, Clan $clan): void{
        if(!self::ready($player)) return;

        $role = $clan->getRole($player->getName()) ?? ClanRole::MEMBER;
        $canManage = ClanRole::canManageBank($role);

        self::mgr()->getBankHistory($clan, function(array $rows) use ($player, $clan, $canManage): void{
            $values = self::tabValues("bank");
            $values["hero"] = self::TEX . "ic_bank";
            $values["line1"] = TF::BOLD . "Clan Bank";
            $values["line2"] = TF::GOLD . TF::BOLD . $clan->getBankCoins() . " coins";

            $callbacks = self::tabCallbacks($player, $clan);
            $i = 0;
            foreach(array_slice($rows, 0, 8) as $row){
                $type = (string) $row["type"];
                $color = $type === "deposit" ? TF::GREEN : TF::RED;
                $sign = $type === "deposit" ? "+" : "-";
                $text = $color . $sign . $row["amount"] . TF::GRAY . " by " . TF::WHITE . $row["username"] . TF::GRAY . " (" . date("M j", (int) $row["created_at"]) . ")";
                self::row($values, $callbacks, $i++, self::TEX . "ic_bank", $text);
            }
            if($i === 0){
                self::row($values, $callbacks, 0, self::TEX . "ic_bank", TF::GRAY . "No transactions yet.");
            }

            $values["primary"] = TF::GREEN . TF::BOLD . "Deposit Coins";
            $callbacks["primary"] = fn(Player $p) => self::promptDeposit($p, $clan);
            if($canManage){
                $values["secondary"] = TF::RED . TF::BOLD . "Withdraw Coins";
                $callbacks["secondary"] = fn(Player $p) => self::promptWithdraw($p, $clan);
            }

            self::dashboard($player, "bank", $values, $callbacks);
        });
    }

    private static function promptDeposit(Player $player, Clan $clan): void{
        $session = self::session($player);
        if($session === null) return;

        $form = new CustomForm(function(Player $player, ?array $data): void{
            if($data === null) return;
            $amount = (int) ($data[0] ?? 0);
            $session = self::session($player);
            if($session === null) return;

            self::mgr()->depositBank($session, $amount, function(bool $ok, string $msg) use ($player): void{
                $player->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $msg);
            });
        });
        $form->setTitle(TF::BOLD . "" . TF::GREEN . "DEPOSIT COINS");
        $form->addLabel(TF::GRAY . "Your balance: " . TF::GOLD . $session->getCoins() . " coins");
        $form->addInput("Amount to deposit", "500", "");
        $player->sendForm($form);
    }

    private static function promptWithdraw(Player $player, Clan $clan): void{
        $form = new CustomForm(function(Player $player, ?array $data) use ($clan): void{
            if($data === null) return;
            $amount = (int) ($data[0] ?? 0);
            $session = self::session($player);
            if($session === null) return;

            self::mgr()->withdrawBank($session, $amount, function(bool $ok, string $msg) use ($player): void{
                $player->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $msg);
            });
        });
        $form->setTitle(TF::BOLD . "" . TF::RED . "WITHDRAW COINS");
        $form->addLabel(TF::GRAY . "Clan bank: " . TF::GOLD . $clan->getBankCoins() . " coins");
        $form->addInput("Amount to withdraw", "500", "");
        $player->sendForm($form);
    }

    // ==========================================================================
    // Applications
    // ==========================================================================

    private const APPLICATIONS_PER_PAGE = 6;

    public static function openApplications(Player $player, Clan $clan, int $page = 0): void{
        if(!self::ready($player)) return;

        self::mgr()->getPendingApplications($clan, function(array $applications) use ($player, $clan, $page): void{
            $pages = max(1, (int) ceil(count($applications) / self::APPLICATIONS_PER_PAGE));
            $page = max(0, min($page, $pages - 1));
            $slice = array_slice($applications, $page * self::APPLICATIONS_PER_PAGE, self::APPLICATIONS_PER_PAGE);

            $values = self::tabValues("apps");
            $values["hero"] = self::TEX . "ic_apps";
            $values["line1"] = TF::BOLD . "Applications";
            $values["line2"] = TF::GRAY . count($applications) . " pending";
            $values["page"] = TF::GRAY . "Page " . ($page + 1) . " / " . $pages;

            $callbacks = self::tabCallbacks($player, $clan);
            foreach($slice as $i => $application){
                $preview = $application->message !== "" ? $application->message : "(no message)";
                if(mb_strlen($preview) > 40){
                    $preview = mb_substr($preview, 0, 40) . "...";
                }
                self::row($values, $callbacks, $i, self::TEX . "ic_apps", TF::WHITE . $application->username . TF::GRAY . "  " . $preview, fn(Player $p) => self::openApplicationActions($p, $clan, $application));
            }
            if(count($slice) === 0){
                self::row($values, $callbacks, 0, self::TEX . "ic_apps", TF::GRAY . "No pending applications right now.");
            }

            if($page > 0){
                $values["prev"] = "btn";
                $callbacks["prev"] = fn(Player $p) => self::openApplications($p, $clan, $page - 1);
            }
            if($page < $pages - 1){
                $values["next"] = "btn";
                $callbacks["next"] = fn(Player $p) => self::openApplications($p, $clan, $page + 1);
            }

            $values["primary"] = TF::GRAY . "Back to Clan Menu";
            $callbacks["primary"] = fn(Player $p) => self::openClanHome($p, $clan);

            self::dashboard($player, "apps", $values, $callbacks);
        });
    }

    private static function openApplicationActions(Player $player, Clan $clan, object $application): void{
        $message = TF::GRAY . "Message:\n" . TF::WHITE . ($application->message !== "" ? $application->message : "(no message)");

        self::dialog($player, self::TEX . "ic_apps", TF::WHITE . strtoupper($application->username), $message, [
            ["label" => TF::GREEN . TF::BOLD . "Accept", "action" => function(Player $player) use ($clan, $application): void{
                $session = self::session($player);
                if($session === null) return;
                self::mgr()->acceptApplication($session, $application->id, function(bool $ok, string $msg) use ($player, $clan): void{
                    $player->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $msg);
                    self::openApplications($player, $clan);
                });
            }],
            ["label" => TF::RED . TF::BOLD . "Reject", "action" => function(Player $player) use ($clan, $application): void{
                self::promptRejectReason($player, function(string $reason) use ($clan, $application): void{
                    self::mgr()->rejectApplication($application->id, $reason, function(bool $ok, string $msg): void{});
                }, fn(Player $p) => self::openApplications($p, $clan));
            }],
        ]);
    }

    /**
     * Shared reject-with-reason dialog, used by both application review and
     * creation-request review so the rejected player always gets a real
     * reason instead of a bare "rejected".
     * @param callable(string):void $onReasonSubmitted
     * @param callable(Player):void $onCancelled
     */
    private static function promptRejectReason(Player $player, callable $onReasonSubmitted, callable $onCancelled): void{
        $form = new CustomForm(function(Player $player, ?array $data) use ($onReasonSubmitted, $onCancelled): void{
            if($data === null){
                $onCancelled($player);
                return;
            }
            $reason = trim((string) ($data[0] ?? ""));
            if($reason === ""){
                $reason = "No reason given.";
            }
            $onReasonSubmitted($reason);
            $onCancelled($player);
        });
        $form->setTitle(TF::BOLD . "" . TF::RED . "REASON FOR REJECTION");
        $form->addInput("Reason", "Explain why this is being rejected...", "");
        $player->sendForm($form);
    }

    // ==========================================================================
    // Notifications
    // ==========================================================================

    public static function openNotificationSettings(Player $player): void{
        if(!self::ready($player)) return;

        BedWarsCore::getInstance()->getClanManager()->getPlayerData()->getState($player->getName(), function(array $state) use ($player): void{
            $notifications = is_array($state["notifications"] ?? null) ? $state["notifications"] : [];

            $form = new CustomForm(function(Player $player, ?array $data): void{
                if($data === null) return;
                $dataManager = BedWarsCore::getInstance()->getClanManager()->getPlayerData();
                foreach(PlayerClanDataManager::ALL_NOTIFICATIONS as $i => $key){
                    $dataManager->setNotificationEnabled($player->getName(), $key, (bool) ($data[$i] ?? true));
                }
                $player->sendMessage(TF::GOLD . "[Clan] " . TF::GRAY . "Notification preferences saved.");
            });

            $form->setTitle(TF::BOLD . "" . TF::WHITE . "CLAN NOTIFICATIONS");
            foreach(PlayerClanDataManager::ALL_NOTIFICATIONS as $key){
                $form->addToggle(PlayerClanDataManager::notificationLabel($key), (bool) ($notifications[$key] ?? true));
            }
            $player->sendForm($form);
        });
    }

    // ==========================================================================
    // Directory / search
    // ==========================================================================

    public static function openDirectory(Player $player): void{
        if(!self::ready($player)) return;

        $form = new CustomForm(function(Player $player, ?array $data): void{
            if($data === null) return;

            $query = trim((string) ($data[0] ?? ""));
            $results = self::mgr()->search($query !== "" ? $query : null, null, null, null, null, false);

            self::openDirectoryResults($player, $results);
        });

        $form->setTitle(TF::BOLD . "" . TF::AQUA . "SEARCH CLANS");
        $form->addInput(TF::AQUA . TF::BOLD . "Clan name", "Leave empty to see all", "");
        $player->sendForm($form);
    }

    /**
     * Reskinned clan-search results (bwc "search" screen - same 8-row +
     * pagination dashboard layout as Members/Bank/etc., see ClanLayouts).
     * The search box itself (openDirectory) stays a vanilla text prompt -
     * Bedrock has no reskin hook for real text input - but everything
     * downstream of it (this list) now matches the rest of the Clan UI.
     * @param Clan[] $results
     */
    private static function openDirectoryResults(Player $player, array $results, int $page = 0): void{
        $results = array_slice($results, 0, 40);
        $config = self::mgr()->getConfig();

        $pages = max(1, (int) ceil(count($results) / self::MEMBERS_PER_PAGE));
        $page = max(0, min($page, $pages - 1));
        $slice = array_slice($results, $page * self::MEMBERS_PER_PAGE, self::MEMBERS_PER_PAGE);

        $values = [];
        $values["hero"] = self::TEX . "ic_search";
        $values["line1"] = TF::BOLD . "Search Results";
        $values["line2"] = TF::GRAY . count($results) . " clan" . (count($results) === 1 ? "" : "s") . " found";
        $values["page"] = TF::GRAY . "Page " . ($page + 1) . " / " . $pages;

        $callbacks = [];
        foreach($slice as $i => $clan){
            $rank = ($page * self::MEMBERS_PER_PAGE) + $i + 1;
            $text = TF::GOLD . "#" . $rank . " " . TF::RESET . $clan->getColoredTag() . " " . TF::WHITE . $clan->getName() . "\n" .
                TF::GRAY . "Lv " . $clan->getLevel() . "  ·  " . $clan->getMemberCount() . "/" . $clan->getCapacity($config) . " members  ·  " .
                ($clan->isPublic() ? TF::GREEN . "Public" : TF::YELLOW . "Invite Only");
            self::row($values, $callbacks, $i, ClanCrestRegistry::getTexture($clan->getCrestId()), $text, fn(Player $p) => self::openInfo($p, $clan));
        }

        if(count($slice) === 0){
            $values["row0_text"] = TF::GRAY . "No clans matched your search.";
        }

        if($page > 0){
            $values["prev"] = "btn";
            $callbacks["prev"] = fn(Player $p) => self::openDirectoryResults($p, $results, $page - 1);
        }
        if($page < $pages - 1){
            $values["next"] = "btn";
            $callbacks["next"] = fn(Player $p) => self::openDirectoryResults($p, $results, $page + 1);
        }

        $values["primary"] = TF::AQUA . TF::BOLD . "New Search";
        $callbacks["primary"] = fn(Player $p) => self::openDirectory($p);
        $values["secondary"] = TF::GRAY . "Back to Clan Menu";
        $callbacks["secondary"] = fn(Player $p) => self::openMain($p);

        self::dashboard($player, "search", $values, $callbacks);
    }

    // ==========================================================================
    // Creation flow (multi-step: details -> crest -> confirm) and the
    // matching Settings-edit flow, sharing the same draft shape. Left on
    // vanilla forms throughout (see class docblock) - it's mostly text
    // fields/dropdowns/sliders that can't be reskinned anyway, and mixing
    // reskinned + vanilla steps in the same wizard would look worse than
    // a consistent plain style across all of it.
    // ==========================================================================

    public static function openCreateStart(Player $player): void{
        self::promptMetaDetails($player, self::defaultDraft(), fn(Player $p, array $draft) => self::promptCrest($p, $draft, fn(Player $p2, array $d2) => self::promptCreateConfirm($p2, $d2)));
    }

    public static function openSettingsStart(Player $player, Clan $clan): void{
        $draft = [
            "name" => $clan->getName(),
            "tag" => $clan->getTag(),
            "color" => $clan->getColor(),
            "description" => $clan->getDescription(),
            "crest_id" => $clan->getCrestId(),
            "visibility" => $clan->getVisibility(),
            "required_level" => $clan->getRequiredLevel(),
        ];
        self::promptSettingsDetails($player, $draft, fn(Player $p, array $d) => self::promptCrest($p, $d, fn(Player $p2, array $d2) => self::promptSettingsConfirm($p2, $d2)));
    }

    /**
     * Settings only ever touches Crest (next screen), clan type and required
     * level - name/tag/color/description stay locked after creation, so this
     * is a dedicated, shorter form rather than reusing promptMetaDetails().
     * @param callable(Player,array):void $onNext
     */
    private static function promptSettingsDetails(Player $player, array $draft, callable $onNext): void{
        $visibilityIndex = $draft["visibility"] === "INVITE_ONLY" ? 1 : 0;

        $form = new CustomForm(function(Player $player, ?array $data) use ($draft, $onNext): void{
            if($data === null) return;

            $draft["description"] = trim((string) ($data[0] ?? ""));
            $draft["visibility"] = ((int) ($data[1] ?? 0)) === 1 ? "INVITE_ONLY" : "PUBLIC";
            $draft["required_level"] = (int) ($data[2] ?? 1);

            $onNext($player, $draft);
        });

        $form->setTitle(TF::BOLD . "" . TF::LIGHT_PURPLE . "CLAN SETTINGS");
        $form->addLabel(TF::GRAY . "Name, tag and color can't be changed here.\n" . TF::AQUA . "Crest is picked on the next screen.");
        $form->addInput(TF::AQUA . TF::BOLD . "Description", "What's your clan about?", $draft["description"]);
        $form->addDropdown(TF::AQUA . TF::BOLD . "Clan type", ["Public - anyone can join instantly", "Invite Only - requires an application"], $visibilityIndex);
        $form->addSlider(TF::GOLD . TF::BOLD . "Required level to join", 1, 30, 1, max(1, min(30, $draft["required_level"])));

        $player->sendForm($form);
    }

    private static function defaultDraft(): array{
        return [
            "name" => "", "tag" => "", "color" => "WHITE", "description" => "",
            "crest_id" => ClanCrestRegistry::getDefault(), "visibility" => "PUBLIC", "required_level" => 1,
        ];
    }

    /** @param callable(Player,array):void $onNext */
    private static function promptMetaDetails(Player $player, array $draft, callable $onNext): void{
        $colorIndex = array_search($draft["color"], self::COLORS, true);
        $colorIndex = $colorIndex === false ? 0 : $colorIndex;
        $visibilityIndex = $draft["visibility"] === "INVITE_ONLY" ? 1 : 0;

        $form = new CustomForm(function(Player $player, ?array $data) use ($draft, $onNext): void{
            if($data === null) return;

            $draft["name"] = trim((string) ($data[0] ?? ""));
            $draft["tag"] = trim((string) ($data[1] ?? ""));
            $draft["description"] = trim((string) ($data[2] ?? ""));
            $draft["color"] = self::COLORS[(int) ($data[3] ?? 0)] ?? "WHITE";
            $draft["visibility"] = ((int) ($data[4] ?? 0)) === 1 ? "INVITE_ONLY" : "PUBLIC";
            $draft["required_level"] = (int) ($data[5] ?? 1);

            $onNext($player, $draft);
        });

        $form->setTitle(TF::BOLD . "" . TF::GOLD . "CLAN DETAILS");
        $form->addInput(TF::GOLD . TF::BOLD . "Clan name", "e.g. Phoenix Legion", $draft["name"]);
        $form->addInput(TF::YELLOW . TF::BOLD . "Clan tag (short)", "e.g. PHX", $draft["tag"]);
        $form->addInput(TF::AQUA . TF::BOLD . "Description", "What's your clan about?", $draft["description"]);
        $form->addDropdown(TF::LIGHT_PURPLE . TF::BOLD . "Tag color", self::colorLabels(), $colorIndex);
        $form->addDropdown(TF::GREEN . TF::BOLD . "Clan type", ["Public - anyone can join instantly", "Invite Only - requires an application"], $visibilityIndex);
        $form->addSlider(TF::DARK_AQUA . TF::BOLD . "Required level to join", 1, 30, 1, max(1, min(30, $draft["required_level"])));

        $player->sendForm($form);
    }

    private static function colorLabels(): array{
        $labels = [];
        foreach(self::COLORS as $color){
            $code = ClanColors::code($color);
            $labels[] = $code . ucwords(strtolower(str_replace("_", " ", $color)));
        }
        return $labels;
    }

    /** @param callable(Player,array):void $onNext */
    private static function promptCrest(Player $player, array $draft, callable $onNext): void{
        $crests = ClanCrestRegistry::all();
        $ids = array_keys($crests);

        $form = new SimpleForm(function(Player $player, ?int $data) use ($draft, $ids, $onNext): void{
            if($data === null || !isset($ids[$data])) return;
            $draft["crest_id"] = $ids[$data];
            $onNext($player, $draft);
        });

        $form->setTitle(TF::BOLD . "" . TF::AQUA . "SELECT CREST");
        $form->setContent(TF::GRAY . "Choose a crest for your clan.");
        foreach($crests as $id => $info){
            $marker = $id === $draft["crest_id"] ? TF::GREEN . TF::BOLD . "[Selected] " . TF::RESET : "";
            $form->addButton($marker . $info["name"], SimpleForm::IMAGE_TYPE_PATH, $info["texture"]);
        }

        $player->sendForm($form);
    }

    private static function draftSummary(array $draft): string{
        $color = ClanColors::code($draft["color"]);
        return
            $color . "[" . $draft["tag"] . "]" . TF::RESET . " " . TF::BOLD . $draft["name"] . TF::RESET . "\n" .
            TF::GRAY . ($draft["description"] !== "" ? $draft["description"] : "(no description)") . "\n\n" .
            TF::GRAY . "Crest: " . TF::WHITE . ClanCrestRegistry::getName($draft["crest_id"]) . "\n" .
            TF::GRAY . "Type: " . TF::WHITE . ($draft["visibility"] === "PUBLIC" ? "Public" : "Invite Only") . "\n" .
            TF::GRAY . "Required Level: " . TF::WHITE . $draft["required_level"];
    }

    /** Reskinned via the shared bwc dialog (icon + title + message + up to 6 options) - see class docblock. */
    private static function promptCreateConfirm(Player $player, array $draft): void{
        $cost = self::mgr()->getConfig()->getCreationCost();
        $message = self::draftSummary($draft) . "\n\n" . TF::YELLOW . "Cost: " . $cost . " coins (charged only if approved)\n" . TF::GRAY . "Your request will be reviewed by staff before the clan is created.";

        self::dialog($player, ClanCrestRegistry::getTexture($draft["crest_id"]), TF::GOLD . "CONFIRM CREATION", $message, [
            ["label" => TF::GRAY . "Cancel", "action" => fn(Player $p) => self::openMain($p)],
            ["label" => TF::GREEN . TF::BOLD . "Submit for Review", "action" => function(Player $p) use ($draft): void{
                $session = self::session($p);
                if($session === null) return;
                self::mgr()->requestCreation($session, $draft, function(bool $ok, string $msg) use ($p): void{
                    $p->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $msg);
                });
            }],
        ]);
    }

    /** Reskinned via the shared bwc dialog - see promptCreateConfirm. */
    private static function promptSettingsConfirm(Player $player, array $draft): void{
        self::dialog($player, ClanCrestRegistry::getTexture($draft["crest_id"]), TF::LIGHT_PURPLE . "CONFIRM CHANGES", self::draftSummary($draft), [
            ["label" => TF::GRAY . "Cancel", "action" => function(Player $p) use ($draft): void{
                $clan = self::mgr()->getClanOf($p->getName());
                if($clan !== null) self::openClanHome($p, $clan);
            }],
            ["label" => TF::GREEN . TF::BOLD . "Save Changes", "action" => function(Player $p) use ($draft): void{
                $session = self::session($p);
                if($session === null) return;
                self::mgr()->updateSettings($session, $draft, function(bool $ok, string $msg) use ($p): void{
                    $p->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $msg);
                    $clan = self::mgr()->getClanOf($p->getName());
                    if($ok && $clan !== null){
                        self::openClanHome($p, $clan);
                    }
                });
            }],
        ]);
    }

    public static function openSettings(Player $player, Clan $clan): void{
        if(!self::ready($player)) return;
        self::openSettingsStart($player, $clan);
    }

    // ==========================================================================
    // Staff: creation request review queue (left on vanilla forms - staff
    // tooling, not part of the player-facing clan experience)
    // ==========================================================================

    public static function openCreateApproval(Player $player): void{
        if(!self::ready($player)) return;
        if(!$player->hasPermission("bedwars.clan.approve")){
            $player->sendMessage(TF::RED . "You don't have permission to review clan requests.");
            return;
        }

        self::mgr()->getPendingCreationRequests(function(array $requests) use ($player): void{
            $form = new SimpleForm(function(Player $player, ?int $data) use ($requests): void{
                if($data === null || !isset($requests[$data])) return;
                self::openCreateApprovalActions($player, $requests[$data]);
            });

            $form->setTitle(TF::BOLD . "" . TF::LIGHT_PURPLE . "CLAN REQUESTS " . TF::GRAY . TF::RESET . "(" . count($requests) . ")");
            $form->setContent(count($requests) > 0 ? TF::GRAY . "Tap a request to review the full preview." : TF::GRAY . "No pending clan creation requests.");

            foreach($requests as $request){
                $color = ClanColors::code($request->color);
                $form->addButton(
                    $color . "[" . $request->tag . "] " . $request->name . TF::RESET . "\n" .
                    TF::GRAY . "by " . $request->username,
                    SimpleForm::IMAGE_TYPE_PATH,
                    ClanCrestRegistry::getTexture($request->crestId)
                );
            }

            $player->sendForm($form);
        });
    }

    private static function openCreateApprovalActions(Player $player, object $request): void{
        $color = ClanColors::code($request->color);
        $preview =
            $color . "[" . $request->tag . "] " . TF::BOLD . $request->name . TF::RESET . "\n" .
            TF::GRAY . "Requested by: " . TF::WHITE . $request->username . "\n" .
            TF::GRAY . "Description: " . TF::WHITE . ($request->description !== "" ? $request->description : "(none)") . "\n" .
            TF::GRAY . "Crest: " . TF::WHITE . ClanCrestRegistry::getName($request->crestId) . "\n" .
            TF::GRAY . "Type: " . TF::WHITE . ($request->visibility === "PUBLIC" ? "Public" : "Invite Only") . TF::GRAY . "  Required Level: " . TF::WHITE . $request->requiredLevel . "\n" .
            TF::GRAY . "Cost on approval: " . TF::GOLD . $request->cost . " coins";

        $form = new SimpleForm(function(Player $player, ?int $data) use ($request): void{
            if($data === null) return;

            if($data === 0){
                self::mgr()->approveCreationRequest($request->id, function(bool $ok, string $msg) use ($player): void{
                    $player->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $msg);
                    self::openCreateApproval($player);
                });
                return;
            }

            if($data === 1){
                self::promptRejectReason(
                    $player,
                    fn(string $reason) => self::mgr()->rejectCreationRequest($request->id, $reason, function(bool $ok, string $msg) use ($request): void{
                        $requesterSession = BedWarsCore::getInstance()->getSessionManager()->getByName($request->username);
                        if($requesterSession !== null){
                            $requesterSession->getPlayer()->sendMessage(TF::GOLD . "[Clan] " . TF::RED . "Your clan request \"" . $request->name . "\" was rejected: " . TF::GRAY . $reason);
                        }
                    }),
                    fn(Player $p) => self::openCreateApproval($p)
                );
                return;
            }

            self::openCreateApproval($player);
        });

        $form->setTitle(TF::BOLD . "" . TF::LIGHT_PURPLE . "REVIEW REQUEST");
        $form->setContent($preview);
        $form->addButton(TF::GREEN . TF::BOLD . "Approve");
        $form->addButton(TF::RED . TF::BOLD . "Reject");
        $form->addButton(TF::GRAY . "Back");
        $player->sendForm($form);
    }
}
