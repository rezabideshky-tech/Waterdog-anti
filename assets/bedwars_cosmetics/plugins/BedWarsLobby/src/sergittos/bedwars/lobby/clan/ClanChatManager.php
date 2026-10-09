<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\clan;

/**
 * Clan chat is a Lobby-only concept (players don't need a clan channel
 * mid-game), so this stays in-memory, in BedWarsLobby, exactly the way
 * Party's chat toggle already works - no database round trip needed for a
 * per-session UI toggle, and mutes are moderation, not permanent record, so
 * losing them on a server restart is an acceptable, deliberate trade-off.
 */
final class ClanChatManager{

    /** @var array<string,true> lowercase username => in clan-chat mode */
    private array $toggled = [];

    /** @var array<string,array<string,int>> clan id => (lowercase username => mute-until timestamp) */
    private array $mutes = [];

    public function isToggled(string $username): bool{
        return isset($this->toggled[strtolower($username)]);
    }

    public function setToggled(string $username, bool $value): void{
        if($value){
            $this->toggled[strtolower($username)] = true;
        }else{
            unset($this->toggled[strtolower($username)]);
        }
    }

    public function mute(string $clanId, string $username, int $seconds): void{
        $this->mutes[$clanId][strtolower($username)] = time() + $seconds;
    }

    public function unmute(string $clanId, string $username): void{
        unset($this->mutes[$clanId][strtolower($username)]);
    }

    public function isMuted(string $clanId, string $username): bool{
        $until = $this->mutes[$clanId][strtolower($username)] ?? 0;
        return $until > time();
    }

    public function getMuteRemaining(string $clanId, string $username): int{
        $until = $this->mutes[$clanId][strtolower($username)] ?? 0;
        return max(0, $until - time());
    }

    public function clearPlayer(string $username): void{
        unset($this->toggled[strtolower($username)]);
    }
}
