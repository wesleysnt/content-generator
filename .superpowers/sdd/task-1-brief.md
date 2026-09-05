### Task 1: Project Scaffold + Dependencies

**Files:**
- Create: `.env` (modified), `config/horizon.php` (via artisan), `config/purifier.php` (via artisan)
- Modify: `composer.json` (via composer require)

**Interfaces:**
- Produces: working Laravel 11 app with Filament 3 panel, Horizon, `openai-php/client`, mews/purifier; git repo initialized

- [ ] **Step 1: Init git + scaffold Laravel 11**

```bash
cd /Users/wesleysnt/Documents/GitHub/content-generator
git init
composer create-project laravel/laravel:^11.0 .
```

The directory contains only markdown docs — no conflicts. Expected: composer output ends with "Application ready! Build something amazing."

- [ ] **Step 2: Require packages**

```bash
composer require filament/filament:^3.2 openai-php/client laravel/horizon mews/purifier
```

- [ ] **Step 3: Install Filament panel + Horizon**

```bash
php artisan filament:install --panels --no-interaction
php artisan horizon:install
php artisan vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"
```

Expected: `app/Providers/Filament/AdminPanelProvider.php` created, `config/horizon.php` + `config/purifier.php` created.

- [ ] **Step 4: Configure .env**

```env
APP_NAME="AI Content Automation"
DB_CONNECTION=mysql
DB_DATABASE=content_generator
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

DEEPSEEK_API_KEY=
DEEPSEEK_GENERATION_MODEL=deepseek-v4-pro
```

- [ ] **Step 5: Verify install**

```bash
php artisan migrate
php artisan test
```

Expected: migrations run, default test suite passes. Then:

```bash
git add -A
git commit -m "chore: scaffold Laravel 11 app with Filament 3, Horizon, DeepSeek client"
```

---

