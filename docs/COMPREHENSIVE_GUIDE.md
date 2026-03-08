# TGSDK Comprehensive Documentation

## Complete Guide to Laravel Telegram Hybrid Storage

---

## Table of Contents

1. [Introduction](#introduction)
2. [Prerequisites](#prerequisites)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Telegram Bot Setup](#telegram-bot-setup)
6. [Admin Panel Configuration](#admin-panel-configuration)
7. [Usage Examples](#usage-examples)
8. [SASS/CSS Architecture](#sasscss-architecture)
9. [Troubleshooting](#troubleshooting)
10. [Best Practices](#best-practices)

---

## Introduction

TGSDK is a powerful Laravel package that implements a custom filesystem driver backed by Telegram channels. It provides unlimited cloud storage through Telegram's MTProto API with features like:

- **Unlimited Storage**: Chunk files beyond Telegram's 2GB/4GB limits
- **Async Uploads**: Non-blocking uploads via Redis queue and Python worker
- **Channel Rotation**: Distribute files across multiple Telegram channels
- **CDN Support**: Optional CDN integration for streaming
- **Security**: HMAC signature verification and token-based access
- **Encryption**: AES-256-GCM encryption for chunks (optional)

---

## Prerequisites

### System Requirements

- **PHP**: 8.4 or higher
- **Laravel**: 12.x or higher
- **Database**: MySQL, PostgreSQL, or SQLite
- **Redis**: Required for queue management
- **Python**: 3.8+ (for worker process)
- **Composer**: Latest version

### Required PHP Extensions

```bash
php-redis
php-sqlite3 (for testing)
php-gd (optional, for image processing)
php-bcmath (for large number operations)
```

### Python Dependencies

The Python worker requires:
```bash
pyrogram>=2.0.0
tgcrypto>=1.2.0
redis>=4.0.0
python-dotenv>=0.19.0
```

---

## Installation

### Step 1: Install Package via Composer

```bash
composer require shamimstack/tgsdk
```

### Step 2: Publish Configuration Files

```bash
# Publish main configuration
php artisan vendor:publish --tag=telegram-storage-config

# Publish database migrations
php artisan vendor:publish --tag=telegram-storage-migrations

# Publish routes (optional)
php artisan vendor:publish --tag=telegram-storage-routes
```

### Step 3: Run Migrations

```bash
php artisan migrate
```

This creates three tables:
- `telegram_channels` - Store channel configurations
- `telegram_files` - Track uploaded files
- `telegram_file_chunks` - Manage file chunks

### Step 4: Configure Filesystem Driver

Add the Telegram disk to `config/filesystems.php`:

```php
'disks' => [
    // ... other disks
    
    'telegram' => [
        'driver' => 'telegram',
    ],
],
```

### Step 5: Set Environment Variables

Add to your `.env` file:

```env
# Telegram API Credentials
TELEGRAM_API_ID=your_api_id_here
TELEGRAM_API_HASH=your_api_hash_here
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_SESSION_NAME=telegram_storage

# Redis Configuration
TELEGRAM_STORAGE_REDIS_CONNECTION=default
TELEGRAM_STORAGE_REDIS_QUEUE=telegram_upload_queue

# Worker Callback
TELEGRAM_STORAGE_CALLBACK_URL=https://your-domain.com/telegram-storage/callback
TELEGRAM_STORAGE_CALLBACK_SECRET=your_random_secret_key

# Optional: CDN Configuration
TELEGRAM_STORAGE_CDN_ENABLED=false
TELEGRAM_STORAGE_CDN_PREFIX=https://cdn.your-domain.com
```

### Step 6: Setup Python Worker

Navigate to the worker directory and install dependencies:

```bash
cd python-worker
cp .env.example .env
# Edit .env with your Telegram credentials
pip install -r requirements.txt
```

Start the worker:

```bash
# Direct execution
python worker.py

# Or using Docker
docker build -t tgsdk-worker .
docker run --env-file .env tgsdk-worker
```

---

## Configuration

### Main Configuration File (`config/telegram-storage.php`)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Channels
    |--------------------------------------------------------------------------
    |
    | List of Telegram channel IDs where files will be stored.
    | Each channel should be a string starting with -100 for supergroups.
    |
    */
    'channels' => [
        '-1001234567890',
        '-1009876543210',
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Rotation Strategy
    |--------------------------------------------------------------------------
    |
    | Determine how channels are selected for each upload:
    | - "round-robin": Distribute evenly across all channels
    | - "least-used": Select channel with fewest files
    | - "capacity-aware": Select channel with most available space
    |
    */
    'rotation_strategy' => 'round-robin',

    /*
    |--------------------------------------------------------------------------
    | File Chunking Configuration
    |--------------------------------------------------------------------------
    |
    | Control how large files are split into chunks for Telegram upload.
    | Telegram has a 2GB limit for regular bots, 4GB for premium.
    |
    */
    'chunk_threshold' => 1950000000,  // Bytes (1.95GB recommended)
    'chunk_size' => 1950000000,       // Bytes per chunk
    'chunk_compression' => false,      // Enable gzip compression
    'chunk_encryption' => false,       // Enable AES-256-GCM encryption

    /*
    |--------------------------------------------------------------------------
    | Download Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how file downloads are handled, including URL signing
    | and CDN integration.
    |
    */
    'download' => [
        'signed_urls' => true,         // Require signed download URLs
        'url_ttl' => 3600,            // Signed URL validity in seconds
        'stream_enabled' => true,      // Allow direct streaming
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Configuration
    |--------------------------------------------------------------------------
    |
    | Optional CDN integration for serving files through a CDN endpoint.
    |
    */
    'cdn' => [
        'enabled' => false,
        'prefix' => env('TELEGRAM_STORAGE_CDN_PREFIX'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the Python upload worker and callback system.
    |
    */
    'worker_callback_secret' => env('TELEGRAM_STORAGE_CALLBACK_SECRET'),
    'temp_path' => storage_path('app/telegram-storage-temp'),

    /*
    |--------------------------------------------------------------------------
    | Redis Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Redis connection and queue settings for async uploads.
    |
    */
    'redis' => [
        'connection' => env('TELEGRAM_STORAGE_REDIS_CONNECTION', 'default'),
        'queue' => env('TELEGRAM_STORAGE_REDIS_QUEUE', 'telegram_upload_queue'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Broadcasting
    |--------------------------------------------------------------------------
    |
    | Configure which events are broadcast to listeners.
    |
    */
    'events' => [
        'upload_completed' => true,
        'upload_failed' => true,
        'chunk_completed' => true,
        'file_deleted' => true,
    ],
];
```

---

## Telegram Bot Setup

### Step 1: Create a Telegram Bot

1. Open Telegram and search for **@BotFather**
2. Send `/newbot` command
3. Follow prompts to name your bot
4. Save the bot token provided

### Step 2: Get API ID and API Hash

1. Visit https://my.telegram.org/apps
2. Log in with your phone number
3. Create a new application
4. Copy your **API ID** and **API Hash**

### Step 3: Create Storage Channels

1. Create a new Telegram channel
2. Add your bot as an administrator
3. Get the channel ID:
   - Forward a message from the channel to @RawDataBot
   - Copy the `chat.id` value (should start with -100)

### Step 4: Configure Session

Create a session file using the Python script:

```bash
cd python-worker
python -c "from pyrogram import Client; app = Client('telegram_storage'); app.start(); app.stop()"
```

Enter your phone number and code when prompted.

### Step 5: Verify Configuration

Test your configuration:

```bash
php artisan tinker
>>> Storage::disk('telegram')->put('test.txt', 'Hello World');
=> true
```

---

## Admin Panel Configuration

### Installing Filament Admin Panel (Optional)

For a beautiful admin interface to manage your storage:

```bash
composer require filament/filament:"^3.0" -W
php artisan filament:install --panels
```

### Creating Resource Classes

Create admin resources for managing channels and files:

```bash
php artisan make:filament-resource TelegramChannel
php artisan make:filament-resource TelegramFile
```

### Example Channel Management Form

```php
// app/Filament/Resources/TelegramChannelResource.php

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('channel_identifier')
                ->label('Channel ID')
                ->required()
                ->placeholder('-1001234567890'),
            
            Forms\Components\TextInput::make('label')
                ->label('Display Name')
                ->required(),
            
            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
            
            Forms\Components\TextInput::make('max_files')
                ->label('Max Files')
                ->numeric()
                ->default(10000),
            
            Forms\Components\TextInput::make('current_usage')
                ->label('Current Usage (bytes)')
                ->numeric()
                ->disabled()
                ->default(0),
        ]);
}
```

### Dashboard Widgets

Create widgets to monitor storage usage:

```php
// app/Filament/Widgets/StorageStatsOverview.php

protected function getStatsData(): array
{
    return [
        'totalFiles' => TelegramFile::count(),
        'totalSize' => TelegramFile::sum('size'),
        'activeChannels' => TelegramChannel::where('is_active', true)->count(),
        'pendingUploads' => TelegramFile::where('status', 'pending')->count(),
    ];
}
```

---

## Usage Examples

### Basic File Operations

```php
use Illuminate\Support\Facades\Storage;

// Upload a file
$diskPath = Storage::disk('telegram')->put('documents/report.pdf', $fileContents);

// Upload from request
$path = $request->file('avatar')->store('avatars', 'telegram');

// Upload from stream
Storage::disk('telegram')->putStream('videos/clip.mp4', fopen('/path/to/file', 'r'));

// Check if file exists
$exists = Storage::disk('telegram')->exists('documents/report.pdf');

// Get file contents
$contents = Storage::disk('telegram')->get('documents/report.pdf');

// Get file as resource
$resource = Storage::disk('telegram')->readStream('documents/report.pdf');

// Delete file
Storage::disk('telegram')->delete('documents/report.pdf');

// Move file
Storage::disk('telegram')->move('old/path.txt', 'new/path.txt');

// Get file size
$size = Storage::disk('telegram')->size('documents/report.pdf');

// Get MIME type
$mime = Storage::disk('telegram')->mimeType('documents/report.pdf');
```

### Generating Download URLs

```php
// Public URL (if configured)
$url = Storage::disk('telegram')->url('documents/report.pdf');

// Temporary signed URL (expires in 1 hour)
$tempUrl = Storage::disk('telegram')->temporaryUrl(
    'documents/report.pdf',
    now()->addHour()
);

// Custom expiration
$customUrl = Storage::disk('telegram')->temporaryUrl(
    'documents/report.pdf',
    now()->addMinutes(30)
);
```

### Working with Large Files

```php
// Files over 1.95GB are automatically chunked
$largeFile = file_get_contents('/path/to/large-video.mkv'); // 5GB file
Storage::disk('telegram')->put('videos/movie.mkv', $largeFile);

// The file is automatically:
// 1. Split into chunks (~1.95GB each)
// 2. Enqueued to Redis
// 3. Uploaded by Python worker
// 4. Reassembled on download
```

### Event Handling

```php
use Shamimstack\Tgsdk\Events\TelegramUploadCompleted;
use Shamimstack\Tgsdk\Events\TelegramUploadFailed;
use Shamimstack\Tgsdk\Events\TelegramChunkCompleted;
use Illuminate\Support\Facades\Event;

// Listen for upload completion
Event::listen(TelegramUploadCompleted::class, function ($event) {
    Log::info("File uploaded successfully", [
        'path' => $event->path,
        'fileId' => $event->fileId,
        'size' => $event->size,
    ]);
    
    // Send notification
    Notification::send($user, new FileUploadedNotification($event));
});

// Handle failures
Event::listen(TelegramUploadFailed::class, function ($event) {
    Log::error("Upload failed", [
        'path' => $event->path,
        'error' => $event->error,
    ]);
    
    // Alert admin
    alertAdmin("Upload failed: {$event->path}");
});

// Monitor chunk uploads
Event::listen(TelegramChunkCompleted::class, function ($event) {
    Log::debug("Chunk uploaded", [
        'fileId' => $event->fileId,
        'chunkIndex' => $event->chunkIndex,
        'totalChunks' => $event->totalChunks,
    ]);
});
```

### Database Integration

```php
use Shamimstack\Tgsdk\Models\TelegramFile;
use Shamimstack\Tgsdk\Models\TelegramChannel;

// Get all files
$files = TelegramFile::with('chunks')->latest()->paginate(20);

// Get files by status
$pending = TelegramFile::where('status', 'pending')->get();
$available = TelegramFile::where('status', 'available')->get();

// Get channel statistics
$channels = TelegramChannel::withCount('files')
    ->orderBy('current_usage', 'desc')
    ->get();

// Find file by path
$file = TelegramFile::where('disk_path', 'documents/report.pdf')->firstOrFail();

// Get total storage used
$totalSize = TelegramFile::sum('size');
```

### Advanced: Custom Upload Job

```php
use Shamimstack\Tgsdk\Jobs\UploadToTelegramJob;
use Illuminate\Support\Facades\Queue;

// Dispatch to specific queue
UploadToTelegramJob::dispatch($fileId)->onQueue('high-priority');

// Chain multiple uploads
$jobs = collect($files)->map(fn($file) => 
    new UploadToTelegramJob($file->id)
);

Bus::batch($jobs->toArray())
    ->then(fn() => Log::info('Batch upload completed'))
    ->catch(fn($e) => Log::error('Batch failed: ' . $e->getMessage()))
    ->dispatch();
```

---

## SASS/CSS Architecture

### Directory Structure

```
docs/assets/
├── scss/
│   ├── _variables.scss      # SCSS variables and design tokens
│   ├── _mixins.scss         # Reusable mixins
│   ├── _base.scss           # Base styles and resets
│   ├── _layout.scss         # Layout components
│   ├── _components.scss     # UI components
│   ├── _animations.scss     # Animations and transitions
│   └── style.scss           # Main stylesheet
├── css/
│   └── style.css            # Compiled CSS
└── js/
    └── main.js              # JavaScript functionality
```

### SCSS Variables (`_variables.scss`)

```scss
/* ==========================================================================
   Color Palette
   ========================================================================== */

// Primary colors (Telegram blue theme)
$primary-color: #0088cc;
$primary-dark: #006699;
$primary-light: #33a0e0;
$primary-glow: rgba(0, 136, 204, 0.5);

// Secondary colors (Amber accents)
$secondary-color: #f59e0b;
$secondary-dark: #d97706;
$secondary-light: #fbbf24;

// Gray palette
$gray-100: #f8fafc;
$gray-200: #edf2f7;
$gray-300: #e2e8f0;
$gray-400: #cbd5e0;
$gray-500: #a0aec0;
$gray-600: #718096;
$gray-700: #4a5568;
$gray-800: #2d3748;
$gray-900: #1a202c;

// Semantic colors
$success-color: #10b981;
$warning-color: #f59e0b;
$error-color: #ef4444;
$info-color: #3b82f6;

/* ==========================================================================
   Typography
   ========================================================================== */

$font-family-base: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
$font-family-mono: 'Fira Code', 'Courier New', monospace;

$font-size-xs: 0.75rem;     // 12px
$font-size-sm: 0.875rem;    // 14px
$font-size-base: 1rem;      // 16px
$font-size-lg: 1.125rem;    // 18px
$font-size-xl: 1.25rem;     // 20px
$font-size-2xl: 1.5rem;     // 24px
$font-size-3xl: 1.875rem;   // 30px
$font-size-4xl: 2.25rem;    // 36px

$line-height-none: 1;
$line-height-tight: 1.25;
$line-height-normal: 1.5;
$line-height-relaxed: 1.75;

/* ==========================================================================
   Spacing Scale
   ========================================================================== */

$spacing-0: 0;
$spacing-1: 0.25rem;    // 4px
$spacing-2: 0.5rem;     // 8px
$spacing-3: 0.75rem;    // 12px
$spacing-4: 1rem;       // 16px
$spacing-5: 1.25rem;    // 20px
$spacing-6: 1.5rem;     // 24px
$spacing-8: 2rem;       // 32px
$spacing-10: 2.5rem;    // 40px
$spacing-12: 3rem;      // 48px
$spacing-16: 4rem;      // 64px

/* ==========================================================================
   Breakpoints
   ========================================================================== */

$breakpoint-sm: 640px;
$breakpoint-md: 768px;
$breakpoint-lg: 1024px;
$breakpoint-xl: 1280px;
$breakpoint-2xl: 1536px;

/* ==========================================================================
   Shadows & Effects
   ========================================================================== */

$shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
$shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
$shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
$shadow-glow-primary: 0 0 20px rgba(0, 136, 204, 0.3);
$shadow-glow-secondary: 0 0 20px rgba(245, 158, 11, 0.3);

$border-radius-sm: 0.25rem;
$border-radius-md: 0.5rem;
$border-radius-lg: 1rem;
$border-radius-xl: 1.5rem;
```

### SCSS Mixins (`_mixins.scss`)

```scss
/* ==========================================================================
   Layout Mixins
   ========================================================================== */

@mixin flex-center {
    display: flex;
    justify-content: center;
    align-items: center;
}

@mixin flex-between {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

@mixin grid-layout($columns, $gap: $spacing-4) {
    display: grid;
    grid-template-columns: repeat($columns, 1fr);
    gap: $gap;
}

/* ==========================================================================
   Responsive Mixins
   ========================================================================== */

@mixin respond-to($breakpoint) {
    @if $breakpoint == sm {
        @media (min-width: $breakpoint-sm) { @content; }
    }
    @else if $breakpoint == md {
        @media (min-width: $breakpoint-md) { @content; }
    }
    @else if $breakpoint == lg {
        @media (min-width: $breakpoint-lg) { @content; }
    }
    @else if $breakpoint == xl {
        @media (min-width: $breakpoint-xl) { @content; }
    }
}

@mixin mobile-first {
    @content;
    @include respond-to(md) {
        @content;
    }
}

/* ==========================================================================
   Component Mixins
   ========================================================================== */

@mixin card-style {
    background: white;
    border-radius: $border-radius-lg;
    box-shadow: $shadow-md;
    padding: $spacing-6;
    transition: transform 0.2s, box-shadow 0.2s;
    
    &:hover {
        transform: translateY(-2px);
        box-shadow: $shadow-lg;
    }
}

@mixin button-style($variant: primary) {
    padding: $spacing-3 $spacing-6;
    border-radius: $border-radius-md;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    
    @if $variant == primary {
        background: $primary-color;
        color: white;
        
        &:hover {
            background: $primary-dark;
            box-shadow: $shadow-glow-primary;
        }
    }
    @else if $variant == secondary {
        background: $secondary-color;
        color: white;
        
        &:hover {
            background: $secondary-dark;
            box-shadow: $shadow-glow-secondary;
        }
    }
}

@mixin glass-effect {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}
```

### Component Classes

```scss
/* Sidebar Navigation */
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, $gray-900 0%, $gray-800 100%);
    color: $gray-100;
    padding: $spacing-6;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    
    .logo {
        margin-bottom: $spacing-8;
        
        h1 {
            font-size: $font-size-2xl;
            color: $primary-light;
            margin-bottom: $spacing-2;
        }
    }
    
    .nav-menu {
        list-style: none;
        
        li {
            margin-bottom: $spacing-2;
            
            a {
                display: flex;
                align-items: center;
                gap: $spacing-3;
                padding: $spacing-3 $spacing-4;
                border-radius: $border-radius-md;
                color: $gray-300;
                text-decoration: none;
                transition: all 0.2s;
                
                &:hover,
                &.active {
                    background: rgba($primary-color, 0.2);
                    color: $primary-light;
                }
            }
        }
    }
}

/* Feature Cards */
.features-grid {
    @include grid-layout(3, $spacing-6);
    margin-top: $spacing-8;
    
    @include respond-to(lg) {
        grid-template-columns: repeat(2, 1fr);
    }
    
    @include respond-to(sm) {
        grid-template-columns: 1fr;
    }
}

.feature-card {
    @include card-style;
    text-align: center;
    
    i {
        font-size: $font-size-4xl;
        color: $primary-color;
        margin-bottom: $spacing-4;
    }
    
    h3 {
        font-size: $font-size-xl;
        margin-bottom: $spacing-3;
        color: $gray-800;
    }
}

/* Code Blocks */
pre {
    background: $gray-900;
    color: $gray-100;
    padding: $spacing-6;
    border-radius: $border-radius-lg;
    overflow-x: auto;
    font-family: $font-family-mono;
    font-size: $font-size-sm;
    line-height: $line-height-relaxed;
    border-left: 4px solid $primary-color;
    box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.2);
}
```

---

## Troubleshooting

### Common Issues and Solutions

#### 1. Redis Connection Error

**Error:** `RedisException: Connection refused`

**Solution:**
```bash
# Check Redis is running
redis-cli ping
# Should return: PONG

# If not running:
sudo systemctl start redis
sudo systemctl enable redis

# Update .env if using different port
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

#### 2. Python Worker Not Starting

**Error:** `ModuleNotFoundError: No module named 'pyrogram'`

**Solution:**
```bash
cd python-worker
pip install -r requirements.txt

# Or create virtual environment
python -m venv venv
source venv/bin/activate  # Linux/Mac
venv\Scripts\activate     # Windows
pip install -r requirements.txt
```

#### 3. File Upload Fails Silently

**Symptoms:** File stays in "pending" status

**Debug Steps:**
```bash
# Check queue status
redis-cli
> LLEN telegram_upload_queue

# Check worker logs
tail -f storage/logs/laravel.log

# Verify bot is admin in channel
# Check channel ID format (must start with -100)
```

#### 4. Signature Verification Failed

**Error:** `Invalid signature` on callback

**Solution:**
```php
// Verify secret matches in both places
// Laravel: config/telegram-storage.php
'worker_callback_secret' => env('TELEGRAM_STORAGE_CALLBACK_SECRET'),

// Python: .env file
CALLBACK_SECRET=your_secret_here

// They must match exactly!
```

#### 5. Chunks Not Reassembling

**Symptoms:** File shows as available but download fails

**Solution:**
```bash
# Check database for orphaned chunks
php artisan tinker
>>> use Shamimstack\Tgsdk\Models\TelegramFileChunk;
>>> TelegramFileChunk::whereNull('file_id')->count();

# Clean up orphaned chunks
>>> TelegramFileChunk::whereNull('file_id')->delete();

# Retry failed uploads
>>> use Shamimstack\Tgsdk\Models\TelegramFile;
>>> TelegramFile::where('status', 'failed')->update(['status' => 'pending']);
```

#### 6. Slow Upload Performance

**Symptoms:** Uploads taking too long

**Optimization:**
```env
# Increase PHP memory limit
memory_limit=512M

# Increase max execution time
max_execution_time=300

# Optimize chunk size (balance between speed and reliability)
TELEGRAM_STORAGE_CHUNK_SIZE=1000000000  # 1GB chunks
```

#### 7. SSL/Certificate Issues

**Error:** `SSL certificate problem: unable to get local issuer certificate`

**Solution:**
```bash
# Update CA certificates
# Ubuntu/Debian:
sudo apt-get update
sudo apt-get install --reinstall ca-certificates

# Or disable SSL verification (NOT recommended for production)
# In Python worker config.py:
VERIFY_SSL = False
```

---

## Best Practices

### Performance Optimization

#### 1. Queue Management

```php
// Use separate queue for Telegram uploads
'telegram' => [
    'driver' => 'redis',
    'connection' => 'telegram-redis',
    'queue' => 'telegram_uploads',
    'retry_after' => 900, // 15 minutes for large files
],

// Process uploads in background
php artisan queue:work telegram --sleep=3 --tries=3
```

#### 2. Database Optimization

```php
// Add indexes for faster queries
Schema::table('telegram_files', function (Blueprint $table) {
    $table->index('status');
    $table->index('channel_id');
    $table->index('disk_path');
});

// Regular cleanup of old failed uploads
Schedule::call(function () {
    TelegramFile::where('status', 'failed')
        ->where('created_at', '<', now()->subDays(7))
        ->delete();
})->weekly();
```

#### 3. Caching Strategy

```php
// Cache frequently accessed data
$channels = Cache::remember('active-channels', 3600, function () {
    return TelegramChannel::where('is_active', true)->get();
});

// Clear cache on changes
Event::listen(ChannelUpdated::class, function () {
    Cache::forget('active-channels');
});
```

#### 4. CDN Integration

```php
// Configure CDN for faster downloads
'cdn' => [
    'enabled' => true,
    'prefix' => 'https://cdn.yourdomain.com/telegram',
    'cache_ttl' => 86400, // 24 hours
],

// Use CloudFlare or similar
// Point CNAME to your Laravel app
```

### Security Recommendations

#### 1. Rate Limiting

```php
// routes/telegram-storage.php
Route::middleware([
    'throttle:60,1',  // 60 requests per minute
    'verified'
])->group(function () {
    Route::post('/callback', [CallbackController::class, 'handle']);
});
```

#### 2. Input Validation

```php
// Validate all file uploads
$request->validate([
    'file' => 'required|file|max:10485760', // 10GB max
    'path' => 'nullable|string|max:255',
]);

// Sanitize paths
$path = str_replace('..', '', $request->path);
```

#### 3. Access Control

```php
// Middleware to check permissions
class CanAccessTelegramStorage
{
    public function handle($request, Closure $next)
    {
        if (!auth()->user()->can('manage-storage')) {
            abort(403, 'Unauthorized access to storage');
        }
        
        return $next($request);
    }
}
```

### Monitoring and Logging

```php
// Monitor queue depth
$scheduler->call(function () {
    $depth = Redis::llen('telegram_upload_queue');
    if ($depth > 100) {
        alertAdmin("High queue depth: {$depth}");
    }
})->everyFiveMinutes();

// Track storage usage
$totalSize = TelegramFile::sum('size');
if ($totalSize > 100 * 1024 * 1024 * 1024) { // 100GB
    alertAdmin('Storage approaching limit');
}
```

### Backup Strategy

```bash
# Regular database backups
php artisan backup:run

# Export channel configurations
php artisan telegram:export-channels > backup-channels.json

# Document session files
cp python-worker/telegram_storage.session backup-location/
```

---

## Conclusion

TGSDK provides a robust, scalable solution for unlimited cloud storage using Telegram. By following this guide, you'll have a fully functional storage system with excellent performance, security, and maintainability.

For additional support and updates:
- **GitHub:** https://github.com/shamimlaravel/tgsdk
- **Issues:** Report bugs and feature requests
- **Discussions:** Community support and ideas

Happy coding! 🚀
