# GitHub Action Setup Guide

This repository is now ready to be used as a GitHub Action! Here's everything you need to know to get started.

## 📁 Files Created

The following files have been added to transform this into a GitHub Action:

```
├── action.yml                     # GitHub Action definition
├── entrypoint.sh                  # Action execution script (executable)
├── .github/workflows/example.yml  # Example workflow file
└── GITHUB_ACTION_SETUP.md         # This setup guide
```

## 🚀 Quick Setup

### 1. Publish Your Action

1. **Fork or copy this repository** to your GitHub account
2. **Make it public** (required for GitHub Actions to work across repositories)
3. **Create a release** (recommended) or use `@main` for the latest version

### 2. Upload Remote Script

Upload `ftp-remote-tree.php` to the root of your FTP server and make sure it's accessible via HTTP.

Test it: `https://your-domain.com/ftp-remote-tree.php`

### 3. Set Up Secrets

In your target repository (the one you want to deploy), add these secrets:

```
Repository Settings > Secrets and variables > Actions > New repository secret
```

**Required secrets:**
- `FTP_SERVER` - Your FTP server hostname/IP
- `FTP_USERNAME` - Your FTP username  
- `FTP_PASSWORD` - Your FTP password
- `DEPLOYER_URL` - Full URL to your ftp-remote-tree.php script

### 4. Create Workflow

Create `.github/workflows/deploy.yml` in your target repository:

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
        uses: YOUR-USERNAME/laravel-ftp-deploy@main  # Replace with your repo
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          remote-tree-url: ${{ secrets.DEPLOYER_URL }}
          exclude: |
            .env
            storage/logs/**
            storage/framework/cache/**
            node_modules/**
```

## 🎯 Usage Examples

### Basic Laravel Deployment

```yaml
- name: 🗂️ Deploy Laravel App
  uses: your-username/laravel-ftp-deploy@v1.0.0
  with:
    server: ${{ secrets.FTP_SERVER }}
    username: ${{ secrets.FTP_USERNAME }}
    password: ${{ secrets.FTP_PASSWORD }}
    remote-tree-url: ${{ secrets.DEPLOYER_URL }}
    timeout: 300
    max-retries: 4
    exclude: |
      .env
      .git/**
      storage/logs/**
      storage/framework/cache/**
      storage/framework/sessions/**
      storage/framework/views/**
      public/storage/**
      node_modules/**
```

### With Build Process

```yaml
steps:
  - uses: actions/checkout@v4
  
  - name: Setup Node.js
    uses: actions/setup-node@v4
    with:
      node-version: '18'
      cache: 'npm'
      
  - name: Install & Build
    run: |
      npm ci
      npm run build
      
  - name: Deploy
    uses: your-username/laravel-ftp-deploy@main
    with:
      server: ${{ secrets.FTP_SERVER }}
      username: ${{ secrets.FTP_USERNAME }}
      password: ${{ secrets.FTP_PASSWORD }}
      remote-tree-url: ${{ secrets.DEPLOYER_URL }}
      exclude: |
        .env
        node_modules/**
        storage/logs/**
```

### Dry Run Mode

```yaml
- name: Preview Changes
  uses: your-username/laravel-ftp-deploy@main
  with:
    server: ${{ secrets.FTP_SERVER }}
    username: ${{ secrets.FTP_USERNAME }}
    password: ${{ secrets.FTP_PASSWORD }}
    remote-tree-url: ${{ secrets.DEPLOYER_URL }}
    dry-run: true  # Only preview, don't execute
```

## 🔧 Available Inputs

| Input | Required | Default | Description |
|-------|----------|---------|-------------|
| `server` | ✅ | - | FTP server hostname/IP |
| `username` | ✅ | - | FTP username |
| `password` | ✅ | - | FTP password |
| `remote-tree-url` | ✅ | - | URL to ftp-remote-tree.php |
| `timeout` | ❌ | `60` | FTP timeout in seconds |
| `max-retries` | ❌ | `4` | Max retry attempts |
| `local-dir` | ❌ | `.` | Local directory (GitHub workspace) |
| `exclude` | ❌ | - | Exclusion patterns (multiline) |
| `dry-run` | ❌ | `false` | Preview mode |

## 🎨 Output Example

When you run the action, you'll see beautiful, colorful output in your GitHub Actions logs:

```
🚀 ═══════════════════════════════════════════════════════════════
⚡    LARAVEL FTP DEPLOY - GITHUB ACTION    ⚡
═══════════════════════════════════════════════════════════════

📁 Using GitHub workspace as local directory: /github/workspace
🚫 Processing exclusion patterns...
   📝 Excluding: .env
   📝 Excluding: storage/logs/**

⚡ Executing deployment command...

📋 ═══════════════ WORK PLAN ═══════════════

📊 SUMMARY:
+--------+-------+---------+
| Action | Files | Folders |
+--------+-------+---------+
| 📤 Upload | 25    | 3       |
| 🔄 Update | 2     | 0       |
| ❌ Delete | 1     | 0       |
+--------+-------+---------+

[1/28] 📁 Creating directory: assets
   ✅ Success

[2/28] 📄 Uploading file: index.php
   ✅ Success

✅ ═══════════════════════════════════════════════════════════════
🎉    DEPLOYMENT COMPLETED SUCCESSFULLY    🎉
═══════════════════════════════════════════════════════════════
```

## 🔒 Security Best Practices

1. **Never commit credentials** - Always use GitHub Secrets
2. **Use HTTPS** for the remote tree URL when possible
3. **Limit FTP permissions** to only what's needed
4. **Pin action versions** - Use `@v1.0.0` instead of `@main` in production

## 🐛 Troubleshooting

### Action Not Found
```
Error: username/laravel-ftp-deploy/.github/actions/main/action.yml not found
```
**Solution:** Make sure your repository is public and the path is correct.

### Permission Denied
```
Error: FTP operation failed after 4 attempts
```
**Solution:** Check FTP credentials and server permissions.

### Remote Tree Error
```
Error: Failed to fetch remote tree
```
**Solution:** Verify `ftp-remote-tree.php` is uploaded and accessible via HTTP.

## 📚 More Examples

Check the example workflow file at `.github/workflows/example.yml` for more comprehensive examples including staging/production deployments and manual triggers.

## 🆘 Support

- Check the main [README.md](README.md) for detailed documentation
- Review GitHub Actions logs for detailed error messages
- Ensure all secrets are properly set
- Verify remote tree script is accessible 
