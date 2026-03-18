<?php

namespace Mvd81\LaravelLogreader\Services;

use Illuminate\Support\Facades\File;

class LogFileReader
{
    private const ALLOWED_ROOT = 'storage/logs';
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

        if ($type) {
            // Filtered read: stream line by line, collect only matching lines.
            $lines = $this->streamFilterLogsByType($fullPath, $type);
            $totalLines = count($lines);
            $paginatedLines = array_slice($lines, ($page - 1) * $perPage, $perPage);
        } else {
            // Unfiltered read: stream line by line so large files don't exhaust memory.
            $file = new \SplFileObject($fullPath);
            $file->seek(PHP_INT_MAX);
            $totalLines = $file->key() + 1;

            $start = ($page - 1) * $perPage;
            $file->seek($start);

            $paginatedLines = [];
            while (!$file->eof() && count($paginatedLines) < $perPage) {
                $paginatedLines[] = rtrim($file->current(), "\r\n");
                $file->next();
            }
        }

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

    public function countByLevel(): ?array
    {
        $yesterday = date('Y-m-d', strtotime('yesterday'));

        // Resolve the correct log file: prefer daily (LOG_STACK=daily), fall back to single
        $path = $this->resolveLogPath($yesterday);

        if ($path === null) {
            return null;
        }

        $fullPath = $this->getFullPath($path);
        $contents = File::get($fullPath);
        $lines = explode("\n", $contents);
        $counts = [];

        foreach ($lines as $line) {
            if (!preg_match('/^\[' . preg_quote($yesterday, '/') . ' \d{2}:\d{2}:\d{2}\]\s+[\w\-]+\.(\w+):/i', $line, $matches)) {
                continue;
            }

            $level = strtolower($matches[1]);
            $counts[$level] = ($counts[$level] ?? 0) + 1;
        }

        return [
            'path' => $path,
            'date' => $yesterday,
            'counts' => $counts,
        ];
    }

    private function resolveLogPath(string $date): ?string
    {
        foreach (["laravel-{$date}.log", 'laravel.log'] as $candidate) {
            $fullPath = $this->getFullPath($candidate);
            if ($fullPath && File::isFile($fullPath) && $this->isAllowedFile($fullPath)) {
                return $candidate;
            }
        }

        return null;
    }

    public function search(string $path, string $query, bool $caseSensitive = false): ?array
    {
        $fullPath = $this->getFullPath($path);

        if (!$fullPath || !File::isFile($fullPath) || !$this->isAllowedFile($fullPath)) {
            return null;
        }

        $headerPattern = '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s+\w+\.\w+:/';
        $results = [];
        $matchCount = 0;
        $currentEntry = [];
        $currentLineNumber = 0;

        $file = new \SplFileObject($fullPath);
        while (!$file->eof()) {
            $line = rtrim($file->current(), "\r\n");
            $lineNumber = $file->key() + 1;
            $file->next();

            if (preg_match($headerPattern, $line)) {
                // Flush previous entry if it had a match
                if (!empty($currentEntry)) {
                    $entryText = implode("\n", array_column($currentEntry, 'content'));
                    $matched = $caseSensitive
                        ? str_contains($entryText, $query)
                        : str_contains(mb_strtolower($entryText), mb_strtolower($query));

                    if ($matched && $matchCount < 500) {
                        array_push($results, ...$currentEntry);
                        $matchCount++;
                    }
                }
                $currentEntry = [['line_number' => $lineNumber, 'content' => $line]];
            } elseif (!empty($currentEntry)) {
                $currentEntry[] = ['line_number' => $lineNumber, 'content' => $line];
            }
        }

        // Flush the last entry
        if (!empty($currentEntry)) {
            $entryText = implode("\n", array_column($currentEntry, 'content'));
            $matched = $caseSensitive
                ? str_contains($entryText, $query)
                : str_contains(mb_strtolower($entryText), mb_strtolower($query));

            if ($matched && $matchCount < 500) {
                array_push($results, ...$currentEntry);
                $matchCount++;
            }
        }

        return [
            'path' => $path,
            'query' => $query,
            'matches' => $matchCount,
            'results' => $results,
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

    private function streamFilterLogsByType(string $fullPath, string $type): array
    {
        $normalizedType = strtoupper($type);
        $matchPattern = '/^\[\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]\s+[\w\-]+\.' . preg_quote($normalizedType, '/') . ':/i';
        $anyHeaderPattern = '/^\[\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]\s+[\w\-]+\.\w+:/';
        $collecting = false;
        $filteredLines = [];

        $file = new \SplFileObject($fullPath);
        while (!$file->eof()) {
            $line = rtrim($file->current(), "\r\n");
            $file->next();

            if (preg_match($anyHeaderPattern, $line)) {
                $collecting = (bool) preg_match($matchPattern, $line);
            }

            if ($collecting) {
                $filteredLines[] = $line;
            }
        }

        return $filteredLines;
    }

    private function filterLogsByType(array $lines, string $type): array
    {
        $filteredLines = [];
        $normalizedType = strtoupper($type);
        $matchPattern = '/^\[\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]\s+[\w\-]+\.' . preg_quote($normalizedType, '/') . ':/i';
        $anyHeaderPattern = '/^\[\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]\s+[\w\-]+\.\w+:/';
        $collecting = false;

        foreach ($lines as $line) {
            if (preg_match($anyHeaderPattern, $line)) {
                $collecting = (bool) preg_match($matchPattern, $line);
            }

            if ($collecting) {
                $filteredLines[] = $line;
            }
        }

        return $filteredLines;
    }
}
