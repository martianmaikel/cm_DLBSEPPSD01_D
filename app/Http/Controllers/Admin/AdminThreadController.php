<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConflictThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminThreadController extends Controller
{
    public function index(): Response
    {
        $threads = ConflictThread::query()
            ->withCount('events')
            ->orderByDesc('updated_at')
            ->paginate(50);

        return Inertia::render('Admin/Threads/Index', [
            'threads' => $threads,
        ]);
    }

    public function show(ConflictThread $thread): Response
    {
        $thread->load([
            'events' => fn($q) => $q->with('source')->orderBy('occurred_at'),
        ]);

        return Inertia::render('Admin/Threads/Show', [
            'thread' => $thread,
            'events' => $thread->events,
        ]);
    }

    public function update(Request $request, ConflictThread $thread): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'summary'  => ['sometimes', 'nullable', 'string'],
            'status'   => ['sometimes', 'required', 'in:open,closed'],
            'hashtags' => ['sometimes', 'nullable', 'string'],
        ]);

        // Parse hashtags from comma/space-separated string into a clean array
        // Accepts: "#Ukraine #Russia" or "#Ukraine, #Russia" or "Ukraine Russia"
        if (array_key_exists('hashtags', $validated)) {
            $raw = $validated['hashtags'] ?? '';
            $tags = preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
            $validated['hashtags'] = array_values(array_unique(array_map(function (string $tag) {
                $tag = ltrim($tag, '#');

                return "#{$tag}";
            }, $tags))) ?: null;
        }

        $thread->update($validated);

        return back()->with('success', 'Thread updated.');
    }
}
