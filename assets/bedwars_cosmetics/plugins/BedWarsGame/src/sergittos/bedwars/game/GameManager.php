<?php

declare(strict_types=1);

namespace sergittos\bedwars\game;

use pocketmine\Server;
use pocketmine\world\World;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\game\map\Map;
use sergittos\bedwars\game\map\MapFactory;
use sergittos\bedwars\game\shop\ShopFactory;
use sergittos\bedwars\game\stage\StartingStage;
use sergittos\bedwars\game\stage\WaitingStage;
use sergittos\bedwars\game\task\GenerateGameTask;
use function array_rand;
use function array_sum;
use function count;
use function max;
use function min;
use function strtolower;

class GameManager{

    /**
     * How many world-instances of a single map generateGames() keeps
     * topped up to. Kept at 1 - the minimum needed for a map to be
     * joinable at all - on purpose: this is not a pre-built "buffer"
     * that sits around unused, it's created only the moment a real
     * player actually needs a game and none is free (see
     * Matchmaker::queue() -> findRandomGame() -> generateGames()).
     * Nothing pre-generates copies speculatively anymore; see the
     * constructor below and BedWarsGame::onEnable()/onDisable() for
     * the rest of what stops worlds/ from accumulating copies.
     */
    private const INSTANCES_PER_MAP = 2;

    private int $next_game_id = 0;

    /** @var Game[] */
    private array $games = [];

    /**
     * Map id => number of GenerateGameTask instances currently in
     * flight (submitted but not yet completed/failed) for that map.
     * Without this, two calls to generateGames() for the same map
     * milliseconds apart (e.g. two players clicking "play" before the
     * first async world-copy finishes) would each see zero existing
     * games and each queue up another full batch, on top of each
     * other.
     *
     * @var array<string,int>
     */
    private array $pending_generations = [];

    /** @var \WeakMap<World, Game> */
    private \WeakMap $worldToGame;

    public function __construct(){
        $this->worldToGame = new \WeakMap();

        ShopFactory::init();

        // Previously every map got generateGames() called on it here,
        // unconditionally, on every single plugin start-up/restart -
        // meaning a fresh batch of world copies was created the moment
        // the server booted, whether or not a single player was online
        // to use them. That's the main source of worlds/ filling up:
        // every restart added another round of copies on top of
        // whatever was already there. World generation is now fully
        // demand-driven: the first player who actually queues for a
        // map triggers generateGames() for it via findGame()/
        // findRandomGame() below, and not a moment before.
    }

    public function bindWorld(World $world, Game $game): void{
        $this->worldToGame[$world] = $game;
    }

    public function unbindWorld(World $world): void{
        unset($this->worldToGame[$world]);
    }

    public function getNextGameId(): int{
        return $this->next_game_id++;
    }

    /**
     * Exposes the pool-size target so other places (e.g. Game::reset()
     * deciding whether a map already has enough spare instances) stay
     * in sync with this value instead of hardcoding their own copy of
     * it, which is how they used to drift apart.
     */
    public function getInstancesPerMap(): int{
        return self::INSTANCES_PER_MAP;
    }

    /** @return Game[] */
    public function getGames(): array{
        return $this->games;
    }

    public function getGameById(int $id): ?Game{
        return $this->games[$id] ?? null;
    }

    public function getGamesCount(Map $map): int{
        return count($this->getGamesByMap($map));
    }

    /** @return Game[] */
    public function getGamesByMap(Map $map): array{
        $games = [];
        foreach($this->games as $game){
            if($game->getMap()->getId() === $map->getId()){
                $games[] = $game;
            }
        }
        return $games;
    }

    public function getGameByWorld(World $world): ?Game{
        return $this->worldToGame[$world] ?? null;
    }

    public function findGameByMemberUsername(string $username): ?Game{
        $u = strtolower($username);

        foreach($this->games as $game){
            foreach($game->getPlayersAndSpectators() as $session){
                if(strtolower($session->getUsername()) === $u){
                    return $game;
                }
            }
        }
        return null;
    }

    public function findRandomGame(int $players_per_team): ?Game{
        $maps = MapFactory::getMapsByPlayers($players_per_team);
        if($maps === []){
            return null;
        }

        $suitableGames = [];
        $mostPlayers = -1;
        $selectedGame = null;

        foreach($maps as $map){
            foreach($this->getGamesByMap($map) as $game){
                $stage = $game->getStage();
                if($stage instanceof WaitingStage || ($stage instanceof StartingStage && !$game->isFull())){
                    $playerCount = $game->getPlayersCount();

                    if($playerCount > $mostPlayers){
                        $mostPlayers = $playerCount;
                        $selectedGame = $game;
                    }

                    $suitableGames[] = $game;
                }
            }
        }

        if($selectedGame !== null && $mostPlayers > 0){
            return $selectedGame;
        }

        if($suitableGames !== []){
            return $suitableGames[array_rand($suitableGames)];
        }

        if($this->getActiveGamesCount() >= $this->getMaxConcurrentGames()){
            return null;
        }

        $this->generateGames($maps[array_rand($maps)]);
        return null;
    }

    public function getActiveGamesCount(): int{
        $count = 0;
        foreach($this->games as $game){
            $stage = $game->getStage();
            if(
                $stage instanceof WaitingStage ||
                $stage instanceof StartingStage ||
                $stage instanceof \sergittos\bedwars\game\stage\PlayingStage
            ){
                $count++;
            }
        }
        return $count;
    }

    public function getMaxConcurrentGames(): int{
        return (int) BedWars::getInstance()->getConfig()->get("max-concurrent-games", 3);
    }

    public function findGame(Map $map): ?Game{
        $games = [];
        foreach($this->getGamesByMap($map) as $game){
            $stage = $game->getStage();
            if($stage instanceof WaitingStage || ($stage instanceof StartingStage && !$game->isFull())){
                $games[] = $game;
            }
        }

        if($games === []){
            $this->generateGames($map);
            return null;
        }

        $found = null;
        $index = PHP_INT_MIN;
        foreach($games as $game){
            $count = $game->getPlayersCount();
            if($count > $index){
                $index = $count;
                $found = $game;
            }
        }
        return $found;
    }

    public function findGameByType(string $gameType, int $playersPerTeam): ?Game{
        foreach($this->games as $game){
            if($game->getType() === $gameType && $game->hasSpace($playersPerTeam)){
                return $game;
            }
        }
        return null;
    }

    /**
     * Tops the map's pool of world-instances up to INSTANCES_PER_MAP,
     * instead of unconditionally creating INSTANCES_PER_MAP more every
     * time it's called. Existing instances (any stage - a match that's
     * still being played still counts, it's still occupying a world
     * folder) and instances already being generated for this map both
     * count towards the target, and the whole server's
     * max-concurrent-games cap is respected too, so a burst of players
     * queuing at once can no longer spawn unlimited extra copies.
     */
    public function generateGames(Map $map): void{
        $mapId = $map->getId();

        $existing = count($this->getGamesByMap($map));
        $pending = $this->pending_generations[$mapId] ?? 0;

        $needed = self::INSTANCES_PER_MAP - $existing - $pending;
        if($needed <= 0){
            return;
        }

        $globalPending = array_sum($this->pending_generations);
        $globalRoom = $this->getMaxConcurrentGames() - $this->getActiveGamesCount() - $globalPending;
        if($globalRoom <= 0){
            return;
        }

        $needed = min($needed, $globalRoom);
        if($needed <= 0){
            return;
        }

        $this->pending_generations[$mapId] = $pending + $needed;

        for($i = 0; $i < $needed; ++$i){
            Server::getInstance()->getAsyncPool()->submitTask(new GenerateGameTask(
                $this->getNextGameId(),
                $map
            ));
        }
    }

    /**
     * Marks one in-flight generation for this map as no longer pending,
     * whether it finished successfully (a game was added) or failed.
     * Failure must go through here too - GenerateGameTask used to just
     * silently return on failure with nothing ever clearing the pending
     * count for that map, which would have permanently wedged that map
     * a little below its pool target after every failed copy.
     */
    private function clearPending(string $mapId): void{
        if(!isset($this->pending_generations[$mapId])){
            return;
        }

        $this->pending_generations[$mapId] = max(0, $this->pending_generations[$mapId] - 1);
        if($this->pending_generations[$mapId] === 0){
            unset($this->pending_generations[$mapId]);
        }
    }

    public function generationFailed(string $mapId): void{
        $this->clearPending($mapId);
    }

    public function addGame(Game $game): void{
        $this->games[$game->getId()] = $game;
        $this->clearPending($game->getMap()->getId());
    }

    public function removeGame(int $id): void{
        unset($this->games[$id]);
    }
}