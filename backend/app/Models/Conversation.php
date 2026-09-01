<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToSchool, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'participant_a_id',
        'participant_b_id',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function participantA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_a_id');
    }

    public function participantB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_b_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    /**
     * Conversations this user belongs to.
     *
     * @param  Builder<Conversation>  $query
     */
    public function scopeForParticipant(Builder $query, int $userId): Builder
    {
        return $query->where(
            fn (Builder $inner) => $inner
                ->where('participant_a_id', $userId)
                ->orWhere('participant_b_id', $userId),
        );
    }

    /**
     * The user at the other end, for the given participant.
     */
    public function otherParticipant(int $userId): ?User
    {
        // Compared as ints on purpose: some drivers hand back numeric columns
        // as strings, and a strict compare would quietly miss a participant.
        if ((int) $this->participant_a_id === $userId) {
            return $this->participantB;
        }

        if ((int) $this->participant_b_id === $userId) {
            return $this->participantA;
        }

        return null;
    }

    /**
     * The id of the user at the other end, without loading the relation.
     */
    public function otherParticipantId(int $userId): ?int
    {
        return match ($userId) {
            (int) $this->participant_a_id => $this->participant_b_id,
            (int) $this->participant_b_id => $this->participant_a_id,
            default => null,
        };
    }

    public function includes(int $userId): bool
    {
        return (int) $this->participant_a_id === $userId
            || (int) $this->participant_b_id === $userId;
    }
}
