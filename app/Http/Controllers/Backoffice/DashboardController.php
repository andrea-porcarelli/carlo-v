<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    public function index() : View {

        return view('backoffice.index');
    }

    public function forget()
    {
        Session::forget('company_id');
        Session::put('company-to-be-select', false);
    }

    public function select_company() : View
    {
        return view('backoffice.pages.dashboard.select-company');
    }
}
