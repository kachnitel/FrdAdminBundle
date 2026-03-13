<?php

namespace Kachnitel\AdminBundle\Utils;

class Text
{
    /**
     * Humanize a snake_case or camelCase identifier into a naturally readable text.
     */
    public static function humanize(string $text): string
    {
        // Handle snake_case: replace underscores with spaces
        $text = str_replace('_', ' ', $text);

        return ucfirst(trim(strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $text))));
    }
}
