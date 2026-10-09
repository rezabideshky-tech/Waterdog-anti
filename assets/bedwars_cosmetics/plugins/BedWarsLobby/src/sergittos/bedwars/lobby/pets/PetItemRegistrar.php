<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\pets;

use customiesdevs\customies\entity\CustomiesEntityFactory;
use pocketmine\plugin\Plugin;
use sergittos\bedwars\lobby\pets\BabyDragon;
use sergittos\bedwars\lobby\pets\Cubee;
use sergittos\bedwars\lobby\pets\Endolotl;
use sergittos\bedwars\lobby\pets\PirateParrot;
use sergittos\bedwars\lobby\pets\Pirateship;
use sergittos\bedwars\lobby\pets\WitchCat;
use sergittos\bedwars\lobby\pets\Rock;

/**
 * Registers the 7 cosmetic pet entity types via Customies. Like hats, this
 * needs Customies to be installed - if it isn't, pets are simply disabled
 * (a clear warning is logged) and nothing else breaks.
 *
 * IMPORTANT: this only registers pet *behaviour*. The 3D model/texture for
 * each "hivepets:*" entity comes from a separate resource pack that was not
 * included in the pets.zip you supplied (only the PHP logic was). If that
 * resource pack isn't already installed on your servers, pets will spawn
 * but may render as invisible/default entities on the client until you add
 * it. See the delivery notes for details.
 */
final class PetItemRegistrar{

    private static bool $registered = false;

    public static function register(Plugin $plugin): void{
        if(self::$registered){
            return;
        }

        if(!class_exists(CustomiesEntityFactory::class)){
            $plugin->getLogger()->warning(
                "Customies plugin not found - cosmetic Pets will not be available. " .
                "Install Customies (softdepend) to enable them."
            );
            return;
        }

        try{
            CustomiesEntityFactory::getInstance()->registerEntity(BabyDragon::class, "hivepets:babydragon");
            CustomiesEntityFactory::getInstance()->registerEntity(Cubee::class, "hivepets:cubee");
            CustomiesEntityFactory::getInstance()->registerEntity(Endolotl::class, "hivepets:endolotl");
            CustomiesEntityFactory::getInstance()->registerEntity(PirateParrot::class, "hivepets:pirateparrot");
            CustomiesEntityFactory::getInstance()->registerEntity(Pirateship::class, "hivepets:pirateship");
            CustomiesEntityFactory::getInstance()->registerEntity(WitchCat::class, "hivepets:witchcat");
            CustomiesEntityFactory::getInstance()->registerEntity(Rock::class, "hivepets:rock");
            self::$registered = true;
        }catch(\Throwable $e){
            $plugin->getLogger()->error("Failed to register cosmetic Pets: " . $e->getMessage());
        }
    }

    public static function isRegistered(): bool{
        return self::$registered;
    }
}
