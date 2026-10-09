<?php

declare(strict_types=1);


namespace sergittos\bedwars\game\entity\shop;



use sergittos\bedwars\game\shop\item\category\ArmorCategory;
use sergittos\bedwars\game\shop\item\category\BlocksCategory;
use sergittos\bedwars\game\shop\item\category\MainCategory;
use sergittos\bedwars\game\shop\item\category\MeleeCategory;
use sergittos\bedwars\game\shop\item\category\MiscCategory;
use sergittos\bedwars\game\shop\item\category\PotionsCategory;
use sergittos\bedwars\game\shop\item\category\RangedCategory;
use sergittos\bedwars\game\shop\item\category\ToolsCategory;
use sergittos\bedwars\gui\ShopGui;
use sergittos\bedwars\session\Session;

class ItemShopVillager extends Villager {

    protected function getName(): string {
        return "ITEM SHOP";
    }

    protected function getForm(Session $session){
        $gui = new ShopGui(
            MainCategory::getSelf(),
            BlocksCategory::getSelf(),
            MeleeCategory::getSelf(),
            ArmorCategory::getSelf(),
            RangedCategory::getSelf(),
            PotionsCategory::getSelf(),
            ToolsCategory::getSelf(),
            MiscCategory::getSelf()
        );
        $gui->open($session->getPlayer());
    }

}