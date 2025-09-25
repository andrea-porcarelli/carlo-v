<?php

namespace App\Http\Controllers\Frontoffice;



use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AppController extends Controller
{

    public function index() : View {
        return view('app.index');
    }

}
