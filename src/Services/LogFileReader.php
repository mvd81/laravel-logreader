<?php

namespace Mvd81\LaravelLogreader\Services;

use Illuminate\Support\Facades\File;

class LogFileReader
{
    private const ALLOWED_ROOT = 'storage/logs';
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_EXTENSIONS = ['log', 'txt'];

    public function list(string $path = ''): ?array
    {
        $fullPath = $this->getFullPath($path);

        if (!$fullPath || !File::isDirectory($fullPath)) {
            return null;
        }

        $items = [];
        $files = File::files($fullPath);
        $directories = File::directories($fullPath);
        $excludePatterns = config('logreader.exclude_logs', []);

        foreach ($directories as $dir) {
            $dirName = basename($dir);
            $dirPath = $this->getRelativePath($dir);

            if (LogExclusionFilter::isExcluded($dirName, $path, $excludePatterns)) {
                continue;
            }

            $items[] = [
                'type' => 'directory',
                'name' => $dirName,
                'path' => $dirPath,
            ];
        }

        foreach ($files as $file) {
            $name = basename($file);
            $extension = File::extension($file);

            if (!in_array(strtolower($extension), self::ALLOWED_EXTENSIONS)) {
                continue;
            }

            if (LogExclusionFilter::isExcluded($name, $path, $excludePatterns)) {
                continue;
            }

            $items[] = [
                'type' => 'file',
                'name' => $name,
                'path' => $this->getRelativePath($file),
                'size' => File::size($file),
                'modified' => File::lastModified($file),
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $a['type'] === 'directory' ? -1 : 1;
        });

        return ['path' => $path ?: '/', 'items' => $items];
    }

    public function read(string $path, int $page = 1, int $perPage = 100, ?string $type = null): ?array
    {
        $fullPath = $this->getFullPath($path);

        if (!$fullPath || !File::isFile($fullPath) || !$this->isAllowedFile($fullPath)) {
            return null;
        }

        $size = File::size($fullPath);
        if ($size > self::MAX_FILE_SIZE) {
            return null;
        }

        $contents = File::get($fullPath);
        $lines = explode("\n", $contents);

        if ($type) {
            $lines = $this->filterLogsByType($lines, $type);
        }

        $totalLines = count($lines);
        $start = ($page - 1) * $perPage;
        $paginatedLines = array_slice($lines, $start, $perPage);

        return [
            'path' => $path,
            'size' => $size,
            'type' => $type,
            'total_lines' => $totalLines,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($totalLines / $perPage),
            'contents' => $paginatedLines,
        ];
    }

    public function search(string $path, string $query, bool $caseSensitive = false): ?array
    {
        $fullPath = $this->getFullPath($path);

        if (!$fullPath || !File::isFile($fullPath) || !$this->isAllowedFile($fullPath)) {
            return null;
        }

        $contents = File::get($fullPath);
        $lines = explode("\n", $contents);
        $results = [];
        $foundLogIndices = [];

        foreach ($lines as $lineNumber => $line) {
            $match = $caseSensitive
                ? strpos($line, $query) !== false
                : stripos($line, $query) !== false;

            if ($match) {
                $parentLogIndex = null;
                for ($i = $lineNumber; $i >= 0; $i--) {
                    if (preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s+\w+\.\w+:/', $lines[$i])) {
                        $parentLogIndex = $i;
                        break;
                    }
                }

                if ($parentLogIndex !== null && !in_array($parentLogIndex, $foundLogIndices)) {
                    $foundLogIndices[] = $parentLogIndex;
                }
            }
        }

        foreach ($foundLogIndices as $logIndex) {
            $logEndLine = $logIndex;
            for ($i = $logIndex + 1; $i < count($lines); $i++) {
                if (preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s+\w+\.\w+:/', $lines[$i])) {
                    $logEndLine = $i - 1;
                    break;
                }
                $logEndLine = $i;
            }

            for ($i = $logIndex; $i <= $logEndLine; $i++) {
                $results[] = [
                    'line_number' => $i + 1,
                    'content' => $lines[$i],
                ];
            }
        }

        return [
            'path' => $path,
            'query' => $query,
            'matches' => count($foundLogIndices),
            'results' => array_slice($results, 0, 500),
        ];
    }

    private function getFullPath(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return base_path(self::ALLOWED_ROOT);
        }

        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = ltrim($relativePath, '/');

        if (strpos($relativePath, '..') !== false) {
            return null;
        }

        $fullPath = base_path(self::ALLOWED_ROOT . '/' . $relativePath);

        if (strpos(realpath($fullPath) ?: $fullPath, realpath(base_path(self::ALLOWED_ROOT))) !== 0) {
            return null;
        }

        return $fullPath;
    }

    private function getRelativePath(string $fullPath): string
    {
        $root = realpath(base_path(self::ALLOWED_ROOT));
        $path = realpath($fullPath);

        if ($root === $path) {
            return '';
        }

        return str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    }

    private function isAllowedFile(string $fullPath): bool
    {
        $extension = strtolower(File::extension($fullPath));
        return in_array($extension, self::ALLOWED_EXTENSIONS);
    }

    private function filterLogsByType(array $lines, string $type): array
    {
        $filteredLines = [];
        $normalizedType = strtoupper($type);
        $matchingLogIndices = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^\[\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]\s+[\w\-]+\.' . preg_quote($normalizedType, '/') . ':/i', $line)) {
                $matchingLogIndices[] = $index;
            }
        }

        foreach ($matchingLogIndices as $logIndex) {
            $logEndLine = $logIndex;
            for ($i = $logIndex + 1; $i < count($lines); $i++) {
                if (trim($lines[$i]) === '') {
                    $logEndLine = $i;
                    continue;
                }
                if (preg_match('/^\[\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]\s+[\w\-]+\.\w+:/', $lines[$i])) {
                    $logEndLine = $i - 1;
                    break;
                }
                $logEndLine = $i;
            }

            for ($i = $logIndex; $i <= $logEndLine; $i++) {
                $filteredLines[] = $lines[$i];
            }
        }

        return $filteredLines;
    }
}
