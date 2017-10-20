<?php

namespace SilverStripe\VendorPlugin\Tests\Methods;

use Composer\Util\Filesystem;
use Composer\Util\Platform;
use PHPUnit\Framework\TestCase;
use SilverStripe\VendorPlugin\Methods\CopyMethod;
use SilverStripe\VendorPlugin\Methods\SymlinkMethod;
use SilverStripe\VendorPlugin\Util;

class SymlinkMethodTest extends TestCase
{
    /**
     * @var Filesystem
     */
    protected $filesystem = null;

    /**
     * @var string app base path
     */
    protected $root = null;

    protected function setUp()
    {
        parent::setUp();

        // Get temp dir
        $this->root = Util::joinPaths(
            sys_get_temp_dir(),
            'SymlinkMethodTest',
            substr(sha1(uniqid()), 0, 10)
        );

        // Setup filesystem
        $this->filesystem = new Filesystem();
        $this->filesystem->ensureDirectoryExists($this->root);
    }

    protected function tearDown()
    {
        $this->filesystem->remove($this->root);
        parent::tearDown();
    }

    public function testSymlink()
    {
        $method = new SymlinkMethod();
        $target = Util::joinPaths($this->root, 'resources', 'client');
        $method->exposeDirectory(
            realpath(__DIR__.'/../fixtures/source/client'),
            $target
        );

        // Ensure file exists
        $this->assertFileExists(Util::joinPaths($this->root, 'resources', 'client', 'subfolder', 'somefile.txt'));

        // Folder is NOT a real folder
        if (Platform::isWindows()) {
            $this->assertTrue($this->filesystem->isJunction($target));
        } else {
            $this->assertTrue($this->filesystem->isSymlinkedDirectory($target));
        }

        // Parent folder is a real folder
        $this->assertDirectoryExists(dirname($target));
    }

    public function testRecoversFromCopy()
    {
        $method = new CopyMethod();
        $target = Util::joinPaths($this->root, 'resources', 'client');
        $method->exposeDirectory(
            realpath(__DIR__.'/../fixtures/source/client'),
            $target
        );

        // Repeat prior test
        $this->testSymlink();
    }
}
