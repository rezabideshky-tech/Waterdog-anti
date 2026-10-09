<?php

declare(strict_types=1);

namespace sergittos\bedwars\party;

use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\session\Session;

class Party {

    private string  $id;
    private Session $leader;
    /** @var array<string,Session> */
    private array   $members     = [];
    /** @var array<string,int> name=>expiry */
    private array   $invitations = [];
    private int     $maxSize;

    public function __construct(Session $leader, int $maxSize = 4) {
        $this->id      = uniqid("party_", true);
        $this->leader  = $leader;
        $this->maxSize = $maxSize;
        $this->members[strtolower($leader->getUsername())] = $leader;
    }

    public function getId(): string      { return $this->id; }
    public function getLeader(): Session { return $this->leader; }
    /** @return Session[] */ 
    public function getMembers(): array  { return $this->members; }
    public function getSize(): int       { return count($this->members); }
    public function getMaxSize(): int    { return $this->maxSize; }
    public function isFull(): bool       { return $this->getSize() >= $this->maxSize; }

    public function isMember(Session $s): bool {
        return isset($this->members[strtolower($s->getUsername())]);
    }
    public function isLeader(Session $s): bool {
        return strtolower($this->leader->getUsername()) === strtolower($s->getUsername());
    }

    public function addMember(Session $s): void {
        $this->members[strtolower($s->getUsername())] = $s;
        $this->broadcast(TF::GREEN . "➔ " . TF::YELLOW . $s->getUsername() . TF::GREEN . " joined the party!");
    }

    public function removeMember(Session $s, bool $kicked = false): void {
        unset($this->members[strtolower($s->getUsername())]);
        if ($kicked) {
            $s->message(TF::DARK_GRAY . "[Party] " . TF::RED . "You were kicked from the party.");
            $this->broadcast(TF::RED . "✘ " . $s->getUsername() . " was kicked.");
        } else {
            $this->broadcast(TF::RED . "✘ " . $s->getUsername() . " left the party.");
        }
    }

    public function invite(Session $target, int $ttl = 60): bool {
        if ($this->isFull() || $this->isMember($target)) return false;
        $this->invitations[strtolower($target->getUsername())] = time() + $ttl;
        $target->message(
            TF::GOLD . "═══════════════════════\n" .
            TF::YELLOW . "  Party Invitation!\n" .
            TF::WHITE  . "  " . $this->leader->getUsername() . TF::GRAY . " invited you.\n" .
            TF::GREEN  . "  /party accept " . $this->leader->getUsername() . "\n" .
            TF::RED    . "  /party decline " . $this->leader->getUsername() . "\n" .
            TF::GRAY   . "  Expires in 60 seconds.\n" .
            TF::GOLD   . "═══════════════════════"
        );
        $this->leader->message(TF::GREEN . "Invite sent to " . TF::YELLOW . $target->getUsername() . TF::GREEN . ".");
        return true;
    }

    public function hasInvite(string $name): bool {
        $key = strtolower($name);
        if (!isset($this->invitations[$key])) return false;
        if (time() > $this->invitations[$key]) {
            unset($this->invitations[$key]);
            return false;
        }
        return true;
    }

    public function acceptInvite(Session $s): bool {
        if (!$this->hasInvite($s->getUsername())) return false;
        unset($this->invitations[strtolower($s->getUsername())]);
        $this->addMember($s);
        return true;
    }

    public function declineInvite(Session $s): void {
        unset($this->invitations[strtolower($s->getUsername())]);
        $this->broadcast(TF::YELLOW . $s->getUsername() . TF::RED . " declined the invite.");
    }

    public function transferLeader(Session $newLeader): bool {
        if (!$this->isMember($newLeader)) return false;
        $this->leader = $newLeader;
        $this->broadcast(TF::GOLD . "★ " . $newLeader->getUsername() . " is now the party leader!");
        return true;
    }

    /**
     * وقتی یک عضو به سرور دیگه‌ای ترنسفر می‌شه، Session قدیمیش دیگه معتبر
     * نیست (پروسه‌ی جدا). این متد وقتی دوباره به لابی برمی‌گرده صدا زده
     * می‌شه تا Session تازه‌ش جایگزین قدیمی بشه - بدون این، پارتی موقع
     * برگشتن از سرور گیم از بین می‌رفت.
     */
    public function updateSession(Session $newSession): void {
        $key = strtolower($newSession->getUsername());
        if (!isset($this->members[$key])) return;

        $wasLeader = strtolower($this->leader->getUsername()) === $key;
        $this->members[$key] = $newSession;
        if ($wasLeader) {
            $this->leader = $newSession;
        }
    }

    public function disband(): void {
        $this->broadcast(TF::RED . "The party has been disbanded.");
    }

    public function broadcast(string $msg): void {
        foreach ($this->members as $m) {
            $m->message(TF::DARK_GRAY . "[Party] " . $msg);
        }
    }

    public function cleanExpiredInvites(): void {
        $now = time();
        foreach ($this->invitations as $k => $exp) {
            if ($now > $exp) unset($this->invitations[$k]);
        }
    }
}
