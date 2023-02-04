<?php

namespace App\Operations;

use App\Commands\InstallCommand;
use App\Enums\SupportedDistro;
use Exception;

class InitialQuestions
{
    public InstallCommand $command;

    /**
     * We want to not only keep the components able to interact with the console
     * but use the root InstallCommand to hold all our variables needed.
     * @param InstallCommand $command
     */
    public function __construct(InstallCommand $command)
    {
        $this->command = $command;
    }

    /**
     * This is the entrace method
     * @return void
     * @throws Exception
     */
    public function run(): void
    {
        $this->command->alert("Logic Installer v" . $this->command->installerVersion);
        $this->command->line("This application will attempt to check and install any prerequsites on your server to " .
            'successfully run Logic on in your environment.');
        // Make sure user is not root.
        $this->checkForRoot();
        // Check Operating System
        $this->checkOS();



    }

    /**
     * Make sure we are not running this as root.
     * @return void
     * @throws Exception
     */
    private function checkForRoot(): void
    {
        $user = $this->command->exe("whoami");
        $this->command->comment("Checking Current User: [$user]");
        if ($user == 'root')
        {
            $this->command->die("You must run this installer as the user it will be run under. You cannot " .
                "execute this as 'root'.");
        }
        $this->command->systemUser = $user;
    }

    /**
     * Ensure that this OS is supported by the installer.
     * @return void
     * @throws Exception
     */
    private function checkOS(): void
    {
        $output = $this->command->exe("lsb_release -a");
        $this->command->systemArch['Distro'] = $this->command->lookFor($output, "Distributor ID:");
        $this->command->systemArch['Description'] = $this->command->lookFor($output, "Description:");
        $this->command->systemArch['Release'] = $this->command->lookFor($output, "Release:");
        $this->command->systemArch['Codename'] = $this->command->lookFor($output, "Codename:");
        $os = SupportedDistro::tryFrom($this->command->systemArch['Distro']);
        if (!$os)
        {
            $this->command->die($this->command->systemArch['Distro'] . " is not currently supported by this installer.");
        }
        if (!$os->isSupported($this->command->systemArch['Release']))
        {
            $this->command->die("Release " . $this->command->systemArch['Release'] . " is not currently supported.");
        }
        $this->command->info(sprintf("%s Release %s is supported by this installer.",
            $this->command->systemArch['Distro'], $this->command->systemArch['Release']));
    }
}
