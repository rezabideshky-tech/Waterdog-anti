<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\stage;

use pocketmine\world\sound\ClickSound;
use sergittos\bedwars\game\stage\trait\JoinableTrait;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\utils\GameUtils;
use function count;
use function str_replace;

final class StartingStage extends Stage{
    use JoinableTrait{
        onQuit as onSessionQuit;
    }

    private int $countdown = 20;

    public function getCountdown(): int{
        return $this->countdown;
    }

    public function onQuit(Session $session): void{
        $this->onSessionQuit($session);

        if(!$this->isReadyToStart()){
            $this->game->setStage(new WaitingStage());
        }
    }

    public function tick(): void{
        if(!$this->isReadyToStart()){
            $this->game->setStage(new WaitingStage());
            return;
        }

        if($this->countdown <= 0){
            $this->game->setStage(new PlayingStage());
            return;
        }

        if($this->countdown <= 10){
            $this->game->broadcastMessage($this->getStartingMessage());
        }

        if($this->countdown <= 5){
            $this->broadcastCountdownTitle();
        }

        $this->game->updateScoreboards();
        $this->countdown--;
    }

    private function isReadyToStart(): bool{
        // Once the countdown has started, the only thing that should be
        // able to cancel it is the player count dropping below the
        // minimum required to play (e.g. someone leaving). Whether the
        // CURRENT player count divides evenly between teams must NOT be
        // re-checked every tick, otherwise a single player joining mid
        // countdown (making the total uneven) would incorrectly cancel
        // an already-valid countdown. PlayingStage::onStart() already
        // knows how to spread leftover players across teams (2v2v1 etc)
        // for doubles/triples/squads, so uneven counts are fully supported.
        return count($this->game->getPlayers()) >= $this->getMinPlayers();
    }

    private function getStartingMessage(): string{
        $message = "{YELLOW}The game starts in {time} {YELLOW}" . ($this->countdown === 1 ? "second" : "seconds") . "!";
        return str_replace("{time}", GameUtils::getColoredMessageNumber($this->countdown), $message);
    }

    private function broadcastCountdownTitle(): void{
        // Resource-pack glyphs for the big 5..1 title countdown ("§f" keeps
        // the glyph untinted).
        $numberWords = [
            5 => "§f\u{F02D}",
            4 => "§f\u{F02C}",
            3 => "§f\u{F02B}",
            2 => "§f\u{F02A}",
            1 => "§f\u{F029}"
        ];

        $countdownText = $numberWords[$this->countdown] ?? ("{YELLOW}" . $this->countdown);
        $this->game->broadcastTitle($countdownText);
        $this->game->broadcastSound(new ClickSound());
    }

    private function getMinPlayers(): int{
        $map = $this->game->getMap();
        $ppt = $map->getPlayersPerTeam();

        return match($ppt){
            1 => 2,
            2 => 4,
            3 => 6,
            4 => 8,
            default => (int) ($map->getMaxCapacity() / 2)
        };
    }
}