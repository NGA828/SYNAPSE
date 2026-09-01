<?php

namespace App\Http\Requests\Admin;

use App\Models\Announcement;
use App\Services\Ai\AnnouncementBrief;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The facts an announcement draft is built from.
 *
 * Every field is content the administrator supplies. There is no field for a
 * school name or a recipient list, because a draft has neither: the audience is
 * chosen at publish time by `AnnouncementService`, not here.
 */
class StoreAnnouncementDraftRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],

            // Short factual points, not paragraphs. The drafter composes the
            // prose; this is the raw material.
            'key_points' => ['nullable', 'array', 'max:10'],
            'key_points.*' => ['string', 'max:500'],

            'action_required' => ['nullable', 'string', 'max:500'],

            /*
            | Deliberately free text rather than a date column. An announcement
            | says "Monday 14 September at 08:00" or "the week of the 14th" —
            | the drafter must not reformat what the school actually means, and
            | a date field would force it to.
            */
            'date_text' => ['nullable', 'string', 'max:120'],
            'venue' => ['nullable', 'string', 'max:120'],

            'audience' => ['nullable', 'string', Rule::in(Announcement::AUDIENCES)],
            'tone' => ['nullable', 'string', Rule::in(AnnouncementBrief::TONES)],
            'locale' => ['nullable', 'string', 'in:en,fr'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => 'Say what the announcement is about.',
            'key_points.max' => 'At most 10 key points — anything longer belongs in the body you edit afterwards.',
        ];
    }
}
