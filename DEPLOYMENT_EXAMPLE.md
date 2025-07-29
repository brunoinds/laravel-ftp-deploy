# Deployment Example

This guide shows you exactly how to use the Laravel FTP Deploy System with practical examples.

## Step 1: Prepare Your Project

First, make sure you have a local directory you want to sync. For this example, let's use the `example-project` directory that's included:

```bash
ls example-project/
# Should show: assets/  index.html  style.css
```

## Step 2: Upload the Remote Script

1. Upload `ftp-remote-tree.php` to the **root** of your FTP directory
2. Test it by visiting the URL in your browser:
   ```
   https://your-domain.com/ftp-remote-tree.php
   ```
   You should see a JSON response with the current remote file structure.

## Step 3: Run Your First Deployment

### Dry Run (Recommended First)

```bash
php artisan deploy \
  --server=your-ftp-server.com \
  --username=your-ftp-username \
  --password=your-ftp-password \
  --local-dir="./example-project" \
  --remote-tree-url="https://your-domain.com/ftp-remote-tree.php" \
  --dry-run
```

This will show you exactly what changes would be made without actually doing them.

### Real Deployment

```bash
php artisan deploy \
  --server=your-ftp-server.com \
  --username=your-ftp-username \
  --password=your-ftp-password \
  --local-dir="./example-project" \
  --remote-tree-url="https://your-domain.com/ftp-remote-tree.php"
```

## Step 4: Using Exclusions

For a more realistic scenario with exclusions:

```bash
php artisan deploy \
  --server=your-ftp-server.com \
  --username=your-ftp-username \
  --password=your-ftp-password \
  --local-dir="/path/to/your/laravel/project" \
  --remote-tree-url="https://your-domain.com/ftp-remote-tree.php" \
  --exclude-paths=".env" \
  --exclude-paths=".git/**" \
  --exclude-paths="storage/logs/**" \
  --exclude-paths="storage/framework/cache/**" \
  --exclude-paths="storage/framework/sessions/**" \
  --exclude-paths="storage/framework/views/**" \
  --exclude-paths="node_modules/**" \
  --exclude-paths="vendor/**" \
  --exclude-paths="*.log"
```

## What You'll See

### During Execution

```
🚀 Starting FTP deployment process...

📁 Step 1: Generating local file tree...
   Found 156 files/directories in local tree
   Local tree saved to: /path/to/storage/app/deploy-local-tree.json

🔌 Step 2: Connecting to FTP server...
   Connected to your-server.com successfully

📤 Uploading remote tree generation script...
   Remote tree script uploaded

🌐 Step 3: Generating remote file tree...
   Found 23 files/directories in remote tree

🔍 Step 4: Comparing local and remote trees...
   Changes to be applied:
   📁 create_directory: 5
   📤 upload_file: 128
   🔄 update_file: 3
   🗑️ remove_file: 2
   📂 remove_directory: 1
   Diff saved to: /path/to/storage/app/deploy-diff.json

⚡ Step 5: Applying changes...
 139/139 [████████████████████████████] 100% Processing upload_file: assets/script.js
   Operations completed: 139/139

✅ Step 6: Verifying synchronization...
   ✅ Verification successful - local and remote are now in sync!

🎉 Deployment process completed!
```

### Generated Files

After deployment, check these files for detailed information:

```bash
ls storage/app/deploy-*
# deploy-diff.json        - What changes were made
# deploy-local-tree.json  - Local file structure
# deploy-results.json     - Results of each operation
```

## Common Patterns

### Laravel Project Deployment

```bash
php artisan deploy \
  --server=your-server.com \
  --username=your-username \
  --password=your-password \
  --local-dir="." \
  --remote-tree-url="https://your-domain.com/ftp-remote-tree.php" \
  --exclude-paths=".env" \
  --exclude-paths=".git/**" \
  --exclude-paths="storage/logs/**" \
  --exclude-paths="storage/framework/cache/**" \
  --exclude-paths="storage/framework/sessions/**" \
  --exclude-paths="storage/framework/views/**" \
  --exclude-paths="node_modules/**" \
  --exclude-paths="vendor/**" \
  --exclude-paths="database/database.sqlite"
```

### Static Website Deployment

```bash
php artisan deploy \
  --server=your-server.com \
  --username=your-username \
  --password=your-password \
  --local-dir="./dist" \
  --remote-tree-url="https://your-domain.com/ftp-remote-tree.php" \
  --exclude-paths="*.log" \
  --exclude-paths="*.tmp"
```

### WordPress Deployment

```bash
php artisan deploy \
  --server=your-server.com \
  --username=your-username \
  --password=your-password \
  --local-dir="./wordpress" \
  --remote-tree-url="https://your-domain.com/ftp-remote-tree.php" \
  --exclude-paths="wp-config.php" \
  --exclude-paths="wp-content/uploads/**" \
  --exclude-paths="wp-content/cache/**" \
  --exclude-paths="wp-content/backup/**"
```

## Troubleshooting

### Issue: "Remote tree generation error"

**Solution**: Check that `ftp-remote-tree.php` is uploaded and the URL is accessible:

```bash
curl https://your-domain.com/ftp-remote-tree.php
```

### Issue: "FTP connection failed"

**Solutions**:
- Verify server, username, password
- Try increasing timeout: `--timeout=120`
- Check if your server uses a non-standard port

### Issue: "Permission denied"

**Solutions**:
- Verify FTP user has write permissions
- Check directory permissions on remote server
- Ensure you have access to create/delete files

### Issue: Some files failed to upload

**Solutions**:
- Check the `deploy-results.json` file for specific errors
- Increase retry count: `--max-retries=6`
- Check file sizes and server limitations

## Pro Tips

1. **Always use `--dry-run` first** to see what changes will be made
2. **Save your command** in a shell script for repeated deployments
3. **Check exclusions carefully** to avoid uploading unnecessary files
4. **Monitor the generated JSON files** for debugging information
5. **Use version control** to track your exclusion patterns

## Security Best Practices

1. Store credentials in environment variables
2. Use HTTPS for the remote tree URL
3. Limit FTP user permissions to necessary directories only
4. Regularly rotate FTP passwords
5. Consider using SFTP instead of FTP when possible 
