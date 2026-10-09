<?php

declare(strict_types=1);

namespace sergittos\bedwars\entity;

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\network\ServerInfo;
use sergittos\bedwars\utils\ColorUtils;
use sergittos\bedwars\utils\GameUtils;
use function array_sum;

/**
 * "Join entity" NPC که توی لابی وایمیسه (مثلاً برای Solo/Double/Triple/Squad).
 *
 * نکته‌ی مهم معماری: این NPC روی سرور Lobby زندگی می‌کنه، در حالی که خودِ
 * بازی‌ها (Game objects) روی سرورهای کاملاً جدا (Solo/Double/Triple/Squad)
 * اجرا می‌شن. پس اینجا امکان "پیدا کردن یک Game و مستقیم اضافه کردن پلیر"
 * وجود نداره (BedWarsCore::getGameManager() اصلاً وجود نداره — GameManager
 * مال پلاگین BedWarsGame است که روی Lobby نصب نیست). کاری که واقعاً باید
 * بشه: پلیر رو با NetworkManager به بهترین سرور آنلاین از همون نوع منتقل کنیم.
 */
class PlayBedwarsEntity extends Human {

    private int $players_per_team;

    public function __construct(Location $location, Skin $skin, CompoundTag $nbt) {
        $this->players_per_team = $nbt->getInt("players_per_team");
        parent::__construct($location, $skin);
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->updateNameTag();
        $this->setNameTagAlwaysVisible();
    }

    public function updateNameTag(): void {
        $amount = $this->getOnlinePlayersCount();
        $this->setNameTag(ColorUtils::translate(
            "{YELLOW}" . GameUtils::getMode($this->players_per_team) . "\n" .
            "{GRAY}" . $amount . " Online"
        ));
    }

    public function attack(EntityDamageEvent $source): void {
        if ($source instanceof EntityDamageByChildEntityEvent || !$source instanceof EntityDamageByEntityEvent) {
            return;
        }
        $damager = $source->getDamager();
        if (!$damager instanceof Player) {
            return;
        }
        if ($damager->hasPermission(DefaultPermissions::ROOT_OPERATOR) &&
            $damager->getInventory()->getItemInHand()->getTypeId() === BlockTypeIds::BEDROCK) {
            $this->kill();
            return;
        }
        $this->sendToBestServer($damager);
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool {
        $this->sendToBestServer($player);
        return true;
    }

    private function getGameType(): string {
        return match ($this->players_per_team) {
            1 => "solo",
            2 => "double",
            3 => "triple",
            4 => "squad",
            default => "solo",
        };
    }

    private function sendToBestServer(Player $player): void {
        $network = BedWarsCore::getInstance()->getNetworkManager();
        $servers = $network->getServersByType($this->getGameType());

        $best = null;
        $bestFree = -1;
        foreach ($servers as $info) {
            if (!$info->isOnline() || $info->isFull()) continue;
            $free = $info->maxPlayers - $info->online;
            if ($free > $bestFree) {
                $bestFree = $free;
                $best = $info;
            }
        }

        if ($best === null) {
            $player->sendMessage(ColorUtils::translate("{RED}No " . $this->getGameType() . " server is available right now, try again shortly."));
            return;
        }

        $network->transferToServer($player, $best->name);
    }

    public function saveNBT(): CompoundTag {
        return parent::saveNBT()->setInt("players_per_team", $this->players_per_team);
    }

    public function getPlayersPerTeam(): int {
        return $this->players_per_team;
    }

    private function getOnlinePlayersCount(): int {
        $servers = BedWarsCore::getInstance()->getNetworkManager()->getServersByType($this->getGameType());
        return array_sum(array_map(fn(ServerInfo $s) => $s->isOnline() ? $s->online : 0, $servers));
    }
}
