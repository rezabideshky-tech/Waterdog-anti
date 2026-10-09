<?php

declare(strict_types=1);


namespace sergittos\bedwars\game\shop\item\editor;


/**
 * Per-player runtime state for the "Shop Editor" feature (customizable
 * Main tab). The toggle/pending-slot state is purely in-memory and scoped
 * to the player's current connection (there's no reason to persist "is the
 * editor UI currently open"), but the actual $overrides layout IS persisted
 * to the database by ShopEditorManager - see loadOverrides()/hasLoaded()
 * below - so a player's customized Main tab survives disconnects, server
 * restarts, and follows them to whichever game server they queue into next.
 *
 * Overrides map a Main-tab slot to either:
 *  - ["category" => string, "id" => string] describing which product
 *    (by source category name + Product::getId()) should render there, or
 *  - self::EMPTY_MARKER, meaning the player deliberately left the slot empty.
 */
final class ShopEditorSession {

    public const EMPTY_MARKER = "__empty__";

    private bool $editing = false;
    private ?int $pendingSlot = null;
    private bool $loaded = false;

    /** @var array<int, array{category: string, id: string}|string> */
    private array $overrides = [];

    public function isEditing(): bool {
        return $this->editing;
    }

    public function setEditing(bool $editing): void {
        $this->editing = $editing;

        if(!$editing) {
            // Leaving edit mode always cancels any in-progress selection
            // so a stray click later can never be misread as a replacement pick.
            $this->pendingSlot = null;
        }
    }

    public function toggleEditing(): bool {
        $this->setEditing(!$this->editing);
        return $this->editing;
    }

    public function getPendingSlot(): ?int {
        return $this->pendingSlot;
    }

    public function setPendingSlot(?int $slot): void {
        $this->pendingSlot = $slot;
    }

    public function hasPendingSlot(): bool {
        return $this->pendingSlot !== null;
    }

    /**
     * @return array<int, array{category: string, id: string}|string>
     */
    public function getOverrides(): array {
        return $this->overrides;
    }

    public function hasOverride(int $slot): bool {
        return isset($this->overrides[$slot]);
    }

    public function setOverride(int $slot, string $category, string $id): void {
        $this->overrides[$slot] = ["category" => $category, "id" => $id];
    }

    public function setEmptyOverride(int $slot): void {
        $this->overrides[$slot] = self::EMPTY_MARKER;
    }

    public function clearOverride(int $slot): void {
        unset($this->overrides[$slot]);
    }

    public function reset(): void {
        $this->overrides = [];
        $this->pendingSlot = null;
    }

    /**
     * Whether the persisted layout has finished loading from the database
     * yet. ShopEditorManager creates this object synchronously (so the shop
     * GUI never has to block/wait on it), then flips this to true once the
     * async database fetch actually lands - see ShopEditorManager::load().
     * The shop editor is safe to use before that point (it just behaves as
     * "no overrides yet"); this exists purely so a save triggered before the
     * load has landed doesn't overwrite a player's real saved layout with an
     * empty one.
     */
    public function hasLoaded(): bool {
        return $this->loaded;
    }

    /**
     * Populates $overrides from a database row, validating each entry so a
     * corrupted/hand-edited row can never crash the shop GUI - anything
     * that isn't exactly the expected shape is silently dropped instead of
     * being loaded. Marks the session as loaded either way (even an empty
     * result is a valid "no saved layout yet" state) so saves are allowed
     * to proceed afterward.
     *
     * @param array<int|string, mixed> $raw
     */
    public function loadOverrides(array $raw): void {
        $overrides = [];

        foreach($raw as $slot => $override) {
            if(!is_numeric($slot)) {
                continue;
            }
            $slot = (int) $slot;

            if($override === self::EMPTY_MARKER) {
                $overrides[$slot] = self::EMPTY_MARKER;
                continue;
            }

            if(
                is_array($override)
                && isset($override["category"], $override["id"])
                && is_string($override["category"])
                && is_string($override["id"])
            ) {
                $overrides[$slot] = ["category" => $override["category"], "id" => $override["id"]];
            }
        }

        $this->overrides = $overrides;
        $this->loaded = true;
    }

}
