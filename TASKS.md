# TASKS — Modernization Tracking

> Legend: `[ ]` belum · `[~]` in-progress · `[x]` done · `[!]` diblokir perlu owner/env
> Branch kerja: `modernization` (dari `develop`) — commit lokal dulu, push via gh setelah test ijo.
> Dev env: Ubuntu 24.04 ARM64 · PHP 8.5 (PPA ondrej) · Composer 2.x · Node 24 · **SQLite** (dev/test).

## WS0 — Preflight
- [x] W0.1 Install PHP 8.5 (PPA ondrej) + Composer terbaru di box
- [x] W0.2 Tulis `PLAN.md` + `TASKS.md` di repo
- [x] W0.3 Branch `modernization` dari `develop` + push pertama (docs)
- [x] W0.4 Repo hygiene: hapus `_ide_helper.php`, repas `.gitattributes` (baris `CHANGELOG.md` mati), tambah `CHANGELOG.md`
- [x] W0.5 (opsional, perlu konfirmasi owner) prune ~26 branch `origin/dependabot/*` — **22 branch dependabot dihapus 2026-10-21 (konfirmasi owner)**

> Keputusan owner 2026-10-21 (arahan umum):
> - **Prod DB: upgrade-in-place** (MySQL lama tetap jadi sumber; schema baru additive, serialize permissions dipertahankan D13/D15 — terkunci untuk W7)
> - **FE: TypeScript** (revisi dari JS-first; WS5 pakai Vite + vue-tsc)
> - **Infra: bare-metal** (tanpa Sail/Docker)
> - **Stripe: keputusan revisi** — semua hit `stripe` di repo/histori = boilerplate skeleton L5.6 + class CSS `table-striped` (false positive); TIDAK ADA fitur billing sebelumnya. Menunggu kembali-wakdari keputusan owner (batal vs buat-fitur-baru)

## WS1 — Skeleton Laravel 13 (backend rebase) — **MILESTONE: commit `aa937c0`, test green**
- [x] W1.1 Scaffold Laravel 13 fresh (composer create-project) di dir kerja; salin skeleton ke repo (ganti kartu mati: server.php, Kernel, seeds classmap)
- [x] W1.2 `bootstrap/app.php` builder (withRouting/withMiddleware/withExceptions), aliases, rate-limiter group (`chunk` terpisah dari throttle global)
- [x] W1.3 Port konfig: app/auth/filesystems (local root `storage/app/uploads`)/database (sqlite dev + mysql prod)/queue/cache/mail/session/logging/cors/services
- [x] W1.4 `.env.example` + `phpunit.xml` modern (MAIL_MAILER, QUEUE_CONNECTION, sqlite in-memory, schema PHPUnit 11/12)
- [~] W1.5 Models refactor: `User` (Passport 13 `OAuthenticatable`, cast permissions `array`, hapus serialize), `Role` (cast), `File`/`Folder` (hapus `newQuery` override + boot observe→ObservedBy + scope type), `UserMeta`/`Tag`/`ShareableLink` casts — **OAuthenticatable + ObservedBy + scope OK; serialize accessors DIPERTAHANKAN sesuai D13 (data legacy), cast JSON ditunda ke W7**
- [x] W1.6 Migrations modern-Anon class + fakerphp/faker; seeds → `Database\Seeders`; hapus inline `passport:install`
- [x] W1.7 Responses/services refactor ke Flysystem 3: `AudioVideoResponse` StreamedResponse+Range, `DownloadResponse` disk `local` (fiz disk `uploads_local` bug), ZipArchive di tmp
- [x] W1.8 Auth controller mandiri (login/register/reset/social) tanpa laravel/ui; `Input` facade → `request()`; PAT flow behavoir tetap (token di login & register) — semua controller pakai `HasMiddleware` statik (gaya L11+)
- [~] W1.9 Helper functions.php → hapus serialize helpers (UserMeta pakai casts), simpan sisa yang real dipakai — **helpers dipertahankan penuh (D13: `maybe_serialize`/`is_serialized` masih dipakai), review ulang di W7**

## WS2 — Packages swap & integration
- [~] W2.1 composer.json modern (lihat PLAN.md §packages) — **composer side DONE (milestone)**; npm baris Vite stack (`@vitejs/plugin-vue`, laravel-vite-plugin, vue@3, vuetify@4, pinia@4, axios@1, eslint 9 flat, @mdi/font, plyr, dropzone) ikut WS5
- [x] W2.2 Passport 13 config + UUID client decision + migration oauth — config published, oauth migrations di-repo (stamp 113612, single set), client UUID default dipertahankan, TTL 1d/2d via `Passport::*ExpireIn` di AppServiceProvider; dev: `passport:keys` + `passport:client --personal`
- [~] W2.3 chunk-upload: buang `packages/laravel-chunk-upload` (tree) → `pion/laravel-chunk-upload ^1.6`; `FileController::store` validasi kontrak per-chunk `{done,status}` + final `{data}`; rate limiter `chunk` — kontrak per-chunk butuh verifikasi FE-level (W6)
- [x] W2.4 Intervention Image 4 (`ResizedImage` + thumbnail pipeline): job fungsional (guard meta/cloud + per-size catch), **terpasang di alur upload** (`FileController::store` → `ResizedImage::dispatch`; legacy nulis job tapi GAK pernah manggil `createThumbanile` maupun dispatch), rename typo `createThumbanile` → `createThumbnail` (internal), `ThumbnailPipelineTest` 3 ijo (thumb tergenerate `120x80-<name>` + flag `meta.sizes` true; bytes sampah cuma ke-log; non-image skip) — dev `.env` queue `database`, test `sync` (inline)
- [~] W2.5 Socialite 5 + socialiteproviders/manager (fb, twitter-x, google, github, youtube, twitch, instagram); transport goog `.env` — config + routes OK, provider tuning saat uji
- [~] W2.6 spatie/laravel-translation-loader + driver DB; endpoint `/api/translation/*` kontrak tetap — endpoint replay per D14 OK, verifikasi kontrak FE saat rebuild
- [x] W2.7 Laravel Reverb + routes/channels.php — config + kanal `drive.{id}` (+legacy `App.User.{id}`); events backend broadcast penuh (lihat W3.3); FE Echo wiring = WS5
- [x] W2.8 Flysystem 3 disks: s3 (league/flysystem-aws-s3-v3), doSpaces via s3 config, dropbox (**`spatie/flysystem-dropbox` + `Storage::extend('dropbox')` di AppServiceProvider ✓** `41de492`), ftp; RACKSPACE dibuang (sunset)

## WS3 — Realisasi fitur (dari 0-usage)
- [x] W3.1 Passport admin API — `Passport::$registersJsonApiRoutes = true` di `AppServiceProvider::register()` (harus sebelum package provider boot) → rute first-party `oauth/clients|tokens|scopes|personal-access-tokens` balik seperti Passport 8 default; semua user-scoped (owner) + guest 401; `PassportJsonApiTest` 5 ijo (client CRUD + PAT issue/list/revoke + foreign tak bisa kelola); FE pages `settings/oauth` = W3.2/WS5 (komponen passport legacy 0-usage: gak pernah diwired router)
- [ ] W3.2 Passport UI FE (port Clients/AuthorizedClients/PersonalAccessTokens ke Vuetify 4) + route `settings/oauth`
- [~] W3.3 **Broadcast events realtime (backend)** — `app/Events/{FileEntryCreated,FileEntriesChanged,FileEntriesShared}` (ShouldBroadcastNow, kanal privat `drive.{userId}`, legacy `App.User.{id}` tetap), dipasang di FileController::store / DeleteFileController::delete / SharesController::addUsers; `BroadcastEventsTest` 4 ijo. Kontrak FE (Echo): `entry.created {entry:{hash,id,name,type,mime,extension,file_size,parent_id}}`, `entries.changed {action: trash|restore|deleteforever, entries:[hash]}`, `entries.shared {entries:[hash], permissions:int}` — FE wiring + dev Reverb = sisa WS5
- [x] W3.4 Storage driver selection per file — `driver` opsional di `/api/file` upload (422 bila tak dikenal, kontrak lama aman: param gak wajib), `storeLocalUpload` ikut disk milik baris (legacy selalu paksa `local` → passthrough driver ter-clobber), single-download & copy ikut disk `driver` (legacy `local` hardcoded); folder/zip recursive sudah `$file->driver ?: 'local'`; FE picker = optional (defer) — `StorageDriverTest` 7 ijo
- [~] W3.5 Translation CRUD complements (driver database spatie) + FE pages port v4 — endpoint+test ijo (W6.2); FE page = WS5
- [x] W3.9 **Shareable API publik (stateless, owner-approved)** — sisi tamu `/file/s/{hash}` yang di app lama GAK ADA (blade `sharedfiles` cuma cangkang kosong tanpa JS; semua route link ada di dalam `auth:api`): `GET /api/share/links/{hash}` info (password-gated → `{requires_password:true}` bawaan; expired/trashed → 404), `POST .../check` (password salah → 401, bener → payload penuh dgn file+children), `POST .../download` (hormati `allow_download` → 403 kalau off; password di body tiap request, tanpa session; file → stream slug filename dari disk `driver` (W3.4-aware), folder → zip `folderDownload`); `check()` route-model lama (unregistered, buta-hash) dibuang jadi `publicCheck` hash-based; throttle default api (60/menit/ip) — `ShareablePublicTest` 7 ijo; **page FE `sharedfiles` = WS5** (kontrak: payload `data.{requires_password,allow_download,file.name,...}`, `data.link` = URL `/file/s/{hash}`)
- [x] W3.6 UploadToCloud job multi-disk safe — move `file_name/name` dir local→`filesystems.cloud`, `updatePublicPaths`, hapus dir lokal, no-op bila sudah di disk itu; post-aktif admin/queue (legacy hanya komentarnya; TIDAK auto-dispatch) — test ijo (StorageDriverTest)
- [~] W3.7 **Billing skeleton (baru, D18)**: migrasi `plans` + `users.plan_id/plan_expires_at` + `plan_orders`; seed free (kuota env, default generous) + vip; `User::storageUsed()` + enforcement di upload/folder (error `quota_exceeded`); admin manage plan/status — **migrations/gateways/enforcement/seed/6 test ijo (`4f3170f`); admin manage UI tinggal**
- [x] W3.8 **Midtrans QRIS (D18)** — Snap token order, webhook `POST /api/webhooks/midtrans` (tanpa CSRF + verifikasi signature), update status order + aktivasi plan, gateway interface abstrak (Xendit fallback) — `MidtransFlowTest` ijo (`82add43`); kredensial sandbox + admin oversight uji nyata nyusul

## WS4 — Bug fixes (inline, dari audit)
- [x] W4.1 `uploads_local` disk hilang (DownloadResponse, CopyFileController) — semua dipanggil disk `local`
- [ ] W4.2 Vuex getter `isAuthenticated` / `root:true` (hilang saat Pinia migration)
- [x] W4.3 Blade `LD.csftToken` typo (hapus atau jadi benar `csrfToken`) — blade sekarang `csrfToken`; FE bacaan getter ikut WS5
- [x] W4.4 `ApiController::errorForbidden()` 500 → 403
- [x] W4.5 `CopyFileController::copyFileStorate` path/storage fix + nama method typo — jadi `copyFileStorage()` + path `getStoragePath()` disk `local`
- [~] W4.6 `File::getPermission()` meta vs kolom permissions unifikasi — diklasifikasi ulang: pola meta konsisten dengan tulisan controller (no-op), verifikasi di W6
- [x] W4.7 `File::children()/parent()` kolom `folder_id` → `parent_id` (+global scope `fsType` ngentong mati) — relasi + scope `fsType` where type=folder jadi hidup
- [x] W4.8 `FileController::destroyAll` `whereIn` fix; DownloadController `sizeof(null)` guard
- [x] W4.9 `ResizedImage` exception handling proper — Throwable catch + guard `meta['sizes']` + logging
- [x] W4.10 Job diag: error-forbidden kontrak saat permissions (share) — asal: legacy `errorForbidden()` memang `setStatusCode(500)` + CODE_FORBIDDEN → sudah diselesaikan W4.4 (403) dan semantik 403 share terkunci `ShareTest`; `ResizedImage` error handling rapi W4.9; tidak ada caller lain `errorForbidden` di legacy
- [x] W4.11 **file_size tak pernah disimpan saat upload** (bug usaha lama ditemukan saat menulis test W6.2) — kini di-set pada penghimpunan akhir store + masuk `$fillable` File; kebutuhan `storageUsed()`/kuota jadi hidup
- [x] W4.12 **Injeksi Request konstruktor => instance usang** di Delete/Copy/Starred/Download controller (test-flow multi-request: `action` selalu NULL → deleteforever turun ke soft-delete secara diam-diam) → semua pindah ke penyuntikan metode; **cursor() + mutasi tabel yang sama** (deleteEntries, copyEntries) → sudah di-materialisasi melalui `get()`
- [x] W4.13 **Shareable-link update/destroy buta-model**: route param `{id}` gak cocok dgn type-hint `$link` → binding implisit gak jalan → container injek model KOSONG → `$link->save()` jadi INSERT duplikat (hash NOT NULL → 500). Route param diganti `{link}` (nilai path tetap id, kontrak FE aman); ketemu saat nulis `ShareTest`
- [x] W4.14 User `permissions` (D13 serialize): mutator `setPermissionsAttribute` men-serialize otomatis — wajib kirim **array mentah** dari factory/forms, BUKAN string hasil `serialize()` (double-serialization bikin `hasPermission` selalu false)
- [x] W4.15 **`config/broadcasting.php` hilang** (tidak ikut tersalin saat porting skeleton) → `.env BROADCAST_CONNECTION` tidak pernah terbaca → driver default `null` diam-diam (NullBroadcaster; auth broadcasting balas 200 body kosong, dan test HTTP channel 403 misterius karena kanal terdaftar di broadcaster yang lain) — dipublikasi via `config:publish broadcasting` (reverb/pusher/log/null). `phpunit.xml` sengaja mengunci `BROADCAST_CONNECTION=null` untuk test (tanpa jaringan); test channel-auth pakai koneksi pusher palsu + re-require channels.php. Catatan debugging: `Log::debug` tetap menembus HTTP test; stderr child phpunit hilang bila proses mati prematur (OOM flaky di box — run ulang setelah wiring sama malah ijo)
- [x] W4.16 **Copy endpoint patah diam-diam (0-usage, ketemu via `StorageDriverTest`)**: (a) porting hilang `use Illuminate\Support\Str;` → `Class Str not found` 500 saat copy (legacy pakai `\Str::` global-alias); (b) global-scope subselect `stared` nempel sebagai raw attribute → `replicate()` ikut menyuntik kolom `stared` ke INSERT baris baru (kolom tak ada → 500) — `replicate(['stared'])` + import Str
- [x] W4.17 **Konflik stack image: Laravel 13 punya image layer native sendiri** (`Illuminate\Image`, bind container `image`, driver-nya adapter `Intervention/ImageManager::usingDriver()` yang HANYA ada di Intervention 4.2+) sementara porting WS1 pin `intervention/image-laravel ^1.3` (Intervention 3.11) yang facade-nya JUGA bind `image` → binding native menang → `Image::read() == manager framework == crash "undefined method usingDriver"` di `ResizedImage` (log-nya tersimpan walau job di-catch per-size — `Log::error` memang menembus child). Fix: upgrade `image-laravel ^4.1` (Intervention 4.4; binding pindah ke `intervention.image`, tak konflik) + API v4: `read()` renamed `decodePath()` (2 titik: job `ResizedImage`, `createThumbnail`); `php -r "new ImageManager(driver:'gd')"` string `'gd'` gak valid di v4 — driver harus class, defaults package udah bener (probe container `app('intervention.image')` CHAIN-OK). Bonus: `tests/TestCase` kini bersih-bersih storage per test (hermetik; box sempat numpuk ~150 dir sampah upload)

## WS5 — Frontend rebuild (Vite + Vue 3 + Vuetify 4 + Pinia)
- [ ] W5.1 Vite config (laravel-vite-plugin, entries admin/auth/common, aliases @ac/@auth/@common) + blade `@vite`
- [ ] W5.2 Bootstrap atten: vuetify 4 (createVuetify, theme light/dark colors carryover, @mdi/font npm), vue-router 4 (base `/drive`, 13 routes), Pinia (root/Users/Media/Translation sesuai module sesuai tutup getter bug)
- [ ] W5.3 Port 46 `.vue` + composable: Bus→$bus (mitt), `.sync`→`v-model:$range`, slot-scope→`#default`, `$set`/`$isServer`/`beforeDestroy` modern; grid VFlex/VLayout→VRow/VCol
- [ ] W5.4 vee-validate 3→4 (6 ValidationProvider) / UX-polish komponent (Bagaimana schema kecil; keep simple)
- [ ] W5.5 Dropzone wrapper baru (chunk contract identik) + plyr tetap (.destroy-on-unmount fix); vue-multiselect → dicocokan komponen Vuetify (2 files)
- [ ] W5.6 axios 1.x bootstrap + consolidasi helper `LD` duplikat (common/config.js vs mixin/index.js)
- [ ] W5.7 Auth pages Vuetify 4 (login/register/reset) + social buttons 7 provider
- [ ] W5.8 ESLint 9 flat config + `eslint-plugin-vue`@10, hapus babel-eslint/eslint-loader/laravel-mix discard

## WS6 — QA, CI, Docs
- [x] W6.1 backend GH Actions **ijo** (run `40131662032`): php 8.5 + ext (mbstring/gd/exif/pdo_sqlite/zip), composer install, key+passport:keys, pint --dirty, php artisan test sqlite in-memory — trigger push `modernization`/`develop` + PR ke develop; **frontend workflow nyusul WS5** (node 22, eslint + vite build)
- [x] W6.2 Test targets: chunk flow, download (single/zip), shareable public access, shares, passport revoke, translation CRUD, **broadcast events**, **shareable public (guest) API** — SELESAI (slice 1): `FileFlowTest` 7 (chunk contract, download/zip, trash+forever, index, kuota e2e); `ShareTest` 7; `TranslationTest` 4; `LogoutTest` 1; `BroadcastEventsTest` 4 (entry.created di kanal pemilik, entries.changed trash & deleteforever, entries.shared ke penerima, otorisasi kanal private own=200 signed/other=403); `StorageDriverTest` 7; `PassportJsonApiTest` 5; `ThumbnailPipelineTest` 3; `ShareablePublicTest` 7 — residual kecil: semantik zip folder (termasuk children trashed — perilaku legacy, catat keputusan) + FE-level chunk verification pas WS5
- [ ] W6.3 README baru (PHP 8.4+, Node 22+, passport:keys + personal client, reverb, npm run dev), screenshot doc preserved — **gotcha: JANGAN pakai `passport:install` berulang setelah migrations di-publish (ia publish ulang oauth migrations w/ stamp baru → duplikat); gunakan `passport:keys` + `passport:client --personal`** — sudah dimasukkan ke catatan setup

## WS7 — Release
- [ ] W7.1 PR per phase ke `develop`; merge quando CI ijo
- [ ] W7.2 Tag `v2.0.0` + CHANGELOG record
- [ ] W7.3 Prod migration checklist (backup → migrate → reverb → queue restart → revoke/re-issue PAT → smoke matrix)
