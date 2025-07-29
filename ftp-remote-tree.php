<?php
/**
 * FTP Remote Tree Generator
 *
 * This script generates a JSON tree of all files and directories
 * in the current directory (and subdirectories) with their MD5 hashes.
 *
 * It's designed to be uploaded to the remote FTP server and called via HTTP
 * to generate the remote file tree for comparison with the local tree.
 */

$DIR = __DIR__ . '/../ftp-sync';

// Set content type to JSON
header('Content-Type: application/json');

// Default exclusion patterns (always exclude this script)
$defaultExclusions = [
    'ftp-remote-tree.php'
];

// Get exclusion patterns from query parameter if provided
$excludeParam = $_GET['exclude'] ?? '';
$userExclusions = $excludeParam ? explode(',', $excludeParam) : [];
$exclusions = array_merge($defaultExclusions, $userExclusions);

/**
 * Check if a path should be excluded based on exclusion patterns
 */
function shouldExclude($path, $exclusions) {
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
 * Generate file tree recursively
 */
function generateTree($dir = '.', $exclusions = [], $basePath = '') {
    $tree = [];
    $realDir = realpath($dir);

    if (!$realDir || !is_dir($realDir)) {
        return $tree;
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
            if (shouldExclude($relativePath, $exclusions)) {
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
        // Return error in the response
        return ['error' => 'Failed to scan directory: ' . $e->getMessage()];
    }

    // Sort by path for consistent ordering
    usort($tree, function($a, $b) {
        return strcmp($a['path'], $b['path']);
    });

    return $tree;
}

// Generate the tree
$result = [
    'generated_at' => date('c'),
    'base_path' => realpath($DIR),
    'exclusions' => $exclusions,
    'files' => generateTree($DIR, $exclusions)
];

// Output JSON
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
