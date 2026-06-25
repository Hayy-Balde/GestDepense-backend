<?php

namespace App\Helpers;

class UserAgentParser
{
    public static function deviceName(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Inconnu';
        }

        $ua = strtolower($userAgent);

        // OS
        if (str_contains($ua, 'mac os x') || str_contains($ua, 'macintosh')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'linux') && ! str_contains($ua, 'android')) {
            $os = 'Linux';
        } elseif (str_contains($ua, 'android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod')) {
            $os = 'iOS';
        } elseif (str_contains($ua, 'cros')) {
            $os = 'ChromeOS';
        } else {
            $os = 'Inconnu';
        }

        // Browser
        if (str_contains($ua, 'edg/') || str_contains($ua, 'edge/')) {
            $browser = 'Edge';
        } elseif (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            $browser = 'Opera';
        } elseif (str_contains($ua, 'chrome/') && ! str_contains($ua, 'edg/') && ! str_contains($ua, 'opr/')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'safari/') && ! str_contains($ua, 'chrome/')) {
            $browser = 'Safari';
        } elseif (str_contains($ua, 'firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'msie') || str_contains($ua, 'trident')) {
            $browser = 'IE';
        } else {
            $browser = null;
        }

        return $browser ? "$browser / $os" : $os;
    }
}
