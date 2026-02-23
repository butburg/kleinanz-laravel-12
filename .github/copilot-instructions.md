
# Copilot Instructions for kleinanz-laravel-12

## Essential Guidance for AI Agents

**Read this first:**
- Use `php artisan`, Pest, and Eloquent ORM commands for scaffolding and code generation. Avoid creating files manually unless necessary.
- Reference `no_laravel/docs/` for workflow and architecture details. Key files: `config/ads.php`, `.env.example`, `no_laravel/migration_source/` for legacy logic.

### Mandatory: Command-First Generation

- Agents must prefer framework generators over manual file creation.
- For tests, use commands like `php artisan make:test ...` or `php artisan pest:test ...`.
- For Laravel artifacts, use `php artisan make:model`, `make:migration`, `make:request`, `make:seeder`, etc.
- Manual file creation should be a last resort and only for edits that generators cannot handle.
- Before generating, check local docs and Laravel MCP/Boost guidance; prefer MCP-assisted workflows when available.

## Docs Index

- `no_laravel/docs/ai-assisted-development.md`: AI agent workflow, Laravel Boost, and project AI guidelines.
- `no_laravel/docs/artisan-console.md`: Artisan CLI usage, writing commands, input expectations, and I/O.
- `no_laravel/docs/browser-testing-pest-v4.md`: Pest browser testing patterns and assertion reference.
- `no_laravel/docs/eloquent-orm-getting-started.md`: Eloquent ORM conventions and core querying patterns.
- `no_laravel/docs/file-storage.md`: Filesystem configuration, disks/drivers, storing/retrieving/testing files.
- `no_laravel/docs/laravel-dusk.md`: Laravel Dusk browser testing setup and usage.
- `no_laravel/docs/laravel-pest-test-plugin.md`: Pest Laravel plugin install, Artisan helpers, and testing helpers.
- `no_laravel/docs/svelte-5-migration-guide.md`: Svelte 5 migration notes (runes, events, slots, breaking changes).
- `no_laravel/docs/testing-getting-started.md`: Laravel testing setup, running tests, parallel/coverage/profiling.
- `no_laravel/docs/validation.md`: Validation quickstart, FormRequests, validators, and common rules.

## Architecture & Data Flow

- **Domain:** AI-powered classified ads generator (Laravel 12, TailwindCSS, Alpine.js/Livewire)
- **Major flows:**
  - Image upload (client-side downsizing, server-side validation, YOLO auto-crop)
  - Ad text generation (OpenAI API, user prompt, user-specific disclaimer)
  - Ad lifecycle: Draft → Online → Archived (60-day expiry)
  - Multi-image management: upload, reorder, delete, set title, toggle crop
- **Data model:** See `config/ads.php` and comments in this file for Ad/AdImage/User fields and relationships
- **Service boundaries:**
  - `app/Services/ImageProcessingService.php`: image validation, resizing, thumbnail, crop
  - `app/Services/TextGenerationService.php`: OpenAI integration
  - `app/Jobs/AutoCropImage.php`: async YOLO crop (ONNX or Python subprocess)
  - `app/Services/AdStorageService.php`: transactional ad/image save

## Configuration & Conventions

- **Two-level config:**
  - `.env`: deployment-specific (API keys, DB, URLs, debug, queue)
  - `config/ads.php`: app logic (image sizes, validation, business rules)
- **Never use `env()` in config/ads.php`**. Use `config()` in code, not `env()`.
- **Validation:** Always pull limits from `config/ads.php` in FormRequests.
- **Image handling:**
  - Client: Downsize before upload (1000px, 80% quality, discard original)
  - Server: Validate, fix EXIF, resize, store as MEDIUMBLOB, generate thumbnail, queue crop job
  - User can toggle cropped/uncropped per image
- **Text generation:**
  - Use title image thumbnail + prompt for OpenAI
  - Append user disclaimer from User model

## Developer Workflows

- **Setup:**
  - `composer install && npm install && npm run dev`
  - `php artisan migrate`
  - `php artisan serve`
- **Testing:**
  - `php artisan test --filter AdCreationTest`
  - `php artisan test --parallel`
  - `./vendor/bin/phpunit --coverage-html coverage/`
- **Image/AI processing:**
  - `php artisan queue:work` (for async jobs)
  - `php artisan storage:link` (if using filesystem storage)

## TDD Workflow

- Prefer TDD for new features: write or update a failing test first, implement the change, then refactor with tests passing.
- Use Pest or PHPUnit via Artisan for test generation and execution.

## Project-Specific Patterns

- **Action classes:** Use invokable classes for business logic (`app/Actions/`)
- **Livewire/Alpine.js:** For reactive UI (image management, form updates)
- **Design system:** Tailwind custom colors in `resources/css/app.css`, see `no_laravel/migration_source/oe-app-docker-compose/` for card/button patterns
- **Routing:** RESTful resource routes for ads/images, see `routes/web.php`

## Integration Points

- **OpenAI:** API key in `.env` or per-user, config in `config/services.php`
- **YOLO crop:** ONNX (preferred) or Python subprocess, model path in `.env`
- **Image storage:** DB BLOB (default), can switch to filesystem/S3

## Reference & Legacy

- For legacy logic, see `no_laravel/migration_source/kleinanz-slim/` (do not copy code, use for business logic reference only)
- For UI/component patterns, see `no_laravel/migration_source`
- make use of mcp or docs in `no_laravel/docs`

---
**Example: Generate Ad model, migration, controller, and form request:**
```bash
php artisan make:model Ad -mfsc
```

**Example: Use config in validation:**
```php
'title' => ['required', 'string', 'max:' . config('ads.validation.title_max_length')],
```

---
**If in doubt, check `no_laravel/docs/` and `config/ads.php` for business rules.**

## Configuration Management

**Two-Level Approach** (simple and clear separation):

### Level 1: Environment Variables (.env)
**For deployment-specific settings that change between environments (dev/staging/production):**
- ✅ API keys and secrets: `OPENAI_API_KEY`, `DB_PASSWORD`
- ✅ URLs: `APP_URL`, `OPENAI_API_URL` (localhost vs production domain)
- ✅ Database connections: `DB_HOST`, `DB_PORT`, `DB_DATABASE`
- ✅ Debug modes: `APP_DEBUG=true` (local) vs `false` (production)
- ✅ Cache/Queue drivers: `QUEUE_CONNECTION=sync` (local) vs `redis` (production)

**Rule**: If it changes when you deploy → `.env`

### Level 2: Application Config or laravel 12
**For application logic that's the same everywhere:**
- ✅ Image dimensions: max size 1000px, thumbnail 220px
- ✅ Quality settings: JPEG quality 85%, progressive encoding
- ✅ Validation rules: title max 80 chars, description 50-1000 chars
- ✅ Business constants: max 10 images, 60-day expiry, condition options
- ✅ Feature flags: auto-crop enabled/disabled

**Rule**: If it defines how the app works → `config/ads.php`

### 🎯 Decision Guide: Where Does This Value Go?

**Ask: "Does this value change between dev/staging/production?"**
- **YES** → `.env` (e.g., database host, API URLs, debug mode)
- **NO** → `config/ads.php` (e.g., max file size, JPEG quality, validation rules)

### ⚠️ Important: NO env() in config/ads.php

**Keep it simple:** Don't use `env('IMAGE_MAX_SIZE', 1000)` in config files.

**Bad (mixed concerns):**
```php
// config/ads.php - DON'T DO THIS
'image' => [
    'max_size' => env('IMAGE_MAX_SIZE', 1000), // ❌ Confusing!
]
```

**Good (clear separation):**
```php
// config/ads.php - Pure application logic
'image' => [
    'max_size' => 1000, // ✅ Clear constant
]

// .env - Only environment-specific values
OPENAI_API_KEY=sk-... // ✅ Changes per environment
```

**Why?** Mixing `env()` calls in config files makes it unclear which values are environment-specific and which are application constants. Keep them separate for clarity.

### 📖 Usage Pattern

```php
// ✅ Always use config() in code
$maxSize = config('ads.image.max_size');
$apiKey = config('services.openai.key'); // This one reads from .env via config/services.php

// ❌ Never use env() directly in code
$maxSize = env('IMAGE_MAX_SIZE'); // NO!
```

**Example .env.example** (see Configuration Files section below)

## Critical Features to Migrate from Streamlit

### Ad Creation Workflow (NEW UNIFIED APPROACH)
1. **Create Empty Ad**: Click "Create Ad" → Opens empty form (same UI as edit mode)
2. **Upload Images**: Drag/drop or select images (max 10)
   - **Client-side downsize**: Large images compressed BEFORE upload (max 1000px, 80% quality)
   - First uploaded image auto-set as title image
   - Images stored with both cropped and uncropped versions (crop service decides if and how)
   - User can toggle between cropped/uncropped for each image (maybe only uncropped exists)
   - Thumbnails always shown (whichever version is selected of croped uncropped)
3. **Image Management in Form** (NEW FEATURE):
   - Add new images anytime (during create/edit)
   - Delete individual images
   - Reorder images (drag-drop or buttons)
   - Click thumbnail to set as title image
   - Toggle crop version for each image
   - Show only thumbnails (222px) for performance
4. **Download Option** (NEW FEATURE):
   - "Download (High-Res)" button
   - Loads all images at once original size (1000px, not full resolution)
   - chooses cropped or uncropped versions by users selection
   - Full resolution discarded client-side (never stored)
5. **Enable Generate Button**: Button becomes active when:
   - At least 1 image uploaded
6. **Generate Action**: When "Generate" clicked:
   - Auto-crop ALL images using YOLO (if enabled)
   - Send title image thumbnail to OpenAI, send user prompt from field withit (can be empty)
   - Overwrite: title, description, price, versand (shipping), zustand (condition)
   - User can still manually edit after generation
7. **Save**: Auto-save on change or explicit save button

### Enhanced Image Processing Pipeline

**Client-Side Downsize (CRITICAL)**
- Before upload, compress large images to max `config('ads.image.max_size')` pixels (1000px)
- Use JavaScript Canvas API or similar (e.g., `imagecompression.js` library)
- Quality: 80% (lighter than server 85% for fast uploads)
- **Discard original file after compression** (never send to server)

**Server-Side Storage**
- Receive: Downsized image from client (~300-500KB instead of 5-10MB)
- Validate: Check dimensions, format, re-check size
- Create two versions:
  - `image_original`: Resize to exactly 1000px (JPEG 85%, progressive)
  - `image_cropped`: YOLO auto-crop result (if enabled, async queue job)
  - `thumbnail_original`: 220px width (JPEG 75%, progressive)
  - `thumbnail_cropped`: 220px width of cropped version
- Store all images organized as file in laravel storage

**User Preference**
- User choice per image: `use_cropped` boolean
- Default: `true` (show uncropped)
- User toggles in edit form → updates database

**Display Strategy**
- **List view**: Show thumbnail only (222px) → fast, lazy-loaded
- **Edit form**: Show thumbnail (222px) + toggle for crop version
- **Download**: Provide high-res original (1000px) in zip, not full 5000px

**YOLO Auto-Crop (Async)**
- Queue job: `AutoCropImage::dispatch($adImage)`
- Call Python script via subprocess (configurable path)
- Store result in `image_cropped` column
- Update UI when complete (Livewire or polling)

### Image Processing
- **Auto-crop** clothing items using YOLOv8 (fashionpedia model)
  - Model path from config: `config('ads.auto_crop.model_path')`
  - Detection threshold: configurable via `config('ads.auto_crop.detection_threshold')`
- **Two versions per image**: Original + Cropped (user selects which to use)
- **Resize** to max width from `config('ads.image.max_size')`
- **Thumbnails**: Width from `config('ads.image.thumbnail_width')`, max height 4x
- **JPEG optimization**: Quality from `config('ads.image.jpeg_quality')`, progressive encoding
- **EXIF orientation fix**: Always applied
- **Supported formats**: From `config('ads.image.supported_formats')`

### Ad List/Management View
- **Modern Card/Table Hybrid** (responsive mobile-first):
  - Compact cards showing: Title image (thumbnail), title (truncated), status badge, price, expiry date
  - Inline status change (Entwurf/Online/Archiviert) with color indicators:
    - Entwurf (orange), Online (green), Archiviert (gray)
  - "Copy title" button (quick action), same for description
  - "Edit" button → Opens full unified form (same form as create)
  - "Download High-Res" button → Zips all images at 1000px
- **Lazy Loading/Pagination**: Essential for performance
  - Lazy load thumbnails via Intersection Observer
  - Paginate 10 ads per page
- **Responsive Grid**: Mobile (1 col) → Tablet (2 col) → Desktop (3 col)

### Ad Edit Form - Visual Reference
See [no_laravel/migration_source/edit_ad_layout_example.png](no_laravel/migration_source/edit_ad_layout_example.png) for Streamlit layout structure (549x953px portrait):
- **Left side**: Image gallery grid with thumbnails
  - Add/Delete/Reorder images
  - Toggle crop version per image
  - Click to set as title image
  - Download high-res button
- **Right side**: Form fields (vertical stack)
  - Title, description, price, condition, shipping
  - AI prompt (optional)
  - Generate & Save buttons

**Laravel Adaptation Pattern**: Maintain the left (images) + right (form) layout on desktop, stack vertically on mobile. Use Livewire component for reactive image management without page refresh.

## Architecture Patterns as IDEA, use laravel to create

### Data Model (based on `no_laravel/migration_source/kleinanz-slim/app/anzeigen_schema.py`)

# Shortcut to generate a model, migration, factory, seeder, policy, controller, and form requests...
php artisan make:model Flight --all
# Generate a model and a migration, factory, seeder, and controller...
php artisan make:model Flight -mfsc

**Ad Model could looks like:**
```php
// app/Models/Ad.php
- uuid: string (primary key)
- title: string (max 80)
- description: text (50-1000 chars)
- price: integer (whole euros)
- condition: enum ['Neu', 'Sehr gut', 'Gut', 'In Ordnung', 'Defekt']
- shipping: enum ['klein', 'mittel']
- status: enum ['Entwurf', 'Archiviert', 'Online'] (default: Entwurf)
- prompt_text: text (nullable, max 1000)
- user_id: foreignId (owner)
- metadata: json (created_at, last_online_at, quelle)
- timestamps
- Relationships: hasMany AdImage, belongsTo User
```

**AdImage Model:**
```php
// app/Models/AdImage.php
- id: bigIncrements
- ad_id: foreignId (uuid)
- filename: string
- image_original: mediumBlob (uncropped version)
- image_cropped: mediumBlob (nullable, YOLO cropped)
- thumbnail_original: mediumBlob
- thumbnail_cropped: mediumBlob (nullable)
- use_cropped: boolean (default false, user preference)
- order: integer (display order)
- is_title_image: boolean
- timestamps
- Accessor: image() returns cropped if use_cropped=true, else original
- Accessor: thumbnail() returns appropriate thumbnail
```

**User Customization:**
```php
// Add to users table migration:
- appendix: text (default disclaimer appended to descriptions)
- openai_api_key: text (nullable, encrypted)
```

### Service Layer (port from `no_laravel/migration_source/kleinanz-slim/app/services/`)

**`app/Services/ImageProcessingService.php`:**
- `processUploadedImages(UploadedFile $file): array` → Validate, process, store with variants
  - Input: Downsized file from client (already max 1000px, 80% quality)
  - Output: Two images (original + cropped) + two thumbnails
  - Queue auto-crop job if enabled
- `generateThumbnail(Image $image): string` → Create 220px thumbnail preserving aspect ratio
- Storage strategy: MEDIUMBLOB in database (all four image variants)

**`app/Jobs/AutoCropImage.php`:**
- `handle(AdImage $image): void` → Call Python YOLO script asynchronously
  - Subprocess: `python auto_crop.py /path/to/image_original.jpg`
  - Parse result paths from JSON response
  - Update `image_cropped` and `thumbnail_cropped` columns
  - Handle failures gracefully (crop optional, not critical)

**`app/Services/TextGenerationService.php`:**
- `generateAdText(AdImage $titleImage, ?string $prompt, User $user): array` → OpenAI API call
- Send: Title image THUMBNAIL (base64, ~20-50KB) + prompt text
- Return: `['title' => '...', 'description' => '...', 'price' => 50, 'condition' => 'Gut', 'shipping' => 'klein']`
- Config: Model `gpt-4o-mini`, temp 0.7, max_tokens 1000, timeout 30s
- Append user-specific disclaimer from `User->appendix`

**Image Storage Decision:**
- ❌ Filesystem (consider for scaling):
  - Better performance (CDN)
  - Smaller database
  - Requires storage symlink
  - Backup complexity increases

**`app/Services/AdStorageService.php`:**
- `saveAd(Ad $ad, array $images): void` → Transaction: Save ad + images
- `updateAdStatus(string $uuid, string $status): void` → Track `last_online_at` when status → 'Online'
- `deleteAd(string $uuid): void` → Delete ad + images (cascade or manual)

### Action Classes Pattern (see `no_laravel/migration_source/oe-app-docker-compose/src/app/Actions/`)
- `app/Actions/StoreImage.php`: Handle image upload, resizing, storage
- `app/Actions/CreateImageVariants.php`: Generate multiple sizes (if needed)
- Keep logic out of controllers; use invokable classes for reusability

### Request Validation
```php
// app/Http/Requests/Ad/StoreUpdateRequest.php
// Pull limits from config, never hard-code
public function rules(): array
{
    return [
        'title' => ['required', 'string', 'max:' . config('ads.validation.title_max_length')],
        'description' => [
            'required',
            'string',
            'min:' . config('ads.validation.description_min_length'),
            'max:' . config('ads.validation.description_max_length')
        ],
        'price' => ['required', 'integer', 'min:0'],
        'condition' => ['required', Rule::in(config('ads.validation.conditions'))],
        'shipping' => ['required', Rule::in(config('ads.validation.shipping_options'))],
        'images' => [
            'nullable',
            'array',
            'min:' . config('ads.validation.images_min'),
            'max:' . config('ads.validation.images_max')
        ],
        'images.*' => [
            'image',
            'mimes:' . implode(',', config('ads.image.supported_formats')),
            'max:' . config('ads.image.max_upload_size')
        ],
        'prompt_text' => ['nullable', 'string', 'max:' . config('ads.validation.prompt_max_length')],
    ];
}
```

## Client-Side Image Handling (CRITICAL FOR PERFORMANCE)

### Client-Side Image Downsizing?
- Reduces server load by 90% (1MB instead of 10MB uploads)
- Faster upload for users (especially mobile)
- Prevents timeout on large image files
- Saves bandwidth (especially important for metered connections)
- Full resolution discarded client-side (never stored or transmitted)

### Implementation Requirements

**JavaScript Process** (values from `config('ads.image')`):
1. User selects image file
2. Check file size against `client_compression_threshold` (500KB default)
3. If larger: Read file → Canvas API via `browser-image-compression` library
4. Resize to `max_size` (1000px max)
5. Compress to `client_quality` quality (80% default)
6. Convert to Blob
7. Upload Blob to server
8. **Discard original file from memory**

**Configuration via Blade**:
Pass config values to JavaScript to avoid magic numbers:

```blade
<!-- Layout or parent Blade component -->
<script>
    window.appConfig = {
        image: {
            max_size: {{ config('ads.image.max_size') }},           // 1000
            client_quality: {{ config('ads.image.client_quality') }},     // 80
            client_compression_threshold: {{ config('ads.image.client_compression_threshold') }}, // 500 KB
        }
    };
</script>
```

Then use in JavaScript:
```javascript
const { max_size, client_quality, client_compression_threshold } = window.appConfig.image;

if (file.size <= client_compression_threshold * 1024) return file; // Skip if small

const options = {
    maxWidthOrHeight: max_size,
    initialQuality: client_quality / 100,
    // ...
};
```

**Recommended Library**: `browser-image-compression`

### Server-Side Processing

**Input:** Already-downsized file (~300-500KB)
**Process:**
1. Validate: Check format, re-validate size
2. Fix EXIF: `Image::read($file)->orient()`
3. Resize again: Ensure exactly 1000px
4. Generate: Thumbnail (220px)
5. Store: Both as MEDIUMBLOB

**Code Example:**
```php
// app/Services/ImageProcessingService.php
public function processImage(UploadedFile $file): array {
    // Validate (should already be ~1000px from client, but re-validate)
    if ($file->getSize() > config('ads.image.max_upload_size') * 1024) {
        throw new InvalidImageFormatError('Image too large');
    }

    $image = Image::read($file);
    $image = $image->orient(); // Fix EXIF

    // Original (resize to exactly max_size)
    $original = $image->scaleDown(
        config('ads.image.max_size'),
        config('ads.image.max_size')
    );
    $originalBytes = $original->toJpeg(
        quality: config('ads.image.jpeg_quality'),
        progressive: config('ads.image.progressive')
    );

    // Thumbnail (220px width, preserve aspect)
    $thumbnail = $image->coverDown(
        config('ads.image.thumbnail_width'),
        config('ads.image.thumbnail_max_height')
    );
    $thumbnailBytes = $thumbnail->toJpeg(quality: 75, progressive: true);

    return [
        'image_original' => $originalBytes,
        'thumbnail_original' => $thumbnailBytes,
    ];
}
```

### Important Constraints

- ❌ **Never store full original**: Already discarded client-side
- ❌ **Never keep original file path**: Only store processed versions
- ✅ **Validate file size twice**: Browser (client) + Server (defensive)
- ✅ **Validate dimensions**: Ensure image isn't maliciously resized
- ✅ **Validate format**: JPEG/PNG/AVIF only

## Design System (from `no_laravel/migration_source/oe-app-docker-compose/`)

### Color Palette (TailwindCSS custom colors via CSS variables)
```css
/* resources/css/app.css */
:root {
  --text: 226 251 253;
  --background: 3 34 38; /* Dark teal #032226 */
  --primary: 0 217 255; /* Cyan #00d9ff */
  --secondary: 153 10 127; /* Purple-pink #990a7f */
  --accent: 165 224 77; /* Lime green #a5e04d */
}

/* Usage in Tailwind */
bg-c-background, text-c-text, bg-c-primary, border-c-accent, etc.
```

**Color Usage Patterns** (ref: `no_laravel/migration_source/oe-app-docker-compose/src/resources/views/`):
- Background: `bg-c-background` (main page)
- Cards/Panels: `bg-c-primary/10` (light tint), `bg-c-primary/20` (medium)
- Buttons: `bg-c-accent/80 text-c-background` (accent buttons), `border-c-accent`
- Links: `text-c-primary hover:underline`
- Status badges: Use accent colors with opacity (orange, green, gray as defined above)

### Typography & Spacing
- Font: `font-serif` using "Nanum Myeongjo" (defined in `tailwind.config.js`)
- Borders: `borderWidth: { DEFAULT: 'thin' }` (Chrome-optimized, not 1px)
- Margins/Padding: Standard Tailwind scale (p-4, p-6, space-y-2, etc.)
- Shadows: `shadow-sm` for cards, `shadow` for elevated elements

### Component Patterns
**Info Panels** (ref: `no_laravel/migration_source/oe-app-docker-compose/src/resources/views/posts/index.blade.php`):
```blade
<div class="mb-4 flex rounded-lg bg-c-background p-4 text-blue-300" role="alert">
  <svg>...</svg>
  <div>Info text here</div>
</div>
```

**Buttons** (ref: `resources/views/components/*.blade.php`):
```blade
<!-- Primary -->
<button class="rounded-lg border-2 border-c-accent/80 bg-c-accent/80 px-2 py-1 text-c-background hover:bg-c-accent active:border-c-primary">
  Action
</button>

<!-- Secondary (tertiary style from Streamlit) -->
<button class="text-c-primary/60 hover:text-c-primary hover:underline">
  Secondary Action
</button>
```

**Card Layout** (combine oe-app post cards + Streamlit collapsible pattern):
```blade
<div class="overflow-hidden bg-c-primary/20 p-6 shadow-sm sm:rounded-lg">
  <!-- Card header: title, status badge, actions -->
  <div class="flex items-center justify-between">
    <h3 class="text-c-text">{{ $ad->title }}</h3>
    <span class="badge-{{ $ad->status }}">{{ $ad->status }}</span>
  </div>

  <!-- Collapsible content (Alpine.js x-show or Livewire) -->
  <div x-show="expanded">
    <!-- Images, description, edit form -->
  </div>
</div>
```

## Development Workflows

### Setup
```bash
First, ensure you have PHP, Composer and the Laravel Installer installed.
(/bin/bash -c "$(curl -fsSL https://php.new/install/linux)"). Done.

laravel new larasvelte --using=oseughu/svelte-starter-kit

npm install && npm run dev

# Database
php artisan migrate
php artisan serve
```

### Image Processing Workflow
1. **Upload Stage** (AJAX/Livewire on image select):
   - Validate upload via form request
   - Store original version immediately
   - Return preview + "Processing..." indicator

2. **Processing Stage** (Queue job recommended):
   - Fix EXIF orientation on original
   - Generate original thumbnail: `coverDown(config('ads.image.thumbnail_width'), config('ads.image.thumbnail_max_height'))`
   - Conditionally apply YOLO crop (if `config('ads.auto_crop.enabled')`)
   - Store cropped version + cropped thumbnail
   - Update `image_cropped` and `thumbnail_cropped` columns

3. **User Selection**:
   - Show both versions side-by-side with radio/toggle
   - Default: uncropped (`use_cropped = false`)
   - Update `use_cropped` boolean on user toggle

4. **Display**:
   - List view: Show thumbnail (respects `use_cropped` preference)
   - Edit form: Show both versions, allow toggle
   - Lazy load images via Intersection Observer

### OpenAI Integration
```php
// app/Services/TextGenerationService.php
use Illuminate\Support\Facades\Http;

public function generateAdText(string $base64Image, string $prompt, ?string $userAppendix = null): array
{
    // All config values from config/ads.php and .env
    $response = Http::timeout(config('ads.openai.timeout'))
        ->withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
        ])
        ->post(config('services.openai.url') . '/chat/completions', [
            'model' => config('ads.openai.model'),
            'temperature' => config('ads.openai.temperature'),
            'max_tokens' => config('ads.openai.max_tokens'),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$base64Image}"]],
                    ],
                ],
            ],
        ]);

    $result = json_decode($response['choices'][0]['message']['content'], true);

    // Append user disclaimer to description
    if ($userAppendix) {
        $result['description'] .= "\n\n" . $userAppendix;
    }

    return $result;
}
```

### Testing Commands
```bash
php artisan test --filter AdCreationTest
php artisan test --parallel
./vendor/bin/phpunit --coverage-html coverage/
```

## Code Conventions

### PHP Standards
- PSR-12 + strict types: `declare(strict_types=1);`
- Type hints required: `public function storeAd(StoreUpdateRequest $request): RedirectResponse`
- Eloquent casts: `protected $casts = ['metadata' => 'array', 'status' => AdStatus::class];`

### Routing Patterns
```php
// routes/web.php
Route::middleware('auth')->group(function () {
    Route::resource('ads', AdController::class); // RESTful routes
    Route::post('ads/{ad}/regenerate', [AdController::class, 'regenerate'])->name('ads.regenerate');
    Route::patch('ads/{ad}/reorder-images', [AdController::class, 'reorderImages']);
    Route::delete('ads/{ad}/images/{image}', [AdImageController::class, 'destroy']);
});
```

## Reference Files for Patterns in no_laravel/migration_source/kleinanz-slim/ (laravel old version)
Use this only for generic insight and not as code best practice, since they are outdate versions.
**Streamlit Features** (`no_laravel/migration_source/kleinanz-slim/`):
- Ad schema: `app/anzeigen_schema.py`
- Image processing: `app/services/image_processing.py`
- Auto-crop: `app/services/auto_crop.py`
- Text generation: `app/services/text_generation.py`
- Storage layer: `app/services/storage.py`
- List view UI: `app/pages/list_ads.py` (lazy_expander pattern)
- Create flow: `app/pages/create_ads.py`

**Laravel Patterns** (`no_laravel/migration_source/oe-app-docker-compose/src/`):
- Image handling: `app/Actions/StoreImage.php`, `app/Models/Image.php`
- Post model: `app/Models/Post.php` (similar structure to Ad)
- Blade layout: `resources/views/layouts/app.blade.php`
- Color system: `resources/css/app.css` + `tailwind.config.js`
- View components: `resources/views/components/*.blade.php`

## Critical Integration Points

### External Dependencies
- **OpenAI API**: `gpt-4o-mini` for text generation (key in `.env` or `users.openai_api_key`)
- **YOLO Auto-Crop**: Native PHP ONNX (recommended)
  - **Why?** ONNX already proven in Streamlit app (`no_laravel/migration_source/kleinanz-slim/app/services/auto_crop.py`)
  - **How:** Use `ax/onnxruntime` PHP package + reuse ONNX model from Streamlit
  - **Alternative:** Python subprocess if VPS/dedicated (for Python heavy environments)
  - **Model:** `yolov8n-fashionpedia` (fashion detection, CPU-friendly)
- **Image Storage**: Choose between:
  - Database BLOB (like Streamlit: `MEDIUMBLOB` for images, `BLOB` for thumbnails)
  - Filesystem (`storage/app/public/ads/{uuid}/`)
  - S3/Cloud storage (for production scalability)

### Configuration Files

**`.env` (environment-specific)**:
```env
# Application
APP_NAME="Kleinanzeigen Generator"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kleinanz
DB_USERNAME=root
DB_PASSWORD=

# OpenAI API (required for text generation)
OPENAI_API_KEY=sk-...
OPENAI_API_URL=https://api.openai.com/v1

# YOLO Auto-Crop (native PHP ONNX - recommended)
AUTO_CROP_METHOD=onnx
AUTO_CROP_MODEL_PATH=/app/storage/models/yolov8n-fashionpedia-1.onnx

# Optional: Python subprocess (fallback, if using VPS)
# AUTO_CROP_METHOD=python
# AUTO_CROP_PYTHON_PATH=/usr/bin/python3

# Queue (recommend 'database' for simple setups, 'redis' for production)
QUEUE_CONNECTION=database

# Cache
CACHE_DRIVER=file
```

**`config/ads.php` (application-level)**:
```php
<?php
return [
    // Image Processing
    'image' => [
        'max_size' => 1000, // Max width/height in pixels
        'thumbnail_width' => 220,
        'thumbnail_max_height' => 880,
        'jpeg_quality' => 85, // 0-100
        'progressive' => true,
        'max_upload_size' => 20480, // KB (20MB)
        'supported_formats' => ['jpg', 'jpeg', 'png', 'avif'],
    ],

    // Auto-Crop (YOLO) - Native PHP ONNX recommended
    'auto_crop' => [
        'enabled' => true,
        'method' => env('AUTO_CROP_METHOD', 'onnx'), // 'onnx' or 'python'
        'detection_threshold' => 0.2,
        'closeup_threshold' => 0.70,
        'margin_percent' => 2,
        'model_path' => env('AUTO_CROP_MODEL_PATH', storage_path('models/yolov8n-fashionpedia-1.onnx')),
    ],

    // OpenAI Text Generation
    'openai' => [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.7,
        'max_tokens' => 1000,
        'timeout' => 30, // seconds
    ],

    // Validation Rules
    'validation' => [
        'title_max_length' => 80,
        'description_min_length' => 50,
        'description_max_length' => 1000,
        'prompt_max_length' => 1000,
        'images_min' => 1,
        'images_max' => 10,
        'conditions' => ['Neu', 'Sehr gut', 'Gut', 'In Ordnung', 'Defekt'],
        'shipping_options' => ['klein', 'mittel'],
    ],

    // Business Logic
    'status' => [
        'expiry_days' => 60, // Days until ad expires after going online
        'default' => 'Entwurf',
        'options' => ['Entwurf', 'Online', 'Archiviert'],
    ],

    // Default disclaimer appended to descriptions
    'default_disclaimer' => "Abholung bevorzugt, Versand möglich.\nPrivatverkauf. Ich schließe jegliche Sachmangelhaftung aus.",
];
```

**`config/services.php` (add to existing file)**:
```php
'openai' => [
    'key' => env('OPENAI_API_KEY'),
    'url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
],

'onnx' => [
    'model_path' => env('AUTO_CROP_MODEL_PATH', storage_path('models/yolov8n-fashionpedia-1.onnx')),
],

'python' => [
    'path' => env('AUTO_CROP_PYTHON_PATH', '/usr/bin/python3'),
],
```

## Deployment Notes
- **Database**: SQLite for dev, MySQL/PostgreSQL for production
- **Queue**: Process image cropping/OpenAI async (`php artisan queue:work`)
- **Storage**: Symlink public storage: `php artisan storage:link`
- **Cache**: Redis recommended for session/cache in production
