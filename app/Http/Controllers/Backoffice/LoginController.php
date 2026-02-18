<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Backoffice\Requests\LoginRequest;
use App\Interfaces\UserInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;

class LoginController extends BaseController
{
    protected UserInterface $user;
    public function __construct(UserInterface $user)
    {
        $this->user = $user;
    }

    public function index() : View {
        return view('backoffice.login');
    }

    public function login(LoginRequest $request) : JsonResponse {
        try {
            if(!Auth::validate($request->all())) {
                return response()->json(['message' => 'I dati inseriti sono errati'], 422);
            }
            $user = Auth::getProvider()->retrieveByCredentials($request->all());
            if($user->role !== 'admin') {
                return response()->json(['message' => "Non puoi accedere a quest'area"], 422);
            }
            Auth::login($user);
            return response()->json(['response' => 'ok', 'url' => redirect()->getIntendedUrl() ?? '/backoffice/index']);
        } catch (Exception $e) {
            $this->exception($e);
        }
    }

    public function logout() : JsonResponse {
        Session::flush();
        Auth::logout();
        return response()->json(['response' => 'ok']);
    }

    public function change_password() : View {
        return view('change-password');
    }

    public function reset_password(ChangePasswordRequest $request, MessageBag $messageBag) : JsonResponse {
        $old = Auth::user()->password;
        $new = Hash::make($request->password);
        if (Hash::check($request->password, $old)) {
            $messageBag->add('error', "La nuova password non puà essere uguale alla vecchia!");
            return response()->json($messageBag, 422);
        }
        Auth::user()->update(['password' => $new, 'change_password_at' => Carbon::now()->format('Y-m-d H:i:s')]);
        $url_intended = session()->get('url-intended');
        session()->remove('url-intended');
        return response()->json(['response' => 'ok', 'url' => $url_intended ?? route('dashboard')]);
    }
}
