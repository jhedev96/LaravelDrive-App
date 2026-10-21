# Changelog

All notable changes to **LaravelDrive** are documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/). Format based on
[Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Planned — v2.0.0 "Modernization" (work in progress on branch `modernization`)
- Laravel 7 → **Laravel 13.x** with **PHP 8.5** (was PHP 7.2).
- Frontend rebuild: **Vue 3 + Vuetify 4 + Vite 8 + Pinia 4** (was Vue 2 / Vuetify 2 / Laravel Mix 5).
- Auth: **Passport 13** (OAuth2, incl. the CVE-2026-39976 security fix) + Passport admin UI realized.
- Real-time via **Laravel Reverb** (Pusher scaffold replaced).
- Chunked uploads moved to maintained upstream `pion/laravel-chunk-upload ^1.6`.
- Image processing: **Intervention Image 4**; thumbnails/resize pipeline refactored.
- Filesystem: **Flysystem 3**, official S3 / DigitalOcean Spaces / Dropbox / FTP disks.
- Translation stack: `spatie/laravel-translation-loader` (waavi/translation is abandoned).
- Social login for 7 providers via `socialiteproviders/*` (Facebook, X/Twitter, Google, GitHub,
  YouTube, Twitch, Instagram).
- Modernized tests (PHPUnit 11/12), first CI pipeline (GitHub Actions), README rewritten.

## [1.x] — legacy (branch `master`)
- Google Drive-like storage: files/folders, sharing, shareable links, trash/starred, previews
  (image/audio/video/pdf/zip/text), tags, roles & permissions, social login, translation admin.
- Built on Laravel 7 + Vue 2 + Vuetify 2 (frozen ~2020).
