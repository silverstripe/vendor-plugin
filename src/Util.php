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
        array_walk_recursive($parts, function ($part) use (&$combined) {
            $combined = $combined
                ? (rtrim($combined, '\\/') . DIRECTORY_SEPARATOR . $part)
                : $part;
        });
        return $combined;
    }
}
