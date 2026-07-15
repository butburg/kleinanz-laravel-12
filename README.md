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

2. **Install project-local Python packages (repo root):**
```bash
cd ..
mkdir -p .python-packages
python3 -m pip install --target .python-packages numpy pillow onnxruntime
cd larasvelte
```

3. **Configure environment:**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Setup database:**
```bash
touch database/database.sqlite
php artisan migrate:fresh --seed
```

### Running Locally

You need **two terminal tabs** (`cd larasvelte`):

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
cd /home/butburg/repos/kleinanz-laravel-12
mkdir -p .python-packages
python3 -m pip install --target .python-packages numpy pillow onnxruntime
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
