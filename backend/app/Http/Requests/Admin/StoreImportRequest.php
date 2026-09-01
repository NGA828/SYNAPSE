<?php

namespace App\Http\Requests\Admin;

use App\Services\ImportMappingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Two shapes are accepted, and which rules apply depends on which arrived.
     *
     * Without `mapping`, rows are already keyed by canonical field names and are
     * validated field by field, exactly as they always were. With `mapping`, the
     * rows are the file's own — keyed by `"Nom"`, `"Classe"` — so there is
     * nothing meaningful to assert about their keys yet; the mapping is applied
     * in the controller and the real validation happens per row inside
     * `ImportService`, which already collects errors instead of aborting.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'type' => ['required', 'string', 'in:students,teachers'],
            'rows' => ['required', 'array', 'min:1'],
        ];

        if ($this->has('mapping')) {
            return array_merge($rules, [
                'rows.*' => ['array'],
                'mapping' => ['required', 'array', 'min:1'],
                'mapping.*' => ['string', 'max:255'],
            ]);
        }

        return array_merge($rules, [
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.email' => ['required', 'email', 'max:255'],
            'rows.*.matricule' => ['nullable', 'string', 'max:50'],
            'rows.*.staff_no' => ['nullable', 'string', 'max:50'],
            'rows.*.class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'rows.*.password' => ['nullable', 'string', 'min:8'],
            'rows.*.phone' => ['nullable', 'string', 'max:32'],
        ]);
    }

    /**
     * The shape rules cannot express: a mapping must name real fields and point
     * at columns that actually exist in the rows sent.
     *
     * Checked here rather than with `Rule::in` because both halves depend on
     * other parts of the payload, and a validator hook can say why it failed.
     */
    public function withValidator(Validator $validator): void
    {
        if (! $this->has('mapping')) {
            return;
        }

        $validator->after(function (Validator $validator) {
            $mapping = (array) $this->input('mapping', []);
            $fields = ImportMappingService::FIELDS[$this->input('type')] ?? [];
            $headers = $this->headerNames();

            foreach ($mapping as $field => $header) {
                if (! in_array((string) $field, $fields, true)) {
                    $validator->errors()->add(
                        'mapping.'.$field,
                        '"'.$field.'" is not a field a '.$this->input('type').' import accepts.',
                    );

                    continue;
                }

                // Without this, a mapping could point at a column the file does
                // not have and every row would silently import as null.
                if (! in_array((string) $header, $headers, true)) {
                    $validator->errors()->add(
                        'mapping.'.$field,
                        'No column named "'.$header.'" was sent in rows.',
                    );
                }
            }
        });
    }

    /**
     * The header strings present in the submitted rows — the only values a
     * mapping may legitimately point at.
     *
     * @return list<string>
     */
    private function headerNames(): array
    {
        foreach ((array) $this->input('rows', []) as $row) {
            if (is_array($row) && $row !== []) {
                return array_map('strval', array_keys($row));
            }
        }

        return [];
    }
}
