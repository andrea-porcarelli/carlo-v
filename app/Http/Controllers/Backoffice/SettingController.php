<?php

namespace App\Http\Controllers\Backoffice;

use App\Models\Printer;
use App\Models\Setting;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends BaseController
{
    public function index(): View
    {
        $settings = Setting::all();
        $printers = Printer::where('is_active', true)->get()->map(function ($printer) {
            return ['id' => $printer->id, 'label' => $printer->label];
        })->toArray();

        return view('backoffice.settings.index', compact('settings', 'printers'));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $settings = Setting::all();

            foreach ($settings as $setting) {
                $value = $request->input($setting->key, $setting->type === 'boolean' ? '0' : $setting->value);
                Setting::set($setting->key, $value, $setting->type, $setting->description);
            }

            return $this->success();
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }
}
