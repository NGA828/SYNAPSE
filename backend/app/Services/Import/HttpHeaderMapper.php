<?php

namespace App\Services\Import;

use App\Contracts\ImportHeaderMapper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Provider-backed header mapping, for headers the rule table has never seen.
 *
 * The rule table already covers ordinary English and French exports; this exists
 * for the tail — "Nom & Prénoms de l'élève inscrits", "N° Tél. Parent", a column
 * heading in a language nobody listed. It is optional, and a failure falls back
 * to the rule table rather than failing the import.
 *
 * Two constraints keep it safe:
 *
 * - **Only the header row is sent.** No pupil data. A CSV of children's names and
 *   phone numbers never leaves the server to work out what a column is called.
 * - **A suggestion is never a decision.** The result is surfaced in the preview
 *   with `source: http` and the administrator confirms or corrects it before the
 *   existing import endpoint runs.
 */
class HttpHeaderMapper implements ImportHeaderMapper
{
    public function __construct(
        private readonly DeterministicHeaderMapper $fallback,
    ) {}

    public function name(): string
    {
        return 'http';
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $fields
     * @return array{mapping: array<string, string>, unmapped: list<string>, conflicts: array<string, list<string>>, confidence: array<string, string>}
     */
    public function map(array $headers, array $fields): array
    {
        if (! config('ai.enabled') || ! config('ai.key') || ! config('ai.model')) {
            return $this->fallback->map($headers, $fields);
        }

        try {
            $suggested = $this->complete($headers, $fields);
        } catch (Throwable $exception) {
            return $this->degrade($exception, $headers, $fields);
        }

        return $this->merge($headers, $fields, $suggested);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $fields
     * @return array<string, string>  field => header
     */
    private function complete(array $headers, array $fields): array
    {
        $response = Http::withToken((string) config('ai.key'))
            ->timeout((int) config('ai.timeout', 15))
            ->connectTimeout((int) config('ai.connection_timeout', 5))
            ->acceptJson()
            ->post(config('ai.base_url').'/chat/completions', [
                'model' => config('ai.model'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($fields)],
                    ['role' => 'user', 'content' => (string) json_encode([
                        'headers' => $headers,
                        'fields' => $fields,
                    ], JSON_UNESCAPED_UNICODE)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Provider returned HTTP '.$response->status());
        }

        $decoded = json_decode(trim((string) $response->json('choices.0.message.content', '')), true);

        if (! is_array($decoded) || ! isset($decoded['mapping']) || ! is_array($decoded['mapping'])) {
            throw new RuntimeException('Provider completion was not the expected JSON.');
        }

        $clean = [];

        foreach ($decoded['mapping'] as $field => $header) {
            if (is_string($field) && is_string($header)) {
                $clean[$field] = $header;
            }
        }

        return $clean;
    }

    /**
     * @param  list<string>  $fields
     */
    private function systemPrompt(array $fields): string
    {
        $list = implode(', ', $fields);

        return <<<PROMPT
        You map a spreadsheet header row onto canonical import fields.

        Reply with a JSON object of exactly one key, "mapping", whose value maps
        a canonical field to the exact header string that supplies it.

        Rules:
        - The canonical fields are: {$list}.
        - Use only the header strings you were given, copied exactly.
        - Omit any field you are not confident about. Guessing is worse than
          leaving it out: an unmapped column is shown to the administrator, a
          wrongly mapped one silently imports the wrong data.
        - Never map two headers to the same field.
        - The file may be in English or French.
        PROMPT;
    }

    /**
     * Trust the provider only where it stays inside the rails: known fields,
     * headers that actually exist, no field claimed twice.
     *
     * @param  list<string>  $headers
     * @param  list<string>  $fields
     * @param  array<string, string>  $suggested
     * @return array{mapping: array<string, string>, unmapped: list<string>, conflicts: array<string, list<string>>, confidence: array<string, string>}
     */
    private function merge(array $headers, array $fields, array $suggested): array
    {
        $base = $this->fallback->map($headers, $fields);

        $mapping = $base['mapping'];
        $confidence = $base['confidence'];
        $conflicts = $base['conflicts'];

        foreach ($suggested as $field => $header) {
            if (! in_array($field, $fields, true) || ! in_array($header, $headers, true)) {
                continue;
            }

            // A rule-table exact match already won; do not let the model
            // overrule something we matched on purpose.
            if (isset($mapping[$field])) {
                if ($confidence[$field] === 'exact' || $mapping[$field] === $header) {
                    continue;
                }

                $conflicts[$field][] = $header;

                continue;
            }

            if (in_array($header, $mapping, true)) {
                $conflicts[$field][] = $header;

                continue;
            }

            $mapping[$field] = $header;
            $confidence[$field] = 'suggested';
        }

        $unmapped = array_values(array_filter(
            $headers,
            fn (string $header) => ! in_array($header, $mapping, true),
        ));

        return [
            'mapping' => $mapping,
            'unmapped' => $unmapped,
            'conflicts' => $conflicts,
            'confidence' => $confidence,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $fields
     * @return array{mapping: array<string, string>, unmapped: list<string>, conflicts: array<string, list<string>>, confidence: array<string, string>}
     */
    private function degrade(Throwable $exception, array $headers, array $fields): array
    {
        if (! config('ai.fallback_on_error', true)) {
            throw $exception;
        }

        Log::warning('CSV header mapping fell back to the rule table.', [
            'reason' => $exception->getMessage(),
        ]);

        return $this->fallback->map($headers, $fields);
    }
}
