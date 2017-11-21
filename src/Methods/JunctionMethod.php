<?php

namespace SilverStripe\VendorPlugin\Methods;

use Composer\Util\Platform;
use RuntimeException;

/**
 * Expose the vendor module resources via a symlink
 */
class JunctionMethod extends SymlinkMethod
{
    const NAME = 'junction';

    protected function createLink($source, $target)
    {
        if (!Platform::isWindows()) {
            throw new RuntimeException("Cannot create junction on non-windows environment");
        }
        $this->filesystem->junction($source, $target);

        // Check if this command succeeded
        $success = $this->filesystem->isJunction($target);
        if (!$success) {
            throw new RuntimeException("Could not create symlink at $target");
        }
    }
}
