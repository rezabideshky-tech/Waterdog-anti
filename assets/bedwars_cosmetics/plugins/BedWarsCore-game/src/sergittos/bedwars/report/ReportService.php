<?php

declare(strict_types=1);

namespace sergittos\bedwars\report;

use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use sergittos\bedwars\network\NetworkManager;
use sergittos\bedwars\provider\mysql\AsyncMysqlProvider;
use sergittos\bedwars\utils\ColorUtils;
use function max;
use function strtolower;
use function time;
use function uniqid;

/**
 * Central place all "report a player" submissions flow through, on both the
 * Game server and the Lobby server. Owns the two anti-abuse guards (a global
 * per-reporter cooldown, and a duplicate-pair window) entirely in memory -
 * cheap, and correct for what they're actually protecting against (a single
 * player mashing the report button), so there's no need to round-trip the
 * database just to decide whether a submission should be allowed.
 *
 * The report itself is still always persisted through AsyncMysqlProvider,
 * so nothing here risks losing a legitimate report - only spam is stopped.
 */
final class ReportService{

    private AsyncMysqlProvider $provider;
    private NetworkManager $networkManager;

    private int $cooldownSeconds;
    private int $duplicateWindowSeconds;

    /** @var array<string, int> lowercased reporter username -> unix timestamp of their last submitted report */
    private array $lastReportAt = [];

    /** @var array<string, int> "reporter|reported" (lowercased) -> unix timestamp of the last report filed for that exact pair */
    private array $lastPairReportAt = [];

    public function __construct(Plugin $plugin, AsyncMysqlProvider $provider, NetworkManager $networkManager){
        $this->provider = $provider;
        $this->networkManager = $networkManager;

        $cfg = (array) $plugin->getConfig()->get("report", []);
        $this->cooldownSeconds = max(0, (int) ($cfg["cooldown-seconds"] ?? 30));
        $this->duplicateWindowSeconds = max(0, (int) ($cfg["duplicate-window-seconds"] ?? 600));
    }

    /** Seconds the reporter must still wait before they're allowed to file another report. 0 means they can report right now. */
    public function getCooldownRemaining(string $reporterUsername): int{
        $last = $this->lastReportAt[strtolower($reporterUsername)] ?? null;
        if($last === null){
            return 0;
        }

        return max(0, $this->cooldownSeconds - (time() - $last));
    }

    /** True if this exact reporter already reported this exact target recently enough that a new report would just be noise. */
    public function hasRecentDuplicate(string $reporterUsername, string $reportedUsername): bool{
        $key = strtolower($reporterUsername) . "|" . strtolower($reportedUsername);
        $last = $this->lastPairReportAt[$key] ?? null;
        if($last === null){
            return false;
        }

        return (time() - $last) < $this->duplicateWindowSeconds;
    }

    /**
     * Validates and files a report. Returns null on success, or a
     * user-facing (already {COLOR}-tagged) error message if the report was
     * rejected before ever reaching the database.
     */
    public function submitReport(Player $reporter, string $reportedUsername, string $reasonId, string $description): ?string{
        $reporterUsername = $reporter->getName();

        if(strtolower($reporterUsername) === strtolower($reportedUsername)){
            return "{RED}You can't report yourself!";
        }

        $remaining = $this->getCooldownRemaining($reporterUsername);
        if($remaining > 0){
            return "{RED}Please wait {GOLD}" . $remaining . "s{RED} before submitting another report.";
        }

        if(ReportReasons::get($reasonId) === null){
            return "{RED}That report reason is no longer valid, please try again.";
        }

        if($this->hasRecentDuplicate($reporterUsername, $reportedUsername)){
            return "{RED}You've already reported {GOLD}" . $reportedUsername . "{RED} recently - our staff have been notified.";
        }

        $now = time();
        $this->lastReportAt[strtolower($reporterUsername)] = $now;
        $this->lastPairReportAt[strtolower($reporterUsername) . "|" . strtolower($reportedUsername)] = $now;

        $this->provider->insertReport([
            "id"          => uniqid("rpt_", true),
            "reporter"    => $reporterUsername,
            "reported"    => $reportedUsername,
            "reason_id"   => $reasonId,
            "description" => $description,
            "server_name" => $this->networkManager->getServerName(),
            "game_id"     => 0,
        ]);

        $this->broadcastToStaff($reporter, $reportedUsername, $reasonId, $description);

        return null;
    }

    private function broadcastToStaff(Player $reporter, string $reportedUsername, string $reasonId, string $description): void{
        $reason = ReportReasons::get($reasonId);
        $reasonLabel = $reason !== null ? $reason->getDisplayLabel() : ColorUtils::translate("{GRAY}Other");

        $message = ColorUtils::translate(
            "{DARK_GRAY}[{RED}Report{DARK_GRAY}] {WHITE}" . $reporter->getName() .
            " {GRAY}reported {WHITE}" . $reportedUsername .
            " {GRAY}for " . $reasonLabel
        );

        if($description !== ""){
            $message .= ColorUtils::translate("\n{DARK_GRAY} > {GRAY}\"" . $description . "\"");
        }

        foreach($reporter->getServer()->getOnlinePlayers() as $staff){
            if($staff->hasPermission("bedwars.report.staff")){
                $staff->sendMessage($message);
            }
        }
    }

}
