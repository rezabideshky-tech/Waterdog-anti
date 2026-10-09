<?php

declare(strict_types=1);

namespace sergittos\bedwars\network;

use jasonw4331\libpmquery\PMQuery;
use pocketmine\scheduler\AsyncTask;

/**
 * PMQuery::query() opens a blocking UDP socket (fsockopen + fread) and waits
 * up to the configured timeout for a reply. NetworkManager used to call this
 * directly from a repeating main-thread task, which stalled the *entire*
 * server for the full timeout every time a configured server was slow or
 * unreachable - this was, by far, the single biggest source of TPS drops
 * in the timings (single calls taking up to several seconds on the main
 * thread). Running the exact same query inside an AsyncTask keeps the
 * result identical but off the main thread, so a slow/offline server can
 * no longer freeze the whole game.
 */
final class ServerQueryTask extends AsyncTask{

    public function __construct(
        private string $name,
        private string $queryIp,
        private int $port,
        private int $timeout,
        \Closure $onResult
    ){
        $this->storeLocal("callback", $onResult);
    }

    public function onRun(): void{
        try{
            $info = PMQuery::query($this->queryIp, $this->port, $this->timeout);
            $this->setResult([
                "success" => true,
                "online" => (int) ($info["Players"] ?? 0),
                "max" => (int) ($info["MaxPlayers"] ?? 0),
            ]);
        }catch(\Throwable){
            $this->setResult(["success" => false]);
        }
    }

    public function onCompletion(): void{
        /** @var \Closure $callback */
        $callback = $this->fetchLocal("callback");
        $result = $this->getResult();
        if(is_array($result)){
            $callback($this->name, $result);
        }
    }
}
