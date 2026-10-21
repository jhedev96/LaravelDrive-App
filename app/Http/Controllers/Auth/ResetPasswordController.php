<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ResetPasswordController extends Controller implements HasMiddleware
{
    protected $redirectTo = '/drive';

    public static function middleware(): array
    {
        return [new Middleware('guest')];
    }

    public function showResetForm(Request $request, $token = null)
    {
        return view('auth.passwords.reset', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Reset the given user's password.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(6)],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (object $user, string $password) {
                $user->forceFill([
                    'password' => $password, // hashed via model cast
                    'remember_token' => Str::random(10),
                ])->save();

                Auth::guard()->login($user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['success' => true, 'redirectTo' => $this->redirectTo]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
