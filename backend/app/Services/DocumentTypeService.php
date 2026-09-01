<?php

namespace App\Services;

use App\Models\DocumentRequest;
use Illuminate\Support\Str;

/**
 * Document-request triage.
 *
 * Two jobs. The first is *classification*: turning the free text a student
 * typed into one of the documents the school actually issues, so a request
 * filed before the type list existed still resolves. The second, and the more
 * important one, is deciding **whether a template may answer it at all**.
 *
 * Before this service existed, an unrecognised type fell through to a generic
 * "is known to this school" certificate, the request was marked ready, and the
 * student was told their document was available. A student who asked for a
 * recommendation letter received a document saying the school knows them — and
 * nothing in the system ever said that had happened.
 *
 * So the rule here is the opposite of a silent default: if a request cannot be
 * matched to a template, it is reported as needing a person, and issuing it
 * automatically is refused.
 *
 * Classification is computed on read rather than stored. A stored classification
 * goes stale the moment the keyword rules improve, and nothing would remember to
 * re-run it.
 */
class DocumentTypeService
{
    /**
     * Keyword rules, most specific first. Order matters: "Transfer Certificate"
     * and "School Leaving Certificate" both contain the word "certificate", so
     * the discriminating word has to be tested before the generic one.
     *
     * @var list<array{0: string, 1: list<string>}>
     */
    private const RULES = [
        [DocumentRequest::TYPE_RECOMMENDATION, ['recommendation', 'reference letter', 'referee', 'testimonial']],
        [DocumentRequest::TYPE_TRANSCRIPT, ['transcript', 'academic record', 'attestation', 'statement of result']],
        [DocumentRequest::TYPE_TRANSFER, ['transfer']],
        [DocumentRequest::TYPE_LEAVING, ['leaving', 'graduat', 'completion of studies']],
        [DocumentRequest::TYPE_GOOD_CONDUCT, ['conduct', 'good standing', 'discipline', 'character']],
        [DocumentRequest::TYPE_ENROLLMENT, ['enrol', 'attendance certificate', 'proof of stud', 'registered at']],
    ];

    /**
     * Resolve free text to a canonical type, or null when nothing matches.
     *
     * An exact match against the known list wins outright; the keyword rules
     * only run for text that is not already canonical.
     */
    public function classify(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $trimmed = trim($text);

        if ($trimmed === '') {
            return null;
        }

        foreach (DocumentRequest::TYPES as $type) {
            if (strcasecmp($type, $trimmed) === 0) {
                return $type;
            }
        }

        $needle = Str::of($trimmed)->lower();

        foreach (self::RULES as [$type, $keywords]) {
            foreach ($keywords as $keyword) {
                if ($needle->contains($keyword)) {
                    return $type;
                }
            }
        }

        // "Other" is a deliberate escape hatch, not a failed classification.
        if ($needle->is('other') || $needle->contains('other')) {
            return DocumentRequest::TYPE_OTHER;
        }

        return null;
    }

    public function isKnown(?string $type): bool
    {
        return $type !== null && in_array($type, DocumentRequest::TYPES, true);
    }

    /**
     * Whether the certificate template can produce this document unaided.
     */
    public function canAutoGenerate(?string $type): bool
    {
        return $type !== null && in_array($type, DocumentRequest::AUTO_GENERATABLE_TYPES, true);
    }

    public function slugFor(?string $type): string
    {
        if ($type === null) {
            return 'unrecognised';
        }

        return DocumentRequest::TYPE_SLUGS[$type] ?? 'unrecognised';
    }

    /**
     * The triage record for one request.
     *
     * `needs_human` is the field an administrator works from. `reason` says why
     * in words, so the queue explains itself rather than presenting a flag.
     *
     * @return array<string, mixed>
     */
    public function triage(DocumentRequest $request): array
    {
        $stored = $request->type;
        $classified = $this->classify($stored);

        $known = $this->isKnown($classified);
        $auto = $this->canAutoGenerate($classified);

        if (! $known) {
            $reason = 'The requested document is not one the system can issue. '
                .'A member of staff needs to read the reason and respond directly.';
        } elseif (! $auto) {
            $reason = $classified === DocumentRequest::TYPE_RECOMMENDATION
                ? 'A recommendation letter has to be written and signed by someone who teaches the student.'
                : 'This request is unspecified, so a member of staff needs to establish what is needed.';
        } else {
            $reason = null;
        }

        return [
            'stored_type' => $stored,
            'type' => $classified,
            'slug' => $this->slugFor($classified),
            'label' => $classified,
            'classified' => $known,
            // Whether the stored text was already canonical, as opposed to
            // rescued by a keyword rule — useful when auditing legacy requests.
            'exact' => $known && strcasecmp((string) $classified, (string) $stored) === 0,
            'auto_generatable' => $auto,
            'needs_human' => ! $auto,
            'reason' => $reason,
        ];
    }

    /**
     * The catalogue the request form is built from, so the client offers exactly
     * what the server will accept and can say which options are instant.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(): array
    {
        return array_map(fn (string $type) => [
            'label' => $type,
            'slug' => $this->slugFor($type),
            'auto_generatable' => $this->canAutoGenerate($type),
            'note' => $this->canAutoGenerate($type)
                ? null
                : 'Prepared by a member of staff, so allow extra time.',
        ], DocumentRequest::TYPES);
    }
}
