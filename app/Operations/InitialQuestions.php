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
        // Obtain Sudo Password for installing
        $this->getSudoPassword();
        $this->getHostname();
        // Show Menu for Installation Instructions.
        $this->getDesiredInstallationMethod();


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

    /**
     * Ask user for the sudo password so that we can execute things as root.
     * @return void
     */
    private function getSudoPassword(): void
    {
        $this->command->alert("The installer requires your sudo password for the root user to verify you have " .
            "the correct packages installed and to perform system commands to set up your webserver.");
        $this->command->sudoPassword = $this->command->ask("Enter sudo password");
        if (!$this->command->sudoPassword)
        {
            $this->command->warn("No sudo password was given. Only application installation process will be attempted.");
            return;
        }
        // Try sudo password
        try
        {
            $this->command->exeSudo("whoami");
        } catch (Exception)
        {
            $this->command->error("SUDO Password appears to be invalid or user not found in sudoers. Please try again.");
            $this->getSudoPassword();
        }
    }

    /**
     * Set our installation method that the user is requiring.
     * @return void
     */
    private function getDesiredInstallationMethod() : void
    {
        $method = $this->command->menu("Logic Installation Method", [
            'Install Webserver and Logic from a Fresh Installation',
            'Install Logic Software Only',
        ])->open();
        $this->command->installationMethod = $method;
        if ($method === null)
        {
            $this->command->die("Installation Aborted.");
        }
        if ($method === 0)
        {
            $distro = $this->command->systemArch['Distro'];
            $resp = $this->command->confirm("Continue with Installation on a brand new $distro installation?");
            if (!$resp)
            {
                $this->command->die("Installation Aborted.");
            }
        }
    }

    /**
     * Get hostname for configuring NGINX
     * @return void
     */
    private function getHostname()
    {
        $this->command->alert("Configuring your Domain");
        $this->command->info("The hostname you enter here will be used for configuring your webserver.");
        $this->command->info("You should add a DNS (A Record) for this domain to point to this server's IP.");
        $this->command->hostname = $this->command->ask("Enter Domain for Logic");
        if (!$this->command->hostname)
        {
            $this->command->error("You must specify a domain for configuring your webserver.");
            $this->getHostname();
        }
    }
}
