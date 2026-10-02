<?php

declare(strict_types=1);

namespace AntiAdvertising\manager;

use AntiAdvertising\detector\DetectionResult;
use AntiAdvertising\Main;
use DateTime;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use function array_keys;
use function count;
use function date;
use function file_put_contents;
use function intval;
use function is_array;
use function max;
use function str_replace;
use function strtolower;
use function time;
use const FILE_APPEND;
use const PHP_EOL;

final class PunishmentManager {

	public function __construct(
		private readonly Main $plugin,
		private readonly WarningManager $warningManager
	) {
	}

	/**
	 * Handle a detected advertising violation by a player.
	 */
	public function handleViolation(Player $player, DetectionResult $result, string $source) : void {
		$config = $this->plugin->getConfig();
		$prefix = (string) $config->get("prefix", "§l§8[§cAnti§4Ad§8]§r ");

		$reason = $result->getType() . " (" . $result->getMatchedFragment() . ")";
		$warns = $this->warningManager->addWarning(
			$player->getName(),
			$result->getType(),
			$result->getMatchedFragment(),
			$source
		);

		/** @var array<int|string, array<string, mixed>> $punishments */
		$punishments = (array) $config->get("punishments", []);
		$maxWarns = $this->getMaxConfiguredStep($punishments);

		$placeholders = [
			"{player}" => $player->getName(),
			"{warns}" => (string) $warns,
			"{max_warns}" => (string) $maxWarns,
			"{reason}" => $reason,
			"{source}" => $source,
			"{content}" => $result->getOriginalText(),
		];

		// 1. Send warning message to the player
		$warnMsg = (string) $config->getNested(
			"messages.ad-blocked-warning",
			"§cتبلیغات در این سرور اکیداً ممنوع است! §e(اخطار {warns}/{max_warns})"
		);
		$player->sendMessage($prefix . $this->replacePlaceholders($warnMsg, $placeholders));

		// 2. Notify staff & log violation
		$this->notifyAndLog($player, $result, $source, $warns, $maxWarns, $placeholders);

		// 3. Determine punishment step (exact step or highest configured step if exceeded)
		$stepConfig = $punishments[$warns] ?? $punishments[(string) $warns] ?? null;
		if ($stepConfig === null && $maxWarns > 0 && $warns >= $maxWarns) {
			$stepConfig = $punishments[$maxWarns] ?? $punishments[(string) $maxWarns] ?? null;
		}

		if (!is_array($stepConfig)) {
			return;
		}

		$action = strtolower((string) ($stepConfig["action"] ?? "warn"));
		$duration = intval($stepConfig["duration"] ?? 300);
		$placeholders["{duration}"] = (string) $duration;

		// Run any custom console commands attached to this step
		if (isset($stepConfig["commands"]) && is_array($stepConfig["commands"])) {
			$server = $this->plugin->getServer();
			$console = new ConsoleCommandSender($server, $server->getLanguage());
			foreach ($stepConfig["commands"] as $rawCmd) {
				$cmd = $this->replacePlaceholders((string) $rawCmd, $placeholders);
				if ($cmd !== "") {
					$server->dispatchCommand($console, $cmd);
				}
			}
		}

		switch ($action) {
			case "warn":
				break;

			case "mute":
				$this->warningManager->mutePlayer($player->getName(), $duration);
				$muteMsg = (string) $config->getNested(
					"messages.muted-notice",
					"§cشما به دلیل تلاش برای تبلیغات به مدت §e{duration} ثانیه §cسایلنت شدید!"
				);
				$player->sendMessage($prefix . $this->replacePlaceholders($muteMsg, $placeholders));
				break;

			case "kick":
				$kickReason = (string) ($stepConfig["reason"] ?? $config->getNested("messages.kick-reason", "§cتبلیغات در سرور ممنوع است!"));
				$kickReason = $this->replacePlaceholders($kickReason, $placeholders);
				$player->kick($kickReason);
				break;

			case "tempban":
				$banReason = (string) ($stepConfig["reason"] ?? $config->getNested("messages.ban-reason", "§cبن موقت به دلیل تبلیغات"));
				$banReason = $this->replacePlaceholders($banReason, $placeholders);
				$expires = (new DateTime())->setTimestamp(time() + max(60, $duration));
				$server = $this->plugin->getServer();
				$server->getNameBans()->addBan($player->getName(), $banReason, $expires, "AntiAdvertising");

				if ((bool) ($stepConfig["ban-ip"] ?? false)) {
					$ip = $player->getNetworkSession()->getIp();
					$server->getIpBans()->addBan($ip, $banReason, $expires, "AntiAdvertising");
				}
				$player->kick($banReason);
				break;

			case "ban":
				$banReason = (string) ($stepConfig["reason"] ?? $config->getNested("messages.ban-reason", "§cبن دائمی به دلیل تبلیغات"));
				$banReason = $this->replacePlaceholders($banReason, $placeholders);
				$server = $this->plugin->getServer();
				$server->getNameBans()->addBan($player->getName(), $banReason, null, "AntiAdvertising");

				if ((bool) ($stepConfig["ban-ip"] ?? false)) {
					$ip = $player->getNetworkSession()->getIp();
					$server->getIpBans()->addBan($ip, $banReason, null, "AntiAdvertising");
				}
				$player->kick($banReason);
				break;

			case "command":
				// Custom commands already executed above
				break;
		}

		// Reset warnings if player reached maximum step and reset-on-max-punishment is enabled
		if (
			$maxWarns > 0 &&
			$warns >= $maxWarns &&
			(bool) $config->getNested("warnings.reset-on-max-punishment", true)
		) {
			$this->warningManager->clearWarnings($player->getName());
		}
	}

	/**
	 * @param array<int|string, mixed> $punishments
	 */
	private function getMaxConfiguredStep(array $punishments) : int {
		if (count($punishments) === 0) {
			return 3;
		}
		$max = 1;
		foreach (array_keys($punishments) as $key) {
			$step = intval($key);
			if ($step > $max) {
				$max = $step;
			}
		}
		return $max;
	}

	/**
	 * @param array<string, string> $placeholders
	 */
	private function notifyAndLog(
		Player $player,
		DetectionResult $result,
		string $source,
		int $warns,
		int $maxWarns,
		array $placeholders
	) : void {
		$config = $this->plugin->getConfig();
		$prefix = (string) $config->get("prefix", "§l§8[§cAnti§4Ad§8]§r ");

		$alertTemplate = (string) $config->getNested(
			"messages.staff-alert",
			"§cهشدار تبلیغ: §e{player} §7در بخش §b{source} §7(اخطار {warns}/{max_warns}) §8» §f{reason} §8| §c{content}"
		);
		$formattedAlert = $prefix . $this->replacePlaceholders($alertTemplate, $placeholders);

		// Notify online staff with permission
		if ((bool) $config->getNested("logging.notify-staff", true)) {
			foreach ($this->plugin->getServer()->getOnlinePlayers() as $onlinePlayer) {
				if ($onlinePlayer->hasPermission("antiadvertising.notify")) {
					$onlinePlayer->sendMessage($formattedAlert);
				}
			}
		}

		// Log to console
		if ((bool) $config->getNested("logging.log-to-console", true)) {
			$this->plugin->getLogger()->warning(
				"[{$source}] Player {$player->getName()} attempted to advertise ({$warns}/{$maxWarns}): " .
				"{$result->getType()} [{$result->getMatchedFragment()}] -> \"{$result->getOriginalText()}\""
			);
		}

		// Log to file
		if ((bool) $config->getNested("logging.log-to-file", true)) {
			$logLine = "[" . date("Y-m-d H:i:s") . "] " .
				"Player={$player->getName()} | IP={$player->getNetworkSession()->getIp()} | " .
				"Source={$source} | Warns={$warns}/{$maxWarns} | " .
				"Type={$result->getType()} | Matched={$result->getMatchedFragment()} | " .
				"Text=\"{$result->getOriginalText()}\"" . PHP_EOL;
			@file_put_contents($this->plugin->getDataFolder() . "detections.log", $logLine, FILE_APPEND);
		}
	}

	/**
	 * @param array<string, string> $placeholders
	 */
	public function replacePlaceholders(string $text, array $placeholders) : string {
		foreach ($placeholders as $key => $val) {
			$text = str_replace($key, $val, $text);
		}
		return $text;
	}
}
