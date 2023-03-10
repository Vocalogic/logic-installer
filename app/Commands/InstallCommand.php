<?php

namespace App\Commands;

use App\Enums\SupportedDistro;
use App\Operations\InitialQuestions;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'install';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Install Logic Application and Prerequisites.';

    /**
     * The following variables will be used to track progress and any input from the user.
     */
    public string $installerVersion = '1.0.0';
    public string $systemUser;
    public array $systemArch;
    public ?string $sudoPassword;
    public ?string $hostname;
    public ?int $installationMethod;
    public string $databasePassword;





    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Gather Initial Questions from the User
        (new InitialQuestions($this))->run();
        $distro = $this->systemArch['Distro'];
        $distro = SupportedDistro::tryFrom($distro);
        if (!$distro)
        {
            $this->die("Distribution not Supported");
        }
        $base = $distro->getRoot();
        $base = "\\App\\Operations\\Distros\\$base\\";

        // Install Base Packages
        $next = $base . "InstallBase";
        (new $next($this))->run();





    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }

    /**
     * This method will execute a shell command, and will return the output of the command.
     * Should the command fail a standard exception will be thrown with the message.
     * @param string $command
     * @return string|null
     * @throws Exception
     */
    public function exe(string $command) : ?string
    {
        $cmd = Process::fromShellCommandline($command);
        $cmd->setTimeout(900);
        $cmd->run();
        if ($cmd->isSuccessful())
        {
            return trim($cmd->getOutput());
        }
        else
        {
            throw new Exception($cmd->getErrorOutput());
        }
        return null;

    }

    /**
     * Exit application with error.
     * @param string $message
     * @return void
     */
    public function die(string $message) : void
    {
        $this->error($message);
        dd();
    }

    /**
     * When executing a command we want to be able to look for
     * certain output lines. If we find it then return it here
     * otherwise return null.
     * @param      $output
     * @param      $search
     * @param bool $stripSearch
     * @return string|null
     */
    public function lookFor($output, $search, bool $stripSearch = true): ?string
    {
        $lines = explode("\n", $output);
        foreach ($lines as $line)
        {
            if (preg_match("/$search/i", $line))
            {
                if ($stripSearch)
                {
                    return trim(str_replace($search, '', $line));
                }
                return trim($line);
            }
        }
        return null;
    }

    /**
     * This will attempt to execute a command as the sudo user.
     * @param string $string
     * @return string|null
     * @throws Exception
     */
    public function exeSudo(string $string) : ?string
    {
        $sudo = $this->sudoPassword;
        return $this->exe("echo \"$sudo\" | sudo -S $string");
    }


}
