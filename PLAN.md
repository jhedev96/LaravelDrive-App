# PLAN — LaravelDrive Modernization (Laravel 13 / Vue 3 + Vuetify 4)

Dokumen hidup berisi rencana modernisasi aplikasi **LaravelDrive** (Google-Drive-like file storage:
file/folder, sharing, shareable links, trash/starred, preview, tag, roles/permissions, social login,
translation admin) dari stack beku era 2020 ke stack October 2026.

> Sumber terinci keputusan & bukti audit: lihat `TASKS.md` dan riwayat commit di branch ini.
> Rencana detail internal: `/home/.opencode/plan/laraveldrive-modernization.md` (di mesin kerja).

## 0. Keputusan final (owner-approved)

| ID | Topik | Keputusan |
|----|-------|-----------|
| D1 | PHP | **8.5** via PPA ondrej (Laravel 13 support 8.3–8.5) |
| D2 | Framework | **Laravel 13.x** |
| D3 | Frontend | **Vue 3 + Vuetify 4 + Vite 8 + Pinia 4** (lompat langsung v4, bukan v3) |
| D4 | Auth | **Laravel Passport 13** (patuh CVE-2026-39976, fix ≥13.7.1) — realisasi Passport UI + OAuth API |
| D5 | Branch `React` | **Dibiarkan hidup** sebagai eksperimen terpisah — BUKAN jalur produksi |
| D6 | Kode "0-usage" & bug lama | Dianggap **fitur belum-final + bugfix**; dihidupkan dalam WS3/WS4, BUKAN dibuang |
| D7 | Build FE | Buang **Laravel Mix 5** → **Vite 8** + laravel-vite-plugin |
| D8 | Chunk upload | Buang fork vendored `packages/laravel-chunk-upload` → **pion/laravel-chunk-upload ^1.6** (upstream aktif, tested Laravel 13); kontrak API Dropzone dipertahankan |
| D9 | Translation | Buang `waavi/translation` (abandoned) → **spatie/laravel-translation-loader**; endpoint tak berubah |
| D10 | Broadcast | Pusher scaffold mati → **Laravel Reverb** (first-party WS) |
| D11 | DB dev/CI | **SQLite** (SQL portabel-refactored); prod lu-sesuaikan (MySQL) |
| D12 | Policy think | Commit lokal per task; push via gh cuma pas test ijo |
| D13 | Permissions serialize | Dipertahankan via accessor (data legacy); cast JSON ditunda kapan prod-DB diputuskan (W7) |
| D14 | Translation kontrak FE | Tabel `languages` sendiri + spatie `LanguageLine`; shape `{name: locale}` + nested `{single|group}[group][key]` |
| D15 | FK legacy columns | Tidak ditambahkan (index saja) — upgrade path zero-delta |
| D16 | Reverb | `^1.12` (latest stable; tak ada 2.x) |
| D17 | Prod DB (owner 2026-10-21) | **Upgrade-in-place** — MySQL lama tetap sumber; schema additive |
| D18 | Billing (owner 2026-10-21) | **Fitur BARU** (bukan peninggalan; hit "stripe" di repo = false positive boilerplate/CSS): plan `free` + `premium/vip` (kuota penyimpanan, env-driven); pembayaran **QRIS via Midtrans** (Snap), Xendit fallback; admin aktif + FE plan pages — WS3.7/W3.8 + WS5 |
| D19 | Infra (owner 2026-10-21) | **Bare-metal** — tanpa Sail/Docker; dokumentasikan setup langkah |
| D20 | FE bahasa (owner 2026-10-21) | **TypeScript** (revisi dari JS-first) — WS5 pakai Vite + vue-tsc, typings Pinia/router |

## 1. Anatomi perpindahan (ringkas)

- **BE skeleton**: `bootstrap/app.php` builder style, routes FQCN (tanpa `$namespace`), middleware aliases,
  exception handler modern, loach auth controller mandiri (buang laravel/ui traits).
- **Passport 13**: `User implements OAuthenticatable`; token lifetime via config; PUTUSAN UUID client
  default → progres: naikkan dimodel/DB; PAT existing di-revoke → re-login.
- **Flysystem 3**: streaming rewrite (`AudioVideoResponse` pakai `readStream` + `StreamedResponse`+Range),
  ZIP pakai stdlib `ZipArchive` di tmpfs; disk `local root = storage/app/uploads` dipertahankan (data jalan).
- **Intervention Image 4**: `read()/resize()/save()`; queue worker refactor (hapus `sleep(5)` → job chain).
- **FE**: `resources/assets/{admin,auth,common}` → `resources/{admin,auth,common}` (Vite), 46 `.vue`
  → Composition API (script setup), Vuex→Pinia, Grid `VFlex`→`VRow/VCol`, EventBus→composable,
  axios 1.x, ESLint 9 flat config, @mdi/font via npm.
- **SQLite portability refactor**: ganti pseudo-MySQL `DB::raw('type = "folder"')` dll yang masih
  diaplikasi agar test/CI berjalan di SQLite; prod-DB bisa tetap MySQL.

## 2. Workstream (urutan eksekusi)

1. **WS0 Preflight** — branch `modernization`, PLAN/TASKS, repo hygiene (_ide_helper artifacts, CHANGELOG dangling).
2. **WS1 Skeleton** — port app code ke Laravel 13 (models/observers/policies/requests/routes/providers).
3. **WS2 Packages** — composer/npm modernise, Passport 13, Socialite, chunk-upload upstream, Flysystem 3, Intervention 4, Reverb, translation-loader.
4. **WS3 Realisasi** — Passport UI + OAuth API, broadcasting realtime, 7 provider social, storage drivers (S3/DO/Dropbox), translation CRUD lengkap, rate limiter chunk, **billing skeleton (plans/quota) + Midtrans QRIS flow (D18)**.
5. **WS4 Bugfix** — 12 bug audit (disk `uploads_local`, Vuex getter, csftToken, copy path, dsb) — inline sepanjangan WS1–WS3.
6. **WS5 Frontend** — Vite + Vuetify 4 rebuild lengkap (**TypeScript — D20**; FE lama jalan sampai FE baru paritas) + halaman plan/billing QRIS.
7. **WS6 QA/CI/docs** — GH Actions, test targets, README, finish line.
8. **WS7 Release** — v2.0.0 tag, checklist **deploy upgrade-in-place (D17)** (backup → migrate additive → seed plans → reverb → queue restart → re-login users → smoke matrix).

## 3. Good practices saat build
- Test merah dulu (bila logic baru), ijo dulu baru push (rule D12).
- ATZ "ask-first": environment/network ambiguity dibuka ke owner, bukan diyeuh di-dummy.
- DOC per Workstream di-update bareng commits.
- CI green di `modernization`; PR ke `develop` per phase utk tracking.
