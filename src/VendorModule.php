<?php

namespace SilverStripe\VendorPlugin;

use Composer\Json\JsonFile;
use LogicException;
use SilverStripe\VendorPlugin\Methods\ExposeMethod;

/**
 * Represents a module in the vendor folder
 */
class VendorModule
{
    /**
     * Default replacement folder for 'vendor'
     */
    const DEFAULT_TARGET = 'resources';

    /**
     * Default source folder
     */
    const DEFAULT_SOURCE = 'vendor';

    /**
     * Project root
     *
     * @var string
     */
    protected $basePath = null;

    /**
     * Module name
     *
     * @var string
     */
    protected $name = null;

    /**
     * Build a vendor module library
     *
     * @param string $basePath Project root folder
     * @param string $name Composer name of this library
     */
    public function __construct($basePath, $name)
    {
        $this->basePath = $basePath;
        $this->name = $name;
    }

    /**
     * Get module name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get full path to the root install for this project
     *
     * @param string $base Rewrite root (or 'vendor' for actual module path)
     * @return string Path for this module
     */
    public function getModulePath($base = self::DEFAULT_SOURCE)
    {
        return Util::joinPaths(
            $this->basePath,
            $base,
            explode('/', $this->name)
        );
    }

    /**
     * Get json content for this module from composer.json
     *
     * @return array
     */
    protected function getJson()
    {
        $composer = Util::joinPaths($this->getModulePath(), 'composer.json');
        $file = new JsonFile($composer);
        return $file->read();
    }

    /**
     * Expose all web accessible paths for this module
     *
     * @param ExposeMethod $method
     * @param string $target Replacement target for 'vendor' prefix to rewrite to. Defaults to 'resources'
     */
    public function exposePaths(ExposeMethod $method, $target = self::DEFAULT_TARGET)
    {
        $folders = $this->getExposedFolders();
        $sourcePath = $this->getModulePath(self::DEFAULT_SOURCE);
        $targetPath = $this->getModulePath($target);
        foreach ($folders as $folder) {
            // Get paths for this folder and delegate to expose method
            $folderSourcePath = Util::joinPaths($sourcePath, $folder);
            $folderTargetPath = Util::joinPaths($targetPath, $folder);
            $method->exposeDirectory($folderSourcePath, $folderTargetPath);
        }
    }

    /**
     * Get name of all folders to expose (relative to module root)
     *
     * @return array
     */
    public function getExposedFolders()
    {
        $data = $this->getJson();

        // Only expose if correct type
        if (empty($data['type']) || $data['type'] !== VendorPlugin::MODULE_TYPE) {
            return [];
        }

        // Get all dirs to expose
        if (empty($data['extra']['expose'])) {
            return [];
        }
        $expose = $data['extra']['expose'];

        // Validate all paths are safe
        foreach ($expose as $exposeFolder) {
            if (!$this->validateFolder($exposeFolder)) {
                throw new LogicException("Invalid module folder " . $exposeFolder);
            }
        }
        return $expose;
    }

    /**
     * Validate the given folder is allowed
     *
     * @param string $exposeFolder Relative folder name to check
     * @return bool
     */
    protected function validateFolder($exposeFolder)
    {
        if (strstr($exposeFolder, '.')) {
            return false;
        }
        if (strpos($exposeFolder, '/') === 0) {
            return false;
        }
        if (strpos($exposeFolder, '\\') === 0) {
            return false;
        }
        return true;
    }
}
