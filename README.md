<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# Laravel FTP Deploy System

A Laravel 11 application that provides intelligent FTP synchronization with file hashing, exclusion patterns, and retry logic.

**🎯 Can be used as a standalone Laravel command OR as a GitHub Action in your CI/CD pipelines!**

## Features

- **File Hashing**: Uses MD5 hashes to detect file changes and avoid unnecessary uploads
- **Exclusion Patterns**: Support for complex exclusion patterns including wildcards and recursive exclusions
- **Retry Logic**: Automatic retry with exponential backoff for failed FTP operations
- **Verification**: Post-sync verification to ensure synchronization was successful
- **Progress Tracking**: Real-time progress bars and detailed logging
- **Dry Run**: Preview changes before applying them
- **GitHub Action**: Ready-to-use as a GitHub Action for CI/CD pipelines

## 🚀 GitHub Action Usage

### Quick Start

Add this to your GitHub workflow (`.github/workflows/deploy.yml`):

```yaml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
      - name: 📥 Checkout code
        uses: actions/checkout@v4
        
      - name: 🗂️ Sync files
        uses: username/laravel-ftp-deploy@main  # Replace with your repo
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          timeout: 120
          max-retries: 4
          remote-tree-url: ${{ secrets.DEPLOYER_URL }}
          exclude: |
            .env
            storage/app
            storage/app/**
            storage/logs
            storage/logs/**
            storage/keys
            storage/keys/**
            storage/framework
            storage/framework/**
            storage/backup
            storage/backup/**
            public/storage
            public/storage/**
            database/database.sqlite
            resources
            resources/**
```

### Action Inputs

| Input | Description | Required | Default |
|-------|-------------|----------|---------|
| `server` | FTP server hostname or IP address | ✅ Yes | - |
| `username` | FTP username | ✅ Yes | - |
| `password` | FTP password | ✅ Yes | - |
| `remote-tree-url` | URL to the remote tree generation script | ✅ Yes | - |
| `timeout` | FTP timeout in seconds | ❌ No | `60` |
| `max-retries` | Maximum retry attempts for FTP operations | ❌ No | `4` |
| `local-dir` | Local directory path to sync | ❌ No | `.` (GitHub workspace) |
| `exclude` | Paths to exclude from sync (one per line) | ❌ No | - |
| `dry-run` | Show what would be done without executing | ❌ No | `false` |

### GitHub Secrets Setup

Create these secrets in your repository settings:

```
FTP_SERVER=your-ftp-server.com
FTP_USERNAME=your-ftp-username  
FTP_PASSWORD=your-ftp-password
DEPLOYER_URL=https://your-domain.com/ftp-remote-tree.php
```

### Advanced Workflow Example

```yaml
name: Deploy Application

on:
  push:
    branches: [ main, staging ]
  
  workflow_dispatch:
    inputs:
      dry_run:
        description: 'Run in dry-run mode'
        required: false
        default: 'false'
        type: boolean

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
      - name: 📥 Checkout code
        uses: actions/checkout@v4
        
      - name: 🔧 Setup Node.js (if needed)
        uses: actions/setup-node@v4
        with:
          node-version: '18'
          cache: 'npm'
          
      - name: 📦 Install dependencies
        run: npm ci
        
      - name: 🏗️ Build assets
        run: npm run build
        
      - name: 🗂️ Deploy to Staging
        if: github.ref == 'refs/heads/staging'
        uses: username/laravel-ftp-deploy@main
        with:
          server: ${{ secrets.STAGING_FTP_SERVER }}
          username: ${{ secrets.STAGING_FTP_USERNAME }}
          password: ${{ secrets.STAGING_FTP_PASSWORD }}
          remote-tree-url: ${{ secrets.STAGING_DEPLOYER_URL }}
          dry-run: ${{ github.event.inputs.dry_run || 'false' }}
          exclude: |
            .env
            .git/**
            node_modules/**
            storage/logs/**
            storage/framework/cache/**
            storage/framework/sessions/**
            storage/framework/views/**
            
      - name: 🗂️ Deploy to Production
        if: github.ref == 'refs/heads/main'
        uses: username/laravel-ftp-deploy@main
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          remote-tree-url: ${{ secrets.DEPLOYER_URL }}
          timeout: 300
          max-retries: 6
          dry-run: ${{ github.event.inputs.dry_run || 'false' }}
          exclude: |
            .env
            .git/**
            node_modules/**
            storage/app/**
            storage/logs/**
            storage/keys/**
            storage/framework/**
            storage/backup/**
            public/storage/**
            database/database.sqlite
            resources/**
            *.log
```

### How the GitHub Action Works

1. **🔧 Sets up PHP 8.2** with required extensions (FTP, cURL, JSON)
2. **📦 Installs dependencies** using Composer in the action directory
3. **🚀 Executes the deploy command** with your specified parameters
4. **📁 Uses GitHub workspace** as the local directory by default
5. **🚫 Processes exclusion patterns** from the multiline `exclude` input
6. **✅ Provides detailed logging** with emojis and colors in the GitHub Action logs

## 📋 Local Development Usage

### Requirements

- PHP 8.2+
- Laravel 11
- FTP extension enabled
- HTTP client access to remote server

### Installation

1. Clone or extract the project
2. Install dependencies:
   ```bash
   composer install
   ```
3. Copy environment file:
   ```bash
   cp .env.example .env
   ```
4. Generate application key:
   ```bash
   php artisan key:generate
   ```

### Basic Command

```bash
php artisan deploy \
  --server=your-ftp-server.com \
  --username=your-ftp-username \
  --password=your-ftp-password \
  --local-dir="/path/to/local/directory" \
  --remote-tree-url="https://your-domain.com/ftp-remote-tree.php"
```

### Command Options

- `--server`: FTP server hostname or IP address
- `--username`: FTP username
- `--password`: FTP password
- `--local-dir`: Local directory path to sync
- `--remote-tree-url`: URL to the remote tree generation script
- `--exclude-paths`: Paths to exclude from sync (supports wildcards)
- `--timeout`: FTP timeout in seconds (default: 60)
- `--max-retries`: Maximum retry attempts for FTP operations (default: 4)
- `--dry-run`: Show what would be done without actually doing it

### Example with Exclusions

```bash
php artisan deploy \
  --server=162.241.63.50 \
  --username=ftp-sync@imedicineapp.com \
  --password=177469466862Ab! \
  --local-dir="/Users/brunofreire/Desktop/test" \
  --remote-tree-url="https://imedicineapp.com/ftp-remote-tree.php" \
  --exclude-paths=".env" \
  --exclude-paths="storage/logs" \
  --exclude-paths="storage/keys" \
  --exclude-paths="storage/app/public" \
  --exclude-paths="public/storage" \
  --exclude-paths="public/storage/**" \
  --exclude-paths="database/database.sqlite" \
  --exclude-paths="resources" \
  --exclude-paths="resources/**"
```

## Remote Setup

1. Upload `ftp-remote-tree.php` to the root of your FTP directory
2. Ensure the script is accessible via HTTP at the URL you specify in `--remote-tree-url`
3. Make sure the remote server has PHP with standard file functions enabled

## Exclusion Patterns

The system supports various exclusion patterns:

- **Exact match**: `file.txt`, `.env`
- **Directory wildcard**: `storage/*` (excludes direct children only)
- **Recursive wildcard**: `storage/**` (excludes directory and all subdirectories)

### Example Exclusions

```bash
# Exact file
--exclude-paths=".env"

# Directory contents (not recursive)
--exclude-paths="storage/logs/*"

# Directory and all contents (recursive)
--exclude-paths="storage/**"

# Multiple patterns
--exclude-paths=".env" --exclude-paths="storage/**" --exclude-paths="*.log"
```

## How It Works

1. **Local Tree Generation**: Scans local directory and generates JSON with file hashes
2. **Remote Tree Generation**: Calls remote script to generate remote file tree
3. **Comparison**: Compares local and remote trees to identify differences
4. **Sync Actions**: Generates ordered list of actions (create, upload, update, delete)
5. **Execution**: Applies changes with retry logic and progress tracking
6. **Verification**: Re-compares trees to verify successful synchronization

## Files Generated

During deployment, the following files are created in `storage/app/`:

- `deploy-local-tree.json`: Local file tree with hashes
- `deploy-diff.json`: Differences between local and remote
- `deploy-results.json`: Results of sync operations
- `deploy-verification.json`: Post-sync verification results (if differences found)

## Error Handling

- **Connection Failures**: Automatic reconnection and retry
- **Timeout Issues**: Configurable timeout settings
- **Partial Failures**: Continue processing remaining files
- **Verification**: Post-sync verification to detect issues

## Security Notes

- Store FTP credentials securely (use GitHub Secrets for GitHub Actions)
- Use HTTPS for remote tree URL when possible
- The `ftp-remote-tree.php` script is automatically excluded from sync
- Consider using environment variables for sensitive data

## Troubleshooting

### Common Issues

1. **FTP Connection Failed**
   - Check server, username, and password
   - Verify FTP server is accessible
   - Try increasing timeout value

2. **Remote Tree Generation Failed**
   - Ensure `ftp-remote-tree.php` is uploaded and accessible
   - Check PHP is enabled on remote server
   - Verify URL is correct and accessible

3. **Permission Errors**
   - Check FTP user has write permissions
   - Verify local directory is readable

4. **GitHub Action Fails**
   - Check that all required secrets are set
   - Verify the remote tree URL is accessible from GitHub Actions
   - Check the GitHub Actions logs for detailed error messages

### Debug Information

Use `--dry-run` to preview changes without executing them:

```bash
php artisan deploy --dry-run [other options]
```

For GitHub Actions, set `dry-run: true` in your workflow.

Check generated JSON files in `storage/app/` for detailed information about the sync process.
