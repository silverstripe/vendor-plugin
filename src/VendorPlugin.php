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
use Composer\Script\Event;
use Composer\Util\Filesystem;
use SilverStripe\VendorPlugin\Console\VendorCommandProvider;

/**
 * Provides public webroot rewrite functionality for vendor modules
 */
class VendorPlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    /**
     * Default module type
     *
     * @deprecated 1.3..2.0 No longer used
     */
    const MODULE_TYPE = 'silverstripe-vendormodule';

    /**
     * Filter for matching library types to expose
     *
     * @deprecated 1.3..2.0 No longer used
     */
    const MODULE_FILTER = '/^silverstripe\-(\w+)$/';

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
            'post-install-cmd' => 'installRootPackage',
            'post-update-cmd' => 'installRootPackage',
        ];
    }

    /**
     * Get vendor module instance for this event
     *
     * @deprecated 1.3..2.0
     * @param PackageEvent $event
     * @return Library|null
     */
    protected function getVendorModule(PackageEvent $event)
    {
        return $this->getLibrary($event);
    }

    /**
     * Gets library being installed
     *
     * @param PackageEvent $event
     * @return Library|null
     */
    public function getLibrary(PackageEvent $event)
    {
        // Ensure package is the valid type
        $package = $this->getOperationPackage($event);
        if (!$package) {
            return null;
        }

        // Get appropriate installer and query install path
        $installer = $event->getComposer()->getInstallationManager()->getInstaller($package->getType());
        $path = $installer->getInstallPath($package);

        // Build module
        return new Library($this->getProjectPath(), $path);
    }

    /**
     * Install resources from an installed or updated package
     *
     * @param PackageEvent $event
     */
    public function installPackage(PackageEvent $event)
    {
        // Ensure module exists and requires exposure
        $library = $this->getLibrary($event);
        if (!$library) {
            return;
        }

        // Install found library
        $this->installLibrary($event->getIO(), $library);
    }

    /**
     * Install resources from the root package
     *
     * @param Event $event
     */
    public function installRootPackage(Event $event)
    {
        // Build library in base path
        $basePath = $this->getProjectPath();
        $library = new Library($basePath, $basePath);

        // Pass to library installer
        $this->installLibrary($event->getIO(), $library);
    }

    /**
     * Get base path to project
     *
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
        // Check if library exists and exposes any directories
        $library = $this->getLibrary($event);
        if (!$library || !$library->requiresExpose()) {
            return;
        }

        // Check path to remove
        $target = $library->getPublicPath();
        if (!is_dir($target)) {
            return;
        }

        // Remove directory
        $name = $library->getName();
        $event->getIO()->write("Removing web directories for module <info>{$name}</info>:");
        $this->filesystem->removeDirectory($target);

        // Cleanup empty vendor dir if this is the last module
        $targetParent = dirname($target);
        if ($this->filesystem->isDirEmpty($targetParent)) {
            $this->filesystem->removeDirectory($targetParent);
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

    /**
     * Expose the given Library object
     *
     * @param IOInterface $IO
     * @param Library $library
     */
    protected function installLibrary(IOInterface $IO, Library $library)
    {
        if (!$library || !$library->requiresExpose()) {
            return;
        }

        // Create exposure task
        $task = new VendorExposeTask(
            $this->getProjectPath(),
            $this->filesystem,
            $library->getBasePublicPath()
        );
        $task->process($IO, [$library]);
    }
}
