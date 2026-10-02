<?php

declare(strict_types=1);

namespace AntiAdvertising\detector;

final class DetectionResult {

	public const TYPE_NONE = "none";
	public const TYPE_IP = "آی‌پی سرور (IP Address)";
	public const TYPE_DOMAIN = "لینک / دامنه (Domain/URL)";
	public const TYPE_DISCORD = "لینک دیسکورد (Discord Invite)";
	public const TYPE_SOCIAL = "لینک شبکه اجتماعی (Social Link)";
	public const TYPE_PHRASE = "عبارت تبلیغاتی (Blacklisted Phrase)";

	public function __construct(
		private readonly bool $detected,
		private readonly string $type = self::TYPE_NONE,
		private readonly string $matchedFragment = "",
		private readonly string $originalText = ""
	) {
	}

	public static function pass(string $originalText = "") : self {
		return new self(false, self::TYPE_NONE, "", $originalText);
	}

	public static function flagged(string $type, string $matchedFragment, string $originalText) : self {
		return new self(true, $type, $matchedFragment, $originalText);
	}

	public function isDetected() : bool {
		return $this->detected;
	}

	public function getType() : string {
		return $this->type;
	}

	public function getMatchedFragment() : string {
		return $this->matchedFragment;
	}

	public function getOriginalText() : string {
		return $this->originalText;
	}
}
