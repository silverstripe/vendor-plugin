<?php

namespace SilverStripe\VendorPlugin;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
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
        ];
    }

    /**
     * Install resources from an installed or updated package
     *
     * @param PackageEvent $event
     */
    public function installPackage(PackageEvent $event)
    {
        // Ensure package is the valid type
        $package = $this->getOperationPackage($event);
        if (!$package || $package->getType() !== self::MODULE_TYPE) {
            return;
        }

        // Find project path
        $projectPath = dirname(realpath(Factory::getComposerFile()));
        $name = $package->getName();
        $module = new VendorModule($projectPath, $name);

        // Check and log all folders being exposed
        $folders = $module->getExposedFolders();
        if (empty($folders)) {
            return;
        }

        // Log details
        $event->getIO()->write("Exposing web directories for module <info>{$name}</info>:");
        foreach ($folders as $folder) {
            $event->getIO()->write("  - <info>$folder</info>");
        }

        // Expose webdirs with given method
        $method = $this->getMethod();
        $module->exposePaths($method);
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
        return null;
    }
}
