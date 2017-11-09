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
use Composer\Plugin\PluginInterface;
use Composer\Util\Filesystem;
use DirectoryIterator;
use SilverStripe\VendorPlugin\Methods\CopyMethod;
use SilverStripe\VendorPlugin\Methods\ExposeMethod;
use SilverStripe\VendorPlugin\Methods\ChainedMethod;
use SilverStripe\VendorPlugin\Methods\SymlinkMethod;

/**
 * Provides public webroot rewrite functionality for vendor modules
 */
class VendorPlugin implements PluginInterface, EventSubscriberInterface
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
        // Check and log all folders being exposed
        $module = $this->getVendorModule($event);
        if (!$module) {
            return;
        }

        // Skip if module has no public resources
        $folders = $module->getExposedFolders();
        if (empty($folders)) {
            return;
        }

        // Log details
        $name = $module->getName();
        $event->getIO()->write("Exposing web directories for module <info>{$name}</info>:");
        foreach ($folders as $folder) {
            $event->getIO()->write("  - <info>$folder</info>");
        }

        // Setup root folder
        $this->setupResources();

        // Expose web dirs with given method
        $method = $this->getMethod();
        $module->exposePaths($method);
    }

    /**
     * @return string
     */
    protected function getProjectPath()
    {
        return dirname(realpath(Factory::getComposerFile()));
    }

    /**
     * Ensure the resources folder is safely created and protected from index.php in root
     */
    protected function setupResources()
    {
        // Setup root dir
        $resourcesPath = Util::joinPaths(
            $this->getProjectPath(),
            VendorModule::DEFAULT_TARGET
        );
        $this->filesystem->ensureDirectoryExists($resourcesPath);

        // Copy missing resources
        $files = new DirectoryIterator(__DIR__.'/../resources');
        foreach ($files as $file) {
            $targetPath = $resourcesPath . DIRECTORY_SEPARATOR . $file->getFilename();
            if ($file->isFile() && !file_exists($targetPath)) {
                copy($file->getPathname(), $targetPath);
            }
        }
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
     * @return ExposeMethod
     */
    protected function getMethod()
    {
        // Switch based on SS_VENDOR_METHOD arg
        switch (getenv(self::METHOD_ENV)) {
            case CopyMethod::NAME:
                return new CopyMethod();
            case SymlinkMethod::NAME:
                return new SymlinkMethod();
            case self::METHOD_NONE:
                // 'none' is forced to an empty chain
                return new ChainedMethod([]);
            case self::METHOD_AUTO:
            default:
                // Default to safe-failover method
                return new ChainedMethod(new SymlinkMethod(), new CopyMethod());
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
}
