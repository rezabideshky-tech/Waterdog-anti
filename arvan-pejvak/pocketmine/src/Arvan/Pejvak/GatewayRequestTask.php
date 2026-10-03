<?php

declare(strict_types=1);

namespace Arvan\Pejvak;

use pocketmine\scheduler\AsyncTask;
use function curl_init;
use function curl_setopt_array;
use function curl_exec;
use function curl_close;
use function function_exists;
use function json_decode;
use function json_encode;
use function is_array;
use function is_string;
use function strlen;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;

final class GatewayRequestTask extends AsyncTask{
    private string $url;
    private string $method;
    private string $body;
    private string $secret;
    private string $kind;
    private string $playerName;

    public function __construct(
        PejvakPlugin $plugin,
        string $url,
        string $method,
        string $body,
        string $secret,
        string $kind,
        string $playerName
    ){
        $this->url = $url;
        $this->method = $method;
        $this->body = $body;
        $this->secret = $secret;
        $this->kind = $kind;
        $this->playerName = $playerName;
        $this->storeLocal("plugin", $plugin);
    }

    public function onRun() : void{
        if(!function_exists("curl_init")){
            $this->setResult(["ok" => false, "error" => "cURL is not available in PocketMine's worker runtime."]);
            return;
        }
        $handle = curl_init($this->url);
        if($handle === false){
            $this->setResult(["ok" => false, "error" => "Could not initialize the HTTPS request."]);
            return;
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $this->method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 7,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Accept: application/json",
                "Authorization: Bearer " . $this->secret,
            ],
        ];
        if($this->body !== ""){
            $options[CURLOPT_POSTFIELDS] = $this->body;
        }
        curl_setopt_array($handle, $options);
        $raw = curl_exec($handle);
        $status = (int) (\curl_getinfo($handle, \CURLINFO_RESPONSE_CODE) ?: 0);
        $error = $raw === false ? (string) \curl_error($handle) : null;
        curl_close($handle);

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $this->setResult([
            "ok" => $error === null && $status >= 200 && $status < 300,
            "status" => $status,
            "error" => $error,
            "payload" => is_array($decoded) ? $decoded : [],
        ]);
    }

    public function onCompletion() : void{
        $plugin = $this->fetchLocal("plugin");
        $result = $this->getResult();
        if($plugin instanceof PejvakPlugin && is_array($result)){
            $plugin->handleGatewayResponse($this->kind, $this->playerName, $result);
        }
    }
}
