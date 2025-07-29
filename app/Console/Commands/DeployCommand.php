<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FileTreeService;
use App\Services\FtpSyncService;
use Exception;

class DeployCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy
                            {--server= : FTP server hostname or IP}
                            {--username= : FTP username}
                            {--password= : FTP password}
                            {--local-dir= : Local directory path to sync}
                            {--remote-tree-url= : URL to the remote tree generation script}
                            {--exclude-paths=* : Paths to exclude from sync (supports wildcards)}
                            {--timeout=60 : FTP timeout in seconds}
                            {--max-retries=4 : Maximum retry attempts for FTP operations}
                            {--dry-run : Show what would be done without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy local directory to remote FTP server with file synchronization';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Validate required options
            $this->validateOptions();

            // Get options
            $server = $this->option('server');
            $username = $this->option('username');
            $password = $this->option('password');
            $localDir = $this->option('local-dir');
            $remoteTreeUrl = $this->option('remote-tree-url');
            $excludePaths = $this->option('exclude-paths') ?? [];
            $timeout = (int) $this->option('timeout');
            $maxRetries = (int) $this->option('max-retries');
            $isDryRun = $this->option('dry-run');

            // Initialize services
            $fileTreeService = new FileTreeService();
            $ftpService = new FtpSyncService($server, $username, $password, $timeout, $maxRetries);

            $this->displayHeader($isDryRun);

            // Step 1: Generate local file tree
            $this->info("📁 <fg=cyan>Step 1:</> Generating local file tree...");
            $localTree = $this->generateLocalTree($fileTreeService, $localDir, $excludePaths);
            $this->line("   <fg=green>✅ Found " . count($localTree['files']) . " files/directories in local tree</>");

            // Step 2: Connect to FTP
            $this->info("🔌 <fg=cyan>Step 2:</> Connecting to FTP server...");
            $ftpService->connect();
            $this->line("   <fg=green>✅ Connected to {$server} successfully</>");

            // Step 3: Generate remote file tree
            $this->info("🌐 <fg=cyan>Step 3:</> Fetching remote file tree...");
            $this->line("   <fg=yellow>📡 Calling: {$remoteTreeUrl}</>");
            $remoteTree = $ftpService->downloadRemoteTree($remoteTreeUrl, $excludePaths);
            $this->line("   <fg=green>✅ Found " . count($remoteTree['files']) . " files/directories in remote tree</>");

            // Step 4: Compare trees and generate diff
            $this->info("🔍 <fg=cyan>Step 4:</> Comparing local and remote trees...");
            $diff = $fileTreeService->compareTrees($localTree, $remoteTree);

            if (empty($diff['actions'])) {
                $this->line("   <fg=green>✅ No changes needed - local and remote are already in sync!</>");
                $ftpService->disconnect();
                return 0;
            }

            // Display detailed work plan
            $this->displayWorkPlan($diff, $localTree['files'], $remoteTree['files']);

            // Step 5: Apply changes
            if ($isDryRun) {
                $this->displayDryRunSummary($diff);
            } else {
                $this->info("⚡ <fg=cyan>Step 5:</> Applying changes...");
                $results = $this->applyChangesWithDetailedLogging($ftpService, $diff['actions'], $localDir, $maxRetries);
                $this->displayDetailedResults($results);

                // Step 6: Verify changes
                if ($results['failed'] === 0) {
                    $this->info("✅ <fg=cyan>Step 6:</> Verifying synchronization...");
                    $this->verifySynchronization($fileTreeService, $ftpService, $localTree, $remoteTreeUrl, $excludePaths);
                } else {
                    $this->warn("⚠️  <fg=yellow>Some operations failed. Skipping verification.</>");
                }
            }

            $ftpService->disconnect();
            $this->displayFooter($isDryRun);

            return 0;

        } catch (Exception $e) {
            $this->error("❌ <fg=red>Deployment failed:</> " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display colorful header
     */
    private function displayHeader(bool $isDryRun): void
    {
        $this->newLine();
        $this->line("<fg=blue>🚀 ═══════════════════════════════════════════════════════════════</>");
        if ($isDryRun) {
            $this->line("<fg=blue>🔬    LARAVEL FTP DEPLOY SYSTEM - DRY RUN MODE    🔬</>");
        } else {
            $this->line("<fg=blue>⚡    LARAVEL FTP DEPLOY SYSTEM - LIVE DEPLOYMENT    ⚡</>");
        }
        $this->line("<fg=blue>═══════════════════════════════════════════════════════════════</>");
        $this->newLine();
    }

    /**
     * Display colorful footer
     */
    private function displayFooter(bool $isDryRun): void
    {
        $this->newLine();
        if ($isDryRun) {
            $this->line("<fg=green>🎉 ═══════════════════════════════════════════════════════════════</>");
            $this->line("<fg=green>🔬    DRY RUN COMPLETED SUCCESSFULLY    🔬</>");
            $this->line("<fg=green>═══════════════════════════════════════════════════════════════</>");
        } else {
            $this->line("<fg=green>🎉 ═══════════════════════════════════════════════════════════════</>");
            $this->line("<fg=green>✨    DEPLOYMENT COMPLETED SUCCESSFULLY    ✨</>");
            $this->line("<fg=green>═══════════════════════════════════════════════════════════════</>");
        }
        $this->newLine();
    }

    /**
     * Validate required command options
     */
    private function validateOptions(): void
    {
        $required = ['server', 'username', 'password', 'local-dir', 'remote-tree-url'];

        foreach ($required as $option) {
            if (!$this->option($option)) {
                throw new Exception("Required option --{$option} is missing");
            }
        }

        $localDir = $this->option('local-dir');
        if (!is_dir($localDir)) {
            throw new Exception("Local directory does not exist: {$localDir}");
        }

        $remoteTreeUrl = $this->option('remote-tree-url');
        if (!filter_var($remoteTreeUrl, FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid remote tree URL: {$remoteTreeUrl}");
        }
    }

    /**
     * Generate local file tree
     */
    private function generateLocalTree(FileTreeService $service, string $localDir, array $excludePaths): array
    {
        $tree = $service->generateCompleteTree($localDir, $excludePaths);

        // Save local tree for debugging/reference
        $treeFile = storage_path('app/deploy-local-tree.json');
        $service->saveTreeToFile($tree, $treeFile);
        $this->line("   <fg=gray>💾 Local tree saved to: {$treeFile}</>");

        return $tree;
    }

    /**
     * Display detailed work plan with statistics and file-by-file breakdown
     */
    private function displayWorkPlan(array $diff, array $localFiles, array $remoteFiles): void
    {
        $this->newLine();
        $this->line("<fg=cyan>📋 ═══════════════ WORK PLAN ═══════════════</>");
        $this->newLine();

        // Calculate statistics
        $stats = $this->calculateStats($diff['actions'], $localFiles, $remoteFiles);

        // Display summary table
        $this->line("<fg=white>📊 <fg=cyan>SUMMARY:</>");
        $this->table(
            ['<fg=cyan>Action</>', '<fg=cyan>Files</>', '<fg=cyan>Folders</>'],
            [
                ['<fg=green>📤 Upload</>', $stats['upload_files'], $stats['upload_dirs']],
                ['<fg=red>❌ Delete</>', $stats['delete_files'], $stats['delete_dirs']],
                ['<fg=blue>🔄 Update</>', $stats['update_files'], '0'],
                ['<fg=gray>😇 No Change</>', $stats['unchanged_files'], $stats['unchanged_dirs']],
            ]
        );

        $this->newLine();

        // Display detailed action list
        $this->line("<fg=white>📝 <fg=cyan>DETAILED PLAN:</>");
        foreach ($diff['actions'] as $action) {
            $this->displayActionDetail($action);
        }

        // Display unchanged files summary
        if ($stats['unchanged_files'] > 0 || $stats['unchanged_dirs'] > 0) {
            $this->newLine();
            $this->line("<fg=gray>😇 {$stats['unchanged_files']} files and {$stats['unchanged_dirs']} directories will remain unchanged</>");
        }

        // Save diff for debugging/reference
        $diffFile = storage_path('app/deploy-diff.json');
        file_put_contents($diffFile, json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line("<fg=gray>💾 Diff saved to: {$diffFile}</>");

        $this->newLine();
    }

    /**
     * Calculate statistics from actions
     */
    private function calculateStats(array $actions, array $localFiles, array $remoteFiles): array
    {
        $stats = [
            'upload_files' => 0,
            'upload_dirs' => 0,
            'update_files' => 0,
            'delete_files' => 0,
            'delete_dirs' => 0,
            'unchanged_files' => 0,
            'unchanged_dirs' => 0,
        ];

        // Count actions
        foreach ($actions as $action) {
            switch ($action['action']) {
                case 'upload_file':
                    $stats['upload_files']++;
                    break;
                case 'create_directory':
                    $stats['upload_dirs']++;
                    break;
                case 'update_file':
                    $stats['update_files']++;
                    break;
                case 'remove_file':
                    $stats['delete_files']++;
                    break;
                case 'remove_directory':
                    $stats['delete_dirs']++;
                    break;
            }
        }

        // Calculate unchanged items
        $actionPaths = array_column($actions, 'path');
        foreach ($localFiles as $file) {
            if (!in_array($file['path'], $actionPaths)) {
                if ($file['type'] === 'file') {
                    $stats['unchanged_files']++;
                } else {
                    $stats['unchanged_dirs']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Display individual action detail
     */
    private function displayActionDetail(array $action): void
    {
        $icon = match($action['action']) {
            'create_directory' => '<fg=green>📁📤</>',
            'upload_file' => '<fg=green>📄📤</>',
            'update_file' => '<fg=yellow>📄🔄</>',
            'remove_file' => '<fg=red>📄❌</>',
            'remove_directory' => '<fg=red>📁❌</>',
            default => '<fg=gray>❓</>'
        };

        $actionText = match($action['action']) {
            'create_directory' => '<fg=green>Create directory</>',
            'upload_file' => '<fg=green>Upload file</>',
            'update_file' => '<fg=yellow>Update file</>',
            'remove_file' => '<fg=red>Delete file</>',
            'remove_directory' => '<fg=red>Delete directory</>',
            default => 'Unknown action'
        };

        $this->line("   {$icon} <fg=white>{$action['path']}</> → {$actionText}");
    }

    /**
     * Display dry run summary
     */
    private function displayDryRunSummary(array $diff): void
    {
        $this->newLine();
        $this->warn("🔬 <fg=yellow>DRY RUN MODE:</> Would apply " . count($diff['actions']) . " changes");
        $this->line("<fg=yellow>   Run without --dry-run to execute these changes</>");
        $this->newLine();
    }

    /**
     * Apply FTP changes with detailed logging
     */
    private function applyChangesWithDetailedLogging(FtpSyncService $ftpService, array $actions, string $localDir, int $maxRetries): array
    {
        $this->newLine();
        $results = ['completed' => 0, 'failed' => 0, 'total' => count($actions), 'results' => []];

        foreach ($actions as $index => $action) {
            $currentStep = $index + 1;
            $totalSteps = count($actions);

            $this->line("<fg=gray>[{$currentStep}/{$totalSteps}]</> " . $this->getActionStartMessage($action));

            $success = false;
            $lastError = null;

            // Try the operation with retry logic
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $this->executeAction($ftpService, $action, $localDir, $attempt, $maxRetries);
                    $success = true;
                    break;
                } catch (Exception $e) {
                    $lastError = $e->getMessage();

                    if ($attempt < $maxRetries) {
                        $this->line("   <fg=yellow>🔄 Retrying ({$attempt}/{$maxRetries})...</>");
                        sleep(min(pow(2, $attempt - 1), 5)); // Exponential backoff
                    }
                }
            }

            if ($success) {
                $this->line("   <fg=green>✅ Success</>");
                $results['completed']++;
                $results['results'][] = [
                    'action' => $action,
                    'status' => 'success',
                    'message' => "Successfully executed {$action['action']} for {$action['path']}"
                ];
            } else {
                $this->line("   <fg=red>⚠️  Error on {$action['action']}: {$action['path']}</>");
                $this->line("   <fg=red>   Details: {$lastError}</>");
                $results['failed']++;
                $results['results'][] = [
                    'action' => $action,
                    'status' => 'failed',
                    'message' => $lastError
                ];
            }
        }

        return $results;
    }

    /**
     * Get action start message with appropriate emoji and color
     */
    private function getActionStartMessage(array $action): string
    {
        return match($action['action']) {
            'create_directory' => "<fg=green>📁 Creating directory:</> <fg=white>{$action['path']}</>",
            'upload_file' => "<fg=green>📄 Uploading file:</> <fg=white>{$action['path']}</>",
            'update_file' => "<fg=yellow>📄 Updating file:</> <fg=white>{$action['path']}</>",
            'remove_file' => "<fg=red>📄 Deleting file:</> <fg=white>{$action['path']}</>",
            'remove_directory' => "<fg=red>📁 Deleting directory:</> <fg=white>{$action['path']}</>",
            default => "<fg=gray>❓ Unknown action:</> <fg=white>{$action['path']}</>"
        };
    }

    /**
     * Execute a single action
     */
    private function executeAction(FtpSyncService $ftpService, array $action, string $localDir, int $attempt, int $maxRetries): void
    {
        $path = $action['path'];
        $localFullPath = rtrim($localDir, '/') . '/' . ltrim($path, '/');

        if ($attempt > 1) {
            $this->line("   <fg=yellow>🔄 Attempt {$attempt}/{$maxRetries}...</>");
        }

        switch ($action['action']) {
            case 'create_directory':
                $ftpService->createDirectory($path);
                break;

            case 'upload_file':
                if (!file_exists($localFullPath)) {
                    throw new Exception("Local file not found: {$localFullPath}");
                }
                $ftpService->uploadFile($localFullPath, $path);
                break;

            case 'update_file':
                if (!file_exists($localFullPath)) {
                    throw new Exception("Local file not found: {$localFullPath}");
                }
                $ftpService->uploadFile($localFullPath, $path);
                break;

            case 'remove_file':
                $ftpService->removeFile($path);
                break;

            case 'remove_directory':
                $ftpService->removeDirectory($path);
                break;

            default:
                throw new Exception("Unknown action: {$action['action']}");
        }
    }

    /**
     * Display detailed operation results
     */
    private function displayDetailedResults(array $results): void
    {
        $this->newLine();
        $this->line("<fg=cyan>📊 ═══════════════ RESULTS ═══════════════</>");
        $this->newLine();

        $successRate = $results['total'] > 0 ? round(($results['completed'] / $results['total']) * 100, 1) : 0;

        $this->line("<fg=green>✅ Completed: {$results['completed']}/{$results['total']} ({$successRate}%)</>");

        if ($results['failed'] > 0) {
            $this->line("<fg=red>❌ Failed: {$results['failed']}</>");
            $this->newLine();
            $this->line("<fg=red>🚨 FAILED OPERATIONS:</>");

            foreach ($results['results'] as $result) {
                if ($result['status'] === 'failed') {
                    $this->line("   <fg=red>❌ {$result['action']['action']}: {$result['action']['path']}</>");
                    $this->line("   <fg=red>   Error: {$result['message']}</>");
                }
            }
        } else {
            $this->line("<fg=green>🎉 All operations completed successfully!</>");
        }

        // Save results for debugging/reference
        $resultsFile = storage_path('app/deploy-results.json');
        file_put_contents($resultsFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line("<fg=gray>💾 Results saved to: {$resultsFile}</>");
    }

    /**
     * Verify synchronization by comparing trees again
     */
    private function verifySynchronization(
        FileTreeService $fileTreeService,
        FtpSyncService $ftpService,
        array $originalLocalTree,
        string $remoteTreeUrl,
        array $excludePaths
    ): void {
        try {
            $this->line("   <fg=yellow>📡 Fetching updated remote tree...</>");

            // Get fresh remote tree
            $newRemoteTree = $ftpService->downloadRemoteTree($remoteTreeUrl, $excludePaths);

            // Compare again
            $verificationDiff = $fileTreeService->compareTrees($originalLocalTree, $newRemoteTree);

            if (empty($verificationDiff['actions'])) {
                $this->line("   <fg=green>✅ Verification successful - local and remote are now in sync!</>");
            } else {
                $this->line("   <fg=yellow>⚠️  Verification found " . count($verificationDiff['actions']) . " remaining differences</>");

                // Show remaining differences
                foreach ($verificationDiff['actions'] as $action) {
                    $this->line("   <fg=yellow>   - {$action['action']}: {$action['path']}</>");
                }

                // Save verification diff
                $verificationFile = storage_path('app/deploy-verification.json');
                file_put_contents($verificationFile, json_encode($verificationDiff, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->line("   <fg=gray>💾 Verification diff saved to: {$verificationFile}</>");
            }

        } catch (Exception $e) {
            $this->line("   <fg=red>⚠️  Verification failed: " . $e->getMessage() . "</>");
        }
    }
}
