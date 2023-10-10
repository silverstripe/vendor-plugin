<?php

namespace SilverStripe\VendorPlugin\Methods;

use Composer\Util\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Expose the vendor module resources via a file copy
 */
class CopyMethod implements ExposeMethod
{
    const NAME = 'copy';

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

        // Copy to destination
        if (!$this->filesystem->copy($source, $target)) {
            throw new RuntimeException("Could not write to directory $target");
        }
    }

    /**
     * Copies a file or directory from $source to $target.
     *
     *
     * @param string $source
     * @param string $target
     * @deprecated 5.2 Use Filesystem::copy instead
     * @return bool
     */
    public function copy($source, $target)
    {
        if (!is_dir($source ?? '')) {
            return copy($source ?? '', $target ?? '');
        }
        $it = new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS);
        /** @var RecursiveDirectoryIterator $ri */
        $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);
        $this->filesystem->ensureDirectoryExists($target);
        $result = true;
        foreach ($ri as $file) {
            $targetPath = $target . DIRECTORY_SEPARATOR . $ri->getSubPathName();
            if ($file->isDir()) {
                $this->filesystem->ensureDirectoryExists($targetPath);
            } else {
                $result = $result && copy($file->getPathname() ?? '', $targetPath ?? '');
            }
        }
        return $result;
    }
}
