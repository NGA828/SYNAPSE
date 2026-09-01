<?php

namespace App\Http\Requests\Student;

use App\Models\DocumentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // A closed list. Free text here is what let a request be filed for
            // a document no template can produce, and then be issued a generic
            // certificate that was not what the student asked for.
            'type' => ['required', 'string', Rule::in(DocumentRequest::TYPES)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
