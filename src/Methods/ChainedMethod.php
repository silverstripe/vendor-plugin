<?php

namespace SilverStripe\VendorPlugin\Methods;

use RuntimeException;

/**
 * Attempt a list of methods until failure.
 * Note: Only treates RuntimeException as retryable, and will fail on all other error
 */
class ChainedMethod implements ExposeMethod
{
    /**
     * @var ExposeMethod[]
     */
    protected $failovers = [];

    /**
     * @param ExposeMethod[] ...$failovers List of failovers
     */
    public function __construct(...$failovers)
    {
        $this->failovers = $failovers;
    }

    /**
     * Exposes the directory with the given paths
     *
     * @param string $source Full filesystem path to file source
     * @param string $target Full filesystem path to the target directory
     * @throws RuntimeException If could not be exposed
     */
    public function exposeDirectory($source, $target)
    {
        $lastException = null;
        foreach ($this->failovers as $failover) {
            try {
                $failover->exposeDirectory($source, $target);
                return; // Return on first success
            } catch (RuntimeException $lastException) {
            }
        }
        if ($lastException) {
            throw $lastException;
        }
    }
}
