<?php

declare(strict_types=1);

namespace AntiAdvertising\manager;

use function array_filter;
use function array_values;
use function count;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function json_decode;
use function json_encode;
use function max;
use function strtolower;
use function time;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;

final class WarningManager {

	/** @var array<string, list<array{time: int, type: string, matched: string, source: string}>> */
	private array $warnings = [];

	/** @var array<string, int> */
	private array $mutedUntil = [];

	private string $storageFile;
	private bool $saveToDisk;
	private int $expireSeconds;

	public function __construct(
		string $dataFolder,
		bool $saveToDisk = true,
		int $expireSeconds = 86400
	) {
		$this->storageFile = $dataFolder . "warnings.json";
		$this->saveToDisk = $saveToDisk;
		$this->expireSeconds = $expireSeconds;
		$this->load();
	}

	public function updateSettings(bool $saveToDisk, int $expireSeconds) : void {
		$this->saveToDisk = $saveToDisk;
		$this->expireSeconds = $expireSeconds;
	}

	/**
	 * Add a warning for a player and return the updated active warning count.
	 */
	public function addWarning(string $playerName, string $type, string $matched, string $source) : int {
		$key = strtolower($playerName);
		$this->pruneExpired($key);

		if (!isset($this->warnings[$key])) {
			$this->warnings[$key] = [];
		}

		$this->warnings[$key][] = [
			"time" => time(),
			"type" => $type,
			"matched" => $matched,
			"source" => $source
		];

		$this->save();
		return count($this->warnings[$key]);
	}

	/**
	 * Get the active warning count for a player.
	 */
	public function getWarningCount(string $playerName) : int {
		$key = strtolower($playerName);
		$this->pruneExpired($key);
		return isset($this->warnings[$key]) ? count($this->warnings[$key]) : 0;
	}

	/**
	 * @return list<array{time: int, type: string, matched: string, source: string}>
	 */
	public function getWarnings(string $playerName) : array {
		$key = strtolower($playerName);
		$this->pruneExpired($key);
		return $this->warnings[$key] ?? [];
	}

	public function clearWarnings(string $playerName) : void {
		$key = strtolower($playerName);
		unset($this->warnings[$key]);
		$this->save();
	}

	public function mutePlayer(string $playerName, int $durationSeconds) : void {
		if ($durationSeconds <= 0) {
			return;
		}
		$key = strtolower($playerName);
		$this->mutedUntil[$key] = time() + $durationSeconds;
	}

	public function unmutePlayer(string $playerName) : void {
		$key = strtolower($playerName);
		unset($this->mutedUntil[$key]);
	}

	public function isMuted(string $playerName) : bool {
		$key = strtolower($playerName);
		if (!isset($this->mutedUntil[$key])) {
			return false;
		}
		if (time() >= $this->mutedUntil[$key]) {
			unset($this->mutedUntil[$key]);
			return false;
		}
		return true;
	}

	public function getRemainingMuteTime(string $playerName) : int {
		$key = strtolower($playerName);
		if (!$this->isMuted($playerName)) {
			return 0;
		}
		return max(0, $this->mutedUntil[$key] - time());
	}

	private function pruneExpired(string $key) : void {
		if ($this->expireSeconds <= 0 || !isset($this->warnings[$key])) {
			return;
		}

		$cutoff = time() - $this->expireSeconds;
		$this->warnings[$key] = array_values(array_filter(
			$this->warnings[$key],
			static fn(array $entry) : bool => ($entry["time"] ?? 0) >= $cutoff
		));

		if (count($this->warnings[$key]) === 0) {
			unset($this->warnings[$key]);
		}
	}

	public function load() : void {
		if (!$this->saveToDisk || !file_exists($this->storageFile)) {
			return;
		}

		$raw = @file_get_contents($this->storageFile);
		if ($raw === false || $raw === "") {
			return;
		}

		$decoded = json_decode($raw, true);
		if (is_array($decoded)) {
			$this->warnings = $decoded;
		}
	}

	public function save() : void {
		if (!$this->saveToDisk) {
			return;
		}

		$encoded = json_encode($this->warnings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		if ($encoded !== false) {
			@file_put_contents($this->storageFile, $encoded);
		}
	}
}
