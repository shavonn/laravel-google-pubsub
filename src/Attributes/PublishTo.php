<?php

namespace Shavonn\GooglePubSub\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class PublishTo
{
    /**
     * Create a new PublishTo attribute.
     */
    public function __construct(
        public string $topic,
        public array $attributes = []
    ) {
    }
}
