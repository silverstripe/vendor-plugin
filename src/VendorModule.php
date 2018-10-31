<?php

namespace SilverStripe\VendorPlugin;

/**
 * Represents a module in the vendor folder
 *
 * @deprecated 1.3..2.0 Use Library instead
 */
class VendorModule extends Library
{
    /**
     * Default source folder
     */
    const DEFAULT_SOURCE = 'vendor';

    /**
     * Default replacement folder for 'vendor'
     */
    const DEFAULT_TARGET = 'resources';

    /**
     * Build a vendor module library
     *
     * @param string $basePath Project root folder
     * @param string $name Composer name of this library
     */
    public function __construct($basePath, $name)
    {
        $path = Util::joinPaths(
            $basePath,
            static::DEFAULT_SOURCE,
            explode('/', $name)
        );
        parent::__construct($basePath, $path, $name);
    }

    /**
     * Get full path to the root install for this project
     *
     * @deprecated 1.3..2.0 use getPath() instead
     * @param string $base Rewrite root (or 'vendor' for actual module path)
     * @return string Path for this module
     */
    public function getModulePath($base = self::DEFAULT_SOURCE)
    {
        if ($base === self::DEFAULT_TARGET) {
            return $this->getPublicPath();
        } else {
            return $this->getPath();
        }
    }
}
