<?php

namespace App\Services\Pdf;

use App\Models\School;
use App\Models\SchoolSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Renders Blade templates to real PDF binaries (dompdf).
 *
 * Every school document goes through here so branding, paper size and the
 * verification footer stay consistent across report cards, transcripts,
 * certificates and receipts.
 */
class PdfRenderer
{
    /**
     * Render a Blade view to raw PDF bytes.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data = [], string $paper = 'a4', string $orientation = 'portrait'): string
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper($paper, $orientation);

        $pdf->setOptions([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'dpi' => 96,
            'chroot' => [base_path('resources'), storage_path('app')],
        ]);

        return $pdf->output();
    }

    /**
     * Render and persist to a disk, returning [path, size].
     *
     * @param  array<string, mixed>  $data
     * @return array{path: string, size: int, disk: string}
     */
    public function store(string $view, array $data, string $path, string $disk = 'local'): array
    {
        $bytes = $this->render($view, $data);

        Storage::disk($disk)->put($path, $bytes);

        return ['path' => $path, 'size' => strlen($bytes), 'disk' => $disk];
    }

    /**
     * Branding block shared by every template (logo, colours, contact lines).
     *
     * @return array<string, mixed>
     */
    public function branding(?School $school): array
    {
        $settings = $school
            ? SchoolSetting::query()->where('school_id', $school->id)->pluck('value', 'key')->all()
            : [];

        return [
            'school' => $school,
            'school_name' => $school?->name ?? config('app.name'),
            'school_address' => $school?->address,
            'school_email' => $school?->email,
            'school_phone' => $school?->phone,
            'primary_color' => $school?->primary_color ?: '#4f46e5',
            'logo' => $this->logoSource($school),
            'motto' => $settings['motto'] ?? null,
            'principal' => $settings['principal_name'] ?? null,
            'country' => $settings['country'] ?? config('synapse.country'),
        ];
    }

    /**
     * dompdf can only embed local files or data URIs — never a remote URL.
     */
    private function logoSource(?School $school): ?string
    {
        $logo = $school?->logo;

        if (! $logo) {
            return null;
        }

        // Already a data URI (schools upload base64 through the settings API).
        if (str_starts_with($logo, 'data:image')) {
            return $logo;
        }

        foreach (['public', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($logo)) {
                $mime = Storage::disk($disk)->mimeType($logo) ?: 'image/png';

                return 'data:'.$mime.';base64,'.base64_encode(Storage::disk($disk)->get($logo));
            }
        }

        return null;
    }

    /**
     * True when the view exists — lets callers fall back to a generic template.
     */
    public function hasTemplate(string $view): bool
    {
        return View::exists($view);
    }
}
