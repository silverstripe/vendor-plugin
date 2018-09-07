<?php

namespace SilverStripe\VendorPlugin\Tests\Methods;

use Composer\Util\Filesystem;
use PHPUnit\Framework\TestCase;
use SilverStripe\VendorPlugin\Methods\CopyMethod;
use SilverStripe\VendorPlugin\Methods\SymlinkMethod;
use SilverStripe\VendorPlugin\Util;

class CopyMethodTest extends TestCase
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
            'CopyMethodTest',
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

    public function testCopy()
    {
        $method = new CopyMethod();
        $target = Util::joinPaths($this->root, '_resources', 'client');
        $method->exposeDirectory(
            realpath(__DIR__.'/../fixtures/source/client'),
            $target
        );

        // Ensure file exists
        $this->assertFileExists(Util::joinPaths($this->root, '_resources', 'client', 'subfolder', 'somefile.txt'));

        // Folder is a real folder and not a symlink
        $this->assertFalse($this->filesystem->isSymlinkedDirectory($target));
        $this->assertDirectoryExists($target);


        // Parent folder is a real folder
        $this->assertFalse($this->filesystem->isSymlinkedDirectory(dirname($target)));
        $this->assertDirectoryExists(dirname($target));
    }

    public function testRecoversFromSymlink()
    {
        $method = new SymlinkMethod();
        $target = Util::joinPaths($this->root, '_resources', 'client');
        $method->exposeDirectory(
            realpath(__DIR__.'/../fixtures/source/client'),
            $target
        );

        $this->testCopy();
    }
}
