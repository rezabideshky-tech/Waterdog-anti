<?php

declare(strict_types=1);

namespace AntiAdvertising;

use AntiAdvertising\command\AntiAdCommand;
use AntiAdvertising\detector\AdDetector;
use AntiAdvertising\listener\EventListener;
use AntiAdvertising\manager\PunishmentManager;
use AntiAdvertising\manager\WarningManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use function intval;
use function strtolower;

final class Main extends PluginBase {

	private AdDetector $detector;
	private WarningManager $warningManager;
	private PunishmentManager $punishmentManager;
	private AntiAdCommand $commandHandler;

	protected function onEnable() : void {
		$this->saveDefaultConfig();

		$config = $this->getConfig();
		$this->detector = new AdDetector(
			(array) $config->get("detectors", []),
			(array) $config->get("whitelist", []),
			(array) $config->get("blacklisted-phrases", []),
			(array) $config->get("monitored-tlds", [])
		);

		$this->warningManager = new WarningManager(
			$this->getDataFolder(),
			(bool) $config->getNested("warnings.save-to-disk", true),
			intval($config->getNested("warnings.expire-seconds", 86400))
		);

		$this->punishmentManager = new PunishmentManager($this, $this->warningManager);
		$this->commandHandler = new AntiAdCommand($this);

		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

		$this->getLogger()->info("§aAntiAdvertising v" . $this->getDescription()->getVersion() . " (PocketMine-MP API 5) enabled successfully!");
	}

	protected function onDisable() : void {
		if (isset($this->warningManager)) {
			$this->warningManager->save();
		}
	}

	public function reloadPluginSettings() : void {
		$this->reloadConfig();
		$config = $this->getConfig();

		$this->detector = new AdDetector(
			(array) $config->get("detectors", []),
			(array) $config->get("whitelist", []),
			(array) $config->get("blacklisted-phrases", []),
			(array) $config->get("monitored-tlds", [])
		);

		$this->warningManager->updateSettings(
			(bool) $config->getNested("warnings.save-to-disk", true),
			intval($config->getNested("warnings.expire-seconds", 86400))
		);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
		if (strtolower($command->getName()) === "antiad") {
			return $this->commandHandler->execute($sender, $command, $label, $args);
		}
		return false;
	}

	public function getDetector() : AdDetector {
		return $this->detector;
	}

	public function getWarningManager() : WarningManager {
		return $this->warningManager;
	}

	public function getPunishmentManager() : PunishmentManager {
		return $this->punishmentManager;
	}
}
