<?php

namespace SilverStripe\VendorPlugin;

use Composer\Json\JsonFile;
use LogicException;
use SilverStripe\VendorPlugin\Methods\ExposeMethod;

/**
 * Represents a library being installed
 */
class Library
{
    const TRIM_CHARS = '/\\';

    /**
     * Hard-coded 'public' web-root folder
     */
    const PUBLIC_PATH = 'public';

    /**
     * Subfolder to map within public webroot
     */
    const RESOURCES_PATH = 'resources';

    /**
     * Project root
     *
     * @var string
     */
    protected $basePath = null;

    /**
     * Install path of this library
     *
     * @var string
     */
    protected $path = null;

    /**
     * Build a vendor module library
     *
     * @param string $basePath Project root folder
     * @param string $libraryPath Path to this library
     * @param string $name Composer name of this library
     */
    public function __construct($basePath, $libraryPath, $name = null)
    {
        $this->basePath = realpath($basePath);
        $this->path = realpath($libraryPath);
        $this->name = $name;
    }

    /**
     * Module name
     *
     * @var string
     */
    protected $name = null;

    /**
     * Get module name
     *
     * @return string
     */
    public function getName()
    {
        if ($this->name) {
            return $this->name;
        }
        // Get from composer
        $json = $this->getJson();
        if (isset($json['name'])) {
            $this->name = $json['name'];
        }
        return $this->name;
    }

    /**
     * Get type of library
     *
     * @return string
     */
    public function getType()
    {
        // Get from composer
        $json = $this->getJson();
        if (isset($json['type'])) {
            return $json['type'];
        }
        return 'module';
    }

    /**
     * Get path to base project for this module
     *
     * @return string Path with no trailing slash E.g. /var/www/
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * Get base path to expose all libraries to
     *
     * @return string Path with no trailing slash E.g. /var/www/public/resources
     */
    public function getBasePublicPath()
    {
        $projectPath = $this->getBasePath();
        $publicPath = $this->publicPathExists()
            ? Util::joinPaths($projectPath, self::PUBLIC_PATH, self::RESOURCES_PATH)
            : Util::joinPaths($projectPath, self::RESOURCES_PATH);
        return $publicPath;
    }

    /**
     * Get path for this module
     *
     * @return string Path with no trailing slash E.g. /var/www/vendor/silverstripe/module
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get path relative to base dir.
     * If module path is base this will be empty string
     *
     * @return string Path with trimmed slashes. E.g. vendor/silverstripe/module.
     * This will be empty for the base project.
     */
    public function getRelativePath()
    {
        return trim(substr($this->path, strlen($this->basePath)), self::TRIM_CHARS);
    }

    /**
     * Get base path to map resources for this module
     *
     * @return string Path with trimmed slashes. E.g. /var/www/public/resources/vendor/silverstripe/module
     */
    public function getPublicPath()
    {
        $relativePath = $this->getRelativePath();

        // 4.0 compatibility: If there is no public folder, and this is a vendor path,
        // remove the leading `vendor` from the destination
        if (!$this->publicPathExists() && $this->installedIntoVendor()) {
            $relativePath = substr($relativePath, strlen('vendor/'));
        }

        return Util::joinPaths($this->getBasePublicPath(), $relativePath);
    }

    /**
     * Cache of composer.json content
     *
     * @var array
     */
    protected $json = [];

    /**
     * Get json content for this module from composer.json
     *
     * @return array
     */
    protected function getJson()
    {
        if ($this->json) {
            return $this->json;
        }
        $composer = Util::joinPaths($this->getPath(), 'composer.json');
        $file = new JsonFile($composer);
        $this->json = $file->read();
        return $this->json;
    }

    /**
     * Determine if this module should be exposed.
     * Note: If not using public folders, only vendor modules need to be exposed
     *
     * @return bool
     */
    public function requiresExpose()
    {
        // Don't expose if no folders configured
        if (!$this->getExposedFolders()) {
            return false;
        }

        // Expose if either public root exists, or vendor module
        return $this->publicPathExists() || $this->installedIntoVendor();
    }

    /**
     * Expose all web accessible paths for this module
     *
     * @param ExposeMethod $method
     */
    public function exposePaths(ExposeMethod $method)
    {
        // No-op if exposure not necessary for this configuration
        if (!$this->requiresExpose()) {
            return;
        }
        $folders = $this->getExposedFolders();
        $sourcePath = $this->getPath();
        $targetPath = $this->getPublicPath();
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

    /**
     * Determin eif the public folder exists
     *
     * @return bool
     */
    public function publicPathExists()
    {
        return is_dir(Util::joinPaths($this->getBasePath(), self::PUBLIC_PATH));
    }

    /**
     * Check if this module is installed in vendor
     *
     * @return bool
     */
    protected function installedIntoVendor()
    {
        return preg_match('#^vendor[/\\\\]#', $this->getRelativePath());
    }
}
