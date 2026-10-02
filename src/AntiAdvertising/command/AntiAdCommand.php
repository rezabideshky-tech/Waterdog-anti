<?php

declare(strict_types=1);

namespace AntiAdvertising\command;

use AntiAdvertising\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function date;
use function implode;
use function in_array;
use function strtolower;
use function trim;

final class AntiAdCommand {

	public function __construct(
		private readonly Main $plugin
	) {
	}

	/**
	 * @param string[] $args
	 */
	public function execute(CommandSender $sender, Command $command, string $label, array $args) : bool {
		$config = $this->plugin->getConfig();
		$prefix = (string) $config->get("prefix", "§l§8[§cAnti§4Ad§8]§r ");

		if (!$sender->hasPermission("antiadvertising.admin")) {
			$sender->sendMessage($prefix . (string) $config->getNested("messages.no-permission", "§cشما دسترسی لازم برای این دستور را ندارید."));
			return true;
		}

		if (count($args) === 0) {
			$this->sendHelp($sender, $prefix);
			return true;
		}

		$sub = strtolower($args[0]);
		switch ($sub) {
			case "help":
			case "?":
				$this->sendHelp($sender, $prefix);
				return true;

			case "reload":
				$this->plugin->reloadPluginSettings();
				$sender->sendMessage($prefix . (string) $this->plugin->getConfig()->getNested(
					"messages.reload-success",
					"§aتنظیمات پلاگین ضد تبلیغات با موفقیت بازخوانی شد."
				));
				return true;

			case "status":
			case "info":
				$checks = (array) $config->get("checks", []);
				$detectors = (array) $config->get("detectors", []);
				$whitelistCount = count($this->plugin->getDetector()->getWhitelist());
				$phrasesCount = count((array) $config->get("blacklisted-phrases", []));

				$sender->sendMessage("§8§m----------------------------------------");
				$sender->sendMessage($prefix . "§eوضعیت پلاگین ضد تبلیغات §7(v" . $this->plugin->getDescription()->getVersion() . ")");
				$sender->sendMessage("§7بخش‌های فعال: " .
					"§fChat=" . $this->formatBool((bool) ($checks["chat"] ?? true)) . " §8| " .
					"§fCmd=" . $this->formatBool((bool) ($checks["commands"] ?? true)) . " §8| " .
					"§fSign=" . $this->formatBool((bool) ($checks["signs"] ?? true)) . " §8| " .
					"§fBook=" . $this->formatBool((bool) ($checks["books"] ?? true)) . " §8| " .
					"§fItem=" . $this->formatBool((bool) ($checks["item-rename"] ?? true))
				);
				$sender->sendMessage("§7تشخیص‌دهنده‌ها: " .
					"§fIP=" . $this->formatBool((bool) ($detectors["ip-address"] ?? true)) . " §8| " .
					"§fDomain=" . $this->formatBool((bool) ($detectors["domain-links"] ?? true)) . " §8| " .
					"§fDiscord=" . $this->formatBool((bool) ($detectors["discord-invites"] ?? true)) . " §8| " .
					"§fAntiObf=" . $this->formatBool((bool) ($detectors["anti-obfuscation"] ?? true))
				);
				$sender->sendMessage("§7تعداد دامنه/آی‌پی مجاز (Whitelist): §a{$whitelistCount}");
				$sender->sendMessage("§7تعداد عبارات ممنوعه (Blacklist): §c{$phrasesCount}");
				$sender->sendMessage("§8§m----------------------------------------");
				return true;

			case "warns":
			case "warnings":
			case "check":
				if (!isset($args[1])) {
					$sender->sendMessage($prefix . "§cاستفاده صحیح: §e/antiad warns <PlayerName>");
					return true;
				}
				$target = $args[1];
				$warns = $this->plugin->getWarningManager()->getWarnings($target);
				$isMuted = $this->plugin->getWarningManager()->isMuted($target);
				$muteRem = $this->plugin->getWarningManager()->getRemainingMuteTime($target);

				$sender->sendMessage($prefix . "§eوضعیت اخطارهای بازیکن §b{$target}§e: §f(" . count($warns) . " اخطار)");
				if ($isMuted) {
					$sender->sendMessage("§7وضعیت سایلنت: §cسایلنت شده ({$muteRem} ثانیه باقی‌مانده)");
				}
				if (count($warns) === 0) {
					$sender->sendMessage("§aاین بازیکن هیچ اخطار فعالی ندارد.");
					return true;
				}
				foreach ($warns as $idx => $entry) {
					$num = $idx + 1;
					$dateStr = date("Y-m-d H:i:s", $entry["time"]);
					$sender->sendMessage("§8[§e#{$num}§8] §7{$dateStr} §8| §b{$entry["source"]} §8| §f{$entry["type"]} §8(§c{$entry["matched"]}§8)");
				}
				return true;

			case "clearwarns":
			case "resetwarns":
			case "clear":
				if (!isset($args[1])) {
					$sender->sendMessage($prefix . "§cاستفاده صحیح: §e/antiad clearwarns <PlayerName>");
					return true;
				}
				$target = $args[1];
				$this->plugin->getWarningManager()->clearWarnings($target);
				$this->plugin->getWarningManager()->unmutePlayer($target);
				$sender->sendMessage($prefix . "§aتمامی اخطارها و محدودیت‌های بازیکن §e{$target} §aپاک شد.");
				return true;

			case "unmute":
				if (!isset($args[1])) {
					$sender->sendMessage($prefix . "§cاستفاده صحیح: §e/antiad unmute <PlayerName>");
					return true;
				}
				$target = $args[1];
				$this->plugin->getWarningManager()->unmutePlayer($target);
				$sender->sendMessage($prefix . "§aبازیکن §e{$target} §aاز حالت سایلنت (Mute) خارج شد.");
				return true;

			case "whitelist":
			case "wl":
				$this->handleWhitelist($sender, $prefix, array_slice($args, 1));
				return true;

			case "test":
				if (count($args) < 2) {
					$sender->sendMessage($prefix . "§cاستفاده صحیح: §e/antiad test <متن جهت بررسی>");
					return true;
				}
				$sample = implode(" ", array_slice($args, 1));
				$result = $this->plugin->getDetector()->inspect($sample);
				if ($result->isDetected()) {
					$sender->sendMessage($prefix . "§cنتیجه تست: تبلیغ تشخیص داده شد!");
					$sender->sendMessage("§7نوع: §e" . $result->getType());
					$sender->sendMessage("§7بخش شناسایی‌شده: §c" . $result->getMatchedFragment());
				} else {
					$sender->sendMessage($prefix . "§aنتیجه تست: این متن پاک است و تبلیغی تشخیص داده نشد.");
				}
				return true;

			default:
				$this->sendHelp($sender, $prefix);
				return true;
		}
	}

	/**
	 * @param string[] $subArgs
	 */
	private function handleWhitelist(CommandSender $sender, string $prefix, array $subArgs) : void {
		if (count($subArgs) === 0) {
			$sender->sendMessage($prefix . "§cاستفاده صحیح: §e/antiad whitelist <list|add|remove> [domain/ip]");
			return;
		}

		$action = strtolower($subArgs[0]);
		$config = $this->plugin->getConfig();
		/** @var string[] $current */
		$current = array_map(
			static fn(mixed $v) : string => strtolower(trim((string) $v)),
			(array) $config->get("whitelist", [])
		);

		switch ($action) {
			case "list":
				$sender->sendMessage($prefix . "§eلیست سفید (Whitelist) فعلی (" . count($current) . " مورد):");
				if (count($current) === 0) {
					$sender->sendMessage("§7(خالی)");
				} else {
					$sender->sendMessage("§a" . implode("§7, §a", $current));
				}
				return;

			case "add":
				if (!isset($subArgs[1]) || trim($subArgs[1]) === "") {
					$sender->sendMessage($prefix . "§cلطفاً دامنه یا آی‌پی مورد نظر را وارد کنید: §e/antiad whitelist add <domain/ip>");
					return;
				}
				$entry = strtolower(trim($subArgs[1]));
				if (in_array($entry, $current, true)) {
					$sender->sendMessage($prefix . "§eمورد §f{$entry} §eاز قبل در لیست سفید وجود دارد.");
					return;
				}
				$current[] = $entry;
				$config->set("whitelist", array_values($current));
				$config->save();
				$this->plugin->reloadPluginSettings();
				$sender->sendMessage($prefix . "§aمورد §e{$entry} §aبا موفقیت به لیست سفید اضافه شد.");
				return;

			case "remove":
			case "del":
			case "delete":
				if (!isset($subArgs[1]) || trim($subArgs[1]) === "") {
					$sender->sendMessage($prefix . "§cلطفاً دامنه یا آی‌پی مورد نظر را وارد کنید: §e/antiad whitelist remove <domain/ip>");
					return;
				}
				$entry = strtolower(trim($subArgs[1]));
				if (!in_array($entry, $current, true)) {
					$sender->sendMessage($prefix . "§cمورد §f{$entry} §cدر لیست سفید یافت نشد.");
					return;
				}
				$updated = array_values(array_filter(
					$current,
					static fn(string $item) : bool => $item !== $entry
				));
				$config->set("whitelist", $updated);
				$config->save();
				$this->plugin->reloadPluginSettings();
				$sender->sendMessage($prefix . "§aمورد §e{$entry} §aاز لیست سفید حذف شد.");
				return;

			default:
				$sender->sendMessage($prefix . "§cاستفاده صحیح: §e/antiad whitelist <list|add|remove> [domain/ip]");
		}
	}

	private function sendHelp(CommandSender $sender, string $prefix) : void {
		$sender->sendMessage("§8§m----------------------------------------");
		$sender->sendMessage($prefix . "§eراهنمای دستورات پلاگین ضد تبلیغات:");
		$sender->sendMessage("§e/antiad status §8- §7نمایش وضعیت فعلی پلاگین");
		$sender->sendMessage("§e/antiad reload §8- §7بازخوانی تنظیمات config.yml");
		$sender->sendMessage("§e/antiad warns <player> §8- §7مشاهده اخطارهای بازیکن");
		$sender->sendMessage("§e/antiad clearwarns <player> §8- §7پاک کردن اخطارهای بازیکن");
		$sender->sendMessage("§e/antiad unmute <player> §8- §7رفع سایلنت بازیکن");
		$sender->sendMessage("§e/antiad whitelist <list|add|remove> §8- §7مدیریت لیست سفید");
		$sender->sendMessage("§e/antiad test <text> §8- §7تست تشخیص تبلیغ روی یک متن");
		$sender->sendMessage("§8§m----------------------------------------");
	}

	private function formatBool(bool $val) : string {
		return $val ? "§aON" : "§cOFF";
	}
}
