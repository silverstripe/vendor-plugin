<?php

namespace SilverStripe\VendorPlugin\Methods;

use Composer\Util\Filesystem;
use Composer\Util\Platform;
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
        // Remove destination directory to ensure it is clean
        $this->filesystem->removeDirectory($target);

        // Ensure parent dir exist
        $parent = dirname($target);
        $this->filesystem->ensureDirectoryExists($parent);

        // Ensure symlink exists
        if (!$this->relativeSymlink($source, $target)) {
            throw new RuntimeException("Could not create symlink at $target");
        }
    }

    /**
     * Create symlink
     *
     * @param string $source File source
     * @param string $target Place to put symlink
     * @return bool
     */
    protected function relativeSymlink($source, $target)
    {
        if (Platform::isWindows()) {
            $this->filesystem->junction($source, $target);
            return true;
        }
        return $this->filesystem->relativeSymlink($source, $target);
    }
}
