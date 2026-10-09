<?php

declare(strict_types=1);

namespace sergittos\bedwars\party;

use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\session\Session;

class PartyManager {

    /** @var array<string,Party> id=>Party */
    private array $parties = [];
    /** @var array<string,string> username=>partyId */
    private array $map     = [];

    public function create(Session $leader): Party {
        $this->leave($leader, silent: true);
        $party = new Party($leader);
        $this->parties[$party->getId()] = $party;
        $this->map[strtolower($leader->getUsername())] = $party->getId();
        $leader->message(TF::GREEN . "Party created! Use /party invite <player> to add members.");
        return $party;
    }

    public function getPartyOf(Session $s): ?Party {
        $id = $this->map[strtolower($s->getUsername())] ?? null;
        return $id !== null ? ($this->parties[$id] ?? null) : null;
    }

    public function getPartyOfLeader(string $leaderName): ?Party {
        foreach ($this->parties as $p) {
            if (strtolower($p->getLeader()->getUsername()) === strtolower($leaderName)) {
                return $p;
            }
        }
        return null;
    }

    public function isInParty(Session $s): bool {
        return isset($this->map[strtolower($s->getUsername())]);
    }

    /**
     * All parties that currently have a live (non-expired) invite out to
     * this session, regardless of who sent it. Used by the "Pending
     * Invites" button in the party GUI so a player can see and accept/
     * decline invites without needing to know the inviter's name.
     *
     * @return Party[]
     */
    public function getPendingInvitesFor(Session $s): array {
        $out = [];
        foreach ($this->parties as $party) {
            if ($party->hasInvite($s->getUsername())) {
                $out[] = $party;
            }
        }
        return $out;
    }

    public function invite(Session $inviter, Session $target): void {
        $party = $this->getPartyOf($inviter) ?? $this->create($inviter);
        if (!$party->isLeader($inviter)) {
            $inviter->message(TF::RED . "Only the party leader can invite.");
            return;
        }
        if ($this->isInParty($target)) {
            $inviter->message(TF::RED . $target->getUsername() . " is already in a party.");
            return;
        }
        if ($party->isFull()) {
            $inviter->message(TF::RED . "Your party is full!");
            return;
        }
        $party->invite($target);
    }

    public function accept(Session $s, string $leaderName): void {
        $party = $this->getPartyOfLeader($leaderName);
        if ($party === null || !$party->hasInvite($s->getUsername())) {
            $s->message(TF::RED . "No valid invite from " . $leaderName . ".");
            return;
        }
        if (!$party->acceptInvite($s)) {
            $s->message(TF::RED . "Invite expired.");
            return;
        }
        $this->map[strtolower($s->getUsername())] = $party->getId();
    }

    public function decline(Session $s, string $leaderName): void {
        $party = $this->getPartyOfLeader($leaderName);
        if ($party !== null) $party->declineInvite($s);
        else $s->message(TF::RED . "No invite from " . $leaderName . ".");
    }

    public function leave(Session $s, bool $silent = false): void {
        $party = $this->getPartyOf($s);
        if ($party === null) {
            if (!$silent) $s->message(TF::RED . "You are not in a party.");
            return;
        }
        if ($party->isLeader($s)) {
            $members = $party->getMembers();
            unset($members[strtolower($s->getUsername())]);
            $party->removeMember($s);
            unset($this->map[strtolower($s->getUsername())]);
            if (empty($members)) {
                $party->disband();
                unset($this->parties[$party->getId()]);
            } else {
                $next = array_values($members)[0];
                $party->transferLeader($next);
            }
        } else {
            $party->removeMember($s);
            unset($this->map[strtolower($s->getUsername())]);
        }
    }

    public function disband(Session $leader): void {
        $party = $this->getPartyOf($leader);
        if ($party === null || !$party->isLeader($leader)) {
            $leader->message(TF::RED . "You are not a party leader.");
            return;
        }
        $party->disband();
        foreach ($party->getMembers() as $m) {
            unset($this->map[strtolower($m->getUsername())]);
        }
        unset($this->parties[$party->getId()]);
    }

    public function kick(Session $leader, Session $target): void {
        $party = $this->getPartyOf($leader);
        if ($party === null || !$party->isLeader($leader)) {
            $leader->message(TF::RED . "You are not the party leader.");
            return;
        }
        if (!$party->isMember($target)) {
            $leader->message(TF::RED . $target->getUsername() . " is not in your party.");
            return;
        }
        $party->removeMember($target, kicked: true);
        unset($this->map[strtolower($target->getUsername())]);
    }

    public function transfer(Session $leader, Session $target): void {
        $party = $this->getPartyOf($leader);
        if ($party === null || !$party->isLeader($leader)) {
            $leader->message(TF::RED . "You are not the party leader.");
            return;
        }
        $party->transferLeader($target);
    }

    public function onPlayerQuit(Session $s): void {
        // اینجا دیگه leave() صدا زده نمی‌شه - "quit" وقتی هم فایر می‌شه که
        // بازیکن به یک سرور Game ترنسفر شده (کاملاً عادی و انتظار می‌ره)،
        // نه فقط وقتی واقعاً از کل شبکه خارج شده. حذف خودکار اینجا دقیقاً
        // همون دلیلی بود که پارتی بعد از برگشت از سرور گیم از بین می‌رفت.
    }

    /**
     * وقتی یک بازیکن دوباره به این سرور (لابی) وصل می‌شه - چه بعد از
     * ترنسفر از یک سرور Game، چه یک لاگین تازه - این متد Session تازه‌ش رو
     * جای Session قدیمی (که با قطع اتصال قبلی نامعتبر شده) توی پارتی‌اش
     * می‌ذاره.
     */
    public function reconnect(Session $newSession): void {
        $party = $this->getPartyOf($newSession);
        if ($party !== null) {
            $party->updateSession($newSession);
        }
    }

    public function tickCleanup(): void {
        foreach ($this->parties as $p) $p->cleanExpiredInvites();
    }
}
