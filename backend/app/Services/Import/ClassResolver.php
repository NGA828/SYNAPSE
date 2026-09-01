<?php

namespace App\Services\Import;

use App\Models\School;
use App\Models\SchoolClass;

/**
 * Turns a class label from a spreadsheet into a `class_id`.
 *
 * The import endpoint takes `class_id`, a numeric foreign key, which means an
 * administrator had to look up "Level 3A" in another screen before they could
 * import a single pupil. This resolves the label against the school's own class
 * list instead.
 *
 * It only ever matches within the given school, and it only ever claims a match
 * it is sure of. Two classes whose keys collide produce no result at all rather
 * than an arbitrary pick — importing a pupil into the wrong class is worse than
 * asking.
 */
class ClassResolver
{
    public function __construct(
        private readonly ValueNormaliser $normaliser,
    ) {}

    /**
     * @return array{class_id: int|null, matched: string|null, ambiguous: bool}
     */
    public function resolve(School $school, ?string $label): array
    {
        $none = ['class_id' => null, 'matched' => null, 'ambiguous' => false];

        $key = $this->normaliser->matchKey($label);

        if ($key === '') {
            return $none;
        }

        $candidates = SchoolClass::query()
            ->where('school_id', $school->id)
            ->get(['id', 'name'])
            ->map(fn (SchoolClass $class) => [
                'id' => $class->id,
                'name' => $class->name,
                'key' => $this->normaliser->matchKey($class->name),
            ]);

        $exact = $candidates->where('key', $key);

        if ($exact->count() === 1) {
            return [
                'class_id' => $exact->first()['id'],
                'matched' => $exact->first()['name'],
                'ambiguous' => false,
            ];
        }

        if ($exact->count() > 1) {
            return ['class_id' => null, 'matched' => null, 'ambiguous' => true];
        }

        // Fall back to containment: "Seconde A" should find a class literally
        // named "Seconde A" even with stray punctuation, but only if exactly one
        // class contains the label.
        $contained = $candidates->filter(
            fn (array $class) => $class['key'] !== ''
                && str_contains($class['key'], $key),
        );

        if ($contained->count() === 1) {
            $only = $contained->first();

            return [
                'class_id' => $only['id'],
                'matched' => $only['name'],
                'ambiguous' => false,
            ];
        }

        return $contained->count() > 1
            ? ['class_id' => null, 'matched' => null, 'ambiguous' => true]
            : $none;
    }

    /**
     * The school's classes, as {id, name}. Shown in the preview so an
     * administrator can see what a label could have matched.
     *
     * @return list<array{id: int, name: string}>
     */
    public function available(School $school): array
    {
        return SchoolClass::query()
            ->where('school_id', $school->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SchoolClass $class) => ['id' => $class->id, 'name' => $class->name])
            ->all();
    }
}
