<?php

namespace SilverStripe\VendorPlugin\Methods;

use RuntimeException;

interface ExposeMethod
{

    /**
     * Exposes the directory with the given paths
     *
     * @param string $source Full filesystem path to file source
     * @param string $target Full filesystem path to the target directory
     * @throws RuntimeException If could not be exposed
     */
    public function exposeDirectory($source, $target);
}
