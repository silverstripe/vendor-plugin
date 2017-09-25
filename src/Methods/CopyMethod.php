<?php

namespace SilverStripe\VendorPlugin\Methods;

use Composer\Util\Filesystem;
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
        if (!$this->filesystem->copy($source, $target)) {
            throw new RuntimeException("Could not write to directory $target");
        }
    }
}
