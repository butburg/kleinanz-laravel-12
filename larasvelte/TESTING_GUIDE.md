# Testing Guide for Ad Generation Feature

## Current Architecture

Ad creation is now inline on the index page (`/ads`) via an expandable "Create Ad" card. No separate `/ads/create` route exists.

### Ad Creation Flow

1. **Index Page:** User clicks "Create Ad" to expand the inline form
2. **Upload Images:** User uploads images via the expandable card
3. **Optional Prompt:** User can add an optional prompt (max 1000 chars)
4. **Generate Ad:** User clicks "Generate Ad" button
5. **Backend Processing:** Ad is created with images and AI generation is triggered
6. **Result:** User is redirected back to `/ads` with success message

### Edit Flow

The Edit page (`/ads/{id}/edit`) remains separate and allows:
- Uploading additional images
- Modifying ad fields
- Regenerating ad text with "Generate with AI" button

## Problem Identified (Previously Fixed)

The "Generate with AI" button on the Edit page didn't work when clicked due to DialogTrigger intercepting clicks.

**This has been fixed by removing DialogTrigger and controlling the dialog manually.**

## Running the Tests

### Setup Browser Testing

1. **Install Chrome/Chromium** (if not already):
   ```bash
   # Linux
   sudo apt install chromium-browser
   ```

2. **Run browser tests:**
   ```bash
   cd larasvelte
   php artisan test tests/Browser/AdGenerationTest.php
   ```

3. **Run specific test:**
   ```bash
   php artisan test --filter="clicking generate button on create page"
   ```

4. **Debug with browser visible:**
   ```bash
   # The tests use Pest's browser testing (not Dusk)
   php artisan test tests/Browser/AdGenerationTest.php --verbose
   ```

### Test Coverage

The `tests/Browser/AdGenerationTest.php` file includes tests for:

1. ✅ Create ad without images
2. ✅ Generate button disabled without images
3. ✅ Upload image on create page
4. ✅ Generate button enabled after image upload
5. ✅ Click generate on create page (form submission)
6. ✅ Full create → upload → generate flow
7. ✅ Upload image on edit page
8. ✅ Generate button enabled on edit page
9. ✅ Debug: Form existence check
10. ✅ Debug: Browser console logs

### Manual Testing Flow

1. **Login:** http://localhost:8000/login
   - Email: test@example.com
   - Password: password

2. **Create Ad (Inline on Index Page):**
   - Go to `/ads`
   - Click "Create Ad" to expand the card
   - Upload image: `no_laravel/migration_source/edit_ad_layout_example.png`
   - Optionally add a prompt
   - Click "Generate Ad"
   - **Expected:** Ad is created, AI generates content, redirects to `/ads` with success message

3. **Edit Page:**
   - Click "Edit ad" on any existing ad
   - Upload additional images if needed
   - Modify fields
   - Click "Generate with AI" to regenerate content
   - **Expected:** Shows confirmation dialog, then regenerates content

## Debug Checklist

When testing, check:

- [ ] Console errors (F12 → Console)
- [ ] Network tab (F12 → Network) - Does form POST happen?
- [ ] Button disabled state (`disabled={ad.images.length === 0}`)
- [ ] Form action URL (`route('ads.generate', ad.id)`)
- [ ] Hidden input with prompt_text
- [ ] `showGenerateConfirm` state value
- [ ] Whether `handleGenerateClick()` runs (add console.log)

## Quick Fix Commands

```bash
# Navigate to project
cd /home/butburg/repos/kleinanz-laravel-12/larasvelte

# Run all browser tests
php artisan test tests/Browser/

# Run with debugging
php artisan test tests/Browser/AdGenerationTest.php --verbose

# Run specific test
php artisan test --filter="debug: check form submission"

# Check errors in real-time
tail -f storage/logs/laravel.log
```

## Recommended Fix

Apply **Option 1** by editing `resources/js/pages/ads/Edit.svelte` to remove `DialogTrigger` and let the button control the dialog manually.
