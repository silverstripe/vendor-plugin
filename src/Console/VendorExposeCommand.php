<?php

namespace SilverStripe\VendorPlugin\Console;

use Composer\Command\BaseCommand;
use Composer\Factory;
use Composer\IO\ConsoleIO;
use Composer\Util\Filesystem;
use SilverStripe\VendorPlugin\Util;
use SilverStripe\VendorPlugin\VendorExposeTask;
use SilverStripe\VendorPlugin\VendorModule;
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
        $this->setDescription('Refresh all exposed vendor module folders');
        $this->addArgument(
            'method',
            InputArgument::OPTIONAL,
            'Optional method to use. Defaults to last used value, or ' . VendorPlugin::METHOD_DEFAULT . ' otherwise'
        );
        $this->setHelp('This command will update all resources for all installed modules using the given method');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new ConsoleIO($input, $output, $this->getHelperSet());

        // Check modules to expose
        $modules = $this->getAllModules();
        if (empty($modules)) {
            $io->write("No modules to expose");
            return;
        }

        // Expose all modules
        $method = $input->getArgument('method');
        $task = new VendorExposeTask($this->getProjectPath(), new Filesystem(), VendorModule::DEFAULT_TARGET);
        $task->process($io, $modules, $method);

        // Success
        $io->write("All modules updated!");
    }

    /**
     * Find all modules
     *
     * @return VendorModule[]
     */
    protected function getAllModules()
    {
        $modules = [];
        $basePath = $this->getProjectPath();
        $search = Util::joinPaths($basePath, 'vendor', '*', '*');
        foreach (glob($search, GLOB_ONLYDIR) as $modulePath) {
            // Filter by non-composer folders
            $composerPath = Util::joinPaths($modulePath, 'composer.json');
            if (!file_exists($composerPath)) {
                continue;
            }
            // Build module
            $name = basename($modulePath);
            $vendor = basename(dirname($modulePath));
            $module = new VendorModule($basePath, "{$vendor}/{$name}");
            // Check if this module has folders to expose
            if ($module->getExposedFolders()) {
                $modules[] = $module;
            }
        }
        return $modules;
    }

    /**
     * @return string
     */
    protected function getProjectPath()
    {
        return dirname(realpath(Factory::getComposerFile()));
    }
}
