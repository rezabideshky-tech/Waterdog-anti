<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\map\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Filesystem;
use function is_dir;

final class RemoveWorldFolderTask extends AsyncTask{

    public function __construct(private string $path){}

    public function onRun(): void{
        if(is_dir($this->path)){
            Filesystem::recursiveUnlink($this->path);
        }
    }
}