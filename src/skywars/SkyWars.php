<?php

/**
 * Copyright 2018 GamakCZ
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace skywars;

use pocketmine\command\Command;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\level\Level;
use pocketmine\plugin\PluginBase;
use skywars\arena\Arena;
use skywars\commands\SkyWarsCommand;
use skywars\math\Vector3;
use skywars\provider\YamlDataProvider;

/**
 * Class SkyWars
 * @package skywars
 */
class SkyWars extends PluginBase implements Listener {

    /** @var YamlDataProvider */
    public $dataProvider;

    /** @var Command[] $commands */
    public $commands = [];

    /** @var Arena[] $arenas */
    public $arenas = [];

    /** @var Arena[] $setters */
    public $setters = [];

    /** @var int[] $setupData */
    public $setupData = [];

    /** @var cfg */
    public $cfg;

    public function onEnable() {
        $this->getServer() -> getPluginManager()->registerEvents($this, $this);
        $this->dataProvider = new YamlDataProvider($this);
        $this->getServer()->getCommandMap()->register("SkyWars", $this->commands[] = new SkyWarsCommand($this));
    }

    public function onDisable() {
        $this->dataProvider->saveArenas();
    }

    /**
     * @param PlayerChatEvent $event
     */
    public function onChat(PlayerChatEvent $event) {
        $player = $event->getPlayer();

        if(!isset($this->setters[$player->getName()])) {
            return;
        }

        $event->setCancelled(\true);
        $args = explode(" ", $event->getMessage());

        /** @var Arena $arena */
        $arena = $this->setters[$player->getName()];

        switch ($args[0]) {
            case "help":
                $player->sendMessage("§aSkyWars Setup:\n".
                "§7help : Melihat semua command setup\n" .
                "§7slots : Memperbarui slot arena\n".
                "§7level : Memperbarui level arena\n".
                "§7spawn : Memperbarui slot arena\n".
                "§7joinsign : Memperbarui joinsign arena\n".
                "§7savelevel : Menyimpan level arena\n".
                "§7enable : Mengaktifkan arena");
                break;
            case "slots":
                if(!isset($args[1])) {
                    $player->sendMessage("§c§lERROR!§r§c Pakai: §7slots <int: slots>");
                    break;
                }
                $arena->data["slots"] = (int)$args[1];
                $player->sendMessage("§a§lSUKSES!§r§a Slots diperbarui ke $args[1]!");
                break;
            case "level":
                if(!isset($args[1])) {
                    $player->sendMessage("§c§lERROR!§r§c Pakai: §7level <levelName>");
                    break;
                }
                if(!$this->getServer()->isLevelGenerated($args[1])) {
                    $player->sendMessage("§c§lERROR!§r§c Level $args[1] tidak ditemukan!");
                    break;
                }
                $player->sendMessage("§a§lSUKSES!§r§a Arena level diperbarui ke $args[1]!");
                $arena->data["level"] = $args[1];
                break;
            case "spawn":
                if(!isset($args[1])) {
                    $player->sendMessage("§c§lERROR!§r§c Pakai: §7setspawn <int: spawn>");
                    break;
                }
                if(!is_numeric($args[1])) {
                    $player->sendMessage("§c§lERROR!§r§c Tipe nomor!");
                    break;
                }
                if((int)$args[1] > $arena->data["slots"]) {
                    $player->sendMessage("§c§lERROR!§r§c Disini hanya terdapat {$arena->data["slots"]} slot!");
                    break;
                }

                $arena->data["spawns"]["spawn-{$args[1]}"] = (new Vector3($player->getX(), $player->getY(), $player->getZ()))->__toString();
                $player->sendMessage("§a§lSUKSES!§r§a Slot $args[1] telah didaftarkan");
                break;
            case "joinsign":
                $player->sendMessage("§aHancurkan block untuk perbarui joinsign!");
                $this->setupData[$player->getName()] = 0;
                break;
            case "savelevel":
                if(!$arena->level instanceof Level) {
                    $player->sendMessage("§c> Error when saving level: world not found.");
                    if($arena->setup) {
                        $player->sendMessage("§6§lERROR!§r§6 Coba pakai savelevel setelah aktifkan arena.");
                    }
                    break;
                }
                $arena->mapReset->saveMap($arena->level);
                $player->sendMessage("§a§lSUKSES!§r§a Level disimpan!");
                break;
            case "enable":
                if(!$arena->setup) {
                    $player->sendMessage("§6§lERROR!§r§6 Arena sudah diaktifkan!");
                    break;
                }
                if(!$arena->enable()) {
                    $player->sendMessage("§c> Could not load arena, there are missing information!");
                    break;
                }
                $player->sendMessage("§a§lSUKSES!§r§a Arena diaktifkan!");
                break;
            case "done":
                $player->sendMessage("§a§lSUKSES!§r§a Kamu berhasil keluar dari setup mode!");
                unset($this->setters[$player->getName()]);
                if(isset($this->setupData[$player->getName()])) {
                    unset($this->setupData[$player->getName()]);
                }
                break;
            default:
                $player->sendMessage("§6Kamu sedang ada didalam setup mode.\n".
                    "§7- pakai §lhelp §r§7untuk melihat command setup\n"  .
                    "§7- atau §ldone §r§7untuk keluar dari setup mode");
                break;
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if(isset($this->setupData[$player->getName()])) {
            switch ($this->setupData[$player->getName()]) {
                case 0:
                    $this->setters[$player->getName()]->data["joinsign"] = [(new Vector3($block->getX(), $block->getY(), $block->getZ()))->__toString(), $block->getLevel()->getFolderName()];
                    $player->sendMessage("§a§lSUKSES!§r§a Join sign diperbarui!");
                    unset($this->setupData[$player->getName()]);
                    $event->setCancelled(\true);
                    break;
            }
        }
    }
}
