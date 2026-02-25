<?php

namespace Mvd81\LaravelLogreader\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Mvd81\LaravelLogreader\Services\LogFileReader;

class LogreaderController extends Controller
{
    public function __construct(
        private readonly LogFileReader $reader
    ) {}

    public function list(Request $request): JsonResponse
    {
        $path = $request->query('path', '');

        $result = $this->reader->list($path);

        if ($result === null) {
            return response()->json(['error' => 'Invalid path or not a directory'], 400);
        }

        return response()->json($result);
    }

    public function read(Request $request): JsonResponse
    {
        $path = $request->query('path');
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 100);
        $type = $request->query('type');

        if (!$path) {
            return response()->json(['error' => 'Path is required'], 400);
        }

        $result = $this->reader->read($path, $page, $perPage, $type);

        if ($result === null) {
            return response()->json(['error' => 'Invalid path, not a file, file type not allowed, or file too large'], 400);
        }

        return response()->json($result);
    }

    public function count(): JsonResponse
    {
        $result = $this->reader->countByLevel();

        if ($result === null) {
            return response()->json(['error' => 'No log file found for yesterday'], 404);
        }

        return response()->json($result);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'query' => 'required|string|max:100',
            'case_sensitive' => 'boolean',
        ]);

        $result = $this->reader->search(
            $validated['path'],
            $validated['query'],
            $validated['case_sensitive'] ?? false
        );

        if ($result === null) {
            return response()->json(['error' => 'Invalid path, not a file, or file type not allowed'], 400);
        }

        return response()->json($result);
    }
}
