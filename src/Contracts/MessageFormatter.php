<?php

namespace Shavonn\GooglePubSub\Contracts;

interface MessageFormatter
{
    /**
     * Format data for publishing.
     *
     * @param  mixed  $data
     */
    public function format($data): string;

    /**
     * Parse data from a message.
     *
     * @return mixed
     */
    public function parse(string $data);
}
