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
        // Remove trailing slash
        $target = rtrim($target ?? '', DIRECTORY_SEPARATOR);

        // Remove destination directory to ensure it is clean
        $this->filesystem->removeDirectory($target);

        // Ensure parent dir exist
        $parent = dirname($target ?? '');
        $this->filesystem->ensureDirectoryExists($parent);

        // Ensure symlink exists
        $this->createLink($source, $target);
    }

    /**
     * Create symlink
     *
     * @param string $source File source
     * @param string $target Place to put symlink
     */
    protected function createLink($source, $target)
    {
        $success = $this->filesystem->relativeSymlink($source, $target);
        if (!$success) {
            throw new RuntimeException("Could not create symlink at $target");
        }
    }
}
