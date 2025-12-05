<?php

namespace App\Traits;

trait DetectsMobileDevice
{
    /**
     * Detect if the current request is from a mobile device
     *
     * @return bool
     */
    protected function isMobile(): bool
    {
        $userAgent = request()->header('User-Agent');

        if (empty($userAgent)) {
            return false;
        }

        // Mobile device patterns
        $mobilePatterns = [
            '/Mobile/i',
            '/Android/i',
            '/iPhone/i',
            '/iPad/i',
            '/iPod/i',
            '/BlackBerry/i',
            '/Windows Phone/i',
            '/webOS/i',
            '/Opera Mini/i',
            '/IEMobile/i',
        ];

        foreach ($mobilePatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if the current request is from a tablet
     *
     * @return bool
     */
    protected function isTablet(): bool
    {
        $userAgent = request()->header('User-Agent');

        if (empty($userAgent)) {
            return false;
        }

        // Tablet specific patterns
        $tabletPatterns = [
            '/iPad/i',
            '/Android(?!.*Mobile)/i',
            '/Tablet/i',
            '/PlayBook/i',
            '/Kindle/i',
        ];

        foreach ($tabletPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the device type
     *
     * @return string (mobile|tablet|desktop)
     */
    protected function getDeviceType(): string
    {
        if ($this->isTablet()) {
            return 'tablet';
        }

        if ($this->isMobile()) {
            return 'mobile';
        }

        return 'desktop';
    }
}
