<?php

namespace skywars\arena;

use pocketmine\level\Level;

/**
 * Class MapReset
 * @package skywars\arena
 */
class MapReset {

    /** @var Arena $plugin */
    public $plugin;

    /**
     * MapReset constructor.
     * @param Arena $plugin
     */
    public function __construct(Arena $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @param Level $level
     */
    public function saveMap(Level $level) {
        $level->save(true);

        $levelPath = $this->plugin->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $level->getFolderName();
        $zipPath = $this->plugin->plugin->getDataFolder() . "saves" . DIRECTORY_SEPARATOR . $level->getFolderName() . ".zip";

        $zip = new \ZipArchive();

        if(is_file($zipPath)) {
            unlink($zipPath);
        }

        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(realpath($levelPath)), \RecursiveIteratorIterator::LEAVES_ONLY);

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if($file->isFile()) {
                $filePath = $file->getPath() . DIRECTORY_SEPARATOR . $file->getBasename();
                $localPath = substr($filePath, strlen($this->plugin->plugin->getServer()->getDataPath() . "worlds"));
                $zip->addFile($filePath, $localPath);
            }
        }

        $zip->close();
    }

    /**
     * @param string $folderName
     * @return Level $level
     */
    public function loadMap(string $folderName): ?Level {
        if(!$this->plugin->plugin->getServer()->isLevelGenerated($folderName)) {
            return null;
        }

        if($this->plugin->plugin->getServer()->isLevelLoaded($folderName)) {
            $this->plugin->plugin->getServer()->getLevelByName($folderName)->unload(true);
        }

        $zipPath = $this->plugin->plugin->getDataFolder() . "saves" . DIRECTORY_SEPARATOR . $folderName . ".zip";

        $zipArchive = new \ZipArchive();
        $zipArchive->open($zipPath);
        $zipArchive->extractTo($this->plugin->plugin->getServer()->getDataPath() . "worlds");
        $zipArchive->close();

        $this->plugin->plugin->getServer()->loadLevel($folderName);
        return $this->plugin->plugin->getServer()->getLevelByName($folderName);
    }
}
