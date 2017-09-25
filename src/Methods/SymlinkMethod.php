<?php

namespace SilverStripe\VendorPlugin\Methods;

use Composer\Util\Filesystem;
use RuntimeException;

/**
 * Expose the vendor module resources via a symlink
 */
class SymlinkMethod implements ExposeMethod
{
    const NAME = 'symlink';

    /**
     * @var Filesystem
     */
    protected $filesystem = null;

    public function __construct(Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?: new Filesystem();
    }

    public function exposeDirectory($source, $target)
    {
        $parent = dirname($target);
        $this->filesystem->ensureDirectoryExists($parent);
        if (!$this->filesystem->relativeSymlink($target, $source)) {
            throw new RuntimeException("Could not create symlink at $target");
        }
    }
}
