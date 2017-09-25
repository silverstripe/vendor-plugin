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

        // Delete target if exists
        if (file_exists($target)) {
            $this->filesystem->removeDirectory($target);
        }
        // Ensure symlink exists
        if (!$this->filesystem->relativeSymlink($source, $target)) {
            throw new RuntimeException("Could not create symlink at $target");
        }
    }
}
