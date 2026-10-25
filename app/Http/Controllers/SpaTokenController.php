<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /spa-token — SPA bearer bootstrap.
 *
 * The legacy FE stored a Passport bearer in localStorage after a login
 * through the SPA; any other way in (session login, registration form,
 socialite) produced a working SESSION but 401s on every /api/* call
 * because the API guard is auth:api. This endpoint mints a personal
 * access token for the session user so the SPA can arm itself on load
 * (W4.21).
 */
class SpaTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->user()->createToken('SPA bootstrap');

        return response()->json([
            'token' => $token->accessToken,
        ]);
    }
}
