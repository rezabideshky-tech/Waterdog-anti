<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\quests\task;

use pocketmine\scheduler\Task;
use sergittos\bedwars\lobby\quests\data\PlayerQuestDataManager;

final class QuestSyncTask extends Task{

    public function __construct(private PlayerQuestDataManager $data){}

    public function onRun(): void{
        $this->data->tick();
    }
}