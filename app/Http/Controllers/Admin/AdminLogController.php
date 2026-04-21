<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class AdminLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logsPath = storage_path('logs');
        $files = $this->getLogFiles($logsPath);
        $selectedFile = $request->input('file', $files[0] ?? null);
        $levelFilter = $request->input('level');
        $search = $request->input('search');

        $entries = [];
        $fileSizeBytes = 0;

        if ($selectedFile && $this->isValidLogFile($selectedFile, $logsPath)) {
            $fullPath = $logsPath . DIRECTORY_SEPARATOR . $selectedFile;
            $fileSizeBytes = File::size($fullPath);
            $entries = $this->parseLogFile($fullPath, $levelFilter, $search);
        }

        return Inertia::render('Admin/Logs', [
            'files' => $files,
            'selectedFile' => $selectedFile,
            'entries' => $entries,
            'fileSizeBytes' => $fileSizeBytes,
            'filters' => [
                'level' => $levelFilter,
                'search' => $search,
            ],
        ]);
    }

    public function download(Request $request)
    {
        $logsPath = storage_path('logs');
        $file = $request->input('file');

        if (! $file || ! $this->isValidLogFile($file, $logsPath)) {
            abort(404);
        }

        return response()->download(
            $logsPath . DIRECTORY_SEPARATOR . $file,
            $file,
            ['Content-Type' => 'text/plain']
        );
    }

    public function clear(Request $request)
    {
        $logsPath = storage_path('logs');
        $file = $request->input('file');

        if (! $file || ! $this->isValidLogFile($file, $logsPath)) {
            abort(404);
        }

        File::put($logsPath . DIRECTORY_SEPARATOR . $file, '');

        return back()->with('success', "Log file {$file} cleared.");
    }

    private function getLogFiles(string $logsPath): array
    {
        if (! File::isDirectory($logsPath)) {
            return [];
        }

        $files = collect(File::files($logsPath))
            ->filter(fn ($file) => $file->getExtension() === 'log')
            ->map(fn ($file) => $file->getFilename())
            ->sortDesc()
            ->values()
            ->all();

        return $files;
    }

    private function isValidLogFile(string $filename, string $logsPath): bool
    {
        // Prevent directory traversal
        if (str_contains($filename, '..') || str_contains($filename, DIRECTORY_SEPARATOR) || str_contains($filename, '/')) {
            return false;
        }

        if (! str_ends_with($filename, '.log')) {
            return false;
        }

        return File::exists($logsPath . DIRECTORY_SEPARATOR . $filename);
    }

    private function parseLogFile(string $path, ?string $levelFilter, ?string $search): array
    {
        // Read last 500KB max to avoid memory issues on huge logs
        $maxBytes = 500 * 1024;
        $size = File::size($path);
        $content = '';

        if ($size > $maxBytes) {
            $handle = fopen($path, 'r');
            fseek($handle, -$maxBytes, SEEK_END);
            // Skip partial first line
            fgets($handle);
            $content = fread($handle, $maxBytes);
            fclose($handle);
        } else {
            $content = File::get($path);
        }

        if (empty(trim($content))) {
            return [];
        }

        $lines = explode("\n", $content);
        $entries = [];
        $current = null;

        // Laravel log format: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2})?)\]\s+(\w+)\.(\w+):\s*(.*)/';

        foreach ($lines as $line) {
            if (preg_match($pattern, $line, $matches)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = [
                    'timestamp' => $matches[1],
                    'env' => $matches[2],
                    'level' => strtolower($matches[3]),
                    'message' => $matches[4],
                    'stack' => '',
                ];
            } elseif ($current !== null && trim($line) !== '') {
                // Stack trace continuation
                $current['stack'] .= ($current['stack'] ? "\n" : '') . $line;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        // Apply filters
        if ($levelFilter) {
            $entries = array_values(array_filter(
                $entries,
                fn ($e) => $e['level'] === strtolower($levelFilter)
            ));
        }

        if ($search) {
            $searchLower = strtolower($search);
            $entries = array_values(array_filter(
                $entries,
                fn ($e) => str_contains(strtolower($e['message']), $searchLower)
                    || str_contains(strtolower($e['stack']), $searchLower)
            ));
        }

        // Return last 500 entries max, newest last
        return array_slice($entries, -500);
    }
}
