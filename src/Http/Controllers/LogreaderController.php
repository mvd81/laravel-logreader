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

    public function count(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
        ]);

        $result = $this->reader->countByLevel($validated['date'] ?? null);

        if ($result === null) {
            return response()->json(['error' => 'No log file found for the requested date'], 404);
        }

        return response()->json($result);
    }

    public function timeRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'time_from' => 'nullable|string|regex:/^\d{1,2}:\d{2}$/',
            'time_to' => 'nullable|string|regex:/^\d{1,2}:\d{2}$/',
        ]);

        $result = $this->reader->searchByTimeRange(
            $validated['path'],
            $validated['time_from'] ?? '',
            $validated['time_to'] ?? '',
        );

        if ($result === null) {
            return response()->json(['error' => 'Invalid path, not a file, or file type not allowed'], 400);
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
