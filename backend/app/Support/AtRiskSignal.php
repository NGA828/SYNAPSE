<?php

namespace App\Support;

/**
 * One reason a student is on the pastoral register.
 *
 * Deliberately immutable and deliberately wordy: the whole point of the
 * register is that a form teacher can read `detail` and know what to do, which
 * a numeric risk score does not tell them.
 */
final readonly class AtRiskSignal
{
    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    /**
     * @param  string  $code  Stable machine key, so a client can order or hide signals.
     * @param  string  $label  Short human name for the signal.
     * @param  string  $detail  The sentence a teacher actually reads.
     */
    private function __construct(
        public string $code,
        public string $label,
        public string $severity,
        public string $detail,
    ) {}

    public static function warning(string $code, string $label, string $detail): self
    {
        return new self($code, $label, self::SEVERITY_WARNING, $detail);
    }

    public static function critical(string $code, string $label, string $detail): self
    {
        return new self($code, $label, self::SEVERITY_CRITICAL, $detail);
    }

    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * @return array{code: string, label: string, severity: string, detail: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'severity' => $this->severity,
            'detail' => $this->detail,
        ];
    }
}
