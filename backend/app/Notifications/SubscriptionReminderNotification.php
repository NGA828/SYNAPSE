<?php

namespace App\Notifications;

use App\Models\School;

/**
 * Trial/renewal dunning: warns a school admin before expiry and once after.
 */
class SubscriptionReminderNotification extends SynapseNotification
{
    public const STAGE_TRIAL_ENDING = 'trial_ending';

    public const STAGE_EXPIRING = 'expiring';

    public const STAGE_EXPIRED = 'expired';

    public function __construct(
        public readonly School $school,
        public readonly string $stage,
        public readonly int $daysLeft = 0,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'subscription_'.$this->stage;
    }

    public function title(mixed $notifiable): string
    {
        return match ($this->stage) {
            self::STAGE_TRIAL_ENDING => 'Your free trial ends in '.$this->daysLeft.' day(s)',
            self::STAGE_EXPIRING => 'Your subscription expires in '.$this->daysLeft.' day(s)',
            default => 'Your subscription has expired',
        };
    }

    public function body(mixed $notifiable): string
    {
        return match ($this->stage) {
            self::STAGE_TRIAL_ENDING => "The free trial for {$this->school->name} ends in {$this->daysLeft} day(s). "
                .'Choose a plan to keep grades, report cards and documents available to your staff and students.',
            self::STAGE_EXPIRING => "The SYNAPSE subscription for {$this->school->name} expires in {$this->daysLeft} day(s). "
                .'Renew now to avoid any interruption.',
            default => "The SYNAPSE subscription for {$this->school->name} has expired. "
                .'Academic features are locked until the subscription is renewed. Your data is safe.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return ['school_id' => $this->school->id, 'stage' => $this->stage, 'days_left' => $this->daysLeft];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/admin/billing');
    }

    public function actionLabel(): string
    {
        return 'Manage billing';
    }

    /**
     * @return array<int, string>
     */
    public function channels(): array
    {
        return $this->stage === self::STAGE_EXPIRED
            ? ['bell', 'mail', 'sms']
            : ['bell', 'mail'];
    }
}
