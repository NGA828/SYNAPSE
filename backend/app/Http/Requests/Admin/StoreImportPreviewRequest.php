<?php

namespace App\Http\Requests\Admin;

use App\Services\ImportMappingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A dry run of an import.
 *
 * `rows` are keyed by the file's own header text — `"Nom"`, `"Courriel"`,
 * whatever the school exported. Nothing is assumed about the column names;
 * working out what they mean is the whole point of the endpoint.
 */
class StoreImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(array_keys(ImportMappingService::FIELDS))],

            /*
            | Capped so a preview cannot be used to push a whole school through
            | the mapping path in one request. The real import has no such cap
            | and is the place to send everything.
            */
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*' => ['array'],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.max' => 'Preview at most 500 rows at a time. The import itself has no limit.',
        ];
    }
}
