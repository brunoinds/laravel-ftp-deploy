<?php

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Exception;

class FileTreeService
{
    /**
     * Generate a file tree for the given directory with hashes
     */
    public function generateTree(string $directory, array $exclusions = []): array
    {
        $tree = [];
        $realDir = realpath($directory);

        if (!$realDir || !is_dir($realDir)) {
            throw new Exception("Directory not found: {$directory}");
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($realDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                $relativePath = ltrim(str_replace($realDir, '', $file->getPathname()), '/\\');
                $relativePath = str_replace('\\', '/', $relativePath); // Normalize path separators

                // Skip if excluded
                if ($this->shouldExclude($relativePath, $exclusions)) {
                    continue;
                }

                $item = [
                    'path' => $relativePath,
                    'type' => $file->isDir() ? 'directory' : 'file',
                    'size' => $file->isFile() ? $file->getSize() : 0,
                    'modified' => $file->getMTime(),
                ];

                // Add hash for files
                if ($file->isFile()) {
                    $item['hash'] = md5_file($file->getPathname());
                }

                $tree[] = $item;
            }

        } catch (Exception $e) {
            throw new Exception("Failed to scan directory: " . $e->getMessage());
        }

        // Sort by path for consistent ordering
        usort($tree, function($a, $b) {
            return strcmp($a['path'], $b['path']);
        });

        return $tree;
    }

    /**
     * Generate complete file tree structure with metadata
     */
    public function generateCompleteTree(string $directory, array $exclusions = []): array
    {
        // Always exclude ftp-remote-tree.php
        $exclusions = array_merge(['ftp-remote-tree.php'], $exclusions);

        return [
            'generated_at' => date('c'),
            'base_path' => realpath($directory),
            'exclusions' => $exclusions,
            'files' => $this->generateTree($directory, $exclusions)
        ];
    }

    /**
     * Save tree to JSON file
     */
    public function saveTreeToFile(array $tree, string $filepath): void
    {
        $json = json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new Exception("Failed to encode tree to JSON");
        }

        if (file_put_contents($filepath, $json) === false) {
            throw new Exception("Failed to write tree to file: {$filepath}");
        }
    }

    /**
     * Load tree from JSON file
     */
    public function loadTreeFromFile(string $filepath): array
    {
        if (!file_exists($filepath)) {
            throw new Exception("Tree file not found: {$filepath}");
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new Exception("Failed to read tree file: {$filepath}");
        }

        $tree = json_decode($content, true);
        if ($tree === null) {
            throw new Exception("Failed to decode JSON from tree file: {$filepath}");
        }

        return $tree;
    }

    /**
     * Check if a path should be excluded based on exclusion patterns
     */
    public function shouldExclude(string $path, array $exclusions): bool
    {
        foreach ($exclusions as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) continue;

            // Handle recursive patterns (ending with /**)
            if (str_ends_with($pattern, '/**')) {
                $basePattern = rtrim($pattern, '/**');
                if (str_starts_with($path, $basePattern . '/') || $path === $basePattern) {
                    return true;
                }
            }
            // Handle wildcard patterns (ending with /*)
            elseif (str_ends_with($pattern, '/*')) {
                $basePattern = rtrim($pattern, '/*');
                $pathParts = explode('/', $path);
                $patternParts = explode('/', $basePattern);

                if (count($pathParts) === count($patternParts) + 1) {
                    $parentPath = implode('/', array_slice($pathParts, 0, -1));
                    if ($parentPath === $basePattern) {
                        return true;
                    }
                }
            }
            // Handle exact matches
            else {
                if ($path === $pattern) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Compare two file trees and generate a diff
     */
    public function compareTrees(array $localTree, array $remoteTree): array
    {
        $localFiles = [];
        $remoteFiles = [];

        // Index files by path
        foreach ($localTree['files'] as $file) {
            $localFiles[$file['path']] = $file;
        }

        foreach ($remoteTree['files'] as $file) {
            $remoteFiles[$file['path']] = $file;
        }

        $diff = [
            'created_at' => date('c'),
            'actions' => []
        ];

        // Find files/directories to create or update
        foreach ($localFiles as $path => $localFile) {
            if (!isset($remoteFiles[$path])) {
                // File/directory doesn't exist remotely - create it
                $diff['actions'][] = [
                    'action' => $localFile['type'] === 'directory' ? 'create_directory' : 'upload_file',
                    'path' => $path,
                    'type' => $localFile['type'],
                    'size' => $localFile['size'] ?? 0,
                    'local_hash' => $localFile['hash'] ?? null
                ];
            } else {
                $remoteFile = $remoteFiles[$path];

                // Check if file content has changed (for files only)
                if ($localFile['type'] === 'file' &&
                    isset($localFile['hash']) && isset($remoteFile['hash']) &&
                    $localFile['hash'] !== $remoteFile['hash']) {

                    $diff['actions'][] = [
                        'action' => 'update_file',
                        'path' => $path,
                        'type' => 'file',
                        'size' => $localFile['size'],
                        'local_hash' => $localFile['hash'],
                        'remote_hash' => $remoteFile['hash']
                    ];
                }
            }
        }

        // Find files/directories to remove
        foreach ($remoteFiles as $path => $remoteFile) {
            if (!isset($localFiles[$path])) {
                $diff['actions'][] = [
                    'action' => $remoteFile['type'] === 'directory' ? 'remove_directory' : 'remove_file',
                    'path' => $path,
                    'type' => $remoteFile['type'],
                    'remote_hash' => $remoteFile['hash'] ?? null
                ];
            }
        }

        // Sort actions: directories first for creation, files first for deletion
        usort($diff['actions'], function($a, $b) {
            // For creation, directories come first
            if (str_starts_with($a['action'], 'create') && str_starts_with($b['action'], 'create')) {
                if ($a['type'] === 'directory' && $b['type'] === 'file') return -1;
                if ($a['type'] === 'file' && $b['type'] === 'directory') return 1;
            }

            // For removal, files come first
            if (str_starts_with($a['action'], 'remove') && str_starts_with($b['action'], 'remove')) {
                if ($a['type'] === 'file' && $b['type'] === 'directory') return -1;
                if ($a['type'] === 'directory' && $b['type'] === 'file') return 1;
            }

            // Then sort by action type (create before update before remove)
            $actionOrder = ['create_directory' => 1, 'upload_file' => 2, 'update_file' => 3, 'remove_file' => 4, 'remove_directory' => 5];
            return ($actionOrder[$a['action']] ?? 999) <=> ($actionOrder[$b['action']] ?? 999);
        });

        return $diff;
    }
}
