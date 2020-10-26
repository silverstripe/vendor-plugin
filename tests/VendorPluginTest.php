<?php

namespace SilverStripe\VendorPlugin\Tests;

use PHPUnit\Framework\TestCase;
use SilverStripe\VendorPlugin\VendorPlugin;

class VendorPluginTest extends TestCase
{
    /**
     * The simplest possible test, check that the plugin can be instantiated
     */
    public function testInstantiation(): void
    {
        new VendorPlugin();
    }
}
