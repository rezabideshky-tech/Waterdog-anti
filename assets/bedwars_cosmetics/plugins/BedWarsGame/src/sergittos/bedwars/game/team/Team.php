<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\team;

use JsonSerializable;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\ArmorInventory;
use pocketmine\math\Vector3;
use pocketmine\player\GameMode;
use pocketmine\player\HungerManager;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\generator\Generator;
use sergittos\bedwars\game\generator\GeneratorType;
use sergittos\bedwars\game\team\upgrade\trap\AlarmTrap;
use sergittos\bedwars\game\team\upgrade\trap\Trap;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\utils\ColorUtils;
use function array_search;
use function array_values;
use function count;
use function file_exists;
use function in_array;
use function max;
use function spl_object_id;
use function strtolower;
use function strtoupper;
use function yaml_parse_file;

final class Team implements JsonSerializable{
    use TeamProperties;

    private int $capacity;
    private Upgrades $upgrades;

    private bool $isWinner = false;
    private bool $hasDistributedRewards = false;
    private bool $bed_destroyed = false;

    private int $wins = 0;
    private int $kills = 0;
    private int $bedsBroken = 0;
    private int $finalKills = 0;

    /** @var Generator[] */
    private array $generators;

    /** @var Session[] */
    private array $members = [];

    /**
     * Every session that was on this team at any point during the current
     * match, keyed by spl_object_id(). Unlike $members this is NOT
     * shrunk when a player is eliminated (PlayingStage::onQuit() ->
     * removeMember() runs for every final death), so at the end of the
     * match the winning team's teammates who died earlier and are just
     * spectating can still be found - that is what lets a Triples/Doubles/
     * Squads win put every teammate on the Podium instead of only the ones
     * that happened to still be alive. Cleared by reset().
     *
     * @var array<int, Session>
     */
    private array $roster = [];

    private array $rankPrefixes = [
        'Owner' => '§e[§dOwner§e] ',
        'Founder' => '§e[§aF§2ounder§e] ',
        'Admin' => '§e[§bA§3dmin§7] ',
        'Fighter' => '§e[§6Fighter§e] ',
        'Dead' => '§e[§0Dead§e] ',
        'Ruthless' => '§e[§3Ruthless§e] ',
        'Element' => '§e[§6Element§e] ',
        'VIP' => '§6[VIP] ',
        'Player' => ' ',
        'Default' => ' '
    ];

    /**
     * @param Generator[] $generators
     */
    public function __construct(
        string $name,
        int $capacity,
        Vector3 $spawn_point,
        Vector3 $bed_position,
        Area $zone,
        Area $claim,
        array $generators
    ){
        $this->name = $name;

        $token = match(strtolower($name)){
            "pink" => "{LIGHT_PURPLE}",
            "aqua" => "{AQUA}",
            "white" => "{WHITE}",
            default => "{" . strtoupper($name) . "}"
        };

        $this->color = ColorUtils::translate($token);

        $this->capacity = $capacity;
        $this->spawn_point = $spawn_point;
        $this->bed_position = $bed_position;
        $this->zone = $zone;
        $this->claim = $claim;
        $this->generators = $generators;
        $this->upgrades = new Upgrades();
    }

    public function getColoredName() : string{
        return $this->color . $this->name;
    }

    public function getFirstLetter() : string{
        return $this->name[0];
    }

    /**
     * Resource-pack glyph (private-use unicode) for each team colour.
     * Used for the scoreboard team rows and the in-game nametag.
     * "gray" and "black" both map to the black-team icon, and the
     * cyan/magenta/orange aliases follow the same renaming MapFactory
     * already does (cyan->Aqua, magenta->Pink, orange->White).
     */
    private const TEAM_ICONS = [
        "red"     => "\u{F274}",
        "blue"    => "\u{F271}",
        "yellow"  => "\u{F27E}",
        "green"   => "\u{F272}",
        "aqua"    => "\u{F273}",
        "cyan"    => "\u{F273}",
        "white"   => "\u{F27F}",
        "orange"  => "\u{F27F}",
        "pink"    => "\u{F27D}",
        "magenta" => "\u{F27D}",
        "black"   => "\u{F278}",
        "gray"    => "\u{F278}",
        "grey"    => "\u{F278}",
    ];

    /**
     * Team icon glyph, prefixed with white (§f) so the client draws the
     * glyph untinted. Always follow it with an explicit colour code.
     * Falls back to the old first-letter behaviour for unknown names so
     * a custom team never shows an empty slot.
     */
    public function getIcon() : string{
        $glyph = self::TEAM_ICONS[strtolower($this->name)] ?? null;
        if($glyph === null){
            return $this->color . $this->getFirstLetter();
        }
        return "§f" . $glyph;
    }

    public function getUpgrades() : Upgrades{
        return $this->upgrades;
    }

    public function isBedDestroyed() : bool{
        return $this->bed_destroyed;
    }

    public function addWin(int $count = 1) : void{
        $this->wins = max(0, $this->wins + $count);

        foreach($this->members as $member){
            $member->addWin();
            $member->save();
        }
    }

    public function isWinner() : bool{
        return $this->isWinner;
    }

    public function setWinner(bool $value) : void{
        $this->isWinner = $value;
    }

    public function hasDistributedRewards() : bool{
        return $this->hasDistributedRewards;
    }

    public function setDistributedRewards(bool $value) : void{
        $this->hasDistributedRewards = $value;
    }

    public function getWins() : int{
        return $this->wins;
    }

    public function getKills() : int{
        return $this->kills;
    }

    public function getBedsBroken() : int{
        return $this->bedsBroken;
    }

    public function getFinalKills() : int{
        return $this->finalKills;
    }

    public function addKills(int $count = 1) : void{
        $this->kills = max(0, $this->kills + $count);
        $this->addXp(5, "");
    }

    public function addBedsBroken(int $count = 1) : void{
        $this->bedsBroken = max(0, $this->bedsBroken + $count);
        $this->addXp(15, "");
    }

    public function addFinalKills(int $count = 1) : void{
        $this->finalKills = max(0, $this->finalKills + $count);
        $this->addXp(10, "");
    }

    private function addXp(int $xpAmount, string $reason) : void{
        foreach($this->members as $member){
            $member->addXp($xpAmount, $reason);
        }
    }

    public function destroyBed(Game $game, ?Session $destroyer = null, bool $break_block = true, bool $silent = false) : void{
    if($this->bed_destroyed){
        return;
    }

    $this->bed_destroyed = true;

    if($break_block){
        $this->breakBedBlock($game);
    }

    if($destroyer !== null){
        $destroyer->addBedsBroken();
        $destroyer->addXp(15, "Bed Destroyed");
    }

    if($silent){
        return;
    }

    if($destroyer !== null){
        $destroyer->playSound("mob.enderdragon.growl");
    }

    foreach($this->members as $member){
        $member->title("{RED}BED DESTROYED!", "{WHITE}You will no longer respawn!", 7, 30, 15);
        $member->playSound("mob.wither.death", 1.0, 1.0);
    }

    foreach($game->getPlayersAndSpectators() as $s){
        if($s->getTeam() !== null && $s->getTeam()->getName() === $this->getName()){
            continue;
        }
        $s->playSound("mob.wither.death", 0.35, 1.0);
    }
}

    public function jsonSerialize() : array{
        return [
            "name" => $this->name,
            "spawn_point" => [
                "x" => $this->spawn_point->getX(),
                "y" => $this->spawn_point->getY(),
                "z" => $this->spawn_point->getZ()
            ],
            "bed" => [
                "x" => $this->bed_position->getX(),
                "y" => $this->bed_position->getY(),
                "z" => $this->bed_position->getZ()
            ],
            "generator" => isset($this->generators[0]) ? [
                "x" => $this->generators[0]->getPosition()->getX(),
                "y" => $this->generators[0]->getPosition()->getY(),
                "z" => $this->generators[0]->getPosition()->getZ()
            ] : null,
            "areas" => [
                "zone" => $this->zone->jsonSerialize(),
                "claim" => $this->claim->jsonSerialize()
            ]
        ];
    }

    /** @return Generator[] */
    public function getGenerators() : array{
        return $this->generators;
    }

    /** @return Session[] */
    public function getMembers() : array{
        return $this->members;
    }

    /**
     * @return Session[] everyone who has been on this team during the
     *                   current match (including eliminated players)
     */
    public function getRoster() : array{
        return array_values($this->roster);
    }

    public function getMembersCount() : int{
        return count($this->members);
    }

    public function isFull() : bool{
        return $this->getMembersCount() >= $this->capacity;
    }

    public function isEmpty() : bool{
        return empty($this->members);
    }

    public function isAlive() : bool{
        return !$this->isEmpty();
    }

    public function hasMember(Session $session) : bool{
        return in_array($session, $this->members, true);
    }

    private function breakBedBlock(Game $game) : void{
        $world = $game->getWorld();
        if($world === null){
            return;
        }

        $position = Position::fromObject($this->bed_position, $world);
        $cx = $position->getFloorX() >> Chunk::COORD_BIT_SIZE;
        $cz = $position->getFloorZ() >> Chunk::COORD_BIT_SIZE;

        $world->requestChunkPopulation($cx, $cz, null)->onCompletion(
            function() use ($world, $position): void{
                $block = $world->getBlock($position);
                foreach($block->getAffectedBlocks() as $b){
                    $world->setBlock($b->getPosition(), VanillaBlocks::AIR());
                }
            },
            function(): void{}
        );
    }

    public function tickGenerators(Game $game) : void{
        // Teams that were eliminated (bed broken + no members left) or that
        // never had any players assigned to them in this match at all
        // (isAlive() === false covers both, since it's just "has members")
        // shouldn't keep dropping iron/gold at their base - nobody is there
        // to collect it and it just clutters the island. Diamond/Emerald/
        // TeamEmerald generators are left untouched (a team can still
        // legitimately own a Team Emerald upgrade purchased before wipe).
        $stillGenerating = $this->isAlive();

        foreach($this->generators as $generator){
            $type = $generator->getType();
            if(!$stillGenerating && ($type === GeneratorType::IRON || $type === GeneratorType::GOLD)){
                continue;
            }
            $generator->tick($game);
        }
    }

    public function addGenerator(Generator $generator) : void{
        $this->generators[] = $generator;
    }

    private function removeEmeraldGenerator() : void{
        foreach($this->generators as $index => $generator){
            if($generator->getType() === GeneratorType::TEAM_EMERALD){
                unset($this->generators[$index]);
                break;
            }
        }
    }

    private static function ensurePlayerInternals(Player $player): void{
        try{
            $rp = new \ReflectionProperty(Player::class, "hungerManager");
            if(!$rp->isInitialized($player)){
                $rp->setAccessible(true);
                $rp->setValue($player, new HungerManager($player));
            }
        }catch(\Throwable){
        }

        try{
            $rp = new \ReflectionProperty(\pocketmine\entity\Living::class, "armorInventory");
            if(!$rp->isInitialized($player)){
                $rp->setAccessible(true);
                $rp->setValue($player, new ArmorInventory($player));
            }
        }catch(\Throwable){
        }
    }

    /**
     * Line inserted above a Bounty target's nametag by getFormattedNametag()
     * below, for as long as sergittos\bedwars\game\bounty\BountyManager
     * keeps that session flagged (i.e. for the rest of their current life,
     * or the rest of the match if they never die again - see
     * BountyManager::onDeath()/reset() for every way it comes back off).
     */
    private const BOUNTY_NAMETAG_LINE = "§4§l[ BOUNTY ]";

    /**
     * Single source of truth for what an in-team nametag looks like -
     * team icon glyph + player name in the team colour. Deliberately does NOT read anything
     * from RankSystemBridge/getRankPrefixDisplay(): same reasoning as
     * the chat listener - once a player is on a team, the team tag is
     * what needs to be instantly readable (who's on my team / who's an
     * enemy), not their rank.
     *
     * When $session is a live Bounty target, BOUNTY_NAMETAG_LINE is
     * prepended as its own line above the usual tag - Bedrock nametags
     * render an embedded "\n" as a separate line above the base text, so
     * this shows as one extra line sitting above the player's normal
     * team-coloured name for as long as the Bounty is active.
     */
    public function getFormattedNametag(Session $session) : string{
        $tag = $this->getIcon() . " " . $this->color . $session->getPlayer()->getName();

        if($session->isBounty()){
            return self::BOUNTY_NAMETAG_LINE . "\n" . $tag;
        }

        return $tag;
    }

    /**
     * Re-applies the team nametag to a member that's already on this
     * team. addMember() only sets it once, at the moment a player
     * joins the team - fine on its own, but if RankSystem (or any
     * other plugin) is installed with its own nametag handling left
     * enabled and touches this player's nametag afterwards (e.g. on a
     * skin change, a periodic refresh task, a rank update...), that
     * later call silently overwrites what addMember() set, same class
     * of problem the chat listener had with RankSystem's chat handler.
     * PlayerChatEvent has priorities that let GameChatListener always
     * have the final word; Player::setNameTag() has no equivalent
     * event to hook into, so the fix here is the same idea applied the
     * only way it can be for a plain property: called once a second
     * from PlayingStage::tickPlayersAndSpectators() (same cadence
     * already used for updateScoreboards()) so BedWars' format is
     * always restored within a second of anything else changing it,
     * without spamming a setNameTag() call every single tick.
     */
    public function refreshNametag(Session $session) : void{
        $player = $session->getPlayer();
        if(!$player->isConnected()){
            return;
        }

        $tag = $this->getFormattedNametag($session);
        if($player->getNameTag() !== $tag){
            $player->setNameTag($tag);
        }
    }

    public function addMember(Session $session) : void{
        if($this->hasMember($session)){
            return;
        }

        $player = $session->getPlayer();
        if(!$player->isConnected()){
            return;
        }

        self::ensurePlayerInternals($player);

        $this->members[] = $session;
        $this->roster[spl_object_id($session)] = $session;

        $session->setTeam($this);
        $session->clearAllInventories();
        $session->getGameSettings()->apply();

        $player->setNameTag($this->getFormattedNametag($session));
        $player->setGamemode(GameMode::SURVIVAL());

        $game = $session->getGame();
        if($game?->getWorld() !== null){
            // Delegates to Game::teleportToTeamSpawn() instead of
            // teleporting straight here: that's what makes sure the
            // chunk around this spawn point is actually loaded first
            // (see prepareTeamSpawnChunks()'s doc comment for why a bare
            // Player::teleport() here was the cause of players randomly
            // falling through their island and taking fall/void damage
            // right at match start).
            $game->teleportToTeamSpawn($session, $this->spawn_point);
        }
    }

    private function getRankPrefix(string $username) : string{
        $server = Server::getInstance();
        $purePermsPath = $server->getDataPath() . "plugin_data/PurePerms/players/";
        $playerFile = $purePermsPath . $username . ".yml";

        if(!file_exists($playerFile)){
            return $this->rankPrefixes['Default'];
        }

        $data = yaml_parse_file($playerFile);
        return $this->rankPrefixes[$data['group'] ?? 'Default'] ?? $this->rankPrefixes['Default'];
    }

    public function removeMember(Session $session, bool $destroyBedIfEmpty = true) : void{
        $idx = array_search($session, $this->members, true);
        if($idx !== false){
            unset($this->members[$idx]);
        }

        if($destroyBedIfEmpty && $this->isEmpty()){
            $game = $session->getGame();
            if($game !== null){
                $this->destroyBed($game);
            }
        }

        $session->setTeam(null);
    }

    public function notifyTrap(Trap $trap, Team $team): void{
        $name = $trap->getName();
        if($trap instanceof AlarmTrap){
            $title = "{BOLD}{RED}ALARM!!!";
            $subtitle = "{WHITE}" . $name . " set off by " . $team->getColoredName() . "{WHITE} team!";
            $message = "{BOLD}{RED}" . $name . " set off by " . $team->getColoredName() . "{RED} team!";
        }else{
            $title = "{RED}TRAP TRIGGERED!";
            $subtitle = "{WHITE}Your $name has been set off!";
            $message = "{BOLD}{RED}" . $name . " was set off!";
        }

        foreach($this->members as $member){
            $member->title($title, $subtitle);
            $member->message($message);
        }
    }

    public function reset() : void{
        $this->bed_destroyed = false;
        $this->upgrades = new Upgrades();

        $this->removeEmeraldGenerator();

        foreach($this->generators as $generator){
            $generator->reset();
        }

        foreach($this->members as $member){
            $member->setTeam(null);
        }

        $this->members = [];
        $this->roster = [];
    }

    public function __clone() : void{
        $this->upgrades = clone $this->upgrades;
        $this->generators = Utils::cloneObjectArray($this->generators);
    }
}