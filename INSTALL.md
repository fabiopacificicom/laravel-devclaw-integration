# Installation Guide

Step-by-step installation of `pacificdev/laravel-devclaw-integration` on the fabiopacifici.com platform.

---

## Step 1 — Copy or require the package

**Option A — Local path (during development):**

Add to your main app's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "/path/to/laravel-devclaw-integration"
    }
]
```

Then:

```bash
composer require pacificdev/laravel-devclaw-integration
```

**Option B — From GitHub:**

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/fabiopacifici-bot/laravel-devclaw-integration"
    }
]
```

```bash
composer require pacificdev/laravel-devclaw-integration
```

---

## Step 2 — Auto-discovery (automatic)

Laravel auto-discovers the `DevClawServiceProvider` via the `extra.laravel.providers` key in `composer.json`. No manual step needed.

If auto-discovery is disabled, add to `config/app.php`:

```php
'providers' => [
    // ...
    Pacificdev\DevClaw\DevClawServiceProvider::class,
],
```

---

## Step 3 — Publish config (optional)

```bash
php artisan vendor:publish --tag=devclaw-config
```

This copies `config/devclaw.php` to your app's `config/` directory so you can customize it.

---

## Step 4 — Generate a secure token

```bash
openssl rand -hex 32
```

Copy the output — this is your `DEVCLAW_TOKEN`.

---

## Step 5 — Add to `.env`

```dotenv
DEVCLAW_ENABLED=true
DEVCLAW_TOKEN=paste_your_generated_token_here
DEVCLAW_DEFAULT_MODEL=
DEVCLAW_ALLOWED_TOOLS=*
DEVCLAW_LOG_REQUESTS=true
```

---

## Step 6 — Clear config cache

```bash
php artisan config:clear
php artisan route:clear
```

---

## Step 7 — Test the installation

```bash
# Replace <token> with your DEVCLAW_TOKEN value
curl -H "Authorization: Bearer <token>" http://fabiopacifici.test/api/devclaw/health
```

Expected response:

```json
{
  "status": "ok",
  "package": "pacificdev/laravel-devclaw-integration",
  "version": "0.1.0",
  "enabled": true
}
```

---

## Step 8 — Verify routes are registered

```bash
php artisan route:list --path=devclaw
```

You should see 6 routes:

```
GET  api/devclaw/health
POST api/devclaw/chat
GET  api/devclaw/tools
POST api/devclaw/tools/{tool}
GET  api/devclaw/courses
GET  api/devclaw/stats
```

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| 401 on all requests | Check `DEVCLAW_TOKEN` is set in `.env` and matches the header |
| 503 "token not configured" | `DEVCLAW_TOKEN` is missing from `.env` |
| 500 on `/chat` | `AiProviderWrapper` not found — ensure it's bound in your `AppServiceProvider` |
| Routes not appearing | Check `DEVCLAW_ENABLED=true` and run `php artisan route:clear` |
| 404 on `/tools` | `app/Services/functions.json` doesn't exist in your project |

---

## Disabling the Integration

To fully disable without uninstalling:

```dotenv
DEVCLAW_ENABLED=false
```

No routes will be registered.
