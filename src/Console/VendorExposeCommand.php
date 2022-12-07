<?php

namespace SilverStripe\VendorPlugin\Console;

use Composer\Command\BaseCommand;
use Composer\Factory;
use Composer\IO\ConsoleIO;
use Composer\Util\Filesystem;
use Generator;
use SilverStripe\VendorPlugin\Library;
use SilverStripe\VendorPlugin\Util;
use SilverStripe\VendorPlugin\VendorExposeTask;
use SilverStripe\VendorPlugin\VendorPlugin;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides `composer vendor-expose` behaviour
 */
class VendorExposeCommand extends BaseCommand
{
    public function configure()
    {
        $this->setName('vendor-expose');
        $this->setDescription('Refresh all exposed module/theme/project folders');
        $this->addArgument(
            'method',
            InputArgument::OPTIONAL,
            'Optional method to use. Defaults to last used value, or "' . VendorPlugin::METHOD_DEFAULT . '" otherwise.'
            . ' Options: "auto", "symlink", "copy" or "junction"'
        );
        $this->setHelp('This command will update all resources for all installed modules using the given method');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new ConsoleIO($input, $output, $this->getHelperSet());

        // Check libraries to expose
        $modules = $this->getAllLibraries();
        if (empty($modules)) {
            $io->write("No modules to expose");
            return 0;
        }

        // Query first library for base destination
        $basePublicPath = $modules[0]->getBasePublicPath();

        // Expose all modules
        $method = $input->getArgument('method');
        $task = new VendorExposeTask($this->getProjectPath(), new Filesystem(), $basePublicPath);

        if ($task->process($io, $modules, $method)) {
            // Success
            $io->write("All modules updated!");
        }
        
        return 0;
    }

    /**
     * Get all libraries
     *
     * @return Library[]
     */
    protected function getAllLibraries()
    {
        $modules = [];
        $basePath = $this->getProjectPath();

        // Get all modules
        foreach ($this->getModulePaths() as $modulePath) {
            // Filter by non-composer folders
            $composerPath = Util::joinPaths($modulePath, 'composer.json');
            if (!file_exists($composerPath ?? '')) {
                continue;
            }

            // Ensure this library should be exposed, and has at least one folder
            $module = new Library($basePath, $modulePath);
            if (!$module->requiresExpose() || !$module->getExposedFolders()) {
                continue;
            }

            // Save this module
            $modules[] = $module;
        }
        return $modules;
    }

    /**
     * Search all paths that could contain a module / theme
     *
     * @return Generator
     */
    protected function getModulePaths()
    {
        // Project root is always returned
        $basePath = $this->getProjectPath();
        yield $basePath;

        // Get vendor modules
        $search = Util::joinPaths($basePath, 'vendor', '*', '*');
        foreach (glob($search ?? '', GLOB_ONLYDIR) as $modulePath) {
            if ($this->isPathModule($modulePath)) {
                yield $modulePath;
            }
        }

        // Check if public/ folder exists
        $publicExists = is_dir(Util::joinPaths($basePath, Library::PUBLIC_PATH) ?? '');
        if (!$publicExists) {
            return;
        }

        // Search all base folders / modules
        $search = Util::joinPaths($basePath, '*');
        foreach (glob($search ?? '', GLOB_ONLYDIR) as $modulePath) {
            if ($this->isPathModule($modulePath)) {
                yield $modulePath;
            }
        }

        // Check all themes
        $search = Util::joinPaths($basePath, 'themes', '*');
        foreach (glob($search ?? '', GLOB_ONLYDIR) as $themePath) {
            yield $themePath;
        }
    }

    /**
     * Check if the given path is a silverstripe module
     *
     * @param string $path
     * @return bool
     */
    protected function isPathModule($path)
    {
        return file_exists(Util::joinPaths($path, '_config') ?? '')
            || file_exists(Util::joinPaths($path, '_config.php') ?? '');
    }

    /**
     * @return string
     */
    protected function getProjectPath()
    {
        return dirname(realpath(Factory::getComposerFile() ?? '') ?? '');
    }
}
