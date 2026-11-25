<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    public function active(): JsonResponse
    {
        $now = now();
        $announcements = Announcement::query()
            ->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get([
                'id', 'title', 'message', 'theme', 'icon', 'font_family',
                'is_bold', 'is_dismissible', 'action_label', 'action_url', 'updated_at',
            ]);

        return response()->json(['data' => $announcements]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => Announcement::query()->latest()->limit(100)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->getKey();
        $announcement = Announcement::query()->create($data);

        return response()->json(['data' => $announcement], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $announcement = Announcement::query()->findOrFail($id);
        $announcement->fill($this->validated($request));
        $announcement->save();

        return response()->json(['data' => $announcement->refresh()]);
    }

    public function destroy(string $id): JsonResponse
    {
        Announcement::query()->findOrFail($id)->delete();

        return response()->json(['data' => ['id' => $id]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
            'theme' => ['required', 'in:blue,teal,amber,red,violet'],
            'icon' => ['required', 'in:megaphone,info,warning,celebration,none'],
            'font_family' => ['required', 'in:system,serif,mono'],
            'is_bold' => ['required', 'boolean'],
            'is_dismissible' => ['required', 'boolean'],
            'action_label' => ['nullable', 'required_with:action_url', 'string', 'max:60'],
            'action_url' => ['nullable', 'required_with:action_label', 'string', 'max:2048'],
            'is_published' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $this->validateActionUrl($data['action_url'] ?? null);

        if (! empty($data['starts_at']) && ! empty($data['ends_at'])
            && Carbon::parse($data['ends_at'])->lessThanOrEqualTo(Carbon::parse($data['starts_at']))) {
            throw ValidationException::withMessages(['ends_at' => 'The end time must be after the start time.']);
        }

        return $data;
    }

    private function validateActionUrl(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $isRelative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $parts = parse_url($url);
        $isHttps = is_array($parts) && ($parts['scheme'] ?? null) === 'https' && isset($parts['host']);

        if (! $isRelative && ! $isHttps) {
            throw ValidationException::withMessages([
                'action_url' => 'Use a site path or an https URL.',
            ]);
        }
    }
}
