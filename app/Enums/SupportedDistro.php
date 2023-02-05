<?php

namespace App\Enums;

enum SupportedDistro: string
{
    case Debian = "Debian";
    case LinuxMint = "Linuxmint";
    case CentOS = "CentOS";

    /**
     * Based on the Operating system, is the release supported by this installer?
     * @param string $release
     * @return bool
     */
    public function isSupported(string $release): bool
    {
        switch ($this)
        {
            case self::Debian :
                $available = ['11'];
                return in_array($release, $available);

            case self::LinuxMint :
                $available = ['21'];
                return in_array($release, $available);

        }
    }

    /**
     * Get the base namespace for executing commands on different
     * distributions.
     * @return string
     */
    public function getRoot(): string
    {
        return match ($this)
        {
            self::Debian => "Debian",
            self::LinuxMint => "Linuxmint",
            self::CentOS => "Centos"
        };
    }

    /**
     * Get a list of packages required for each distro to begin installing
     * @return array
     */
    public function getPackageList(): array
    {
        return match ($this)
        {
            self::Debian => [
                'curl',
                'php8.2-fpm',
                'php8.2-curl',
                'php8.2-gd',
                'php8.2-igbinary',
                'php8.2-mbstring',
                'php8.2-mysql',
                'php8.2-opcache',
                'php8.2-bcmath',
                'php8.2-phpdbg',
                'php8.2-readline',
                'php8.2-redis',
                'php8.2-zip',
                'php8.2-common',
                'gcc',
                'g++',
                'make',
                'nginx',
                'redis-server',
                'certbot',
                'python3-certbot-nginx',
                'git',
                'mariadb-server',
                'mariadb-client'
            ],
        };
    }
}
