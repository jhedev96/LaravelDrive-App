<?php

namespace App\Http\Controllers\API\V1;

use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/stats — the numbers for the SPA dashboard.
 *
 * The legacy dashboard shipped hard-coded digits (3796 files, 23 users,
 * 650.3 MB…) that no endpoint ever fed; the backend never had a stats
 * route. This endpoint realises the widgets: each user sees their own
 * live counts and storage use; the user count is revealed to
 * administrators only.
 */
class StatsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $own = $user->files();

        $stats = [
            'files' => (clone $own)->where('type', 'file')->count(),
            'folders' => (clone $own)->where('type', 'folder')->count(),
            'storage_bytes' => $user->storageUsed(),
            'users' => $user->hasPermission('administrator') ? User::query()->count() : null,
        ];

        return response()->json(['data' => $stats]);
    }
}
