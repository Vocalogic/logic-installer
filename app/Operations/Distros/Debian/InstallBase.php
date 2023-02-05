<?php

namespace App\Operations\Distros\Debian;

use App\Commands\InstallCommand;
use App\Enums\SupportedDistro;
use Exception;
use Illuminate\Support\Facades\File;

class InstallBase
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
     * Install Base Packages
     * @return void
     * @throws Exception
     */
    public function run(): void
    {
        $this->installBasePackages();
        $this->installComposer();
        $this->installNode();
        $this->configureDatabase();
        $this->downloadLogic();
        $this->runInstallers();
        $this->configureNginx();
        $this->installSupervisor();
        $this->setupCron();
    }

    /**
     * Install base PHP packages and other misc.
     * @return void
     * @throws Exception
     */
    private function installBasePackages(): void
    {
        $distro = SupportedDistro::from($this->command->systemArch['Distro']);
        $list = $distro->getPackageList();
        foreach ($list as $item)
        {
            $this->command->line("Installing $item");
            $this->command->exeSudo("apt-get -y install $item");
        }
    }

    /**
     * Install Composer
     * @return void
     * @throws Exception
     */
    private function installComposer(): void
    {
        $this->command->alert("Installing Composer from Source");
        $this->command->info("Downloading Composer..");
        $composer = file_get_contents("https://getcomposer.org/installer");
        File::put("composer_setup.php", $composer);
        $this->command->info("Running Installer..");
        $this->command->exe("php composer_setup.php");
        $this->command->info("Moving to /usr/bin for global access.");
        $this->command->exeSudo("mv composer.phar /usr/bin/composer");
        $this->command->exeSudo("chmod 755 /usr/bin/composer");
    }

    /**
     * Install NodeJS and npm
     * @return void
     * @throws Exception
     */
    private function installNode(): void
    {
        $this->command->alert("Installing NodeJS");
        $this->command->info("Downloading Setup for v16");
        $this->command->exe("curl -sL https://deb.nodesource.com/setup_16.x | sudo -E bash -");
        $this->command->info("Installing NodeJS Package");
        $this->command->exeSudo("apt-get -y install nodejs");
    }

    /**
     * Configure Database
     * @return void
     * @throws Exception
     */
    private function configureDatabase(): void
    {
        $this->command->alert("Creating Databases and User");
        $this->command->databasePassword = uniqid();
        $this->command->info("Creating database for Logic");
        $this->command->exeSudo("mysql -Bne \"create database logic;\"");
        $this->command->info("Creating user for database access");
        $this->command->exeSudo("mysql -Bne \"GRANT all on logic.* to 'logic'@'localhost' identified by '{$this->command->databasePassword}';\"");
    }

    /**
     * Clone Repo and update .env
     * @return void
     * @throws Exception
     */
    private function downloadLogic(): void
    {
        $this->command->alert("Downloading Logic Latest");
        $this->command->info("Cloning Logic from Official Repository..");
        $this->command->exe("git clone https://www.github.com/Vocalogic/logic");
        $this->command->info("Updating .env");
        $this->command->exe("cd logic && mv .env.example .env");
        $this->command->exe("cd logic && sed -i 's/DB\_DATABASE=\(.*\)/DB\_DATABASE=logic/' .env");
        $this->command->exe("cd logic && sed -i 's/DB\_USERNAME=\(.*\)/DB\_USERNAME=logic/' .env");
        $this->command->exe("cd logic && sed -i 's/DB\_PASSWORD=\(.*\)/DB\_PASSWORD=\"{$this->command->databasePassword}\"/' .env");
        $this->command->exe("cd logic && sed -i 's/APP\_URL=\(.*\)/APP\_URL=http\:\/\/{$this->command->hostname}/' .env");
    }

    /**
     * Cleanup for installing and running our base npm and composer install
     * @return void
     * @throws Exception
     */
    private function runInstallers(): void
    {
        $this->command->alert("Running initial installers (npm and composer)");
        $this->command->info("Running Composer to get Vendor Packages");
        $this->command->exe("cd logic && composer install");
        $this->command->info("Running NPM Installer");
        $this->command->exe("cd logic && npm install");
        $this->command->exe("cd logic && php artisan key:generate");
        $this->command->info("Updating Permissions..");
        $this->command->exeSudo("chown {$this->command->systemUser}.{$this->command->systemUser} /home/{$this->command->systemUser}/logic/bootstrap -R");
        $this->command->exeSudo("chown {$this->command->systemUser}.{$this->command->systemUser} /home/{$this->command->systemUser}/logic/storage -R");
        $this->command->exe("cd logic && chmod 777 storage -R");
        $this->command->exe("cd logic && chmod 777 bootstrap -R");
        $this->command->info("Running Logic Upgrade Routine.. (this may take a minute)");
        $this->command->exe("cd logic && php artisan logic:upgrade");
    }

    /**
     * Configure Nginx
     * @return void
     * @throws Exception
     */
    private function configureNginx(): void
    {
        $template = <<<EOF
server {

    server_name {$this->command->hostname};
    root /home/{$this->command->systemUser}/logic/public;
    index index.php;
    access_log /var/log/nginx/logic.access.log;
    error_log /var/log/nginx/logic.error.log;
    charset utf-8;
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { log_not_found off; access_log off; }
    location = /robots.txt { log_not_found off; access_log off; }
    error_page 404 /index.php;
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    # Deny .htaccess file access
    location ~ /\.ht {
    deny all;
    }
}
EOF;
        $this->command->info("Removing Default Nginx Site Configuration");
        $this->command->exeSudo("rm /etc/nginx/sites-enabled/default -rf");
        File::put("logic_nginx", $template);
        $this->command->info("Writing new Logic Entry for Nginx");
        $this->command->exeSudo("mv logic_nginx /etc/nginx/sites-enabled/logic");
        File::delete("logic_nginx");
        $this->command->info("Restarting nginx");
        $this->command->exeSudo("/etc/init.d/nginx restart");


    }

    /**
     * Install Supervisor and Create Workers
     * @return void
     * @throws Exception
     */
    private function installSupervisor() : void
    {
        $this->command->alert("Installing Supervisor for Queue Workers");
        $template = <<<EOF
[program:logic]
command=php8.2 /home/{$this->command->systemUser}/logic/artisan queue:listen redis --sleep=10 --quiet --force --queue="logic"
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user={$this->command->systemUser}
numprocs=1
stdout_logfile=/home/{$this->command->systemUser}/logic-worker.log
EOF;

        $this->command->exeSudo("apt-get -y install supervisor");
        File::put("logic_super", $template);
        $this->command->info("Writing new Logic Supervisor Config");
        $this->command->exeSudo("mv logic_super /etc/supervisor/conf.d/logic.conf");
        File::delete("logic_super");
        $this->command->info("Starting Supervisor");

        $this->command->exeSudo("supervisorctl reread");
        $this->command->exeSudo("supervisorctl update");
        $this->command->exeSudo("supervisorctl start logic:*");
    }

    /**
     * Setup Cron to run scheduler every minute.
     * @return void
     * @throws Exception
     */
    private function setupCron() : void
    {
        $this->command->info("Setting up system cron for Scheduling Engine..");
        $this->command->exe("echo '* * * * * cd /home/{$this->command->systemUser}/logic; php artisan schedule:run > /dev/null 2&>1' | crontab -u {$this->command->systemUser} -");
    }
}
