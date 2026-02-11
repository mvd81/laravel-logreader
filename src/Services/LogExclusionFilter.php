<?php

namespace Mvd81\LaravelLogreader\Services;

class LogExclusionFilter
{
    public static function isExcluded(string $name, string $path = '', array $excludePatterns = []): bool
    {
        if (empty($excludePatterns)) {
            return false;
        }

        $fullPath = trim($path . '/' . $name, '/');

        foreach ($excludePatterns as $pattern) {
            $pattern = trim($pattern);

            if ($pattern === '') {
                continue;
            }

            if ($name === $pattern) {
                return true;
            }

            if ($fullPath === $pattern) {
                return true;
            }

            if (str_ends_with($pattern, '/*')) {
                $folder = str_replace('/*', '', $pattern);
                if ($name === $folder) {
                    return true;
                }
            }

            if (str_contains($pattern, '*')) {
                if (self::wildcardMatch($name, $pattern) || self::wildcardMatch($fullPath, $pattern)) {
                    return true;
                }
            }

            if (str_ends_with($pattern, '/')) {
                if (str_starts_with($fullPath, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function wildcardMatch(string $subject, string $pattern): bool
    {
        $regexPattern = preg_quote($pattern, '#');
        $regexPattern = str_replace('\*', '.*', $regexPattern);
        $regexPattern = str_replace('\?', '.', $regexPattern);

        return (bool) preg_match('#^' . $regexPattern . '$#i', $subject);
    }
}
