<?php

use App\Http\Controllers\API\V1\BillingController;
use App\Http\Controllers\API\V1\CopyFileController;
use App\Http\Controllers\API\V1\DeleteFileController;
use App\Http\Controllers\API\V1\DownloadController;
use App\Http\Controllers\API\V1\FileController;
use App\Http\Controllers\API\V1\FolderController;
use App\Http\Controllers\API\V1\MoveFileController;
use App\Http\Controllers\API\V1\RoleController;
use App\Http\Controllers\API\V1\ShareableLinkController;
use App\Http\Controllers\API\V1\SharesController;
use App\Http\Controllers\API\V1\StarredController;
use App\Http\Controllers\API\V1\StatsController;
use App\Http\Controllers\API\V1\UserController;
use App\Http\Controllers\Billing\MidtransWebhookController;
use App\Http\Controllers\Translation\LanguageController;
use App\Http\Controllers\Translation\LanguageTranslationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (auth:api = Passport bearer tokens)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    Route::delete('users/delete-multiple', [UserController::class, 'deleteMultiple'])->name('users.delete-multiple');
    Route::get('users/search', [UserController::class, 'search'])->name('users.search');
    Route::resource('users', UserController::class, ['except' => ['edit']]);

    Route::resource('roles', RoleController::class, ['except' => ['edit']]);
    Route::post('roles/{role_id}/attach_users', [RoleController::class, 'attachUser'])->name('roles.attach-user');
    Route::get('permissions', [RoleController::class, 'getAbilities'])->name('permissions');

    Route::resource('folder', FolderController::class);

    Route::delete('file/delete', [DeleteFileController::class, 'delete'])->name('file.delete');
    Route::get('file/trash', [DeleteFileController::class, 'trash'])->name('file.trash');
    Route::get('file/star', [StarredController::class, 'index'])->name('file.star');
    Route::post('file/copy', [CopyFileController::class, 'copy'])->name('file.copy');
    Route::post('file/move', MoveFileController::class)->name('file.move');
    Route::get('stats', [StatsController::class, 'index'])->name('stats.index');
    Route::post('file', [FileController::class, 'store'])->middleware('throttle:chunk')->name('file.store');
    Route::resource('file', FileController::class);

    Route::post('tag/star', [StarredController::class, 'add'])->name('tag.star');
    Route::post('tag/unstar', [StarredController::class, 'remove'])->name('tag.unstar');

    Route::post('/download', [DownloadController::class, 'download'])->name('download');

    // sharing
    Route::get('shared/files', [SharesController::class, 'sharedWithMe'])->name('shared.files');
    Route::get('shared/file/{file_id}/share-with', [SharesController::class, 'sharedWith'])->name('shared.share-with');
    Route::post('shares/add-users', [SharesController::class, 'addUsers'])->name('shares.add-users');
    Route::put('shares/update-users', [SharesController::class, 'updateUsers'])->name('shares.update-users');
    Route::delete('shares/remove-user/{userId}', [SharesController::class, 'removeUser'])->name('shares.remove-user');

    // shareable links
    Route::get('shareable-links/file/{fileId}', [ShareableLinkController::class, 'getFileShareableLink'])->name('shareable-links.get');
    Route::post('shareable-links/file/{fileId}', [ShareableLinkController::class, 'storeFileShareableLink'])->name('shareable-links.store');
    Route::put('shareable-links/{link}', [ShareableLinkController::class, 'update'])->name('shareable-links.update');
    Route::delete('shareable-links/{link}', [ShareableLinkController::class, 'destroy'])->name('shareable-links.destroy');

    // translations
    Route::get('translation/languages', [LanguageController::class, 'index'])->name('translation.languages.index');
    Route::post('translation/languages', [LanguageController::class, 'store'])->name('translation.languages.store');
    Route::get('translation/{language}/translations', [LanguageTranslationController::class, 'index'])->name('translation.translations.index');
    Route::post('translation/{language}/translations', [LanguageTranslationController::class, 'store'])->name('translation.translations.store');
    Route::put('translation/{language}/translations', [LanguageTranslationController::class, 'update'])->name('translation.translations.update');

    // billing (D18) — plans catalog + QRIS orders
    Route::get('billing/plans', [BillingController::class, 'index'])->name('billing.plans');
    Route::get('billing/orders', [BillingController::class, 'orders'])->name('billing.orders.index');
    Route::post('billing/orders', [BillingController::class, 'store'])->name('billing.orders.store');
});

// payment notifications (signature-guarded, no auth)
Route::post('webhooks/midtrans', MidtransWebhookController::class)->name('webhooks.midtrans');

// W3.9 public shareable-link surface for /file/s/{hash} (guest, stateless —
// owner-approved: the password travels with every request, no session).
// The default `api` throttle (60/min per ip) rates the download path.
Route::prefix('share/links')->group(function (): void {
    Route::get('{hash}', [ShareableLinkController::class, 'publicLink'])->name('share.links.show');
    Route::post('{hash}/check', [ShareableLinkController::class, 'publicCheck'])->name('share.links.check');
    Route::post('{hash}/download', [ShareableLinkController::class, 'publicDownload'])->name('share.links.download');
});
