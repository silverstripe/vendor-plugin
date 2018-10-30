<?php

namespace SilverStripe\VendorPlugin\Tests\Methods;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Composer\Package\Locker;
use Composer\Repository\RepositoryManager;
use PHPUnit\Framework\TestCase;
use SilverStripe\VendorPlugin\Library;

class LibraryTest extends TestCase
{
    /**
     * @var Filesystem
     */
    protected $filesystem = null;

    /**
     * @var string app base path
     */
    protected $root = null;

    protected $cwd;

    protected function setUp()
    {
        parent::setUp();
        $this->cwd = getcwd();
    }

    protected function tearDown()
    {
        parent::tearDown();
        chdir($this->cwd);
    }

    /**
     * @dataProvider resourcesDirProvider
     */
    public function testResourcesDir($expected, $projectPath, $preloadLock)
    {
        $lib = $this->getLib($projectPath, $preloadLock);
        $this->assertEquals($expected, $lib->getResourcesDir());
    }

    public function resourcesDirProvider()
    {
        return [
            ['resources', 'ss43', false],
            ['_resources', 'ss44', false],
            ['customised-resources-dir', 'ss44WithCustomResourcesDir', false],
            ['resources', 'ss43', true],
            ['_resources', 'ss44', true],
            ['customised-resources-dir', 'ss44WithCustomResourcesDir', true],
        ];
    }

    /**
     * Get a library for the provided project
     * @param string $project name of the project folder in the fixtures directory
     * @param bool $preloadLock Whatever to preload the lock file or let Library do that for us
     * @return Library
     */
    private function getLib($project, $preloadLock)
    {
        $path = __DIR__ . '/fixtures/projects/' . $project;
        chdir($path);
        $factory = new Factory();


        $io = new NullIO();
        $composer = $factory->createComposer($io, null, false, $path);
        $preloadLock && $composer->setLocker(new Locker(
            $io,
            new JsonFile($path . '/composer.lock', null, $io),
            $composer->getRepositoryManager(),
            $composer->getInstallationManager(),
            file_get_contents($path . '/composer.lock')
        ));

        $lib = new Library(
            $path,
            'vendor/silverstripe/skynet',
            null,
            $composer,
            $io
        );

        return $lib;
    }
}
