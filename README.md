# Kleinanzeigen Laravel 12

AI-powered classified ads generator built with Laravel 12, Svelte 5, and TailwindCSS.

## Quick Start

**Always** make sure:

```bash
cd larasvelte
```

### Prerequisites
- PHP 8.3+
- Node.js 20+
- Composer
- Python 3.12+ with `pip` (for YOLO auto-crop script)

### Installation

1. **Clone and setup:**
```bash
cd larasvelte
composer install
npm install
```

2. **Setup local Python runtime for auto-crop:**
```bash
bash scripts/setup_auto_crop_dev.sh
source .venv-crop/bin/activate
```

3. **Put the YOLO model in the default local path:**
```bash
mkdir -p storage/models
# Place yolov8n-fashionpedia-1.onnx in storage/models/
```

4. **Configure environment:**
```bash
cp .env.example .env
php artisan key:generate
```

5. **Setup database:**
```bash
touch database/database.sqlite
php artisan migrate:fresh --seed
```

### Running Locally

You need **three terminal tabs** (`cd larasvelte`):

**Terminal 1 - Laravel Server:**
```bash
php artisan serve
```
Server runs at: `http://localhost:8000`

**Terminal 2 - Vite Dev Server (in same directory):**
```bash
npm run dev
```
Vite runs in the background for hot module reloading.

**Terminal 3 - Queue Worker (required for auto-crop):**
```bash
php artisan queue:work --queue=default -v
```

Then open **http://localhost:8000** in your browser.

### Default Test Credentials

You will find them in larasvelte/database/seeders/DefaultUserSeeder.php


**Email:** `test@example.com`
**Password:** `password`

These are automatically created when you run `php artisan migrate:fresh --seed`.

## Development

### Available Commands

**Backend:**
```bash
php artisan serve              # Start Laravel dev server
php artisan migrate            # Run migrations
php artisan tinker            # Interactive PHP shell
php artisan queue:work        # Process async jobs
```

**Ad-hoc auto-crop tuning (single fixture image):**
```bash
php artisan app:crop-fixture-image --detection-threshold=0.65 --closeup-threshold=0.80 --margin-percent=2
```
This uses `larasvelte/tests/fixtures/test-image.jpg` as input and writes `larasvelte/tests/fixtures/test-image_cropped.jpg` so you can compare results quickly.
Useful options to tune behavior: `--detection-threshold` (0..1), `--closeup-threshold` (0..1), `--margin-percent`, `--model=/absolute/path/to/model.onnx`.

**Auto-crop matrix mode (many output images, 0.10 step):**
```bash
php artisan app:crop-fixture-image --matrix --detection-min=0.10 --detection-max=0.90 --closeup-min=0.10 --closeup-max=0.90 --step=0.10 --margin-percent=3
```
This creates one image per parameter combination, for example:
`larasvelte/tests/fixtures/test-image_cropped_dt010_ct020_m03.jpg`.
It also writes a diagnostics report at `larasvelte/tests/fixtures/test-image_cropped_matrix_report.json` with per-run fields like `decision_reason`, `detection_count`, and `main_coverage`.

**Frontend:**
```bash
npm run dev                    # Start Vite dev server
npm run build                  # Build for production
npm run lint                   # Run ESLint
npm run format                 # Format code with Prettier
```

### Project Structure

```
larasvelte/
├── app/                       # Laravel application code
│   ├── Http/Controllers/
│   ├── Models/
│   └── Services/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── resources/
│   ├── js/                    # Svelte components
│   ├── css/                   # TailwindCSS
│   └── views/
├── routes/
│   └── web.php
├── tests/                     # Pest tests
├── config/ads.php             # Application configuration
└── .env                       # Environment variables
```

### Configuration

**Application Logic:** [config/ads.php](larasvelte/config/ads.php)
- Image processing settings
- Auto-crop YOLO configuration
- Validation rules
- Business logic constants

**Environment Variables:** [.env](larasvelte/.env)
- `OPENAI_API_KEY` - Required for text generation
- `DB_DATABASE` - SQLite database path
- Debug and queue settings

## Features

- **Ad Management**: Create, edit, archive classified ads
- **Multi-Image Upload**: Upload up to 10 images with client-side compression
- **AI Text Generation**: Generate ad titles and descriptions using OpenAI
- **Auto-Crop**: Automatic image cropping with YOLO
- **Image Variants**: Original and cropped versions for each image
- **Responsive UI**: Mobile-first design with Svelte + TailwindCSS

## Testing

```bash
php artisan test                    # Run all tests
php artisan test --parallel         # Run tests in parallel
./vendor/bin/phpunit --coverage     # Generate coverage report
```

## Documentation

See [docs/](no_laravel/docs/) for detailed guides:
- `ai-assisted-development.md` - AI workflow and architecture
- `eloquent-orm-getting-started.md` - Database patterns
- `browser-testing-pest-v4.md` - Testing guide
- `validation.md` - Form validation patterns

## Troubleshooting

**Running Laravel CLI on the server?**
The hosting default `php` binary is older than the app requirement. Before running `php artisan`, `composer`, or other PHP CLI commands manually on the server, switch the shell session to PHP 8.4:
```bash
export PATH="/opt/lima-php/8.4/bin:$PATH"
```
This only affects the current bash session and makes `php` resolve to the same PHP 8.4 binary used in deployment.

**Need to check which database the live app is using?**
Use Laravel config via Tinker instead of reading `.env` directly:
```bash
php artisan tinker --execute="dump(config('database.default')); dump(config('database.connections.'.config('database.default').'.database'));"
```
This is a troubleshooting/debug command. It prints the active connection name first, for example `mysql`, and then the configured database name, for example `db_439120_9`.

**Database error?**
```bash
rm database/database.sqlite
touch database/database.sqlite
php artisan migrate:fresh --seed
```

**Dependencies not installed?**
```bash
composer install
npm install
```

**Auto-crop Python dependencies missing?**
```bash
cd larasvelte
bash scripts/setup_auto_crop_dev.sh
source .venv-crop/bin/activate
php artisan config:clear
```

**Auto-crop model file missing?**
```bash
cd larasvelte
ls -lh storage/models/yolov8n-fashionpedia-1.onnx
grep '^AUTO_CROP_MODEL_PATH=' .env
```

**Port 8000 already in use?**
```bash
php artisan serve --port=8001
```

**Vite not updating changes?**
Restart the dev server:
```bash
# Kill (Ctrl+C) and restart
npm run dev
```

## License

This project is private. See LICENSE file for details.
