<?php
declare(strict_types=1);

namespace sergittos\bedwars\lobby\halloween;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;

/**
 * HalloweenPedestal — پایه‌ی دکوراتیو هالووینی که دور ستون متن هولوگرام لیدربورد می‌شینه.
 *
 * هندسه‌ی مدل (arvan_leaderboard.geo.json) با 1 unit = 1/16 بلاک ساخته شده و این‌طوری چیده شده:
 *      مدل y = 0    .. 24        پایه (پشت متن، زیر پایین‌ترین خط)
 *      مدل y = 24   .. 86        ناحیه‌ی خالی = دقیقاً جایی که ۱۳ خط متن هولوگرام
 *                                (title + "Live Rankings" + خط خالی + ۱۰ رنک با lineSpacing = 0.3)
 *                                از pos.y تا pos.y + 3.6 رندر می‌شه
 *      مدل y = 88   .. 115       تاج (حلقه‌ی رونیک، فانوس کدویی، خفاش در گردش)
 * پس انتیتی باید روی pos.y - Y_OFFSET اسپاون بشه تا متن دقیقاً وسط قاب بیفته.
 */
abstract class HalloweenPedestal extends Entity {

    public const Y_OFFSET = 1.5;   // 24 unit پایه ÷ 16 — فاصله‌ی پایه از نقطه‌ی هولوگرام

    abstract protected function statKey() : string;

    public function getStatKey() : string{
        return $this->statKey();
    }

    /** هیت‌باکس تقریباً صفر: هدف کلیک/برخورد نیست، فقط دکور است. */
    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(0.1, 0.1);
    }

    protected function getInitialDragMultiplier() : float{
        return 0.0;
    }

    protected function getInitialGravityMultiplier() : float{
        return 0.0;
    }

    protected function initEntity(CompoundTag $nbt) : void{
        parent::initEntity($nbt);
        $this->setNameTag("");
        $this->setNameTagAlwaysVisible(false);
        $this->setNoClientPredictions();
        // گرانش از طریق getInitialGravityMultiplier() = 0 خاموش شده (همون الگوی LobbyNpc).
    }

    /** دکور موقتی است — با سیو شدن چانک نباید توی دنیا تکثیر بشه. */
    public function canSaveWithChunk() : bool{
        return false;
    }

    public function attack(EntityDamageEvent $source) : void{
        $source->cancel();
    }
}
