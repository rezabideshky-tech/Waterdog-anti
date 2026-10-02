<?php

declare(strict_types=1);

namespace AntiAdvertising\detector;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function intval;
use function mb_strtolower;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function str_contains;
use function str_ireplace;
use function str_replace;
use function strtolower;
use function trim;

final class AdDetector {

	/** @var string[] */
	private array $whitelist = [];

	/** @var string[] */
	private array $blacklistedPhrases = [];

	/** @var string[] */
	private array $monitoredTlds = [];

	private bool $checkIp = true;
	private bool $checkDomain = true;
	private bool $checkDiscord = true;
	private bool $checkSocial = true;
	private bool $checkPhrases = true;
	private bool $antiObfuscation = true;

	/**
	 * @param array<string, mixed> $detectorsConfig
	 * @param string[]             $whitelist
	 * @param string[]             $blacklistedPhrases
	 * @param string[]             $monitoredTlds
	 */
	public function __construct(
		array $detectorsConfig,
		array $whitelist,
		array $blacklistedPhrases,
		array $monitoredTlds
	) {
		$this->checkIp = (bool) ($detectorsConfig["ip-address"] ?? true);
		$this->checkDomain = (bool) ($detectorsConfig["domain-links"] ?? true);
		$this->checkDiscord = (bool) ($detectorsConfig["discord-invites"] ?? true);
		$this->checkSocial = (bool) ($detectorsConfig["social-links"] ?? true);
		$this->checkPhrases = (bool) ($detectorsConfig["blacklisted-phrases"] ?? true);
		$this->antiObfuscation = (bool) ($detectorsConfig["anti-obfuscation"] ?? true);

		$this->setWhitelist($whitelist);
		$this->setBlacklistedPhrases($blacklistedPhrases);
		$this->setMonitoredTlds($monitoredTlds);
	}

	/**
	 * @param string[] $whitelist
	 */
	public function setWhitelist(array $whitelist) : void {
		$clean = [];
		foreach ($whitelist as $item) {
			$trimmed = trim(strtolower((string) $item));
			if ($trimmed !== "") {
				$clean[] = $trimmed;
			}
		}
		$this->whitelist = array_values(array_unique($clean));
	}

	/**
	 * @return string[]
	 */
	public function getWhitelist() : array {
		return $this->whitelist;
	}

	/**
	 * @param string[] $phrases
	 */
	public function setBlacklistedPhrases(array $phrases) : void {
		$clean = [];
		foreach ($phrases as $phrase) {
			$trimmed = trim($this->toLower((string) $phrase));
			if ($trimmed !== "") {
				$clean[] = $trimmed;
			}
		}
		$this->blacklistedPhrases = array_values(array_unique($clean));
	}

	/**
	 * @param string[] $tlds
	 */
	public function setMonitoredTlds(array $tlds) : void {
		$clean = [];
		foreach ($tlds as $tld) {
			$trimmed = trim(strtolower((string) $tld), ". \t\n\r\0\x0B");
			if ($trimmed !== "") {
				$clean[] = preg_quote($trimmed, "/");
			}
		}
		$this->monitoredTlds = array_values(array_unique($clean));
	}

	/**
	 * Inspect a text string for any form of server/link advertising.
	 */
	public function inspect(string $rawText) : DetectionResult {
		if (trim($rawText) === "") {
			return DetectionResult::pass($rawText);
		}

		// 1. Strip Minecraft formatting codes (§a, &c, etc.) and zero-width chars
		$cleaned = $this->stripFormattingAndInvisible($rawText);

		// 2. Convert Persian/Arabic numerals to standard ASCII numerals
		$cleaned = $this->normalizeDigits($cleaned);

		// 3. Remove whitelisted domains/links from the text before checking
		$cleaned = $this->stripWhitelisted($cleaned);
		if (trim($cleaned) === "") {
			return DetectionResult::pass($rawText);
		}

		$lowered = $this->toLower($cleaned);

		// 4. Prepare de-obfuscated variants if enabled
		$candidates = [$lowered];
		if ($this->antiObfuscation) {
			$deobfuscated = $this->deobfuscate($lowered);
			if (!in_array($deobfuscated, $candidates, true)) {
				$candidates[] = $deobfuscated;
			}

			$collapsed = $this->collapseSpacedCharacters($deobfuscated);
			if (!in_array($collapsed, $candidates, true)) {
				$candidates[] = $collapsed;
			}
		}

		// Check 1: Blacklisted phrases
		if ($this->checkPhrases) {
			foreach ($candidates as $text) {
				foreach ($this->blacklistedPhrases as $phrase) {
					if ($phrase !== "" && str_contains($text, $phrase)) {
						return DetectionResult::flagged(
							DetectionResult::TYPE_PHRASE,
							$phrase,
							$rawText
						);
					}
				}
			}
		}

		// Check 2: Discord Invite Links
		if ($this->checkDiscord) {
			foreach ($candidates as $text) {
				if (preg_match('/\b(?:https?:\/\/)?(?:www\.)?(?:discord\.(?:gg|io|me|li)|discordapp\.com\/invite|discord\.com\/invite|dsc\.gg)\/[a-z0-9\-_]{2,32}\b/iu', $text, $matches) === 1) {
					if (!$this->isFragmentWhitelisted($matches[0])) {
						return DetectionResult::flagged(
							DetectionResult::TYPE_DISCORD,
							$matches[0],
							$rawText
						);
					}
				}
			}
		}

		// Check 3: Social Media Links (Telegram, Rubika, Eitaa, Bale, Instagram)
		if ($this->checkSocial) {
			foreach ($candidates as $text) {
				if (preg_match('/\b(?:https?:\/\/)?(?:www\.)?(?:t\.me|telegram\.me|telegram\.dog|rubika\.ir|ble\.ir|eitaa\.com|instagram\.com)\/[a-z0-9_\-\.]{3,64}\b/iu', $text, $matches) === 1) {
					if (!$this->isFragmentWhitelisted($matches[0])) {
						return DetectionResult::flagged(
							DetectionResult::TYPE_SOCIAL,
							$matches[0],
							$rawText
						);
					}
				}
			}
		}

		// Check 4: IPv4 Addresses (e.g., 192.168.1.1 or 45.12.34.56:19132)
		if ($this->checkIp) {
			foreach ($candidates as $text) {
				$ipMatch = $this->findIpv4Address($text);
				if ($ipMatch !== null && !$this->isFragmentWhitelisted($ipMatch)) {
					return DetectionResult::flagged(
						DetectionResult::TYPE_IP,
						$ipMatch,
						$rawText
					);
				}
			}
		}

		// Check 5: Domains and Web URLs
		if ($this->checkDomain) {
			foreach ($candidates as $text) {
				$domainMatch = $this->findDomainOrUrl($text);
				if ($domainMatch !== null && !$this->isFragmentWhitelisted($domainMatch)) {
					return DetectionResult::flagged(
						DetectionResult::TYPE_DOMAIN,
						$domainMatch,
						$rawText
					);
				}
			}
		}

		return DetectionResult::pass($rawText);
	}

	private function stripFormattingAndInvisible(string $text) : string {
		// Strip Minecraft § and & color/formatting codes
		$text = (string) preg_replace('/(?:§|&)[0-9a-fk-org]/iu', '', $text);
		// Strip zero-width characters (ZWSP, ZWNJ, ZWJ, BOM)
		$text = str_replace(["\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}", "\u{00AD}"], '', $text);
		return $text;
	}

	private function normalizeDigits(string $text) : string {
		$persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
		$arabicDigits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
		$asciiDigits   = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

		$text = str_replace($persianDigits, $asciiDigits, $text);
		$text = str_replace($arabicDigits, $asciiDigits, $text);
		return $text;
	}

	private function stripWhitelisted(string $text) : string {
		foreach ($this->whitelist as $allowed) {
			if ($allowed !== "") {
				$text = str_ireplace($allowed, " ", $text);
			}
		}
		return $text;
	}

	private function isFragmentWhitelisted(string $fragment) : bool {
		$lowerFragment = strtolower(trim($fragment));
		foreach ($this->whitelist as $allowed) {
			if ($allowed !== "" && ($lowerFragment === $allowed || str_contains($lowerFragment, $allowed))) {
				return true;
			}
		}
		return false;
	}

	private function deobfuscate(string $text) : string {
		// Replace common dot obfuscations with a literal '.'
		$dotPatterns = [
			'/\s*[\(\[\{<]\s*(?:dot|d0t|نقطه|دات|\.)\s*[\)\]\}>]\s*/iu',
			'/\s+(?:dot|d0t|نقطه|دات)\s+/iu',
			'/[•●∙⋅。．｡]/u',
		];
		foreach ($dotPatterns as $pattern) {
			$text = (string) preg_replace($pattern, '.', $text);
		}

		// Replace [:] or (colon) with ':'
		$text = (string) preg_replace('/\s*[\(\[\{<]\s*(?:colon|:)\s*[\)\]\}>]\s*/iu', ':', $text);

		// Normalize multiple dots or spaces around dots (e.g. "play . example . ir" -> "play.example.ir")
		$text = (string) preg_replace('/\s*\.\s*/u', '.', $text);
		$text = (string) preg_replace('/\.{2,}/u', '.', $text);

		return $this->stripWhitelisted($text);
	}

	/**
	 * Collapses single spaced-out characters like "p l a y . m c . i r" or "1 9 2 . 1 6 8 . 1 . 1"
	 */
	private function collapseSpacedCharacters(string $text) : string {
		// Collapse sequences of single alphanumeric characters separated by spaces
		$collapsed = (string) preg_replace_callback(
			'/(?<![a-z0-9])([a-z0-9](?:[\s_\-]+[a-z0-9]){2,})(?![a-z0-9])/iu',
			static function(array $m) : string {
				return (string) preg_replace('/[\s_\-]+/', '', $m[1]);
			},
			$text
		);
		$collapsed = (string) preg_replace('/\s*\.\s*/u', '.', $collapsed);
		return $this->stripWhitelisted($collapsed);
	}

	private function findIpv4Address(string $text) : ?string {
		// Match standard or lightly separated IPv4 addresses: e.g. 192.168.1.1, 192,168,1,1, 192 - 168 - 1 - 1
		$pattern = '/(?<!\d)(\d{1,3})\s*[\.,\-_\/]\s*(\d{1,3})\s*[\.,\-_\/]\s*(\d{1,3})\s*[\.,\-_\/]\s*(\d{1,3})(?:\s*[:\s]\s*(\d{2,5}))?(?!\d)/u';
		if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) > 0) {
			foreach ($matches as $match) {
				$o1 = intval($match[1]);
				$o2 = intval($match[2]);
				$o3 = intval($match[3]);
				$o4 = intval($match[4]);

				if ($o1 <= 255 && $o2 <= 255 && $o3 <= 255 && $o4 <= 255) {
					// Ignore 0.0.0.0
					if ($o1 === 0 && $o2 === 0 && $o3 === 0 && $o4 === 0) {
						continue;
					}
					$ip = "{$o1}.{$o2}.{$o3}.{$o4}";
					if (isset($match[5]) && $match[5] !== "") {
						$port = intval($match[5]);
						if ($port >= 1 && $port <= 65535) {
							$ip .= ":" . $port;
						}
					}
					return $ip;
				}
			}
		}
		return null;
	}

	private function findDomainOrUrl(string $text) : ?string {
		// 1. Explicit protocol links (http:// or https://)
		if (preg_match('/\bhttps?:\/\/[a-z0-9\-._~%]+(?:\:[0-9]{1,5})?(?:\/[^\s]*)?/iu', $text, $m) === 1) {
			return $m[0];
		}

		// 2. Common Minecraft server prefixes followed by domain-like pattern (play.xxx.xx, mc.xxx.xx, join.xxx.xx, pe.xxx.xx)
		if (preg_match('/\b(?:play|mc|pe|bedrock|hub|lobby|join|server|pvp|survival|skyblock)\.[a-z0-9\-]{2,63}\.[a-z]{2,16}(?:\:\d{2,5})?\b/iu', $text, $m) === 1) {
			return $m[0];
		}

		// 3. Domains matching monitored TLDs
		if (count($this->monitoredTlds) > 0) {
			$tldGroup = implode('|', $this->monitoredTlds);
			$pattern = '/\b(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+(?:' . $tldGroup . ')(?:\:\d{2,5})?(?:\/[^\s]*)?\b/iu';
			if (preg_match($pattern, $text, $m) === 1) {
				return $m[0];
			}
		}

		return null;
	}

	private function toLower(string $text) : string {
		return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
	}
}
