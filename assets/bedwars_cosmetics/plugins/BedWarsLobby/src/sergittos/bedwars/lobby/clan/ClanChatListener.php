<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\clan;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\clan\ClanRole;
use sergittos\bedwars\lobby\BedWarsLobby;

/**
 * Redirects chat to the clan channel for any player who toggled it on (see
 * ClanMenu's "Clan Chat: ON/OFF" button). Registered at HIGH priority and
 * cancels the vanilla event so BedWarsCore's own CoreListener::onChat()
 * (NORMAL priority, no @handleCancelled) never runs for these messages -
 * that shared listener is left completely untouched, this only ever
 * intercepts messages from players who explicitly opted into clan chat.
 */
final class ClanChatListener implements Listener{

    public function __construct(private BedWarsLobby $plugin){}

    /**
     * @priority HIGH
     */
    public function onChat(PlayerChatEvent $event): void{
        $player = $event->getPlayer();
        $chat = $this->plugin->getClanChatManager();

        if(!$chat->isToggled($player->getName())){
            return;
        }

        $clanManager = BedWarsCore::getInstance()->getClanManager();
        $clan = $clanManager->getClanOf($player->getName());

        if($clan === null){
            // Stale toggle (e.g. they left their clan without turning chat
            // back off) - clear it and let this message go to public chat
            // like normal instead of silently eating it.
            $chat->setToggled($player->getName(), false);
            return;
        }

        $event->cancel();

        if($chat->isMuted($clan->getId(), $player->getName())){
            $player->sendMessage(TF::RED . "You're muted in clan chat for another " . $chat->getMuteRemaining($clan->getId(), $player->getName()) . "s.");
            return;
        }

        $role = $clan->getRole($player->getName()) ?? ClanRole::MEMBER;
        $rankTag = ClanRole::color($role) . "[" . ClanRole::displayName($role) . "]" . TF::RESET;

        $message = $clan->getColoredTag() . " " . $rankTag . " " . TF::GRAY . "[" . TF::RESET . $player->getName() . TF::GRAY . "] " . TF::WHITE . $event->getMessage();

        foreach(array_keys($clan->getMembers()) as $username){
            $memberSession = BedWarsCore::getInstance()->getSessionManager()->getByName($username);
            if($memberSession !== null && $memberSession->getPlayer()->isOnline()){
                $memberSession->getPlayer()->sendMessage($message);
            }
        }
    }

    public function onQuit(PlayerQuitEvent $event): void{
        $this->plugin->getClanChatManager()->clearPlayer($event->getPlayer()->getName());
    }
}
