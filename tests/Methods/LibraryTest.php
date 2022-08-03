<?php

namespace SilverStripe\VendorPlugin\Tests\Methods;

use PHPUnit\Framework\TestCase;
use SilverStripe\VendorPlugin\Library;

class LibraryTest extends TestCase
{
    /**
     * @dataProvider resourcesDirProvider
     */
    public function testGetBasePublicPath(string $projectPath, bool $hasDir): void
    {
        $path = __DIR__ . '/../fixtures/projects/' . $projectPath;
        $lib = new Library($path, 'vendor/silverstripe/skynet');
        if ($hasDir) {
            $expected = realpath($path) . '/public/_resources';
        } else {
            $expected = realpath($path) . '/_resources';
        }
        $this->assertEquals($expected, $lib->getBasePublicPath());
    }

    public function resourcesDirProvider()
    {
        return [
            ['ss43', true],
            ['ss44', true],
            ['ss44WithCustomResourcesDir', true],
            ['noPublicDir', false],
        ];
    }
}
