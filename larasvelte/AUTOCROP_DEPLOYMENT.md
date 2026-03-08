# Auto-Crop Implementation: SSH Testing & Deployment

## Overview

This document covers:
1. **Safe SSH testing** on Lima City (temporary virtualenv)
2. **Local development** setup (persistent virtualenv)
3. **CI/CD** deployment strategy (for GitHub Actions)

---

## 1️⃣ SSH Testing on Lima City (Safe, Reversible)

### One-Time Setup Test

This creates a **temporary** virtualenv, tests dependencies, then cleanup:

```bash
# From your local machine
scp larasvelte/scripts/test_auto_crop_env.sh eweedy@lima-ssh:~/

# On Lima City SSH
ssh eweedy@lima-ssh
bash ~/test_auto_crop_env.sh
```

**Output:**
```
=========================================
🔬 Auto-Crop Environment Test Script
=========================================

1️⃣  Checking Python...
   ✅ Python 3.8.10
...
✅ All tests passed!
```

**Automatic cleanup:** Environment deleted after test completes.

### Keep Test Environment for Debugging

```bash
ssh eweedy@lima-ssh
bash ~/test_auto_crop_env.sh --keep-env
```

The virtualenv stays at `/tmp/auto-crop-test-venv-<PID>` for debugging. Manual cleanup:
```bash
rm -rf /tmp/auto-crop-test-venv-*
```

---

## 2️⃣ Local Development Setup

### First-Time Setup

```bash
cd larasvelte

# Create persistent virtualenv with dependencies
bash scripts/setup_auto_crop_dev.sh

# Activate
source venv/bin/activate

# Verify Python auto-crop script works
python3 scripts/auto_crop.py --help
```

### Directories

```
larasvelte/
  venv/                           # Python virtualenv (git-ignored)
  scripts/
    auto_crop.py                  # Main crop processor
    setup_auto_crop_dev.sh        # Create local virtualenv
    test_auto_crop_env.sh         # Test script for SSH
  storage/
    models/
      yolov8n-fashionpedia-1.onnx # YOLO model (12MB)
  config/
    ads.php                        # Auto-crop config
  app/
    Jobs/
      AutoCropImage.php           # Queue job (to implement)
  tests/
    Feature/
      Ads/
        AutoCropTest.php          # Auto-crop tests (to implement)
```

### Running Tests

```bash
# With virtualenv active
source venv/bin/activate

# Run Laravel tests (includes auto-crop tests)
php artisan test

# Watch for changes
php artisan test --watch

# Specific test
php artisan test tests/Feature/Ads/AutoCropTest.php
```

### Development Workflow

**Terminal 1: Vite (JS/CSS)**
```bash
npm run dev
```

**Terminal 2: Laravel Server**
```bash
php artisan serve
```

**Terminal 3: Queue Worker** (processes auto-crop jobs)
```bash
source venv/bin/activate
php artisan queue:work --tries=3 --timeout=90
```

**Terminal 4: Tests** (watch mode)
```bash
source venv/bin/activate
php artisan test --watch
```

---

## 3️⃣ CI/CD Deployment Strategy (GitHub Actions)

### Deployment Approach

Lima City shared hosting doesn't support:
- Long-running background processes
- Cron jobs triggering queue workers continuously
- Large file uploads

**Recommended workflow:**

1. **On deployment**, create persistent virtualenv once:
   ```bash
   bash scripts/setup_auto_crop_dev.sh
   ```

2. **Queue jobs run locally** during request:
   - When image uploaded → Queue `AutoCropImage` job
   - Queue driver: `database` (default)

3. **Manual/scheduled processing**:
   - Use `php artisan queue:work --tries=3 --timeout=90`
   - Can be triggered via cron (if available) or manual admin command
   - Or process synchronously if auto-crop must be instant

### GitHub Actions Deployment Template

Create `.github/workflows/deploy-lima-city.yml`:

```yaml
name: Deploy to Lima City

on:
  push:
    branches: [main, master]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Deploy via SSH
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.LIMA_HOST }}
          username: ${{ secrets.LIMA_USER }}
          key: ${{ secrets.LIMA_SSH_KEY }}
          port: 22
          script: |
            cd ${{ secrets.LIMA_APP_PATH }}
            git pull origin main

            # Laravel
            php artisan migrate --force
            php artisan config:cache

            # Python auto-crop environment (one-time)
            if [ ! -d "venv" ]; then
              echo "Setting up auto-crop virtualenv..."
              bash scripts/setup_auto_crop_dev.sh
            else
              echo "Auto-crop virtualenv already exists"
            fi

            # Update model if needed
            if [ ! -f "storage/models/yolov8n-fashionpedia-1.onnx" ]; then
              echo "Error: Model file missing"
              exit 1
            fi

            echo "Deployment complete!"
```

### Environment Variables on Lima City

Create `.env` on server:
```env
AUTO_CROP_ENABLED=true
AUTO_CROP_DETECTION_THRESHOLD=0.7
AUTO_CROP_CLOSEUP_THRESHOLD=0.70
AUTO_CROP_MARGIN_PERCENT=2
AUTO_CROP_MODEL_PATH=/var/www/kleinanz/storage/models/yolov8n-fashionpedia-1.onnx
PYTHON_PATH=/usr/bin/python3
QUEUE_CONNECTION=database
```

---

## 4️⃣ Troubleshooting

### On Lima City SSH

**Check Python installation:**
```bash
python3 --version
which python3
pip3 list | grep onnxruntime
```

**Check virtualenv:**
```bash
# List active virtualenvs
ls -la /home/eweedy/*/bin/python3

# Test virtualenv
source /path/to/venv/bin/activate
python3 -c "import onnxruntime; print(onnxruntime.__version__)"
deactivate
```

**Check model file:**
```bash
ls -lh /var/www/kleinanz/storage/models/yolov8n-fashionpedia-1.onnx
```

**Manual crop test:**
```bash
source /path/to/venv/bin/activate
python3 scripts/auto_crop.py /tmp/test_image.jpg --output /tmp/cropped.jpg
```

### Subprocess Errors

If crop job fails with "python3 not found":
1. Check `PYTHON_PATH` env var matches `which python3` on server
2. Ensure virtualenv is activated in queue worker:
   ```bash
   source venv/bin/activate
   php artisan queue:work
   ```

### Model File Errors

If crop job fails with "Model file not found":
1. Check model exists: `ls -l storage/models/yolov8n-fashionpedia-1.onnx`
2. Check config: `php artisan config:show ads.auto_crop`
3. Verify permissions: `chmod 644 storage/models/yolov8n-fashionpedia-1.onnx`

---

## 5️⃣ Next: TDD Implementation

Once SSH testing passes:

1. Create `app/Jobs/AutoCropImage.php` ← Write test first
2. Create `tests/Feature/Ads/AutoCropTest.php` ← TDD approach
3. Integrate job into `AdController`
4. Add UI toggle for cropped/uncropped versions

See `BUGFIX_GENERATE_BUTTON.md` for TDD workflow reference.
