# TASKS — Modernization Tracking

> Legend: `[ ]` belum · `[~]` in-progress · `[x]` done · `[!]` diblokir perlu owner/env
> Branch kerja: `modernization` (dari `develop`) — commit lokal dulu, push via gh setelah test ijo.
> Dev env: Ubuntu 24.04 ARM64 · PHP 8.5 (PPA ondrej) · Composer 2.x · Node 24 · **SQLite** (dev/test).

## WS0 — Preflight
- [~] W0.1 Install PHP 8.5 (PPA ondrej) + Composer terbaru di box
- [x] W0.2 Tulis `PLAN.md` + `TASKS.md` di repo
- [x] W0.3 Branch `modernization` dari `develop` + push pertama (docs)
- [x] W0.4 Repo hygiene: hapus `_ide_helper.php`, repas `.gitattributes` (baris `CHANGELOG.md` mati), tambah `CHANGELOG.md`
- [ ] W0.5 (opsional, perlu konfirmasi owner) prune ~26 branch `origin/dependabot/*`

## WS1 — Skeleton Laravel 13 (backend rebase)
- [ ] W1.1 Scaffold Laravel 13 fresh (composer create-project) di dir kerja; salin skeleton ke repo (ganti kartu mati: server.php, Kernel, seeds classmap)
- [ ] W1.2 `bootstrap/app.php` builder (withRouting/withMiddleware/withExceptions), aliases, rate-limiter group (`chunk` terpisah dari throttle global)
- [ ] W1.3 Port konfig: app/auth/filesystems (local root `storage/app/uploads`)/database (sqlite dev + mysql prod)/queue/cache/mail/session/logging/cors/services
- [ ] W1.4 `.env.example` + `phpunit.xml` modern (MAIL_MAILER, QUEUE_CONNECTION, sqlite in-memory, schema PHPUnit 11/12)
- [ ] W1.5 Models refactor: `User` (Passport 13 `OAuthenticatable`, cast permissions `array`, hapus serialize), `Role` (cast), `File`/`Folder` (hapus `newQuery` override + boot observe→ObservedBy + scope type), `UserMeta`/`Tag`/`ShareableLink` casts
- [ ] W1.6 Migrations modern-Anon class + fakerphp/faker; seeds → `Database\Seeders`; hapus inline `passport:install`
- [ ] W1.7 Responses/services refactor ke Flysystem 3: `AudioVideoResponse` StreamedResponse+Range, `DownloadResponse` disk `local` (fiz disk `uploads_local` bug), ZipArchive di tmp
- [ ] W1.8 Auth controller mandiri (login/register/reset/social) tanpa laravel/ui; `Input` facade → `request()`; PAT flow behavoir tetap (token di login & register)
- [ ] W1.9 Helper functions.php → hapus serialize helpers (UserMeta pakai casts), simpan sisa yang real dipakai

## WS2 — Packages swap & integration
- [ ] W2.1 composer.json modern (lihat PLAN.md §packages) + npm baris Vite stack (`@vitejs/plugin-vue`, laravel-vite-plugin, vue@3, vuetify@4, pinia@4, axios@1, eslint 9 flat, @mdi/font, plyr, dropzone)
- [ ] W2.2 Passport 13 config + UUID client decision + migration oauth
- [ ] W2.3 chunk-upload: buang `packages/laravel-chunk-upload` (tree) → `pion/laravel-chunk-upload ^1.6`; `FileController::store` validasi kontrak per-chunk `{done,status}` + final `{data}`; rate limiter `chunk`
- [ ] W2.4 Intervention Image 4 refactor (`ResizedImage` job + `createThumbanile`), hapus `sleep(5)` → job chaining cleanup
- [ ] W2.5 Socialite 5 + socialiteproviders/manager (fb, twitter-x, google, github, youtube, twitch, instagram); transport goog `.env`
- [ ] W2.6 spatie/laravel-translation-loader + driver DB; endpoint `/api/translation/*` kontrak tetap
- [ ] W2.7 Laravel Reverb + routes/channels.php (event FileCreated/FileDeleted/FileShared skeleton)
- [ ] W2.8 Flysystem 3 disks: s3 (league/flysystem-aws-s3-v3), doSpaces via s3 config, dropbox (spatie), ftp; RACKSPACE dibuang (sunset)

## WS3 — Realisasi fitur (dari 0-usage)
- [ ] W3.1 Passport admin API endpoints + route + policy owner
- [ ] W3.2 Passport UI FE (port Clients/AuthorizedClients/PersonalAccessTokens ke Vuetify 4) + route `settings/oauth`
- [ ] W3.3 Broadcast events + channel + FE Echo wiring realtime (list/controller sync)
- [ ] W3.4 Storage driver selection per file (driver modernis PATH/FLOWS di File model/observers)
- [ ] W3.5 Translation CRUD complements (driver database spatie) + FE pages port v4
- [ ] W3.6 UploadToCloud job siap S3/DO/Dropbox/FTP (multi-disk safe + tests)

## WS4 — Bug fixes (inline, dari audit)
- [ ] W4.1 `uploads_local` disk hilang (DownloadResponse, CopyFileController)
- [ ] W4.2 Vuex getter `isAuthenticated` / `root:true` (hilang saat Pinia migration)
- [ ] W4.3 Blade `LD.csftToken` typo (hapus atau jadi benar `csrfToken`)
- [ ] W4.4 `ApiController::errorForbidden()` 500 → 403
- [ ] W4.5 `CopyFileController::copyFileStorate` path/storage fix + nama method typo
- [ ] W4.6 `File::getPermission()` meta vs kolom permissions unifikasi
- [ ] W4.7 `File::children()/parent()` kolom `folder_id` → `parent_id` (+global scope `fsType` ngentong mati)
- [ ] W4.8 `FileController::destroyAll` `whereIn` fix; DownloadController `sizeof(null)` guard
- [ ] W4.9 `ResizedImage` exception handling proper
- [ ] W4.10 Job diag: error forbidden kontrak saat permissions read lors of sharing

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
- [ ] W6.1 GH Actions: backend (PHP 8.4?/composer/pint/phpunit sqlite) + frontend (node 22, eslint + vite build)
- [ ] W6.2 Test targets: chunk flow, download (single/zip), shareable public access, shares, passport revoke, translation CRUD
- [ ] W6.3 README baru (PHP 8.4+, Node 22+, passport:keys + personal client, reverb, npm run dev), screenshot doc preserved

## WS7 — Release
- [ ] W7.1 PR per phase ke `develop`; merge quando CI ijo
- [ ] W7.2 Tag `v2.0.0` + CHANGELOG record
- [ ] W7.3 Prod migration checklist (backup → migrate → reverb → queue restart → revoke/re-issue PAT → smoke matrix)
