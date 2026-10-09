<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\challenges\registry;

use pocketmine\utils\Config;
use sergittos\bedwars\lobby\challenges\model\ChallengeDefinition;
use function array_filter;
use function array_values;
use function is_array;
use function strtolower;

final class ChallengeRegistry{

    private Config $cfg;

    /** @var array<string, ChallengeDefinition> */
    private array $items = [];

    public function __construct(private string $path){
        $this->cfg = new Config($path, Config::YAML);
    }

    public function reload(): void{
        $this->items = [];
        $all = $this->cfg->getAll();

        foreach($all as $id => $data){
            if(!is_array($data)){
                continue;
            }
            $this->items[(string)$id] = ChallengeDefinition::fromConfig((string)$id, $data);
        }
    }

    /** @return ChallengeDefinition[] */
    public function getAll(): array{
        return array_values($this->items);
    }

    /** @return ChallengeDefinition[] */
    public function getVisibleChallenges(): array{
        return array_values(array_filter($this->items, fn(ChallengeDefinition $c) => $c->isVisible()));
    }

    public function get(string $id): ?ChallengeDefinition{
        return $this->items[$id] ?? null;
    }

    public function upsert(array $row): bool{
        $id = (string)($row["id"] ?? "");
        if($id === ""){
            return false;
        }
        unset($row["id"]);
        $this->cfg->set($id, $row);
        $this->cfg->save();
        $this->reload();
        return true;
    }

    public function delete(string $id): bool{
        if($this->cfg->get($id, null) === null){
            return false;
        }
        $this->cfg->remove($id);
        $this->cfg->save();
        $this->reload();
        return true;
    }

    public function setTime(string $id, int $start, int $end): bool{
        $row = $this->cfg->get($id, null);
        if(!is_array($row)){
            return false;
        }
        $row["start"] = $start;
        $row["end"] = $end;
        $this->cfg->set($id, $row);
        $this->cfg->save();
        $this->reload();
        return true;
    }

    public function addReward(string $id, array $reward): bool{
        $row = $this->cfg->get($id, null);
        if(!is_array($row)){
            return false;
        }
        $rewards = is_array($row["rewards"] ?? null) ? $row["rewards"] : [];
        $rewards[] = $reward;
        $row["rewards"] = $rewards;
        $this->cfg->set($id, $row);
        $this->cfg->save();
        $this->reload();
        return true;
    }
}