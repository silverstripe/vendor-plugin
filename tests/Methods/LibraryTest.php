<?php

namespace SilverStripe\VendorPlugin\Tests\Methods;

use PHPUnit\Framework\TestCase;
use SilverStripe\VendorPlugin\Library;

class LibraryTest extends TestCase
{
    /**
     * @dataProvider resourcesDirProvider
     */
    public function testResourcesDir($expected, $projectPath)
    {
        $path = __DIR__ . '/../fixtures/projects/' . $projectPath;
        $lib = new Library($path, 'vendor/silverstripe/skynet');
        $this->assertEquals($expected, $lib->getResourcesDir());
    }

    public function resourcesDirProvider()
    {
        return [
            ['_resources', 'ss43'],
            ['_resources', 'ss44'],
            ['customised-resources-dir', 'ss44WithCustomResourcesDir']
        ];
    }

    public function testInvalidResourceDir()
    {
        $this->expectException(\LogicException::class);
        $path = __DIR__ . '/../fixtures/projects/ss44InvalidResourcesDir';
        $lib = new Library($path, 'vendor/silverstripe/skynet');
        $lib->getResourcesDir();
    }
}
