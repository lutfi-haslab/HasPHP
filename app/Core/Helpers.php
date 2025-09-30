<?php

/**
 * Helper functions for HasPHP Framework
 */

if (! function_exists('snake_case')) {
    /**
     * Convert a string to snake case.
     */
    function snake_case(string $value, string $delimiter = '_'): string
    {
        if (! ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1'.$delimiter, $value));
        }

        return $value;
    }
}

if (! function_exists('str_studly')) {
    /**
     * Convert a value to studly caps case.
     */
    function str_studly(string $value): string
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return str_replace(' ', '', $value);
    }
}

if (! function_exists('str_plural')) {
    /**
     * Get the plural form of an English word.
     */
    function str_plural(string $value, int $count = 2): string
    {
        if ($count == 1 || empty($value)) {
            return $value;
        }

        // Simple pluralization rules
        $plural = [
            '/(quiz)$/i'                => '\1zes',
            '/^(ox)$/i'                 => '\1en',
            '/([m|l])ouse$/i'           => '\1ice',
            '/(matr|vert|ind)ix|ex$/i'  => '\1ices',
            '/(x|ch|ss|sh)$/i'          => '\1es',
            '/([^aeiouy]|qu)y$/i'       => '\1ies',
            '/(hive)$/i'                => '\1s',
            '/(?:([^f])fe|([lr])f)$/i'  => '\1\2ves',
            '/(shea|lea|loa|thie)f$/i'  => '\1ves',
            '/sis$/i'                   => 'ses',
            '/([ti])um$/i'              => '\1a',
            '/(tomat|potat|ech|her|vet)o$/i' => '\1oes',
            '/(bu)s$/i'                 => '\1ses',
            '/(alias)$/i'               => '\1es',
            '/(octop)us$/i'             => '\1i',
            '/(ax|test)is$/i'           => '\1es',
            '/(us)$/i'                  => '\1es',
            '/s$/i'                     => 's',
            '/$/'                       => 's'
        ];

        foreach ($plural as $rule => $replacement) {
            if (preg_match($rule, $value)) {
                return preg_replace($rule, $replacement, $value);
            }
        }

        return $value;
    }
}

if (! function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     */
    function class_basename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if (! function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     */
    function tap($value, ?callable $callback = null)
    {
        if (is_null($callback)) {
            return $value;
        }

        $callback($value);

        return $value;
    }
}

if (! function_exists('collect')) {
    /**
     * Create a collection from the given value.
     */
    function collect($value = null): array
    {
        return is_array($value) ? $value : [$value];
    }
}

if (! function_exists('str_is')) {
    /**
     * Determine if a given string matches a given pattern.
     */
    function str_is(string|array $pattern, string $value): bool
    {
        $patterns = is_array($pattern) ? $pattern : [$pattern];

        if (empty($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            // If the given value is an exact match we can of course return true right
            // from the beginning. Otherwise, we will translate asterisks and do an
            // actual pattern match against the two strings to see if they match.
            if ($pattern == $value) {
                return true;
            }

            $pattern = preg_quote($pattern, '#');

            // Asterisks are translated into zero-or-more regular expression wildcards
            // to make it convenient to check if the strings starts with the given
            // pattern such as "library/*", making any string check convenient.
            $pattern = str_replace('\*', '.*', $pattern);

            if (preg_match('#^'.$pattern.'$#u', $value) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('str_starts_with')) {
    /**
     * Determine if a given string starts with a given substring.
     */
    function str_starts_with(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (! function_exists('str_ends_with')) {
    /**
     * Determine if a given string ends with a given substring.
     */
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
    }
}

if (! function_exists('value')) {
    /**
     * Return the default value of the given value.
     */
    function value($value, ...$args)
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}

if (! function_exists('with')) {
    /**
     * Return the given value, optionally passed through the given callback.
     */
    function with($value, ?callable $callback = null)
    {
        return is_null($callback) ? $value : $callback($value);
    }
}
