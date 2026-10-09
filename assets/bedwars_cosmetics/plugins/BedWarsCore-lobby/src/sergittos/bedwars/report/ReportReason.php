<?php

declare(strict_types=1);

namespace sergittos\bedwars\report;

use sergittos\bedwars\utils\ColorUtils;

/**
 * A single selectable violation category shown in the report reason dropdown.
 * Kept as a small value object (rather than a bare string) so the display
 * label can carry its own color while $id stays a stable, storage-safe
 * identifier that never changes even if the display text is reworded later.
 */
final class ReportReason{

    public function __construct(
        private string $id,
        private string $label
    ){}

    public function getId(): string{
        return $this->id;
    }

    /** Raw label, including {COLOR} placeholders - use getDisplayLabel() for anything sent to a player. */
    public function getLabel(): string{
        return $this->label;
    }

    public function getDisplayLabel(): string{
        return ColorUtils::translate($this->label);
    }

}
