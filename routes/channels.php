<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Every user owns a private `drive.{id}` channel that reflects all drive
| activity they can see (new owned/uploaded entries, deletions, shares).
| Attached clients receive the broadcast events (W3.2, Reverb).
|
*/

Broadcast::channel('drive.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// legacy channel name kept for old clients that still connect to it
Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
