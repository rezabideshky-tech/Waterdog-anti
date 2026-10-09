<?php

declare(strict_types=1);

namespace sergittos\bedwars\session;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\InvisibilityEffect;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\Limits;
use pocketmine\world\Position;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\clan\ClanConfig;
use sergittos\bedwars\cosmetics\api\CosmeticsAPI;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\stage\EndingStage;
use sergittos\bedwars\game\stage\StartingStage;
use sergittos\bedwars\game\stage\WaitingStage;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\rank\RankSystemBridge;
use sergittos\bedwars\session\scoreboard\Scoreboard;
use sergittos\bedwars\session\settings\GameSettings;
use sergittos\bedwars\session\settings\SpectatorSettings;
use sergittos\bedwars\session\setup\MapSetup;
use sergittos\bedwars\utils\ColorUtils;
use function in_array;
use function max;
use function min;
use function time;

class Session{

    public const RESPAWN_DURATION = 5;

    private Player $player;

    private GameSettings $game_settings;
    private SpectatorSettings $spectatorSettings;

    private ?Scoreboard $scoreboard = null;

    private ?Game $game = null;
    private ?Team $team = null;
    private ?MapSetup $mapSetup = null;

    private ?Session $last_session_hit = null;
    private ?Session $tracking_session = null;

    private ?int $respawn_time = null;
    private ?int $last_session_hit_time = null;

    private bool $receivedVictoryRewards = false;

    private int $coins = 0;
    private int $kills = 0;
    private int $wins = 0;
    private int $finalKills = 0;
    private int $bedsBroken = 0;
    private int $deaths = 0;
    private int $xp = 0;
    private int $level = 1;
    private int $winStreak = 0;
    private int $bestWinStreak = 0;

    private int $gameStartKills = 0;
    private int $gameStartFinalKills = 0;

    /**
     * Snapshot of which clan this player belonged to when the session
     * loaded (set once by AsyncMysqlProvider::loadSession()). Used only to
     * credit clan XP for gameplay stats picked up on this Game server - the
     * full clan directory/GUI/roster logic lives entirely on the Lobby
     * server, this is intentionally the only clan-awareness the Game core
     * needs. If a player joins a clan mid-session they start being credited
     * on their next login, same as other session-scoped snapshots already
     * in this class.
     */
    private ?string $clanId = null;
    private static ?ClanConfig $clanConfig = null;
    private int $gameStartBedsBroken = 0;

    private string $selectedKillEffect = "none";
    private string $selectedKillSound  = "none";
    private string $selectedKillMessage = "none";
    private string $selectedCape       = "none";
    private string $selectedWing       = "none";
    private string $selectedHat        = "none";
    private string $selectedParticle   = "none";
    private string $selectedDance      = "none";
    private array $unlockedCosmetics = [];

    // Real wearable cosmetics (pet/wing/cape/hat) - separate from the
    // selectedCape/selectedWing/selectedHat fields above, which actually
    // back unrelated FX categories (bed break / death cry / win effect).
    private string $wearablePet  = "none";
    private string $wearableWing = "none";
    private string $wearableCape = "none";
    private string $wearableHat  = "none";

    private bool $loaded = false;

    private ?int $pendingRejoinUntil = null;

    private bool $spectatorVisualsApplied = false;

    /**
     * True while this session is a winner being held in place for the
     * victory countdown (EndingStage) - kept in their normal gamemode and
     * fully visible, just locked from moving, instead of being turned
     * into an invisible noclip spectator like eliminated players.
     */
    private bool $frozen = false;
    private ?Position $freezePosition = null;

    /**
     * True for a brief window right after this session's very first
     * team-spawn teleport at match start (see Game::teleportToTeamSpawn()
     * / SpawnFallSafetyTask). While true, GameListener::onReceiveDamage()
     * cancels any damage against this session outright - a last-resort
     * net against the classic "fell through the island because its
     * chunk hadn't finished loading yet" race, on top of the chunk
     * pre-loading that's the actual fix for that. Cleared by
     * SpawnFallSafetyTask once it's done watching, so it's never left on
     * for longer than that brief window.
     */
    private bool $spawnProtected = false;

    /**
     * True while this session is an active Bounty target for the
     * BedWarsGame in-match Bounty system (see
     * sergittos\bedwars\game\bounty\BountyManager). Purely transient,
     * per-life state: it is never saved with the rest of this session's
     * stats and always ends the moment this session dies or the match
     * ends - see BountyManager::onDeath()/reset(). Team::getFormattedNametag()
     * reads this to prepend the Bounty line above the player's nametag.
     */
    private bool $bounty = false;

    public function __construct(Player $player){
        $this->player = $player;
        $this->game_settings = new GameSettings($this);
        // Night vision defaults to on so spectators automatically get it the
        // moment they start spectating, without needing to open the spectator
        // settings menu first. Still fully player-toggleable afterwards.
        $this->spectatorSettings = new SpectatorSettings($this, 1, false, true);
        $this->setEffectHooks();
        BedWarsCore::getInstance()->getProvider()->loadSession($this);
    }

    private function safe(\Closure $fn): void{
        try{ $fn(); }catch(\Throwable){}
    }

    public function getPlayer(): Player{ return $this->player; }
    public function getUsername(): string{ return $this->player->getName(); }
    public function isLoaded(): bool{ return $this->loaded; }

    public function getCoins(): int{ return $this->coins; }
    public function getKills(): int{ return $this->kills; }
    public function getWins(): int{ return $this->wins; }
    public function getWinStreak(): int{ return $this->winStreak; }
    public function getBestWinStreak(): int{ return $this->bestWinStreak; }
    public function getFinalKills(): int{ return $this->finalKills; }
    public function getBedsBroken(): int{ return $this->bedsBroken; }
    public function getDeaths(): int{ return $this->deaths; }
    public function getXp(): int{ return $this->xp; }
    public function getLevel(): int{ return $this->level; }

    public function snapshotGameStats(): void{
        $this->gameStartKills = $this->kills;
        $this->gameStartFinalKills = $this->finalKills;
        $this->gameStartBedsBroken = $this->bedsBroken;
    }

    /**
     * Restores this session's "this game" kills/final kills/beds broken to
     * whatever a previous session for the same player had already earned,
     * instead of leaving them at zero.
     *
     * snapshotGameStats() above is only ever called once, for everyone
     * present when PlayingStage starts (see PlayingStage::onStart()). A
     * session created for a player who reconnects mid-match (see
     * Game::rejoinPlayer()) never goes through that, so without this its
     * gameStart* baseline stays at its default of 0 - which means
     * getGameKills()/getGameFinalKills()/getGameBedsBroken() would compute
     * against a zero baseline and report this player's entire LIFETIME
     * kill/final-kill/bed count as if it were earned in the current match.
     * That's what let a player who disconnected and reconnected - without
     * doing anything else - shoot to the top of the end-of-match "TOP
     * PLAYERS" board with numbers they never earned that game.
     *
     * Game::rejoinPlayer() reads the stale (pre-disconnect) session's
     * getGameKills()/getGameFinalKills()/getGameBedsBroken() before
     * discarding it and passes those three numbers in here, so the
     * reconnecting session picks up exactly where the old one left off -
     * any kills/finals/beds earned before the disconnect are kept, and
     * anything earned from this point on adds on top of them normally.
     */
    public function carryOverGameStats(int $kills, int $finalKills, int $bedsBroken): void{
        $this->gameStartKills = $this->kills - $kills;
        $this->gameStartFinalKills = $this->finalKills - $finalKills;
        $this->gameStartBedsBroken = $this->bedsBroken - $bedsBroken;
    }

    public function getGameKills(): int{ return $this->kills - $this->gameStartKills; }
    public function getGameFinalKills(): int{ return $this->finalKills - $this->gameStartFinalKills; }
    public function getGameBedsBroken(): int{ return $this->bedsBroken - $this->gameStartBedsBroken; }

    public function getSelectedKillEffect(): string{ return $this->selectedKillEffect; }
    public function getSelectedKillSound(): string{ return $this->selectedKillSound; }
    public function getSelectedKillMessage(): string{ return $this->selectedKillMessage; }
    public function getSelectedCape(): string{ return $this->selectedCape; }
    public function getSelectedWing(): string{ return $this->selectedWing; }
    public function getSelectedHat(): string{ return $this->selectedHat; }
    public function getSelectedParticle(): string{ return $this->selectedParticle; }
    public function getSelectedDance(): string{ return $this->selectedDance; }
    public function getUnlockedCosmetics(): array{ return $this->unlockedCosmetics; }

    public function getWearablePet(): string{ return $this->wearablePet; }
    public function getWearableWing(): string{ return $this->wearableWing; }
    public function getWearableCape(): string{ return $this->wearableCape; }
    public function getWearableHat(): string{ return $this->wearableHat; }

    public function getGameSettings(): GameSettings{ return $this->game_settings; }
    public function getSpectatorSettings(): SpectatorSettings{ return $this->spectatorSettings; }
    public function getGame(): ?Game{ return $this->game; }
    public function getTeam(): ?Team{ return $this->team; }
    public function getMapSetup(): ?MapSetup{ return $this->mapSetup; }

    public function setPendingRejoinUntil(?int $until): void{ $this->pendingRejoinUntil = $until; }
    public function getPendingRejoinUntil(): ?int{ return $this->pendingRejoinUntil; }
    public function hasPendingRejoin(): bool{ return $this->pendingRejoinUntil !== null && time() <= $this->pendingRejoinUntil; }

    public function getLastSessionHit(): ?Session{
        if($this->last_session_hit_time === null){
            return null;
        }
        if(time() - $this->last_session_hit_time <= 10){
            return $this->last_session_hit;
        }
        return null;
    }

    public function getTrackingSession(): ?Session{ return $this->tracking_session; }
    public function getRespawnTime(): ?int{ return $this->respawn_time; }

    /**
     * Locks this session in its current position (movement only - looking
     * around is still allowed) until unfreeze() is called. Used to hold
     * the winner(s) still and visible during the victory countdown.
     */
    public function freeze(): void{
        $this->frozen = true;
        $this->freezePosition = $this->player->isConnected() ? $this->player->getPosition() : null;
    }

    public function unfreeze(): void{
        $this->frozen = false;
        $this->freezePosition = null;
    }

    public function isFrozen(): bool{ return $this->frozen; }
    public function getFreezePosition(): ?Position{ return $this->freezePosition; }

    public function grantSpawnProtection(): void{
        $this->spawnProtected = true;
    }

    public function clearSpawnProtection(): void{
        $this->spawnProtected = false;
    }

    public function isSpawnProtected(): bool{ return $this->spawnProtected; }

    public function isBounty(): bool{ return $this->bounty; }
    public function setBounty(bool $bounty): void{ $this->bounty = $bounty; }

    public function setLoaded(bool $v): void{
        $this->loaded = $v;

        if($v && $this->player->isConnected()){
            $this->syncXpBar();
            if($this->game === null){
                $this->updateNametag();
            }
        }
    }

    public function getRequiredXPForNextLevel(): int{
        // Level 1 needs 5,000 XP, level 2 needs 10,000, level 3 needs
        // 15,000 ... i.e. 5,000 more per level. Single source of truth:
        // StatsGui, LobbyScoreboard, HologramManager, RewardXp and the XP
        // bar all read through this, so every display and the leveling
        // loop stay in sync automatically.
        return 5000 * max(1, $this->level);
    }

    public function setCoins(int $v): void{ $this->coins = max(0, $v); $this->save(); }
    public function setKills(int $v): void{ $this->kills = max(0, $v); $this->save(); }
    public function setWins(int $v): void{ $this->wins = max(0, $v); $this->save(); }
    public function setWinStreak(int $v): void{ $this->winStreak = max(0, $v); $this->save(); }
    public function setBestWinStreak(int $v): void{ $this->bestWinStreak = max(0, $v); $this->save(); }
    public function setFinalKills(int $v): void{ $this->finalKills = max(0, $v); $this->save(); }
    public function setBedsBroken(int $v): void{ $this->bedsBroken = max(0, $v); $this->save(); }
    public function setDeaths(int $v): void{ $this->deaths = max(0, $v); $this->save(); }
    public function setXp(int $v): void{ $this->xp = max(0, $v); $this->save(); }
    public function setLevel(int $v): void{ $this->level = max(1, $v); $this->save(); }

    public function setSelectedKillEffect(string $v): void{ $this->selectedKillEffect = $v; $this->save(); }
    public function setSelectedKillSound(string $v): void{ $this->selectedKillSound = $v; $this->save(); }
    public function setSelectedKillMessage(string $v): void{ $this->selectedKillMessage = $v; $this->save(); }
    public function setSelectedCape(string $v): void{ $this->selectedCape = $v; $this->save(); }
    public function setSelectedWing(string $v): void{ $this->selectedWing = $v; $this->save(); }
    public function setSelectedHat(string $v): void{ $this->selectedHat = $v; $this->save(); }
    public function setSelectedParticle(string $v): void{ $this->selectedParticle = $v; $this->save(); }
    public function setSelectedDance(string $v): void{ $this->selectedDance = $v; $this->save(); }
    public function setUnlockedCosmetics(array $v): void{ $this->unlockedCosmetics = $v; $this->save(); }

    public function setWearablePet(string $v): void{ $this->wearablePet = $v; $this->save(); }
    public function setWearableWing(string $v): void{ $this->wearableWing = $v; $this->save(); }
    public function setWearableCape(string $v): void{ $this->wearableCape = $v; $this->save(); }
    public function setWearableHat(string $v): void{ $this->wearableHat = $v; $this->save(); }

    public function setSpectatorSettings(SpectatorSettings $settings): void{ $this->spectatorSettings = $settings; }

    public function setScoreboard(Scoreboard $scoreboard): void{
        $this->scoreboard = $scoreboard;
        $this->updateScoreboard();
    }

    public function setGame(?Game $game): void{
        $this->game = $game;
        $this->receivedVictoryRewards = false;

        if($game === null){
            $this->team = null;
            $this->tracking_session = null;
            $this->pendingRejoinUntil = null;
            $this->setSpectatorVisuals(false);
        }else{
            $this->setSpectatorVisuals(false);
        }
    }

    public function hasReceivedVictoryRewards(): bool{ return $this->receivedVictoryRewards; }
    public function setReceivedVictoryRewards(bool $v): void{ $this->receivedVictoryRewards = $v; }

    public function setTeam(?Team $team): void{
        $this->team = $team;
        if($team !== null){
            $this->setSpectatorVisuals(false);
        }
    }

    public function setMapSetup(?MapSetup $mapSetup): void{ $this->mapSetup = $mapSetup; }

    public function setLastSessionHit(?Session $last_session_hit): void{
        $this->last_session_hit = $last_session_hit;
        $this->last_session_hit_time = time();
    }

    public function setTrackingSession(?Session $tracking_session): void{
        $this->tracking_session = $tracking_session;
        $this->updateCompassDirection();
    }

    public function setRespawnTime(?int $time): void{ $this->respawn_time = $time; }

    public function addCoins(int $amount): void{ $this->setCoins($this->coins + max(0, $amount)); }

    public function addKills(int $amount = 1): void{
        $this->setKills($this->kills + max(0, $amount));
        if($amount > 0){
            \sergittos\bedwars\profile\ProfileHooks::frag($this, false);
        }
        $this->creditClanXpPercent(max(0, $amount), self::getClanConfigInstance()->getKillXpPercent());
    }

    public function addWin(int $amount = 1): void{ $this->addWins($amount); }

    public function addWins(int $amount = 1): void{
        $this->setWins($this->wins + max(0, $amount));
        $this->creditClanXpPercent(max(0, $amount), self::getClanConfigInstance()->getWinXpPercent());
        $this->creditClanWeeklyCombat(max(0, $amount), 0);
    }

    public function addFinalKills(int $amount = 1): void{
        $this->setFinalKills($this->finalKills + max(0, $amount));
        if($amount > 0){
            \sergittos\bedwars\profile\ProfileHooks::frag($this, true);
        }
        $this->creditClanWeeklyCombat(0, max(0, $amount));
    }

    public function addBedsBroken(int $amount = 1): void{
        $this->setBedsBroken($this->bedsBroken + max(0, $amount));
        if($amount > 0){
            \sergittos\bedwars\profile\ProfileHooks::bed($this);
        }
        $this->creditClanXpPercent(max(0, $amount), self::getClanConfigInstance()->getBedXpPercent());
    }

    public function addDeath(): void{
        $this->setDeaths($this->deaths + 1);
        \sergittos\bedwars\profile\ProfileHooks::death($this);
    }

    public function getClanId(): ?string{ return $this->clanId; }
    public function setClanId(?string $clanId): void{ $this->clanId = $clanId; }

    private static function getClanConfigInstance(): ClanConfig{
        return self::$clanConfig ??= new ClanConfig(BedWarsCore::getInstance());
    }

    /**
     * Awards a percentage of a personal-XP-bearing gameplay event to this
     * player's clan, atomically, straight to MySQL - no shared cache with
     * the Lobby server is needed (or possible, since this is a different
     * process), see the doc-comment on $clanId above.
     */
    private function creditClanXpPercent(int $eventCount, float $percent): void{
        if($this->clanId === null || $eventCount <= 0 || $percent <= 0) return;
        // Each personal-XP "point" this event is worth isn't tracked as a
        // separate figure here, so we treat the event count itself as the
        // XP base - one kill/win/bed = 1 unit - and apply the configured
        // percentage on top, rounded down, with a floor of 1 so small
        // percentages still register something instead of vanishing to 0.
        $amount = (int) max(1, floor($eventCount * ($percent / 100) * 10));
        BedWarsCore::getInstance()->getProvider()->addClanXp($this->clanId, $amount);
    }

    private function creditClanWeeklyCombat(int $winsDelta, int $finalKillsDelta): void{
        if($this->clanId === null) return;
        if($winsDelta === 0 && $finalKillsDelta === 0) return;
        BedWarsCore::getInstance()->getProvider()->addClanWeeklyCombat($this->clanId, $winsDelta, $finalKillsDelta);
    }

    public function registerWinStreak(): void{
        $this->winStreak++;
        if($this->winStreak > $this->bestWinStreak){
            $this->bestWinStreak = $this->winStreak;
        }
        $this->save();
    }

    public function resetWinStreak(): void{
        $this->winStreak = 0;
        $this->save();
    }

    public function addXp(int $amount, string $reason = ""): void{
        $amount = max(0, $amount);
        $this->xp += $amount;

        if($amount > 0){
            $this->message("§6+{$amount} XP §7(" . ($reason !== "" ? $reason : "Progress") . "§7)");
        }

        while($this->xp >= $this->getRequiredXPForNextLevel()){
            $this->xp -= $this->getRequiredXPForNextLevel();
            $this->level++;
            $this->message("§a§lLevel Up§r §7- You reached §6" . $this->level . "§7.");
        }

        $this->save();
        $this->syncXpBar();
        if($this->game === null){
            $this->updateNametag();
        }
    }

    public function syncXpBar(): void{
        if(!$this->player->isConnected()){
            return;
        }

        $xpManager = $this->player->getXpManager();
        $need = $this->getRequiredXPForNextLevel();
        $progress = $need > 0 ? max(0.0, min(1.0, $this->xp / $need)) : 0.0;

        $xpManager->setXpLevel($this->level);
        $xpManager->setXpProgress($progress);
    }

    public function hasCosmetic(string $id): bool{
        return in_array($id, $this->unlockedCosmetics, true);
    }

    public function unlockCosmetic(string $id): void{
        if(!$this->hasCosmetic($id)){
            $this->unlockedCosmetics[] = $id;
            $this->save();
        }
    }

    /**
     * RankSystem (if installed and its session for this player has
     * finished loading) is now the source of truth for rank names.
     * The PurePerms/permission-based checks below only run as a
     * fallback - e.g. right at join before RankSystem's session has
     * loaded, or if RankSystem is ever removed/disabled.
     */
    public function getRank(): string{
        $rank = RankSystemBridge::getRankName($this->getUsername());
        if($rank !== null){
            return $rank;
        }

        $pp = $this->player->getServer()->getPluginManager()->getPlugin("PurePerms");
        if($pp !== null && method_exists($pp, "getUserDataMgr")){
            try{
                $group = $pp->getUserDataMgr()->getGroup($this->player);
                if($group !== null){
                    return $group->getName();
                }
            }catch(\Throwable){}
        }

        if($this->player->hasPermission("bedwars.rank.owner")) return "Owner";
        if($this->player->hasPermission("bedwars.rank.admin")) return "Admin";
        if($this->player->hasPermission("bedwars.rank.mod")) return "Mod";
        if($this->player->hasPermission("bedwars.rank.vipplus")) return "VIP+";
        if($this->player->hasPermission("bedwars.rank.vip")) return "VIP";
        return "Player";
    }

    private function getFallbackRankPrefixDisplay(): string{
        return match($this->getRank()){
            "Owner"  => "§e[§dOwner§e] ",
            "Admin"  => "§e[§bAdmin§7] ",
            "Mod"    => "§e[§6Mod§e] ",
            "VIP+"   => "§6[VIP+] ",
            "VIP"    => "§6[VIP] ",
            "Player" => "§6[Player] ",
            default  => "§6[Player] ",
        };
    }

    public function getRankPrefixDisplay(): string{
        return RankSystemBridge::getNametagPrefix($this->getUsername()) ?? $this->getFallbackRankPrefixDisplay();
    }

    public function getRankPrefix(?string $username = null): string{
        if($username !== null && $username !== $this->getUsername()){
            return "";
        }
        return $this->getRankPrefixDisplay();
    }

    public function getRankColor(): string{
        $color = RankSystemBridge::getNametagColor($this->getUsername());
        if($color !== null){
            return $color;
        }
        return match($this->getRank()){
            "Owner" => "§b",
            "Admin" => "§f",
            "Mod"   => "§e",
            "VIP+"  => "§6",
            "VIP"   => "§6",
            default => "§7",
        };
    }

    public function getRankChatColor(): string{
        $color = RankSystemBridge::getChatNameColor($this->getUsername());
        if($color !== null){
            return $color;
        }
        return match($this->getRank()){
            "Owner" => "§e",
            "Admin" => "§b",
            "Mod"   => "§6",
            "VIP+"  => "§e",
            "VIP"   => "§e",
            default => "§7",
        };
    }

    public function getLevelColor(): string{
        return match(true){
            $this->level < 10  => "§7",
            $this->level < 20  => "§f",
            $this->level < 30  => "§e",
            $this->level < 40  => "§6",
            $this->level < 60  => "§a",
            $this->level < 80  => "§b",
            $this->level < 100 => "§3",
            $this->level < 150 => "§c",
            $this->level < 200 => "§d",
            default            => "§5",
        };
    }

    public function getLevelLetter(): string{
        // Resource-pack level-star glyph ("§f" keeps it untinted).
        return "§f\u{F167}";
    }

    public function getFormattedLevel(): string{
        // The level colour is re-applied after the glyph so the closing
        // bracket keeps the level colour.
        return $this->getLevelColor() . "[" . $this->level . $this->getLevelLetter() . $this->getLevelColor() . "]";
    }

    public function updateNametag(): void{
        if(!$this->player->isConnected()){
            return;
        }

        if($this->game !== null && $this->team !== null){
            return;
        }

        $this->player->setNameTag($this->getFormattedLevel() . " " . $this->getRankPrefixDisplay() . "§r" . $this->player->getName());
        $this->player->setNameTagVisible(true);
        $this->player->setNameTagAlwaysVisible(true);
    }

    public function isPlaying(): bool{ return $this->game !== null && $this->game->isPlaying($this); }
    public function isSpectator(): bool{ return $this->game !== null && $this->game->isSpectator($this); }
    public function hasGame(): bool{ return $this->game !== null; }
    public function hasTeam(): bool{ return $this->team !== null; }
    public function isCreatingMap(): bool{ return $this->mapSetup !== null; }
    public function isRespawning(): bool{ return $this->respawn_time !== null; }
    public function isOnline(): bool{ return $this->player->isOnline(); }

    public function updateScoreboard(): void{
        $this->scoreboard?->show($this);
    }

    public function updateCompassDirection(): void{
        if(!$this->player->isConnected()){
            return;
        }

        $this->player->getNetworkSession()->syncWorldSpawnPoint(
            $this->tracking_session !== null
                ? $this->tracking_session->getPlayer()->getPosition()
                : $this->player->getWorld()->getSpawnLocation()
        );
    }

    public function attemptToRespawn(): void{
        if($this->respawn_time === null){
            return;
        }

        if($this->respawn_time <= 0){
            $this->respawn_time = null;
            $this->respawn();
            return;
        }

        if($this->respawn_time < 5){
            $msg = "§eRespawning in §c" . $this->respawn_time . "§e " . ($this->respawn_time === 1 ? "second" : "seconds") . "§e.";
            $this->title("§c§lYou Died", $msg);
            $this->message($msg);
        }

        $this->respawn_time--;
    }

    private function respawn(): void{
        if($this->game === null || $this->team === null){
            return;
        }

        $this->setSpectatorVisuals(false);

        $this->message("§a§lRespawned§r §7- Back in the fight.");
        $this->title("§a§lRespawned", "", 7, 21, 7);

        $this->game_settings->apply();
        $this->player->setGamemode(GameMode::SURVIVAL());
        $this->player->setHealth($this->player->getMaxHealth());
        $this->player->teleport(Position::fromObject($this->team->getSpawnPoint(), $this->game->getWorld()));
    }

    public function kill(int $cause, ?EntityDamageByEntityEvent $event = null): void{
        if($this->game === null){
            return;
        }

        $stage = $this->game->getStage();
        if($stage instanceof WaitingStage || $stage instanceof StartingStage){
            $this->respawn_time = null;

            $this->setSpectatorVisuals(false);

            $this->safe(fn() => $this->player->getEffects()->clear());
            $this->safe(fn() => $this->player->setGamemode(GameMode::SPECTATOR()));
            $this->safe(fn() => $this->player->setHealth($this->player->getMaxHealth()));
            $this->safe(fn() => $this->player->getHungerManager()->setFood(20));

            $this->giveWaitingItems();
            $this->teleportToWaitingWorld();
            return;
        }

        $this->addDeath();

        $bounty = $this->game->getBountyManager();

        $killer_session = $this->getLastSessionHit();
        $session_username = $this->getColoredUsername();

        if($killer_session !== null){
            if($this->hasTeam() && $this->team !== null && $this->team->isBedDestroyed()){
                $killer_session->addFinalKills();
                $killer_session->addXp(15, "Final Kill");
                $killer_session->playSound("random.orb");
                $killer_username = $killer_session->getColoredUsername();
                $bounty->registerKill($killer_session, true);

                $custom = $killer_session->getPlayer()->isConnected()
                    ? CosmeticsAPI::buildKillMessage($killer_session->getPlayer(), $session_username, $killer_username, false, true)
                    : null;
                $this->game->broadcastMessage($custom ?? ($session_username . " §7was killed by " . $killer_username . " §bFINAL KILL!"));
            }else{
                $killer_session->addKills();
                $killer_session->addXp(5, "Kill");
                $killer_session->playSound("random.orb");
                $killer_username = $killer_session->getColoredUsername();
                $bounty->registerKill($killer_session, false);

                if($cause === EntityDamageEvent::CAUSE_ENTITY_ATTACK){
                    $custom = $killer_session->getPlayer()->isConnected()
                        ? CosmeticsAPI::buildKillMessage($killer_session->getPlayer(), $session_username, $killer_username, false, false)
                        : null;
                    $this->game->broadcastMessage($custom ?? ($session_username . " §7was killed by " . $killer_username . "§7."));
                }elseif($cause === EntityDamageEvent::CAUSE_VOID){
                    $custom = $killer_session->getPlayer()->isConnected()
                        ? CosmeticsAPI::buildKillMessage($killer_session->getPlayer(), $session_username, $killer_username, true, false)
                        : null;
                    $this->game->broadcastMessage($custom ?? ($session_username . " §7was knocked into the void by " . $killer_username . "§7."));
                }else{
                    $this->game->broadcastMessage($session_username . " §7died.");
                }
            }

            if(!$killer_session->isSpectator()){
                foreach($this->player->getInventory()->getContents() as $item){
                    if(!in_array($item->getTypeId(), [ItemTypeIds::IRON_INGOT, ItemTypeIds::GOLD_INGOT, ItemTypeIds::DIAMOND, ItemTypeIds::EMERALD], true)){
                        continue;
                    }
                    $killer_session->getPlayer()->getInventory()->addItem($item);
                }
            }
        }else{
            if($cause === EntityDamageEvent::CAUSE_VOID){
                if($this->hasTeam() && $this->team !== null && $this->team->isBedDestroyed()){
                    $this->game->broadcastMessage($session_username . " §7fell into the void. §bFINAL KILL!");
                }else{
                    $this->game->broadcastMessage($session_username . " §7fell into the void.");
                }
            }else{
                $this->game->broadcastMessage($session_username . " §7died.");
            }
        }

        // Bounty state never survives a death - Bounty kill, normal kill,
        // or a killerless death (void/self) all end it the same way. See
        // BountyManager::onDeath()'s doc comment for the reward/expiry
        // split and why this must run after $killer_session's own
        // addKills()/addFinalKills() above but before $this leaves its
        // team below.
        $bounty->onDeath($this, $killer_session);

        $this->player->getEffects()->clear();
        $this->setSpectatorVisuals(false);

        $this->player->teleport(Position::fromObject($this->game->getMap()->getSpectatorSpawnPosition(), $this->game->getWorld()));
        $this->player->setGamemode(GameMode::SPECTATOR());

        $this->game_settings->decreasePickaxeTier();
        $this->game_settings->decreaseAxeTier();

        if($this->hasTeam() && $this->team !== null && $this->team->isBedDestroyed()){
            $this->game->removePlayer($this, false, true);
            return;
        }

        if($this->game->getStage() instanceof EndingStage){
            return;
        }

        $this->respawn_time = self::RESPAWN_DURATION;
        $this->clearCommonInventories();
        $this->title("§c§lYou Died", "§eRespawning in §c" . self::RESPAWN_DURATION . "§e seconds.", 0, 41);
        $this->message("§c§lEliminated§r §7- Respawning soon.");
    }

    /**
     * Forces a Podium Ceremony winner back into a fully visible, normal
     * gamemode state before being placed on their pedestal.
     *
     * Needed for the exact case where the match's very last exchange kills
     * BOTH the last enemy and the eventual winner at once (e.g. two
     * players knocked off the same bridge into the void together): the
     * winner's own kill() call above still runs (their fall/void damage
     * is real), which unconditionally teleports them to the spectator
     * spawn and puts them in a REAL GameMode::SPECTATOR() - engine-level
     * invisible to every non-spectator, exactly like any other Bedrock/
     * Java spectator. kill() then hits its
     * "$this->game->getStage() instanceof EndingStage" early-return (the
     * enemy's death already ended the match a moment earlier) and bails
     * out before ever restoring gamemode/health, because mid-match that
     * branch only exists to skip re-arming a respawn timer with no arena
     * left to respawn into - it was never written with "this player might
     * actually be the winner" in mind.
     *
     * EndingStage::handleSessionEnding() then finds isWinner === true and
     * (correctly) never calls promoteToSpectator() on them, but that just
     * means nothing else in the pipeline ever changes their gamemode back
     * either - so without this, a winner who died at that exact moment
     * stays in real Spectator on their own podium: only their
     * PodiumCeremony nametag/stats hologram is visible (that's built
     * independently of the player entity), never their actual skin.
     */
    public function restoreForVictoryCeremony(): void{
        if(!$this->player->isConnected()){
            return;
        }

        $this->setSpectatorVisuals(false);
        $this->safe(fn() => $this->player->setGamemode(GameMode::ADVENTURE()));
        $this->safe(fn() => $this->player->setHealth($this->player->getMaxHealth()));
        $this->safe(fn() => $this->player->getEffects()->clear());
    }

    public function clearAllInventories(): void{
        $this->clearCommonInventories();
        $this->safe(fn() => $this->player->getEnderInventory()->clearAll());
    }

    public function clearCommonInventories(): void{
        $this->safe(function(): void{
            if(method_exists($this->player, "getCursorInventory")){
                $this->player->getCursorInventory()->clearAll();
            }
        });

        $this->safe(function(): void{
            if(method_exists($this->player, "getOffHandInventory")){
                $this->player->getOffHandInventory()->clearAll();
            }
        });

        $this->safe(fn() => $this->player->getArmorInventory()->clearAll());
        $this->safe(fn() => $this->player->getInventory()->clearAll());
    }

    public function giveCreatingMapItems(): void{
        $this->setSpectatorVisuals(false);
        $this->clearAllInventories();
        $inv = $this->player->getInventory();

        $inv->setItem(0, BedwarsItems::CONFIGURATION()->asItem());

        $setup = $this->getMapSetup();
        if($setup !== null && method_exists($setup, "isEditing") && $setup->isEditing()){
            $inv->setItem(4, BedwarsItems::SAVE_EDITS()->asItem());
        }else{
            $inv->setItem(4, BedwarsItems::CREATE_MAP()->asItem());
        }

        $inv->setItem(8, BedwarsItems::EXIT_SETUP()->asItem());
    }

    public function giveWaitingItems(): void{
        $this->setSpectatorVisuals(false);
        $this->clearAllInventories();
        $inv = $this->player->getInventory();
        $inv->setItem(0, BedwarsItems::TEAM_SELECTOR()->asItem());
        $inv->setItem(8, BedwarsItems::LEAVE_GAME()->asItem());
    }

    /**
     * @param bool $trueSpectator When false (default - mid-match elimination,
     *        rejoin-into-eliminated-team, admin /viewgames spectating), the
     *        player is kept in a FAKE spectator built on top of ADVENTURE:
     *        on Bedrock the client never renders the hotbar/crosshair in
     *        real GameMode::SPECTATOR(), so none of the items below
     *        (Teleporter, Spectator Settings, Play Again, Return to Lobby)
     *        could ever be selected/used there - ADVENTURE keeps the hotbar
     *        working, and flight + disabled block collision + invisibility
     *        (setSpectatorVisuals()) reproduce the rest of the spectator feel.
     *
     *        When true - used for the end-of-match ("game over") spectator
     *        promotion in EndingStage only - the player is put into a REAL
     *        GameMode::SPECTATOR() instead, as required for that stage.
     *        Item interaction there is handled by SpectatorRawItemUseListener
     *        (reads the raw InventoryTransactionPacket before the engine's
     *        SPECTATOR handling can drop it) together with
     *        SpectatorProtectionListener's @handleCancelled-aware handlers,
     *        so Teleporter/Report Player/Play Again/Return to Lobby keep
     *        working the same way even though the gamemode itself is the
     *        real one this time.
     */
    public function giveSpectatorItems(bool $trueSpectator = false): void{
        $this->clearAllInventories();

        if($trueSpectator){
            $this->safe(fn() => $this->player->setGamemode(\pocketmine\player\GameMode::SPECTATOR()));
        }else{
            $this->safe(fn() => $this->player->setGamemode(\pocketmine\player\GameMode::SPECTATOR()));
            $this->safe(fn() => $this->player->setAllowFlight(true));
            $this->safe(fn() => $this->player->setFlying(true));
            $this->safe(fn() => $this->player->setHasBlockCollision(false));
        }

        $this->safe(fn() => $this->player->setHealth($this->player->getMaxHealth()));
        $this->safe(fn() => $this->player->getHungerManager()->setFood(20));

        $this->setSpectatorVisuals(true, $trueSpectator);

        $inv = $this->player->getInventory();
        $inv->setItem(0, \sergittos\bedwars\item\BedwarsItems::TELEPORTER()->asItem());
        $inv->setItem(3, \sergittos\bedwars\item\BedwarsItems::REPORT_PLAYER()->asItem());
        $inv->setItem(4, \sergittos\bedwars\item\BedwarsItems::SPECTATOR_SETTINGS()->asItem());
        $inv->setItem(7, \sergittos\bedwars\item\BedwarsItems::PLAY_AGAIN()->asItem());
        $inv->setItem(8, \sergittos\bedwars\item\BedwarsItems::RETURN_TO_LOBBY()->asItem());

        $this->spectatorSettings?->apply();
    }

    /**
     * @param bool $trueSpectator Whether this player is (or is about to be
     *        put) in the real GameMode::SPECTATOR() rather than the fake
     *        ADVENTURE-based one. Real spectator mode already has zero
     *        collision, no hitbox and is server-side exempt from arrows/
     *        melee/block-placement - none of that needs the hitbox-collapse
     *        trick below. Applying setScale(0.01) to a REAL spectator on
     *        top of that used to be dead weight at best, and at worst is
     *        exactly the kind of leftover "ADVENTURE workaround" visual
     *        that has no business running once the player is a genuine
     *        spectator - a near-zero model scale changes the player's own
     *        eye height/camera and can visibly glitch their own view
     *        (and third-person/other-viewer rendering) for no gameplay
     *        benefit once real spectator physics are already doing the
     *        job. Invisibility/nametag hiding stay on for both cases
     *        (also used to hide already-dead spectators from each other
     *        during the Podium Ceremony), since those are harmless and
     *        don't touch the hitbox/scale at all.
     */
    private function setSpectatorVisuals(bool $enabled, bool $trueSpectator = false): void{
        if(!$this->player->isConnected()){
            return;
        }

        if($enabled){
            // Deliberately NOT gated behind "already applied" like the
            // disable branch below - EndingStage::handleSessionEnding()
            // unconditionally clears every player's effects (including an
            // already-spectating player's Invisibility effect) right
            // before re-promoting them to spectator for the Podium
            // Ceremony. If this early-returned here because
            // $spectatorVisualsApplied was already true from earlier in
            // the match, the invisibility EFFECT that was just stripped
            // never got re-added (only the metadata flag persisted),
            // which is exactly why some already-dead spectators would
            // flicker visible only during the ceremony. Re-running this
            // every time is cheap and fully idempotent, so always do it.
            $this->spectatorVisualsApplied = true;

            $this->safe(fn() => $this->player->setInvisible(true));
            $this->safe(fn() => $this->player->setNameTagVisible(false));
            $this->safe(fn() => $this->player->setNameTagAlwaysVisible(false));

            if(!$trueSpectator){
                // Fake spectators (real GameMode::ADVENTURE(), see
                // giveSpectatorItems()) still keep a normal, full-size hitbox.
                // setHasBlockCollision(false) only stops *them* colliding with
                // blocks - it does nothing about *other* players' arrows,
                // melee swings or block placement colliding with *this*
                // entity. Real spectator mode is exempt from all of that
                // server-side; ours isn't, which let a spectator physically
                // stand in front of someone and eat their arrow, steal their
                // melee target, or block a block placement. Shrinking the
                // entity to a near-zero scale collapses its bounding box down
                // to something arrows/melee/placement checks never actually
                // intersect in practice, without needing a real spectator
                // gamemode (and its broken Bedrock hotbar) or a custom Player
                // subclass just to override collision. Combined with the
                // invisibility above, the player is both unseen and
                // untouchable.
                //
                // Skipped entirely for a real GameMode::SPECTATOR() player -
                // see this method's doc-comment for why forcing this on a
                // true spectator was itself a bug.
                $this->safe(fn() => $this->player->setScale(0.01));
            }

            $this->safe(function(): void{
                $effects = $this->player->getEffects();
                if(!$effects->has(VanillaEffects::INVISIBILITY())){
                    $effects->add(new EffectInstance(VanillaEffects::INVISIBILITY(), Limits::INT32_MAX, 0, false));
                }
            });

            return;
        }

        if(!$this->spectatorVisualsApplied){
            return;
        }
        $this->spectatorVisualsApplied = false;

        $this->safe(fn() => $this->player->setInvisible(false));
        $this->safe(function(): void{
            $this->player->getEffects()->remove(VanillaEffects::INVISIBILITY());
        });

        $this->safe(fn() => $this->player->setNameTagVisible(true));
        $this->safe(fn() => $this->player->setNameTagAlwaysVisible(true));
        // Restore the real hitbox size - harmless even when the scale hack
        // above was skipped (setScale() to the value it already is).
        $this->safe(fn() => $this->player->setScale(1.0));

        // giveSpectatorItems() puts fake spectators in ADVENTURE with block
        // collision disabled (see there for why). Since every place that ends
        // spectating goes through here first, this is the one guaranteed spot
        // to turn collision back on - relying on the gamemode change alone
        // isn't safe, since some exits (e.g. teleportToHub()) also land on
        // ADVENTURE, and setting a player to the gamemode they're already in
        // won't reset the flag on its own.
        $this->safe(fn() => $this->player->setHasBlockCollision(true));
        $this->safe(fn() => $this->player->setFlying(false));
        $this->safe(fn() => $this->player->setAllowFlight(false));
    }

    public function teleportToWaitingWorld(): void{
        if($this->game === null){
            return;
        }

        // Keep the arena copy warming up in the background (chunk lock,
        // shop villagers, etc.) since match start still needs it ready -
        // but it's no longer where the player actually waits, so a
        // failure here shouldn't block the teleport below.
        if($this->game->getWorld() === null){
            try{
                $this->game->setupWorld();
            }catch(\Throwable){
            }
        }

        $map = $this->game->getMap();
        $pos = Position::fromObject($map->getWaitingSpawnPosition(), $map->getWaitingWorld());
        $this->player->teleport($pos);
    }

    public function teleportToHub(): void{
        // A player who already disconnected (e.g. someone who dropped
        // mid-match and was still inside their 120s rejoin window when the
        // match ended) has a closed Player object: Living::$effectManager
        // is uninitialized on it, so getEffects() below throws
        // \Error "must not be accessed before initialization". That
        // exception used to escape all the way out of Game::reset() and
        // abort the ENTIRE arena reset (nobody got sent to the lobby, the
        // world never unloaded, the ending countdown just kept going
        // negative). There is nobody to transfer here anyway - only clear
        // our own bookkeeping and stop.
        if(!$this->player->isConnected()){
            $this->unfreeze();
            $this->setTrackingSession(null);
            $this->setTeam(null);
            $this->setGame(null);
            return;
        }

        $this->unfreeze();
        $this->setSpectatorVisuals(false);

        $this->safe(fn() => $this->player->getEffects()->clear());
        $this->safe(fn() => $this->player->setGamemode(GameMode::ADVENTURE()));
        $this->safe(fn() => $this->player->setHealth($this->player->getMaxHealth()));
        $this->safe(fn() => $this->player->setNameTag($this->player->getDisplayName()));
        $this->clearAllInventories();
        $this->setTrackingSession(null);
        $this->setTeam(null);
        $this->setGame(null);

        if(!$this->player->isConnected()){
            return;
        }

        BedWarsCore::getInstance()->getNetworkManager()->transferToLobby($this->player);
    }

    public function addEffect(EffectInstance $effect): void{
        $this->player->getEffects()->add($effect);
    }

    public function playSound(string $sound, float $volume = 1.0, float $pitch = 1.0): void{
        if(!$this->player->isConnected()){
            return;
        }
        $loc = $this->player->getLocation();
        $pk = PlaySoundPacket::create($sound, $loc->getX(), $loc->getY(), $loc->getZ(), $volume, $pitch);
        $this->sendDataPacket($pk);
    }

    public function title(string $title, string $sub = "", int $fadeIn = 0, int $stay = 21, int $fadeOut = 0): void{
        if(!$this->player->isConnected()){
            return;
        }
        $this->player->sendTitle(ColorUtils::translate($title), ColorUtils::translate($sub), $fadeIn, $stay, $fadeOut);
    }

    public function message(string $msg): void{
        if(!$this->player->isConnected()){
            return;
        }
        $this->player->sendMessage(ColorUtils::translate($msg));
    }

    public function sendDataPacket(ClientboundPacket $packet): void{
        if(!$this->player->isConnected()){
            return;
        }
        $this->player->getNetworkSession()->sendDataPacket($packet);
    }

    public function showBossBar(string $title): void{
        $this->hideBossBar();
        $this->sendDataPacket(BossEventPacket::show($this->player->getId(), ColorUtils::translate($title), 10, false, 0, BossBarColor::BLUE));
    }

    public function hideBossBar(): void{
        if(!$this->player->isConnected()){
            return;
        }
        $this->sendDataPacket(BossEventPacket::hide($this->player->getId()));
    }

    private function setEffectHooks(): void{
        // Wrapped in safe(): a Session must never be able to crash the
        // server just by being constructed. getEffects() reads Living's
        // typed $effectManager property, which PHP throws an Error for if
        // accessed on an entity that isn't (or is no longer) fully
        // initialized - e.g. a Session built around a Player object that
        // has already been closed elsewhere (a stale reference held past
        // that player's disconnect, as could previously happen via
        // TeleporterForm's spectator lookup). That is now fixed at the
        // source (callers should never hand a closed Player in here), but
        // this stays as a last-resort net: if it ever happens again for
        // any other reason, this session simply skips wiring up the
        // vanish-on-invisibility hooks instead of taking the whole server
        // down with an uncaught Error.
        $this->safe(function(): void{
            $effects = $this->player->getEffects();

            $effects->getEffectAddHooks()->add(function(EffectInstance $ei): void{
                if($ei->getType() instanceof InvisibilityEffect && ($this->isPlaying() || $this->isSpectator())){
                    $this->vanish();
                }
            });

            $effects->getEffectRemoveHooks()->add(function(EffectInstance $ei): void{
                if($ei->getType() instanceof InvisibilityEffect && ($this->isPlaying() || $this->isSpectator())){
                    $this->unvanish();
                }
            });
        });
    }

    private function vanish(): void{
        if($this->game === null){
            return;
        }

        $id = $this->player->getId();
        $air = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(VanillaItems::AIR()));
        $slot = $this->player->getInventory()->getHeldItemIndex();

        foreach($this->game->getPlayersAndSpectators() as $session){
            $ns = $session->getPlayer()->getNetworkSession();
            $ns->sendDataPacket(MobEquipmentPacket::create($id, $air, $slot, $slot, ContainerIds::INVENTORY));
            $ns->sendDataPacket(MobArmorEquipmentPacket::create($id, $air, $air, $air, $air, $air));
        }
    }

    private function unvanish(): void{
        if($this->game === null){
            return;
        }

        foreach($this->game->getPlayersAndSpectators() as $session){
            $ns = $session->getPlayer()->getNetworkSession();
            $broadcaster = $ns->getEntityEventBroadcaster();
            $broadcaster->onMobArmorChange([$ns], $this->player);
            $broadcaster->onMobMainHandItemChange([$ns], $this->player);
        }
    }

    public function save(): void{
        if($this->loaded){
            BedWarsCore::getInstance()->getProvider()->saveSession($this);
        }
    }

    public function getColoredUsername(): string{
        if($this->team !== null){
            return $this->team->getColor() . $this->getUsername();
        }
        return $this->getUsername();
    }

    public function resetSettings(): void{
        $this->game_settings = new GameSettings($this);
        $this->respawn_time = null;
        $this->unfreeze();
    }
}