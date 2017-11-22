<?php

namespace SilverStripe\VendorPlugin\Console;

use Composer\Command\BaseCommand;
use Composer\Plugin\Capability\CommandProvider;

class VendorCommandProvider implements CommandProvider
{
    /**
     * Retreives an array of commands
     *
     * @return BaseCommand[]
     */
    public function getCommands()
    {
        return [
            new VendorExposeCommand()
        ];
    }
}
