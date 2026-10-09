<?php

declare(strict_types=1);

namespace sergittos\bedwars\item;

use pocketmine\utils\CloningRegistryTrait;
use sergittos\bedwars\game\generator\GeneratorType;
use sergittos\bedwars\game\shop\Shop;
use sergittos\bedwars\item\game\LeaveGameItem;
use sergittos\bedwars\item\game\TeamSelectorItem;
use sergittos\bedwars\item\game\TrackerShopItem;
use sergittos\bedwars\item\game\ViewGamesItem;
use sergittos\bedwars\item\game\PlayerListItem;
use sergittos\bedwars\item\game\ModLeaveItem;
use sergittos\bedwars\item\setup\AddGeneratorItem;
use sergittos\bedwars\item\setup\AddVillagerItem;
use sergittos\bedwars\item\setup\CancelItem;
use sergittos\bedwars\item\setup\ClaimingWandItem;
use sergittos\bedwars\item\setup\ConfigurationItem;
use sergittos\bedwars\item\setup\CreateMapItem;
use sergittos\bedwars\item\setup\ExitSetupItem;
use sergittos\bedwars\item\setup\SaveEditsItem;
use sergittos\bedwars\item\setup\SetBedPositionItem;
use sergittos\bedwars\item\setup\SetTeamGeneratorItem;
use sergittos\bedwars\item\setup\RemoveVillagerItem;
use sergittos\bedwars\item\setup\ClearVillagersItem;
use sergittos\bedwars\item\spectator\PlayAgainItem;
use sergittos\bedwars\item\spectator\ReportItem;
use sergittos\bedwars\item\spectator\ReturnToLobbyItem;
use sergittos\bedwars\item\spectator\SpectatorSettingsItem;
use sergittos\bedwars\item\spectator\TeleporterItem;

final class BedwarsItems{
    use CloningRegistryTrait;

    protected static function setup() : void{
        self::register("leave_game", new LeaveGameItem());
        self::register("team_selector", new TeamSelectorItem());

        self::register("play_again", new PlayAgainItem());
        self::register("return_to_lobby", new ReturnToLobbyItem());
        self::register("spectator_settings", new SpectatorSettingsItem());
        self::register("teleporter", new TeleporterItem());
        self::register("report_player", new ReportItem());
        self::register("tracker_shop", new TrackerShopItem());

        self::register("diamond_generator", new AddGeneratorItem(GeneratorType::DIAMOND));
        self::register("emerald_generator", new AddGeneratorItem(GeneratorType::EMERALD));
        self::register("item_villager", new AddVillagerItem(Shop::ITEM));
        self::register("upgrades_villager", new AddVillagerItem(Shop::UPGRADES));

        self::register("remove_villager", new RemoveVillagerItem());
        self::register("remove_all_villagers", new ClearVillagersItem());
        self::register("clear_villagers", new ClearVillagersItem());

        self::register("configuration", new ConfigurationItem());
        self::register("create_map", new CreateMapItem());
        self::register("save_edits", new SaveEditsItem());
        self::register("exit_setup", new ExitSetupItem());
        self::register("team_generator", new SetTeamGeneratorItem());
        self::register("bed_position", new SetBedPositionItem());
        self::register("claiming_wand", new ClaimingWandItem());
        self::register("cancel", new CancelItem());
        self::register("waiting_spawn", new \sergittos\bedwars\item\setup\SetWaitingSpawnItem());

        self::register("view_games", new ViewGamesItem());
        self::register("player_list", new PlayerListItem());
        self::register("mod_leave", new ModLeaveItem());
    }

    public static function getAll() : array{
        return self::_registryGetAll();
    }

    public static function get(string $name) : object{
        return self::_registryFromString($name);
    }

    private static function register(string $name, BedwarsItem $item) : void{
        self::_registryRegister($name, $item);
    }
}