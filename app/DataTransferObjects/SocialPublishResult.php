<?php

namespace App\DataTransferObjects;

class SocialPublishResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $platformPostId = null,
        public readonly ?string $error = null,
    ) {}
}
