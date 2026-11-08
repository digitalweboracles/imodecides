# Imo Decides 2027 — rebuild

PHP 8 + SQLite. No framework, no build step. Runs on Railway (Docker) or any cPanel host.

## What's in it
- Home: hero + 4 positions (Governor, Senate, House of Reps, House of Assembly)
- House of Reps: all 10 Imo federal constituencies (same URLs as the old site)
- **Ahiazu/Ezinnihitte Mbaise FHR Candidates 2027** — all 17 candidates + parties, live poll with results bars, and all 9 existing answers migrated (including the reply and the photo)
- Old links keep working: `/question/ahiazu-mbaise-ezinihitte/`, `/house-of-representative/`, `/senate/` …
- One vote per person per poll (cookie + per-IP cap), results, leaderboard, share buttons, answers with replies and photo upload, search
- Admin `/admin/`: polls, candidates (photo, bio, bulk paste), reset votes, open/close poll, answer moderation, settings, logo/share-image replace

## Admin login
User: `admin` · Password: `ImoDecides@2027` (or the `ADMIN_PASSWORD` env var). Change it in Admin → Settings.

## Images
The old logo, share image, favicon and the answer photo download automatically from imodecides.com on first page load (server-side). If the old site is already down, upload the logo/share image in Admin → Settings, and candidate photos in each poll.

## Deploy on Railway
1. Push this folder to a GitHub repo and create a Railway service from it (Dockerfile is detected).
2. Add a **Volume** mounted at `/data` — this keeps votes, answers and photos across deploys.
3. Variables (optional): `ADMIN_PASSWORD=...`
4. Generate a domain, or add `imodecides.com` as a custom domain and point DNS (CNAME) to Railway.

## Deploy on cPanel
Upload the zip contents to `public_html` and extract. Make sure `data/` is writable (755/775). Done — the database creates itself.
Subfolder install: set `BASE_PATH` in `config.php`, e.g. `/imodecides`, and add `RewriteBase /imodecides/` to `.htaccess`.
