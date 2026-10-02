<?php

declare(strict_types=1);

namespace AntiAdvertising\listener;

use AntiAdvertising\Main;
use pocketmine\block\utils\SignText;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerEditBookEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\item\WrittenBook;
use pocketmine\player\Player;
use function array_map;
use function count;
use function explode;
use function implode;
use function in_array;
use function preg_split;
use function str_contains;
use function str_replace;
use function strtolower;
use function trim;

final class EventListener implements Listener {

	public function __construct(
		private readonly Main $plugin
	) {
	}

	/**
	 * @priority HIGH
	 * @handleCancelled false
	 */
	public function onPlayerChat(PlayerChatEvent $event) : void {
		$player = $event->getPlayer();
		if ($player->hasPermission("antiadvertising.bypass")) {
			return;
		}

		// Check if player is currently muted by AntiAdvertising
		$warningManager = $this->plugin->getWarningManager();
		if ($warningManager->isMuted($player->getName())) {
			$event->cancel();
			$this->sendMutedNotice($player, $warningManager->getRemainingMuteTime($player->getName()));
			return;
		}

		if (!(bool) $this->plugin->getConfig()->getNested("checks.chat", true)) {
			return;
		}

		$result = $this->plugin->getDetector()->inspect($event->getMessage());
		if ($result->isDetected()) {
			$event->cancel();
			$this->plugin->getPunishmentManager()->handleViolation($player, $result, "چت عمومی (Chat)");
		}
	}

	/**
	 * @priority HIGH
	 * @handleCancelled false
	 */
	public function onCommand(CommandEvent $event) : void {
		$sender = $event->getSender();
		if (!($sender instanceof Player)) {
			return;
		}

		if ($sender->hasPermission("antiadvertising.bypass")) {
			return;
		}

		if (!(bool) $this->plugin->getConfig()->getNested("checks.commands", true)) {
			return;
		}

		$commandLine = trim($event->getCommand());
		if ($commandLine === "") {
			return;
		}

		$parts = preg_split('/\s+/', $commandLine, 2);
		if ($parts === false || count($parts) < 2) {
			return;
		}

		$rawCmd = strtolower(trim($parts[0], "/"));
		// Strip plugin namespace prefix if used (e.g., "pocketmine:tell" -> "tell")
		if (str_contains($rawCmd, ":")) {
			$subParts = explode(":", $rawCmd);
			$rawCmd = $subParts[count($subParts) - 1];
		}

		/** @var string[] $monitored */
		$monitored = array_map(
			static fn(mixed $cmd) : string => strtolower(trim((string) $cmd, "/ ")),
			(array) $this->plugin->getConfig()->get("monitored-commands", [])
		);

		if (!in_array($rawCmd, $monitored, true)) {
			return;
		}

		// Check if player is muted from using private message / broadcast commands
		$warningManager = $this->plugin->getWarningManager();
		if ($warningManager->isMuted($sender->getName())) {
			$event->cancel();
			$this->sendMutedNotice($sender, $warningManager->getRemainingMuteTime($sender->getName()));
			return;
		}

		$argumentsText = $parts[1];
		$result = $this->plugin->getDetector()->inspect($argumentsText);
		if ($result->isDetected()) {
			$event->cancel();
			$this->plugin->getPunishmentManager()->handleViolation(
				$sender,
				$result,
				"دستور (/" . $rawCmd . ")"
			);
		}
	}

	/**
	 * @priority HIGH
	 * @handleCancelled false
	 */
	public function onSignChange(SignChangeEvent $event) : void {
		$player = $event->getPlayer();
		if ($player->hasPermission("antiadvertising.bypass")) {
			return;
		}

		if (!(bool) $this->plugin->getConfig()->getNested("checks.signs", true)) {
			return;
		}

		$lines = $event->getNewText()->getLines();
		$detector = $this->plugin->getDetector();

		// 1. Check each individual line
		foreach ($lines as $line) {
			$result = $detector->inspect($line);
			if ($result->isDetected()) {
				$this->blockSign($event, $player, $result);
				return;
			}
		}

		// 2. Check combined lines (prevents splitting an IP/Domain across multiple sign lines)
		$joinedWithSpace = implode(" ", $lines);
		$resultSpace = $detector->inspect($joinedWithSpace);
		if ($resultSpace->isDetected()) {
			$this->blockSign($event, $player, $resultSpace);
			return;
		}

		$joinedDirect = implode("", $lines);
		$resultDirect = $detector->inspect($joinedDirect);
		if ($resultDirect->isDetected()) {
			$this->blockSign($event, $player, $resultDirect);
		}
	}

	private function blockSign(SignChangeEvent $event, Player $player, \AntiAdvertising\detector\DetectionResult $result) : void {
		$blockedLine = (string) $this->plugin->getConfig()->getNested("messages.sign-blocked-line", "§c[تبلیغ حذف شد]");
		$event->setNewText(new SignText([$blockedLine, "", "", ""]));
		$this->plugin->getPunishmentManager()->handleViolation($player, $result, "تابلو (Sign)");
	}

	/**
	 * @priority HIGH
	 * @handleCancelled false
	 */
	public function onBookEdit(PlayerEditBookEvent $event) : void {
		$player = $event->getPlayer();
		if ($player->hasPermission("antiadvertising.bypass")) {
			return;
		}

		if (!(bool) $this->plugin->getConfig()->getNested("checks.books", true)) {
			return;
		}

		$book = $event->getNewBook();
		$detector = $this->plugin->getDetector();

		if ($book instanceof WrittenBook) {
			$titleResult = $detector->inspect($book->getTitle());
			if ($titleResult->isDetected()) {
				$event->cancel();
				$this->plugin->getPunishmentManager()->handleViolation($player, $titleResult, "عنوان کتاب (Book Title)");
				return;
			}
		}

		foreach ($book->getPages() as $page) {
			$pageResult = $detector->inspect($page->getText());
			if ($pageResult->isDetected()) {
				$event->cancel();
				$this->plugin->getPunishmentManager()->handleViolation($player, $pageResult, "متن کتاب (Book)");
				return;
			}
		}
	}

	/**
	 * @priority HIGH
	 * @handleCancelled false
	 */
	public function onInventoryTransaction(InventoryTransactionEvent $event) : void {
		$transaction = $event->getTransaction();
		$player = $transaction->getSource();

		if ($player->hasPermission("antiadvertising.bypass")) {
			return;
		}

		if (!(bool) $this->plugin->getConfig()->getNested("checks.item-rename", true)) {
			return;
		}

		$detector = $this->plugin->getDetector();
		foreach ($transaction->getActions() as $action) {
			$targetItem = $action->getTargetItem();
			if ($targetItem->hasCustomName()) {
				$result = $detector->inspect($targetItem->getCustomName());
				if ($result->isDetected()) {
					$event->cancel();
					$this->plugin->getPunishmentManager()->handleViolation($player, $result, "تغییر نام آیتم (Item Name)");
					return;
				}
			}
		}
	}

	/**
	 * @priority HIGH
	 * @handleCancelled false
	 */
	public function onPlayerItemHeld(PlayerItemHeldEvent $event) : void {
		$player = $event->getPlayer();
		if ($player->hasPermission("antiadvertising.bypass")) {
			return;
		}

		if (!(bool) $this->plugin->getConfig()->getNested("checks.item-rename", true)) {
			return;
		}

		$item = $event->getItem();
		if ($item->hasCustomName()) {
			$result = $this->plugin->getDetector()->inspect($item->getCustomName());
			if ($result->isDetected()) {
				$item->clearCustomName();
				$player->getInventory()->setItem($event->getSlot(), $item);
				$this->plugin->getPunishmentManager()->handleViolation($player, $result, "نام آیتم (Held Item)");
			}
		}
	}

	private function sendMutedNotice(Player $player, int $remainingSeconds) : void {
		$config = $this->plugin->getConfig();
		$prefix = (string) $config->get("prefix", "§l§8[§cAnti§4Ad§8]§r ");
		$msg = (string) $config->getNested(
			"messages.still-muted",
			"§cشما به دلیل تبلیغات سایلنت هستید! زمان باقی‌مانده: §e{remaining} ثانیه"
		);
		$player->sendMessage($prefix . str_replace("{remaining}", (string) $remainingSeconds, $msg));
	}
}
