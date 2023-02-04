<?php

namespace App\Enums;

enum SupportedDistro : string
{
    case Debian = "Debian";
    case LinuxMint = "Linuxmint";

    /**
     * Based on the Operating system, is the release supported by this installer?
     * @param string $release
     * @return bool
     */
    public function isSupported(string $release) : bool
    {
        switch($this)
        {
            case self::Debian :
                $available = ['11'];
                return in_array($release, $available);

            case self::LinuxMint :
                $available = ['21'];
                return in_array($release, $available);

        }


    }
}
