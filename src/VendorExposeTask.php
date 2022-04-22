<?php


namespace SilverStripe\VendorPlugin;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use DirectoryIterator;
use InvalidArgumentException;
use SilverStripe\VendorPlugin\Methods\ChainedMethod;
use SilverStripe\VendorPlugin\Methods\CopyMethod;
use SilverStripe\VendorPlugin\Methods\ExposeMethod;
use SilverStripe\VendorPlugin\Methods\JunctionMethod;
use SilverStripe\VendorPlugin\Methods\SymlinkMethod;

/**
 * Task for exposing all vendor paths
 */
class VendorExposeTask
{
    /**
     * Absolute filesystem path to root folder
     *
     * @var string
     */
    protected $basePath = null;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Path to expose to
     *
     * @var string
     */
    protected $resourcesPath;

    /**
     * Construct task for the given base folder
     *
     * @param string $basePath
     * @param Filesystem $filesystem
     * @param string $publicPath Public path to expose to
     */
    public function __construct($basePath, Filesystem $filesystem, $publicPath)
    {
        $this->basePath = $basePath;
        $this->filesystem = $filesystem;
        $this->resourcesPath = $publicPath;
    }

    /**
     * Expose all modules with the given method
     *
     * @param IOInterface $io
     * @param Library[] $libraries
     * @param string $methodKey Method key, or null to auto-detect from environment
     */
    public function process(IOInterface $io, array $libraries, $methodKey = null)
    {
        // No-op
        if (empty($libraries)) {
            return false;
        }

        // Setup root folder
        $this->setupResources($io);

        // Get or choose method
        if (!$methodKey) {
            $methodKey = $this->getMethodKey();
        }
        $method = $this->getMethod($methodKey);

        if ($methodKey === VendorPlugin::METHOD_NONE) {
            return false;
        }

        // Update all modules
        foreach ($libraries as $module) {
            // Skip this module if no exposure required
            if (!$module->requiresExpose()) {
                continue;
            }
            $name = $module->getName();
            $type = $module->getType();
            $io->write(
                "Exposing web directories for {$type} <info>{$name}</info> with method <info>{$methodKey}</info>:"
            );
            foreach ($module->getExposedFolders() as $folder) {
                $io->write("  - <info>$folder</info>");
            }

            // Expose web dirs with given method
            $module->exposePaths($method);
        }

        // On success, write `.method` token to persist for subsequent updates
        $this->saveMethodKey($methodKey);

        return true;
    }


    /**
     * Ensure the resources folder is safely created and protected from index.php in root
     *
     * @param IOInterface $io
     */
    protected function setupResources(IOInterface $io)
    {
        // Setup root dir
        $resourcesPath = $this->getResourcesPath();
        $this->filesystem->ensureDirectoryExists($resourcesPath);

        // Copy missing resources
        $files = new DirectoryIterator(__DIR__.'/../resources');
        foreach ($files as $file) {
            $targetPath = $resourcesPath . DIRECTORY_SEPARATOR . $file->getFilename();
            if ($file->isFile() && !file_exists($targetPath ?? '')) {
                $name = $file->getFilename();
                $io->write("Writing <info>{$name}</info> to resources folder");
                copy($file->getPathname() ?? '', $targetPath ?? '');
            }
        }
    }

    /**
     * Get named method instance
     *
     * @param string $key Key of method to use
     * @return ExposeMethod
     */
    protected function getMethod($key)
    {
        switch ($key) {
            case CopyMethod::NAME:
                return new CopyMethod();
            case SymlinkMethod::NAME:
                return new SymlinkMethod();
            case JunctionMethod::NAME:
                return new JunctionMethod();
            case VendorPlugin::METHOD_NONE:
                // 'none' is forced to an empty chain (and doesn't run anyway)
                return new ChainedMethod();
            case VendorPlugin::METHOD_AUTO:
                // Default to safe-failover method
                if (Platform::isWindows()) {
                    // Use junctions on windows environment
                    return new ChainedMethod(new JunctionMethod(), new CopyMethod());
                } else {
                    // Use symlink on non-windows environments
                    return new ChainedMethod(new SymlinkMethod(), new CopyMethod());
                }
            default:
                throw new InvalidArgumentException("Invalid method: {$key}");
        }
    }

    /**
     * Get 'key' of method to use
     *
     * @return string
     */
    protected function getMethodKey()
    {
        // Switch if `resources/.method` contains a file
        $methodFilePath = $this->getMethodFilePath();
        if (file_exists($methodFilePath ?? '') && is_readable($methodFilePath ?? '')) {
            return trim(file_get_contents($methodFilePath ?? '') ?? '');
        }

        // Switch based on SS_VENDOR_METHOD arg
        $method = getenv(VendorPlugin::METHOD_ENV);
        if ($method) {
            return $method;
        }

        // Default method
        return VendorPlugin::METHOD_DEFAULT;
    }

    /**
     * Persist method key to `resources/.method` to set value
     *
     * @param string $key
     */
    protected function saveMethodKey($key)
    {
        $methodFilePath = $this->getMethodFilePath();
        file_put_contents($methodFilePath ?? '', $key);
    }

    /**
     * Get path to method cache file
     *
     * @return string
     */
    protected function getMethodFilePath()
    {
        return Util::joinPaths(
            $this->getResourcesPath(),
            VendorPlugin::METHOD_FILE
        );
    }

    /**
     * Path to 'resources' folder
     *
     * @return string
     */
    protected function getResourcesPath()
    {
        return $this->resourcesPath;
    }
}
