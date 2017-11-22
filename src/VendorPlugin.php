<?php

namespace SilverStripe\VendorPlugin;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Util\Filesystem;
use SilverStripe\VendorPlugin\Console\VendorCommandProvider;

/**
 * Provides public webroot rewrite functionality for vendor modules
 */
class VendorPlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    /**
     * Module type to match
     */
    const MODULE_TYPE = 'silverstripe-vendormodule';

    /**
     * Method env var to query
     */
    const METHOD_ENV = 'SS_VENDOR_METHOD';

    /**
     * Method name for "none" option
     */
    const METHOD_NONE = 'none';

    /**
     * Method name to auto-attempt best method
     */
    const METHOD_AUTO = 'auto';

    /**
     * File name to use for linking method storage
     */
    const METHOD_FILE = '.method';

    /**
     * Define default as 'auto'
     */
    const METHOD_DEFAULT = self::METHOD_AUTO;

    /**
     * @var Filesystem
     */
    protected $filesystem = null;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * Apply vendor plugin
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            'post-package-update' => 'installPackage',
            'post-package-install' => 'installPackage',
            'pre-package-uninstall' => 'uninstallPackage',
        ];
    }

    /**
     * Get vendor module instance for this event
     *
     * @param PackageEvent $event
     * @return VendorModule
     */
    protected function getVendorModule(PackageEvent $event)
    {
        // Ensure package is the valid type
        $package = $this->getOperationPackage($event);
        if (!$package || $package->getType() !== self::MODULE_TYPE) {
            return null;
        }

        // Find project path
        $projectPath = $this->getProjectPath();
        $name = $package->getName();

        // Build module
        return new VendorModule($projectPath, $name);
    }

    /**
     * Install resources from an installed or updated package
     *
     * @param PackageEvent $event
     */
    public function installPackage(PackageEvent $event)
    {
        // Ensure module exists and has any folders to expose
        $module = $this->getVendorModule($event);
        if (!$module || !$module->getExposedFolders()) {
            return;
        }

        // Run with task
        $task = new VendorExposeTask($this->getProjectPath(), $this->filesystem, VendorModule::DEFAULT_TARGET);
        $task->process($event->getIO(), [$module]);
    }

    /**
     * @return string
     */
    protected function getProjectPath()
    {
        return dirname(realpath(Factory::getComposerFile()));
    }

    /**
     * Remove package
     *
     * @param PackageEvent $event
     */
    public function uninstallPackage(PackageEvent $event)
    {
        // Ensure package is the valid type
        $module = $this->getVendorModule($event);
        if (!$module) {
            return;
        }

        // Check path to remove
        $target = $module->getModulePath(VendorModule::DEFAULT_TARGET);
        if (!is_dir($target)) {
            return;
        }

        // Remove directory
        $name = $module->getName();
        $event->getIO()->write("Removing web directories for module <info>{$name}</info>:");
        $this->filesystem->removeDirectory($target);

        // Cleanup empty vendor dir if this is the last module
        $vendorTarget = dirname($target);
        if ($this->filesystem->isDirEmpty($vendorTarget)) {
            $this->filesystem->removeDirectory($vendorTarget);
        }
    }

    /**
     * Get target package from operation
     *
     * @param PackageEvent $event
     * @return PackageInterface
     */
    protected function getOperationPackage(PackageEvent $event)
    {
        $operation = $event->getOperation();
        if ($operation instanceof UpdateOperation) {
            return $operation->getTargetPackage();
        }
        if ($operation instanceof InstallOperation) {
            return $operation->getPackage();
        }
        if ($operation instanceof UninstallOperation) {
            return $operation->getPackage();
        }
        return null;
    }

    public function getCapabilities()
    {
        return [
            CommandProvider::class => VendorCommandProvider::class
        ];
    }
}
