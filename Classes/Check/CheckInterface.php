<?php

namespace Anexia\Neos\Monitoring\Check;

interface CheckInterface
{
    /**
     * Check if a specific function works correctly.
     * Return false for a generic error message, otherwise throw an exception with a custom message
     *
     * @return bool
     */
    public function run(): bool;
}
