<?php

namespace App\Services\Meta;

use RuntimeException;

/** A Graph API error, classified so callers can say something useful. */
class MetaApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?int $metaCode = null,
        public readonly ?int $subcode = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(int $status, ?array $body): self
    {
        $e = $body['error'] ?? [];
        $msg = $e['error_user_msg'] ?? $e['message'] ?? "Meta API error (HTTP {$status})";

        return new self($msg, $status, isset($e['code']) ? (int) $e['code'] : null, isset($e['error_subcode']) ? (int) $e['error_subcode'] : null);
    }

    /** token_expired | permission | rate_limit | invalid_parameter | other */
    public function kind(): string
    {
        return match (true) {
            $this->metaCode === 190 => 'token_expired',                       // invalid/expired token
            in_array($this->metaCode, [10, 200, 283], true) || ($this->metaCode >= 200 && $this->metaCode < 300) => 'permission',
            in_array($this->metaCode, [4, 17, 32, 613, 80000, 80001, 80002, 80003, 80004, 80005, 80006, 80008, 80009, 80014], true) => 'rate_limit',
            $this->metaCode === 100 => 'invalid_parameter',                   // also "invalid metric"
            default => 'other',
        };
    }

    public function hint(): string
    {
        return match ($this->kind()) {
            'token_expired' => 'The Meta access token is invalid or expired. Create a new (long-lived or System User) token and update META_ACCESS_TOKEN.',
            'permission' => 'The token is missing a permission this needs. Open the Meta Connection page to see which.',
            'rate_limit' => 'Meta is rate limiting us. This will be retried on the next run.',
            default => '',
        };
    }
}
