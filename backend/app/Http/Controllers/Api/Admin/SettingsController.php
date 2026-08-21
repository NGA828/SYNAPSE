<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private const KEYS = [
        'grading_scale',
        'semester_structure',
        'custom_branding_enabled',
        'notification_preferences',
        'primary_color',
        'timezone',
    ];

    /**
     * School settings + branding (logo and identity fields).
     */
    public function show(Request $request): JsonResponse
    {
        $school = $request->user()->school;

        return response()->json([
            'data' => $this->readSettings($school),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['sometimes', 'array'],
            // White-label branding. `logo` is a base64 data URL (kept small).
            'logo' => ['nullable', 'string', 'max:5000000'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $school = $request->user()->school;

        foreach ($data['settings'] ?? [] as $key => $value) {
            if (in_array($key, self::KEYS, true)) {
                $school->setSetting($key, $value);
            }
        }

        if (array_key_exists('primary_color', $data['settings'] ?? [])) {
            $school->update(['primary_color' => $data['settings']['primary_color']]);
        }

        if (array_key_exists('timezone', $data['settings'] ?? [])) {
            $school->update(['timezone' => $data['settings']['timezone']]);
        }

        // Branding: logo + identity. The logo is stored on the school so the
        // whole platform can white-label the sidebar/topbar for this tenant.
        if ($request->has('logo')) {
            $school->update(['logo' => $data['logo']]);
        }

        $identity = collect($data)
            ->only(['name', 'email', 'phone', 'address'])
            ->filter(fn ($value, $key) => $request->has($key))
            ->all();

        if ($identity !== []) {
            $school->update($identity);
        }

        return response()->json([
            'data' => $this->readSettings($school->fresh()),
            'message' => 'Settings updated.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readSettings(School $school): array
    {
        $settings = [];

        foreach (self::KEYS as $key) {
            $settings[$key] = $school->setting($key);
        }

        // Branding / identity (white-label).
        $settings['logo'] = $school->logo;
        $settings['name'] = $school->name;
        $settings['email'] = $school->email;
        $settings['phone'] = $school->phone;
        $settings['address'] = $school->address;

        return $settings;
    }
}
