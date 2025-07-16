<?php

namespace Shavonn\GooglePubSub\Contracts;

interface ShouldPublishToPubSub
{
    /**
     * Get the Pub/Sub topic for this event.
     */
    public function pubsubTopic(): string;

    /**
     * Convert the event to Pub/Sub data format.
     */
    public function toPubSub(): array;
}
