# Extracted Features

- o means optional, do not implement

## Auth User
- [x] Login with email and password
- [ ] Registration with email validation
- [ ] Registration with password strength validation
- o[ ] Registration with captcha
- [x] Logout

## User
- [x] Per-user ad ownership
- [ ] Per-user OpenAI API key
- [ ] Per-user one disclaimer appendixes by platform (Vinted, Kleinanzeigen)

## Ad Create
- [ ] Create empty ad first
- [ ] Unified create/edit form
- [ ] Generate button enabled only with image(s)
- [ ] Generate fills title
- [ ] Generate fills description
- [ ] Generate fills price
- [ ] Generate fills shipping
- [ ] Generate fills condition
- [ ] Generate accepts optional user input message (in UI named "Prompt")
- [ ] User can switch platform while creating ad (platform: Vinted, Kleinanzeigen)
- [ ] Generate uses different system-prompts based on choosen platform
- [ ] Manual edits after generation
- [x] Save ad with metadata
- o[ ] Auto-save on changes
- [x] Explicit save button flow


## Image Upload and Processing
- [x] Multi-image upload
- [x] Max image count limit
- [x] Supported formats validation
- [ ] Client-side resize before upload
- [ ] Client-side compression before upload
- [ ] Discard full-resolution original
- [ ] EXIF orientation fix
- [x] Server-side image validation
- [ ] Processed image at max size
- [x] Thumbnail generation
- [ ] Progressive JPEG encoding
- [x] Auto-crop clothing detection
- [x] Close-up detection skip crop
- [x] Crop with configurable margin
- [x] Crop failure fallback to original
- [x] Async auto-crop job dispatch on upload
- [x] Crop metadata persisted per image

## Image Management in Form
- [x] Image preview grid
- [x] First image default as title image
- [x] Set/change title image of ad by click
- [x] Delete individual images
- [x] Add more images during edit
- [x] Store original and cropped variants
- [x] Let user choose uncropped version if image was cropped
- [x] Thumbnail-only rendering in form and lists
- [ ] Download high-res selected variants
- [x] Delete image confirmation prompt

## Ad List and Edit
- [x] List saved ads
- [x] Status color indicators
- [ ] Expiry indicator (60 days kleinanzeigen platform)
- [x] copy title action from ads overview list (without need to open edit)
- [x] copy description action from ads overview list (without need to open edit)
- [x] Gallery thumbnail
- [x] Per-image download from list
- [x] Per-image download respects crop preference
- [x] Delete ad with confirmation dialog
- [x] Flash success/error messages
- [ ] Lazy loading behavior
- [x] Pagination
- [x] Responsive grid layout

## Lifecycle and Business Rules
- [x] Statuses: Entwurf, Online, Archiviert
- [x] Default status Entwurf
- [x] Track `last_online_at` when status becomes Online
- [x] Expiry after 60 days online
- [x] Expiry date display

## Text Generation
- [ ] OpenAI model integration
- [ ] Title-image input to model
- [ ] User prompt inclusion
- [ ] Structured JSON output parsing
- [ ] Mock mode for generation
- [ ] Append user disclaimer to description based on choosen platform

## Info and Service
- [ ] API key save/remove UI
- [ ] API key masked status display
- [ ] Test mode toggle
- [ ] Debug info dialog
- [ ] Database online/offline status
- [ ] Changelog view

## Small Requests
- [x] Create/Edit: remove title image index before upload; always default title image
- [x] Create/Edit: identical image-first layout with visible generate button
- [x] Create/Edit: live input counters (for example description `373 / 1000`)
- [x] Create/Edit: improve copy/text clarity and image upload UX (\"Click to upload images\")
- [x] Ads list: preview image height shown at 220px
- [x] Ads list: clickable status button/dropdown for quick status changes
- [x] Ads list: single \"Download all images\" action
