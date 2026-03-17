<?php

declare(strict_types=1);

namespace News\Skill\Exception;

use RuntimeException;

final class InfrastructureException extends RuntimeException implements InfrastructureExceptionInterface
{
    public static function rssFetchFailed(string $url, string $reason): self
    {
        return new self(sprintf('Failed to fetch RSS from %s: %s', $url, $reason));
    }

    public static function rssParseFailed(string $url, string $reason): self
    {
        return new self(sprintf('Failed to parse RSS from %s: %s', $url, $reason));
    }
}
