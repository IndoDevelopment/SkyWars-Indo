<?php

namespace skywars\arena;

use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\scheduler\Task;
use pocketmine\tile\Sign;
use skywars\math\Time;
use skywars\math\Vector3;

/**
 * Class ArenaScheduler
 * @package skywars\arena
 */
class ArenaScheduler extends Task {

    /** @var Arena $plugin */
    protected $plugin;

    /** @var int $startTime */
    public $startTime = 40;

    /** @var float|int $gameTime */
    public $gameTime = 20 * 60;

    /** @var int $restartTime */
    public $restartTime = 10;

    /** @var array $restartData */
    public $restartData = [];

    /**
     * ArenaScheduler constructor.
     * @param Arena $plugin
     */
    public function __construct(Arena $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick) {
        $this->reloadSign();

        if($this->plugin->setup) return;

        switch ($this->plugin->phase) {
            case Arena::PHASE_LOBBY:
                if(count($this->plugin->players) >= 2) {
                    $this->plugin->broadcastMessage("§aDimulai dalam " . Time::calculateTime($this->startTime) . " detik.", Arena::MSG_TIP);
                    $this->startTime--;
                    if($this->startTime == 0) {
                        $this->plugin->startGame();
                        foreach ($this->plugin->players as $player) {
                            $this->plugin->level->addSound(new AnvilUseSound($player->asVector3()));
                        }
                    }
                    else {
                        foreach ($this->plugin->players as $player) {
                            $this->plugin->level->addSound(new ClickSound($player->asVector3()));
                        }
                    }
                }
                else {
                    $this->plugin->broadcastMessage("§cMembutuhkan player untuk memulai game!", Arena::MSG_TIP);
                    $this->startTime = 40;
                }
                break;
            case Arena::PHASE_GAME:
                $this->plugin->broadcastMessage("§aTerdapat " . count($this->plugin->players) . " player, sisa waktu: " . Time::calculateTime($this->gameTime) . "", Arena::MSG_TIP);
                switch ($this->gameTime) {
                    case 15 * 60:
                        $this->plugin->broadcastMessage("§aSemua chest akan diisi ulang dalam 5 min.");
                        break;
                    case 11 * 60:
                        $this->plugin->broadcastMessage("§aSemua chest akan diisi ulang dalam 1 min.");
                        break;
                    case 10 * 60:
                        $this->plugin->broadcastMessage("§aSemua chest telah diisi ulang.");
                        break;
                }
                if($this->plugin->checkEnd()) $this->plugin->startRestart();
                $this->gameTime--;
                break;
            case Arena::PHASE_RESTART:
                $this->plugin->broadcastMessage("§aRestart dalam {$this->restartTime} detik.", Arena::MSG_TIP);
                $this->restartTime--;

                switch ($this->restartTime) {
                    case 0:

                        foreach ($this->plugin->players as $player) {
                            $player->teleport($this->plugin->plugin->getServer()->getDefaultLevel()->getSpawnLocation());

                            $player->getInventory()->clearAll();
                            $player->getArmorInventory()->clearAll();
                            $player->getCursorInventory()->clearAll();

                            $player->setFood(20);
                            $player->setHealth(20);

                            $player->setGamemode($this->plugin->plugin->getServer()->getDefaultGamemode());
                        }
                        $this->plugin->loadArena(true);
                        $this->reloadTimer();
                        break;
                }
                break;
        }
    }

    public function reloadSign() {
        if(!is_array($this->plugin->data["joinsign"]) || empty($this->plugin->data["joinsign"])) return;

        $signPos = Position::fromObject(Vector3::fromString($this->plugin->data["joinsign"][0]), $this->plugin->plugin->getServer()->getLevelByName($this->plugin->data["joinsign"][1]));

        if(!$signPos->getLevel() instanceof Level) return;

        $signText = [
            "§e§lSkyWars",
            "§9[ §b? / ? §9]",
            "§6Setup",
            "§6Tunggu sebentar..."
        ];

        if($signPos->getLevel()->getTile($signPos) === null) return;

        if($this->plugin->setup) {
            /** @var Sign $sign */
            $sign = $signPos->getLevel()->getTile($signPos);
            $sign->setText($signText[0], $signText[1], $signText[2], $signText[3]);
            return;
        }

        $signText[1] = "§9[ §b" . count($this->plugin->players) . " / " . $this->plugin->data["slots"] . " §9]";

        switch ($this->plugin->phase) {
            case Arena::PHASE_LOBBY:
                if(count($this->plugin->players) >= $this->plugin->data["slots"]) {
                    $signText[2] = "§6Penuh";
                    $signText[3] = "§8Map: §7{$this->plugin->level->getFolderName()}";
                }
                else {
                    $signText[2] = "§aMasuk";
                    $signText[3] = "§8Map: §7{$this->plugin->level->getFolderName()}";
                }
                break;
            case Arena::PHASE_GAME:
                $signText[2] = "§5Bermain";
                $signText[3] = "§8Map: §7{$this->plugin->level->getFolderName()}";
                break;
            case Arena::PHASE_RESTART:
                $signText[2] = "§cRestarting...";
                $signText[3] = "§8Map: §7{$this->plugin->level->getFolderName()}";
                break;
        }

        /** @var Sign $sign */
        $sign = $signPos->getLevel()->getTile($signPos);
        $sign->setText($signText[0], $signText[1], $signText[2], $signText[3]);
    }

    public function reloadTimer() {
        $this->startTime = 30;
        $this->gameTime = 20 * 60;
        $this->restartTime = 10;
    }
}
