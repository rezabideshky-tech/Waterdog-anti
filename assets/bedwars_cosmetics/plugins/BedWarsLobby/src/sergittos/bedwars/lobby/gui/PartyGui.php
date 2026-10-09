<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\gui;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\party\Party;
use sergittos\bedwars\party\PartyManager;
use sergittos\bedwars\session\Session;
use function array_filter;
use function array_values;
use function count;
use function strtolower;

/**
 * Party GUI.
 *
 * Flow:
 *  - No party  -> "Party Menu" (create / view pending invites)
 *  - Pending invites (no party) -> accept/decline list
 *  - In a party -> "Party Menu" (info / invite / manage / leave)
 *  - Manage (leader only) -> "Party Management" (kick / promote / disband)
 */
class PartyGui {

    public function open(Player $player): void {
        $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
        if ($session === null) return;

        $pm = BedWarsCore::getInstance()->getPartyManager();
        $party = $pm->getPartyOf($session);

        if ($party === null) {
            $this->openNoParty($player, $session, $pm);
        } else {
            $this->openPartyMenu($player, $session, $party);
        }
    }

    // ------------------------------------------------------------------------
    // No party yet
    // ------------------------------------------------------------------------
    private function openNoParty(Player $player, Session $session, PartyManager $pm): void {
        $pending = $pm->getPendingInvitesFor($session);
        $pendingCount = count($pending);

        $form = new SimpleForm(function(Player $player, ?int $data) use ($session, $pm): void {
            if ($data === null) return;
            if ($data === 0) {
                $pm->create($session);
                $player->sendMessage(TF::GREEN . "Party created!");
                $this->open($player);
            } elseif ($data === 1) {
                $this->openPendingInvites($player, $session, $pm);
            }
        });

        $form->setTitle(TF::BOLD . TF::LIGHT_PURPLE . "PARTY");

        $content =
            "§7Squad up and dominate together.\n\n" .
            "§7Status §f» §cNo Party\n" .
            "§7Invites §f» §e" . $pendingCount . " §7waiting";
        $form->setContent($content);

        $form->addButton("§d§lStart a Party\n§7Become the leader", 0, "textures/ui/icon_multiplayer.png");

        $inviteLabel = "§5§lView Invites\n§7" . $pendingCount . " people want you";
        $form->addButton($inviteLabel, 0, "textures/ui/mail.png");

        $form->sendToPlayer($player);
    }

    private function openPendingInvites(Player $player, Session $session, PartyManager $pm): void {
        $pending = $pm->getPendingInvitesFor($session);

        if (empty($pending)) {
            $player->sendMessage(TF::YELLOW . "You have no pending party invites.");
            $this->open($player);
            return;
        }

        $form = new SimpleForm(function(Player $player, ?int $data) use ($pending, $session, $pm): void {
            if ($data === null) return;

            $list = array_values($pending);
            if (!isset($list[$data])) {
                $this->open($player);
                return;
            }

            $party = $list[$data];
            $this->openInviteResponse($player, $session, $pm, $party);
        });

        $form->setTitle(TF::BOLD . TF::AQUA . "Pending Invites");
        $form->setContent(TF::GRAY . "Select an invite to accept or decline:\n");

        foreach ($pending as $party) {
            $leader = $party->getLeader();
            $form->addButton(
                TF::YELLOW . TF::BOLD . $leader->getUsername() . "\n" . TF::GRAY . "Party size: " . TF::WHITE . $party->getSize() . TF::DARK_GRAY . "/" . TF::GRAY . $party->getMaxSize(),
                0,
                "textures/ui/icon_multiplayer.png"
            );
        }

        $form->addButton(TF::RED . "Back", 0, "textures/ui/icon_none.png");
        $form->sendToPlayer($player);
    }

    private function openInviteResponse(Player $player, Session $session, PartyManager $pm, Party $party): void {
        $leaderName = $party->getLeader()->getUsername();

        $form = new SimpleForm(function(Player $player, ?int $data) use ($session, $pm, $leaderName): void {
            if ($data === 0) {
                $pm->accept($session, $leaderName);
                $player->sendMessage(TF::GREEN . "You joined " . TF::YELLOW . $leaderName . TF::GREEN . "'s party!");
            } elseif ($data === 1) {
                $pm->decline($session, $leaderName);
                $player->sendMessage(TF::YELLOW . "Invite declined.");
            }
            $this->open($player);
        });

        $form->setTitle(TF::BOLD . TF::AQUA . "Invite from " . $leaderName);
        $form->setContent(TF::WHITE . $leaderName . TF::GRAY . " invited you to join their party.\n" . TF::GRAY . "Party size: " . TF::WHITE . $party->getSize() . TF::DARK_GRAY . "/" . TF::GRAY . $party->getMaxSize());
        $form->addButton(TF::GREEN . TF::BOLD . "Accept", 0, "textures/ui/icon_check.png");
        $form->addButton(TF::RED . TF::BOLD . "Decline", 0, "textures/ui/icon_none.png");
        $form->sendToPlayer($player);
    }

    // ------------------------------------------------------------------------
    // In a party
    // ------------------------------------------------------------------------
    private function openPartyMenu(Player $player, Session $session, Party $party): void {
        $isLeader = $party->isLeader($session);

        $form = new SimpleForm(function(Player $player, ?int $data) use ($session, $party, $isLeader): void {
            if ($data === null) return;

            $pm = BedWarsCore::getInstance()->getPartyManager();
            $actions = $this->getMainActions($isLeader);

            if (!isset($actions[$data])) return;

            switch ($actions[$data]) {
                case "info":
                    $this->openPartyMembers($player, $session, $party);
                    break;
                case "invite":
                    $this->openInviteList($player, $session, $party);
                    break;
                case "manage":
                    $this->openManagement($player, $session, $party);
                    break;
                case "leave":
                    $wasLeader = $party->isLeader($session);
                    $pm->leave($session);
                    $player->sendMessage($wasLeader ? TF::YELLOW . "You left the party." : TF::YELLOW . "You left the party.");
                    $this->open($player);
                    break;
            }
        });

        $form->setTitle(TF::BOLD . TF::LIGHT_PURPLE . "PARTY");
        $form->setContent(
            "§7Status §f» §aIn a Party\n" .
            "§7Leader §f» §d" . $party->getLeader()->getUsername() . "\n" .
            "§7Members §f» §e" . $party->getSize() . " §8/ §7" . $party->getMaxSize()
        );

        foreach ($this->getMainActions($isLeader) as $action) {
            $form->addButton($this->getActionLabel($action), 0, $this->getActionIcon($action));
        }
        $form->sendToPlayer($player);
    }

    private function getMainActions(bool $isLeader): array {
        $actions = ["info", "invite", "manage", "leave"];
        if (!$isLeader) {
            // A non-leader can still see info & leave; inviting/managing
            // stays leader-only but the button remains visible so members
            // know who can do what - clicking it as a non-leader is safely
            // rejected by PartyManager (see kick()/transfer()/invite()
            // "not the party leader" checks), it never silently no-ops.
        }
        return $actions;
    }

    private function getActionLabel(string $action): string {
        return match($action) {
            "info"    => "§d§lMembers\n§7See who's in",
            "invite"  => "§a§lInvite\n§7Add a player",
            "manage"  => "§6§lManage\n§7Leader tools",
            "leave"   => "§c§lLeave\n§7Exit the party",
            default   => $action,
        };
    }

    private function getActionIcon(string $action): string {
        return match($action) {
            "info"   => "textures/ui/icon_steve.png",
            "invite" => "textures/ui/icon_invite.png",
            "manage" => "textures/ui/gear.png",
            "leave"  => "textures/ui/icon_none.png",
            default  => "textures/ui/icon_none.png",
        };
    }

    private function openPartyMembers(Player $player, Session $session, Party $party): void {
        $members = $party->getMembers();
        $list = "";
        foreach ($members as $m) {
            $star = $party->isLeader($m) ? TF::GOLD . "★ " : TF::GRAY . "• ";
            $list .= $star . $m->getFormattedLevel() . " " . TF::WHITE . $m->getUsername() . "\n";
        }

        $form = new SimpleForm(function(Player $player, ?int $data) use ($session, $party): void {
            $this->openPartyMenu($player, $session, $party);
        });

        $form->setTitle(TF::BOLD . TF::LIGHT_PURPLE . "MEMBERS");
        $form->setContent(
            "§7Leader §f» §d" . $party->getLeader()->getUsername() . "\n" .
            "§7Members §f» §e" . $party->getSize() . " §8/ §7" . $party->getMaxSize() . "\n\n" .
            $list
        );
        $form->addButton(TF::RED . TF::BOLD . "Back", 0, "textures/ui/icon_none.png");
        $form->sendToPlayer($player);
    }

    // ------------------------------------------------------------------------
    // Invite a player - list online players who aren't already in a party
    // ------------------------------------------------------------------------
    private function openInviteList(Player $player, Session $session, Party $party): void {
        if (!$party->isLeader($session)) {
            $player->sendMessage(TF::RED . "Only the party leader can invite players.");
            $this->openPartyMenu($player, $session, $party);
            return;
        }

        $online = $player->getServer()->getOnlinePlayers();
        $candidates = array_filter($online, function(Player $p) use ($session): bool {
            if (strtolower($p->getName()) === strtolower($session->getUsername())) return false;
            $s = BedWarsCore::getInstance()->getSessionManager()->get($p);
            if ($s === null) return false;
            return !BedWarsCore::getInstance()->getPartyManager()->isInParty($s);
        });
        $candidates = array_values($candidates);

        if (empty($candidates)) {
            $player->sendMessage(TF::YELLOW . "No players available to invite.");
            $this->openPartyMenu($player, $session, $party);
            return;
        }

        $form = new SimpleForm(function(Player $player, ?int $data) use ($candidates, $session, $party): void {
            if ($data === null) return;
            if (!isset($candidates[$data])) return;

            $target = $candidates[$data];
            $targetSession = BedWarsCore::getInstance()->getSessionManager()->get($target);
            if ($targetSession === null) return;

            BedWarsCore::getInstance()->getPartyManager()->invite($session, $targetSession);
            $player->sendMessage(TF::GREEN . "Invitation sent to " . TF::YELLOW . $target->getName() . TF::GREEN . ".");
            $this->openPartyMenu($player, $session, $party);
        });

        $form->setTitle(TF::BOLD . TF::LIGHT_PURPLE . "Invite Player");
        $form->setContent(TF::GRAY . "Select a player to invite:\n");
        foreach ($candidates as $p) {
            $form->addButton(TF::WHITE . $p->getName(), 0, "textures/ui/icon_steve.png");
        }
        $form->addButton(TF::RED . "Cancel", 0, "textures/ui/icon_none.png");
        $form->sendToPlayer($player);
    }

    // ------------------------------------------------------------------------
    // Party Management (leader only): kick / promote / disband
    // ------------------------------------------------------------------------
    private function openManagement(Player $player, Session $session, Party $party): void {
        if (!$party->isLeader($session)) {
            $player->sendMessage(TF::RED . "Only the party leader can manage the party.");
            $this->openPartyMenu($player, $session, $party);
            return;
        }

        $form = new SimpleForm(function(Player $player, ?int $data) use ($session, $party): void {
            if ($data === null) return;

            $actions = $this->getManageActions($party);
            if (!isset($actions[$data])) return;

            switch ($actions[$data]) {
                case "kick":
                    $this->openMemberSelect($player, $session, $party, "kick");
                    break;
                case "promote":
                    $this->openMemberSelect($player, $session, $party, "promote");
                    break;
                case "disband":
                    $this->openDisbandConfirm($player, $session, $party);
                    break;
                case "back":
                    $this->openPartyMenu($player, $session, $party);
                    break;
            }
        });

        $form->setTitle(TF::BOLD . TF::GOLD . "PARTY MANAGEMENT");
        $form->setContent(TF::GRAY . "Manage your party members.");

        foreach ($this->getManageActions($party) as $action) {
            $form->addButton($this->getManageLabel($action), 0, $this->getManageIcon($action));
        }
        $form->sendToPlayer($player);
    }

    private function getManageActions(Party $party): array {
        $actions = [];
        if ($party->getSize() > 1) {
            $actions[] = "kick";
            $actions[] = "promote";
        }
        $actions[] = "disband";
        $actions[] = "back";
        return $actions;
    }

    private function getManageLabel(string $action): string {
        return match($action) {
            "kick"    => TF::RED . TF::BOLD . "Kick Member\n" . TF::GREEN . "Remove a player",
            "promote" => TF::YELLOW . TF::BOLD . "Promote Member\n" . TF::GREEN . "Transfer leadership",
            "disband" => TF::RED . TF::BOLD . "Disband Party\n" . TF::GREEN . "Delete the party",
            "back"    => TF::GRAY . TF::BOLD . "Back",
            default   => $action,
        };
    }

    private function getManageIcon(string $action): string {
        return match($action) {
            "kick"    => "textures/ui/icon_none.png",
            "promote" => "textures/ui/icon_crown.png",
            "disband" => "textures/ui/trash.png",
            "back"    => "textures/ui/arrow_left.png",
            default   => "textures/ui/icon_none.png",
        };
    }

    private function openMemberSelect(Player $player, Session $session, Party $party, string $action): void {
        $members = array_values(array_filter($party->getMembers(), fn(Session $m) => !$party->isLeader($m)));
        if (empty($members)) {
            $player->sendMessage(TF::YELLOW . "No other members to " . $action . ".");
            $this->openManagement($player, $session, $party);
            return;
        }

        $form = new SimpleForm(function(Player $player, ?int $data) use ($members, $session, $party, $action): void {
            if ($data === null) return;
            if (!isset($members[$data])) return;
            $target = $members[$data];
            $pm = BedWarsCore::getInstance()->getPartyManager();

            if ($action === "kick") {
                $pm->kick($session, $target);
                $player->sendMessage(TF::RED . "Kicked " . TF::YELLOW . $target->getUsername() . TF::RED . ".");
                $this->openManagement($player, $session, $party);
            } elseif ($action === "promote") {
                $pm->transfer($session, $target);
                $player->sendMessage(TF::GOLD . "Transferred leadership to " . TF::YELLOW . $target->getUsername() . TF::GOLD . ".");
                // The acting player is no longer the leader after a promote,
                // so send them back to the main party menu (Manage Party is
                // leader-only and would immediately reject them otherwise).
                $this->openPartyMenu($player, $session, $party);
            }
        });

        $form->setTitle($action === "kick" ? TF::BOLD . TF::RED . "Kick Member" : TF::BOLD . TF::YELLOW . "Promote Member");
        $form->setContent(TF::GRAY . "Select a member:\n");
        foreach ($members as $m) {
            $form->addButton($m->getFormattedLevel() . " " . TF::WHITE . $m->getUsername(), 0, "textures/ui/icon_steve.png");
        }
        $form->addButton(TF::RED . "Cancel", 0, "textures/ui/icon_none.png");
        $form->sendToPlayer($player);
    }

    private function openDisbandConfirm(Player $player, Session $session, Party $party): void {
        $form = new SimpleForm(function(Player $player, ?int $data) use ($session): void {
            if ($data === 0) {
                BedWarsCore::getInstance()->getPartyManager()->disband($session);
                $player->sendMessage(TF::RED . "Party disbanded.");
            }
            $this->open($player);
        });

        $form->setTitle(TF::BOLD . TF::RED . "Disband Party");
        $form->setContent(TF::YELLOW . "Are you sure you want to disband the party?\n" . TF::GRAY . "This cannot be undone.");
        $form->addButton(TF::RED . TF::BOLD . "Yes, Disband", 0, "textures/ui/trash.png");
        $form->addButton(TF::GREEN . "Cancel", 0, "textures/ui/icon_none.png");
        $form->sendToPlayer($player);
    }
}
