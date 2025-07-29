<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class FtpSyncService
{
    private $connection;
    private string $server;
    private string $username;
    private string $password;
    private int $timeout;
    private int $maxRetries;

    public function __construct(string $server, string $username, string $password, int $timeout = 60, int $maxRetries = 4)
    {
        $this->server = $server;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    /**
     * Connect to FTP server
     */
    public function connect(): void
    {
        $this->connection = ftp_connect($this->server, 21, $this->timeout);

        if (!$this->connection) {
            throw new Exception("Failed to connect to FTP server: {$this->server}");
        }

        if (!ftp_login($this->connection, $this->username, $this->password)) {
            ftp_close($this->connection);
            throw new Exception("Failed to login to FTP server with provided credentials");
        }

        // Set passive mode
        ftp_pasv($this->connection, true);
    }

    /**
     * Disconnect from FTP server
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            ftp_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Execute an operation with retry logic
     */
    private function executeWithRetry(callable $operation, string $operationName): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (Exception $e) {
                $lastException = $e;

                if ($attempt < $this->maxRetries) {
                    // Reconnect if connection was lost
                    if (!$this->connection || !$this->isConnected()) {
                        try {
                            $this->connect();
                        } catch (Exception $connectException) {
                            // Continue to next attempt
                        }
                    }

                    // Wait before retry (exponential backoff)
                    sleep(min(pow(2, $attempt - 1), 10));
                } else {
                    throw new Exception("Operation '{$operationName}' failed after {$this->maxRetries} attempts. Last error: " . $e->getMessage());
                }
            }
        }

        throw $lastException;
    }

    /**
     * Check if still connected to FTP server
     */
    private function isConnected(): bool
    {
        if (!$this->connection) {
            return false;
        }

        // Try a simple operation to check if connection is still alive
        try {
            return ftp_pwd($this->connection) !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create a directory on the remote server
     */
    public function createDirectory(string $path): void
    {
        $this->executeWithRetry(function() use ($path) {
            if (!ftp_mkdir($this->connection, $path)) {
                throw new Exception("Failed to create directory: {$path}");
            }
        }, "create directory: {$path}");
    }

    /**
     * Remove a directory from the remote server
     */
    public function removeDirectory(string $path): void
    {
        $this->executeWithRetry(function() use ($path) {
            if (!ftp_rmdir($this->connection, $path)) {
                throw new Exception("Failed to remove directory: {$path}");
            }
        }, "remove directory: {$path}");
    }

    /**
     * Upload a file to the remote server
     */
    public function uploadFile(string $localPath, string $remotePath): void
    {
        $this->executeWithRetry(function() use ($localPath, $remotePath) {
            // Create directory if it doesn't exist
            $remoteDir = dirname($remotePath);
            if ($remoteDir !== '.' && $remoteDir !== '/') {
                $this->ensureDirectoryExists($remoteDir);
            }

            if (!ftp_put($this->connection, $remotePath, $localPath, FTP_BINARY)) {
                throw new Exception("Failed to upload file from {$localPath} to {$remotePath}");
            }
        }, "upload file: {$localPath} -> {$remotePath}");
    }

    /**
     * Download a file from the remote server
     */
    public function downloadFile(string $remotePath, string $localPath): void
    {
        $this->executeWithRetry(function() use ($remotePath, $localPath) {
            // Create local directory if it doesn't exist
            $localDir = dirname($localPath);
            if (!is_dir($localDir)) {
                mkdir($localDir, 0755, true);
            }

            if (!ftp_get($this->connection, $localPath, $remotePath, FTP_BINARY)) {
                throw new Exception("Failed to download file from {$remotePath} to {$localPath}");
            }
        }, "download file: {$remotePath} -> {$localPath}");
    }

    /**
     * Remove a file from the remote server
     */
    public function removeFile(string $path): void
    {
        $this->executeWithRetry(function() use ($path) {
            if (!ftp_delete($this->connection, $path)) {
                throw new Exception("Failed to remove file: {$path}");
            }
        }, "remove file: {$path}");
    }

    /**
     * Ensure a directory exists on the remote server (create if it doesn't)
     */
    private function ensureDirectoryExists(string $path): void
    {
        $parts = explode('/', trim($path, '/'));
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath .= '/' . $part;
            $currentPath = ltrim($currentPath, '/');

            // Check if directory exists by trying to change to it
            $currentDir = ftp_pwd($this->connection);
            if (!@ftp_chdir($this->connection, $currentPath)) {
                // Directory doesn't exist, create it
                if (!ftp_mkdir($this->connection, $currentPath)) {
                    throw new Exception("Failed to create directory: {$currentPath}");
                }
            }

            // Return to original directory
            ftp_chdir($this->connection, $currentDir);
        }
    }

    /**
     * Check if a file or directory exists on the remote server
     */
    public function exists(string $path): bool
    {
        try {
            $size = ftp_size($this->connection, $path);
            if ($size >= 0) {
                return true; // It's a file
            }

            // Try to change to the path (for directories)
            $currentDir = ftp_pwd($this->connection);
            if (ftp_chdir($this->connection, $path)) {
                ftp_chdir($this->connection, $currentDir);
                return true;
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }



    /**
     * Download remote tree JSON via HTTP
     */
    public function downloadRemoteTree(string $remoteTreeUrl, array $exclusions = []): array
    {
        $url = $remoteTreeUrl;
        if (!empty($exclusions)) {
            $url .= '?exclude=' . urlencode(implode(',', $exclusions));
        }

        try {
            $response = Http::timeout($this->timeout)->get($url);

            if (!$response->successful()) {
                throw new Exception("Failed to fetch remote tree. HTTP status: " . $response->status());
            }

            $data = $response->json();
            if (!$data) {
                throw new Exception("Failed to decode remote tree JSON response");
            }

            if (isset($data['error'])) {
                throw new Exception("Remote tree generation error: " . $data['error']);
            }

            return $data;
        } catch (Exception $e) {
            throw new Exception("Failed to download remote tree: " . $e->getMessage());
        }
    }

    /**
     * Apply a list of sync actions
     */
    public function applySyncActions(array $actions, string $localBasePath): array
    {
        $results = [];
        $completed = 0;
        $failed = 0;

        foreach ($actions as $action) {
            try {
                $this->applySyncAction($action, $localBasePath);
                $results[] = [
                    'action' => $action,
                    'status' => 'success',
                    'message' => "Successfully executed {$action['action']} for {$action['path']}"
                ];
                $completed++;
            } catch (Exception $e) {
                $results[] = [
                    'action' => $action,
                    'status' => 'failed',
                    'message' => $e->getMessage()
                ];
                $failed++;

                // Log the error but continue with other actions
                // You might want to add logging here
            }
        }

        return [
            'completed' => $completed,
            'failed' => $failed,
            'total' => count($actions),
            'results' => $results
        ];
    }

    /**
     * Apply a single sync action
     */
    private function applySyncAction(array $action, string $localBasePath): void
    {
        $path = $action['path'];
        $localFullPath = rtrim($localBasePath, '/') . '/' . ltrim($path, '/');

        switch ($action['action']) {
            case 'create_directory':
                $this->createDirectory($path);
                break;

            case 'upload_file':
                if (!file_exists($localFullPath)) {
                    throw new Exception("Local file not found: {$localFullPath}");
                }
                $this->uploadFile($localFullPath, $path);
                break;

            case 'update_file':
                if (!file_exists($localFullPath)) {
                    throw new Exception("Local file not found: {$localFullPath}");
                }
                $this->uploadFile($localFullPath, $path);
                break;

            case 'remove_file':
                $this->removeFile($path);
                break;

            case 'remove_directory':
                $this->removeDirectory($path);
                break;

            default:
                throw new Exception("Unknown action: {$action['action']}");
        }
    }

    /**
     * Get connection info for debugging
     */
    public function getConnectionInfo(): array
    {
        return [
            'server' => $this->server,
            'username' => $this->username,
            'connected' => $this->isConnected(),
            'timeout' => $this->timeout,
            'max_retries' => $this->maxRetries
        ];
    }
}
