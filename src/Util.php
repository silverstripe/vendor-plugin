<?php

namespace SilverStripe\VendorPlugin;

class Util
{
    /**
     * Join paths
     *
     * @param array ...$parts
     * @return string
     */
    public static function joinPaths(...$parts)
    {
        $combined = null;
        $parts = array_filter($parts ?? []);
        array_walk_recursive($parts, function ($part) use (&$combined) {
            // Normalise path
            $part = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part ?? '');
            $combined = $combined
                ? (rtrim($combined, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $part)
                : $part;
        });
        return $combined;
    }
}
