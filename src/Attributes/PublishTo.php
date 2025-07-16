<?php

declare(strict_types=1);

namespace Shavonn\GooglePubSub\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
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
