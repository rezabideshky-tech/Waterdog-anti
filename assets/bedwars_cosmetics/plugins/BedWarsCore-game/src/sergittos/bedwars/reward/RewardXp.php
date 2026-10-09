<?php

declare(strict_types=1);

namespace sergittos\bedwars\reward;

use sergittos\bedwars\session\Session;

/**
 * Grants XP the same way Session::addXp() does (including the leveling
 * loop) but without Session::addXp()'s own chat message - reward features
 * report their own grants through NotificationService instead, so nothing
 * gets printed to chat twice.
 */
final class RewardXp{

    private function __construct(){}

    public static function grantSilently(Session $session, int $amount): void{
        if($amount <= 0){
            return;
        }

        $xp = $session->getXp() + $amount;
        $level = $session->getLevel();

        while($xp >= $session->getRequiredXPForNextLevel()){
            // getRequiredXPForNextLevel() depends on the *current* level,
            // so each level step must be committed before asking again.
            $need = $session->getRequiredXPForNextLevel();
            $xp -= $need;
            $level++;
            $session->setLevel($level);
        }

        $session->setXp($xp);
        $session->syncXpBar();
    }
}
