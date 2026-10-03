<?php

declare(strict_types=1);

namespace Arvan\Pejvak;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use function hash;
use function max;
use function is_array;
use function json_encode;
use function mb_substr;
use function preg_match;
use function rtrim;
use function strtolower;
use function str_starts_with;
use function trim;
use function time;

final class PejvakPlugin extends PluginBase implements Listener{
    private string $gatewayUrl = "";
    private string $sharedSecret = "";
    private string $serverId = "";
    private int $presenceSyncTicks = 20;
    private int $lastWarningAt = 0;
    private bool $presenceRequestInFlight = false;

    protected function onEnable() : void{
        $this->saveDefaultConfig();
        $this->gatewayUrl = rtrim(trim((string) $this->getConfig()->get("gateway-url", "")), "/");
        $this->sharedSecret = trim((string) $this->getConfig()->get("shared-secret", ""));
        $this->serverId = trim((string) $this->getConfig()->get("server-id", ""));
        $this->presenceSyncTicks = max(20, (int) $this->getConfig()->get("presence-sync-ticks", 20));
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        if(!$this->isConfigured()){
            $this->getLogger()->warning("Voice gateway is not configured. Set gateway-url, shared-secret and server-id in config.yml.");
        }else{
            $this->getLogger()->info("Arvan Pejvak bridge enabled for server-id '" . $this->serverId . "'.");
        }

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
            $this->syncPresence();
        }), $this->presenceSyncTicks);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if(strtolower($command->getName()) !== "pejvak") return false;
        if(!$sender instanceof Player){
            $sender->sendMessage("Run this command in-game: /pejvak code");
            return true;
        }
        $subcommand = strtolower((string) ($args[0] ?? "code"));
        if($subcommand === "status"){
            if(!$sender->hasPermission("arvanpejvak.admin")){
                $sender->sendMessage("§cYou do not have permission to inspect the bridge.");
                return true;
            }
            $sender->sendMessage($this->isConfigured()
                ? "§aArvan Pejvak gateway configured for §f" . $this->serverId . "§a."
                : "§cArvan Pejvak gateway is not configured.");
            return true;
        }
        if($subcommand !== "code"){
            $sender->sendMessage("§eUsage: §f/pejvak code §7or §f/pejvak status");
            return true;
        }
        if(!$sender->hasPermission("arvanpejvak.use")){
            $sender->sendMessage("§cYou cannot create a voice link code.");
            return true;
        }
        if(!$this->isConfigured()){
            $sender->sendMessage("§cVoice chat is not configured on this server yet.");
            return true;
        }

        $identity = $this->getStableIdentity($sender);
        if($identity === ""){
            $sender->sendMessage("§cYour Bedrock account identity is not available.");
            return true;
        }
        $sender->sendMessage("§7در حال ساخت کد امن اتصال…");
        $this->submitRequest(
            "POST",
            "/v1/pocketmine/code",
            [
                "serverId" => $this->serverId,
                "xuid" => $identity,
                "name" => mb_substr($sender->getName(), 0, 36, "UTF-8"),
            ],
            "issue_code",
            $sender->getName(),
        );
        return true;
    }

    public function onPlayerJoin(PlayerJoinEvent $event) : void{
        // Wait a few ticks so world and login identity are fully initialized.
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() : void{
            $this->syncPresence();
        }), 10);
    }

    public function onPlayerQuit(PlayerQuitEvent $event) : void{
        // The next complete snapshot removes the player from the proximity graph.
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() : void{
            $this->syncPresence();
        }), 10);
    }

    public function handleGatewayResponse(string $kind, string $playerName, array $result) : void{
        if(!$this->isEnabled()) return;
        if($kind === "presence"){
            $this->presenceRequestInFlight = false;
            if(!($result["ok"] ?? false)) $this->warnGatewayOnce((string) ($result["error"] ?? "presence request failed"));
            return;
        }
        if($kind !== "issue_code") return;

        $player = $this->getServer()->getPlayerExact($playerName);
        if($player === null || !$player->isConnected()) return;
        if(!($result["ok"] ?? false)){
            $player->sendMessage("§cساخت کد پژواک ناموفق بود. چند لحظه بعد دوباره امتحان کن.");
            $this->warnGatewayOnce((string) ($result["error"] ?? "code request failed"));
            return;
        }
        $payload = is_array($result["payload"] ?? null) ? $result["payload"] : [];
        $code = trim((string) ($payload["code"] ?? ""));
        if(preg_match('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/', $code) !== 1){
            $player->sendMessage("§cپاسخ درگاه پژواک معتبر نبود. بعداً دوباره تلاش کن.");
            return;
        }
        $player->sendMessage("§d§lآروان پژواک §r§7کد یک‌بارمصرف اتصال تو:");
        $player->sendMessage("§f§l" . $code . "§r §8(حداکثر ۲ دقیقه)");
        $player->sendMessage("§7این کد را فقط در اپ رسمی آروان پژواک وارد کن.");
    }

    private function syncPresence() : void{
        if(!$this->isConfigured() || $this->presenceRequestInFlight) return;
        $players = [];
        foreach($this->getServer()->getOnlinePlayers() as $player){
            $location = $player->getLocation();
            $players[] = [
                "xuid" => $this->getStableIdentity($player),
                "name" => mb_substr($player->getName(), 0, 36, "UTF-8"),
                // Hash the folder name so private world names aren't exposed to mobile clients.
                "world" => hash("sha256", $player->getWorld()->getFolderName()),
                "x" => $location->getX(),
                "y" => $location->getY(),
                "z" => $location->getZ(),
            ];
        }
        $this->presenceRequestInFlight = true;
        $this->submitRequest("POST", "/v1/pocketmine/presence", [
            "serverId" => $this->serverId,
            "players" => $players,
            "sentAt" => time(),
        ], "presence", "");
    }

    private function submitRequest(string $method, string $path, array $payload, string $kind, string $playerName) : void{
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if(!is_string($body)){
            if($kind === "presence") $this->presenceRequestInFlight = false;
            $this->warnGatewayOnce("could not encode gateway payload");
            return;
        }
        try{
            $this->getServer()->getAsyncPool()->submitTask(new GatewayRequestTask(
                $this,
                $this->gatewayUrl . $path,
                $method,
                $body,
                $this->sharedSecret,
                $kind,
                $playerName,
            ));
        }catch(\Throwable $error){
            if($kind === "presence") $this->presenceRequestInFlight = false;
            $this->warnGatewayOnce($error->getMessage());
        }
    }

    private function getStableIdentity(Player $player) : string{
        $xuid = trim($player->getXuid());
        return $xuid !== "" ? $xuid : $player->getUniqueId()->toString();
    }

    private function isConfigured() : bool{
        return $this->gatewayUrl !== "" && str_starts_with(strtolower($this->gatewayUrl), "https://") &&
            $this->sharedSecret !== "" && $this->sharedSecret !== "CHANGE-ME-TO-A-LONG-RANDOM-SECRET" &&
            preg_match('/^[a-zA-Z0-9._-]{1,64}$/', $this->serverId) === 1;
    }

    private function warnGatewayOnce(string $message) : void{
        if(time() - $this->lastWarningAt < 30) return;
        $this->lastWarningAt = time();
        $this->getLogger()->warning("Voice gateway request failed: " . $message);
    }
}
