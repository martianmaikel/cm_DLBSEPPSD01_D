<?php

namespace App\Contracts;

use App\DataTransferObjects\SocialPublishResult;
use App\Models\SocialChannel;

interface SocialDriver
{
    /**
     * Publish a post to the social channel.
     *
     * @param  array<array{url: string, type: string}>|null  $media  Optional media items to attach
     */
    public function publish(SocialChannel $channel, string $text, ?string $link = null, ?array $media = null): SocialPublishResult;

    /**
     * Attempt to refresh the channel's access token.
     * Returns true if refreshed (or no refresh needed), false on failure.
     */
    public function refreshToken(SocialChannel $channel): bool;
}
