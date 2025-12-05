<?php

namespace App\Http\Controllers\Frontoffice;

use App\Http\Controllers\Controller;
use App\Traits\DetectsMobileDevice;
use Illuminate\View\View;

class AppController extends Controller
{
    use DetectsMobileDevice;

    public function index(): View
    {
        $deviceType = $this->getDeviceType();

        // Route to the appropriate view based on device type
        if ($deviceType === 'mobile') {
            return view('app.mobile.index')->with('deviceType', $deviceType);
        }

        if ($deviceType === 'tablet') {
            return view('app.tablet.index')->with('deviceType', $deviceType);
        }

        // Desktop view (default)
        return view('app.index')->with('deviceType', $deviceType);
    }
}
