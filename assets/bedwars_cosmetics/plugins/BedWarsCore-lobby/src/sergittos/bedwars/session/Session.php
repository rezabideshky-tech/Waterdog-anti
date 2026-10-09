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

    public function __construct(Player $player){
        $this->player = $player;
        $this->game_settings = new GameSettings($this);
        $this->spectatorSettings = new SpectatorSettings($this, 1, false, false);
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
    public function addKills(int $amount = 1): void{ $this->setKills($this->kills + max(0, $amount)); }
    public function addWin(int $amount = 1): void{ $this->addWins($amount); }
    public function addWins(int $amount = 1): void{ $this->setWins($this->wins + max(0, $amount)); }
    public function addFinalKills(int $amount = 1): void{ $this->setFinalKills($this->finalKills + max(0, $amount)); }
    public function addBedsBroken(int $amount = 1): void{ $this->setBedsBroken($this->bedsBroken + max(0, $amount)); }
    public function addDeath(): void{ $this->setDeaths($this->deaths + 1); }

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
            $this->safe(fn() => $this->player->setGamemode(GameMode::ADVENTURE()));
            $this->safe(fn() => $this->player->setHealth($this->player->getMaxHealth()));
            $this->safe(fn() => $this->player->getHungerManager()->setFood(20));

            $this->giveWaitingItems();
            $this->teleportToWaitingWorld();
            return;
        }

        $this->addDeath();

        $killer_session = $this->getLastSessionHit();
        $session_username = $this->getColoredUsername();

        if($killer_session !== null){
            if($this->hasTeam() && $this->team !== null && $this->team->isBedDestroyed()){
                $killer_session->addFinalKills();
                $killer_session->addXp(15, "Final Kill");
                $killer_session->playSound("random.orb");
                $killer_username = $killer_session->getColoredUsername();
                $this->game->broadcastMessage($session_username . " §7was killed by " . $killer_username . " §bFINAL KILL!");
            }else{
                $killer_session->addKills();
                $killer_session->addXp(5, "Kill");
                $killer_session->playSound("random.orb");
                $killer_username = $killer_session->getColoredUsername();

                if($cause === EntityDamageEvent::CAUSE_ENTITY_ATTACK){
                    $this->game->broadcastMessage($session_username . " §7was killed by " . $killer_username . "§7.");
                }elseif($cause === EntityDamageEvent::CAUSE_VOID){
                    $this->game->broadcastMessage($session_username . " §7was knocked into the void by " . $killer_username . "§7.");
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

    public function giveSpectatorItems(): void{
        $this->clearAllInventories();

        // Fake spectator instead of real GameMode::SPECTATOR(): on Bedrock the
        // client never renders the hotbar/crosshair in true spectator mode, so
        // none of the items below (Teleporter, Spectator Settings, Play Again,
        // Return to Lobby) could ever be selected or used - no packet from the
        // server can override that client-side behaviour. ADVENTURE keeps the
        // hotbar working; flight + disabled block collision reproduce the fly-
        // through-blocks feel of real spectator, and setSpectatorVisuals()
        // below (invisibility + hidden nametag) hides the player from others.
        $this->safe(fn() => $this->player->setGamemode(\pocketmine\player\GameMode::ADVENTURE()));
        $this->safe(fn() => $this->player->setAllowFlight(true));
        $this->safe(fn() => $this->player->setFlying(true));
        $this->safe(fn() => $this->player->setHasBlockCollision(false));
        $this->safe(fn() => $this->player->setHealth($this->player->getMaxHealth()));
        $this->safe(fn() => $this->player->getHungerManager()->setFood(20));

        $this->setSpectatorVisuals(true);

        $inv = $this->player->getInventory();
        $inv->setItem(0, \sergittos\bedwars\item\BedwarsItems::TELEPORTER()->asItem());
        $inv->setItem(4, \sergittos\bedwars\item\BedwarsItems::SPECTATOR_SETTINGS()->asItem());
        $inv->setItem(7, \sergittos\bedwars\item\BedwarsItems::PLAY_AGAIN()->asItem());
        $inv->setItem(8, \sergittos\bedwars\item\BedwarsItems::RETURN_TO_LOBBY()->asItem());

        $this->spectatorSettings?->apply();
    }

    private function setSpectatorVisuals(bool $enabled): void{
        if(!$this->player->isConnected()){
            return;
        }

        if($enabled){
            if($this->spectatorVisualsApplied){
                return;
            }
            $this->spectatorVisualsApplied = true;

            $this->safe(fn() => $this->player->setInvisible(true));
            $this->safe(fn() => $this->player->setNameTagVisible(false));
            $this->safe(fn() => $this->player->setNameTagAlwaysVisible(false));

            // See the matching comment in BedWarsCore-game's Session - fake
            // spectators (real GameMode::ADVENTURE()) keep a normal hitbox,
            // which lets them physically block other players' arrows,
            // melee and block placement. Shrinking the scale collapses the
            // bounding box so nothing actually collides with it anymore.
            $this->safe(fn() => $this->player->setScale(0.01));

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
        // Restore the real hitbox size - see the setScale(0.01) call above.
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
        $this->setSpectatorVisuals(false);

        $this->player->getEffects()->clear();
        $this->player->setGamemode(GameMode::ADVENTURE());
        $this->player->setHealth($this->player->getMaxHealth());
        $this->player->setNameTag($this->player->getDisplayName());
        $this->clearAllInventories();
        $this->setTrackingSession(null);
        $this->setTeam(null);
        $this->setGame(null);

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
        // that player's disconnect). Kept in sync with the same fix in
        // BedWarsCore-game's Session.php.
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
    }
}