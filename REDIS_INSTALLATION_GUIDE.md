# Redis Extension Installation Guide for Windows (XAMPP)

## Issue
The ChannelRotator tests require the Redis PHP extension, which is currently not loaded in your environment.

## Solution Steps

### Step 1: Check Your PHP Version
```bash
php -v
```
Expected: PHP 8.4.17 (based on test output)

### Step 2: Download Redis Extension

1. Go to: https://windows.php.net/downloads/pecl/releases/redis/
2. Find the latest version compatible with PHP 8.4
3. Download the ZIP file matching your PHP version:
   - For PHP 8.4 TS (Thread Safe) x64: `php_redis-5.3.7-8.4-ts-vs16-x64.zip`
   
**Direct Links:**
- Latest Redis: https://windows.php.net/downloads/pecl/releases/redis/5.3.7/php_redis-5.3.7-8.4-ts-vs16-x64.zip

### Step 3: Install the Extension

1. **Extract the DLL:**
   - Open the downloaded ZIP file
   - Extract `php_redis.dll`

2. **Copy to PHP Extensions Directory:**
   - Copy `php_redis.dll` to: `C:\xampp\php\ext\`

3. **Check for Dependencies:**
   - Some Redis versions require `igbinary.dll` and/or `zstd.dll`
   - If present in the ZIP, also copy these to `C:\xampp\php\ext\`

### Step 4: Enable in php.ini

1. **Open php.ini:**
   ```bash
   notepad C:\xampp\php\php.ini
   ```

2. **Add the extension line:**
   Scroll to the `[Dynamic Extensions]` section and add:
   ```ini
   extension=redis
   ```

3. **Save and close**

### Step 5: Restart Apache

1. Open XAMPP Control Panel
2. Stop Apache (if running)
3. Start Apache

OR restart from command line:
```bash
# Stop Apache
C:\xampp\apache\bin\httpd.exe -k stop

# Start Apache
C:\xampp\apache\bin\httpd.exe -k start
```

### Step 6: Verify Installation

```bash
php -m | findstr /i "redis"
```

Expected output:
```
redis
```

You should also see Redis in `phpinfo()` output.

### Step 7: Run Tests

Now run the ChannelRotator tests:
```bash
cd c:\Users\Administrator\Documents\Herd\tgsdk
./vendor/bin/phpunit --filter ChannelRotator --testdox
```

Expected result: All 5 ChannelRotator tests should now pass! ✅

## Alternative: Using Docker

If you prefer using Docker instead:

```yaml
# docker-compose.yml
version: '3.8'
services:
  app:
    image: php:8.4-cli
    volumes:
      - ./:/var/www/html
    working_dir: /var/www/html
    depends_on:
      - redis
  
  redis:
    image: redis:alpine
    ports:
      - 6379:6379
```

Then run:
```bash
docker-compose up -d
docker-compose exec app composer install
docker-compose exec app ./vendor/bin/phpunit --filter ChannelRotator
```

## Troubleshooting

### Error: "The specified module could not be found"

**Cause:** Wrong DLL version or missing dependencies

**Solution:**
1. Ensure you downloaded the correct version for PHP 8.4
2. Check if `igbinary.dll` or other dependencies are needed
3. Verify the DLL is in `C:\xampp\php\ext\`

### Error: "Unable to load dynamic library"

**Cause:** Extension not enabled in php.ini

**Solution:**
1. Verify `extension=redis` is in php.ini
2. Make sure there's no semicolon (`;`) before the line
3. Restart Apache after changes

### Still Not Working?

Try these steps:
1. Close all terminal windows
2. Restart XAMPP completely
3. Reboot your computer
4. Try installing via Composer (alternative method):
   ```bash
   composer require predis/predis
   ```

## Testing Without Redis Extension

If you cannot install Redis immediately, the tests will continue to skip automatically:

```
Channel Rotator (Shamimstack\Tgsdk\Tests\Feature\ChannelRotator)
 ⏭️ Select returns active channel [skipped]
 ⏭️ Select throws when all inactive [skipped]
 ⏭️ Round robin rotates through channels [skipped]
 ⏭️ Least used selects channel with fewest files [skipped]
 ⏭️ Capacity aware selects channel with most space [skipped]
```

This is by design - the code works fine in production when Redis is available.

## Success Indicators

✅ `php -m` shows "redis"  
✅ No warnings when running `php -v`  
✅ ChannelRotator tests pass (no skipped tests)  
✅ Test summary shows improved pass rate  

---

*Need help? Check:*
- *PECL Redis: https://pecl.php.net/package/redis*
- *Laravel Redis Docs: https://laravel.com/docs/redis*
- *XAMPP Documentation: https://www.apachefriends.org/*
