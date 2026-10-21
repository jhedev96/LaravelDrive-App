<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller implements HasMiddleware
{
    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/drive';

    public static function middleware(): array
    {
        return [
            new Middleware('guest', except: ['logout']),
            new Middleware('auth', only: ['logout']),
        ];
    }

    /**
     * Show the application's login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle a login request to the application.
     */
    public function login(Request $request)
    {
        $field = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        $request->validate([
            'email' => 'required|min:1',
            'password' => 'required|min:6',
            'remember' => 'nullable|boolean',
        ], [], [
            'email' => $field,
            'password' => 'password',
        ]);

        $credentials = [
            $field => $request->input('email'),
            'password' => $request->input('password'),
        ];

        $remember = $request->boolean('remember');

        if (auth()->attempt($credentials, $remember)) {
            $user = auth()->user();

            // update the last login timestamp and ip
            $user->timestamps = false;
            $user->last_loged_in = now();
            $user->ip = $request->ip();
            $user->save();
            $user->timestamps = true;

            $token = $user->createToken('Laravel Personal Access Client')->accessToken;

            return [
                'success' => true,
                'redirectTo' => $this->redirectTo,
                'token' => $token,
            ];
        }

        throw ValidationException::withMessages([
            $field => [__('auth.failed')],
        ]);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        $user = auth()->user();

        // revoke all previously issued API tokens
        if ($user) {
            $user->tokens()->delete();
        }

        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        \Cookie::queue(\Cookie::forget('laravel_session'));
        \Cookie::queue(\Cookie::forget('laravel_token'));

        return response()->json(null, 204);
    }
}
