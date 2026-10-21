<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use App\UserMeta;
use App\Traits\CaptureIpTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialController extends Controller
{
    /**
     * Redirect to the OAuth provider.
     */
    public function getSocialRedirect($provider)
    {
        $providerKey = config('services.'.$provider);

        if (empty($providerKey) || empty($providerKey['client_id']) || empty($providerKey['client_secret'])) {
            return redirect()->to('login')
                ->with('error', "$provider is not configured yet!!");
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the callback from the OAuth provider.
     */
    public function getSocialHandle($provider, \Illuminate\Http\Request $request)
    {
        if ($request->input('denied')) {
            return redirect()->to('login')
                ->with('status', 'danger')
                ->with('message', "$provider denied");
        }

        $socialUserObject = Socialite::driver($provider)->user();

        $socialUser = null;

        $email = $socialUserObject->email ?: 'missing'.Str::random(10).'@'.Str::random(10).'.example.org';

        // check if email is already registered
        $userCheck = User::withTrashed()->where('email', '=', $email)->first();

        if (! $userCheck) {
            $sameSocialId = UserMeta::where('key', '=', $provider)
                ->where('value', '=', (string) $socialUserObject->id)
                ->first();

            if (! $sameSocialId) {
                $ipAddress = new CaptureIpTrait();

                $fullname = explode(' ', (string) $socialUserObject->name);
                if (count($fullname) === 1) {
                    $fullname[1] = '';
                }

                $username = $socialUserObject->nickname;
                if ($username == null) {
                    $username = '';
                    foreach ($fullname as $name) {
                        $username .= $name;
                    }
                }

                // make sure username does not already exist in DB
                $username = $this->checkUserName($username, $email);

                $user = User::create([
                    'name' => $username,
                    'firstname' => $fullname[0],
                    'lastname' => $fullname[1] ?? '',
                    'email' => $email,
                    'password' => Str::random(40), // hashed via model cast
                    'avatar' => $socialUserObject->getAvatar(),
                    'status' => 1,
                    'email_verified_at' => Carbon::now(),
                    'ip' => $ipAddress->getClientIp(),
                ]);

                $socialUser = $user;

                // record the provider relation so future logins link back correctly
                $socialUser->meta()->create([
                    'key' => $provider,
                    'value' => $socialUserObject->id,
                    'hide' => true,
                ]);
            } else {
                $socialUser = $sameSocialId->user;
            }

            auth()->login($socialUser, true);

            return redirect()->intended('/drive');
        }

        auth()->login($userCheck, true);

        return redirect()->intended('/drive');
    }

    /**
     * Check username against database and return valid username.
     */
    public function checkUserName($username, $email)
    {
        $userNameCheck = User::withTrashed()->where('name', '=', $username)->first();

        if ($userNameCheck) {
            do {
                $username = $this->generateUserName($username);
                $newCheck = User::withTrashed()->where('name', '=', $username)->first();
            } while ($newCheck != null);
        }

        return $username;
    }

    public function generateUserName($username)
    {
        return $username.'_'.Str::random(10);
    }
}
