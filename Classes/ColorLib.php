<?php

namespace ouhouuhu\Classes;

class ColorLib
{
    /**
     * Converts an HSL color value to RGB. Conversion formula
     * adapted from http://en.wikipedia.org/wiki/HSL_color_space.
     * Assumes $h, $s, and $l are contained in the set [0, 1] and
     * returns $r, $g, and $b in the set [0, 255].
     *
     * @param float $h The hue
     * @param float $s The saturation
     * @param float $l The lightness
     * @return array   The RGB representation
     */
    public static function hsl2rgb($h, $s, $l): array
    {

        if ($s == 0) {
            $r = $g = $b = $l; // achromatic
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hue2rgb($p, $q, $h + 1 / 3);
            $g = self::hue2rgb($p, $q, $h);
            $b = self::hue2rgb($p, $q, $h - 1 / 3);
        }

        return [round($r * 255), round($g * 255), round($b * 255)];
    }

    private static function hue2rgb($p, $q, $t)
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    }

    /**
     * Converts an RGB color value to HSL. Conversion formula
     * adapted from http://en.wikipedia.org/wiki/HSL_color_space.
     * Assumes $r, $g, and $b are contained in the set [0, 255] and
     * returns $h, $s, and $l in the set [0, 1].
     *
     * @param int $r The red color value
     * @param int $g The green color value
     * @param int $b The blue color value
     * @return array The HSL representation
     */
    public static function rgb2hsl($r, $g, $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $h = ($max + $min) / 2;
        $s = ($max + $min) / 2;
        $l = ($max + $min) / 2;

        if ($max == $min) {
            $h = $s = 0; // achromatic
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r:
                    $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
                    break;
                case $g:
                    $h = ($b - $r) / $d + 2;
                    break;
                case $b:
                    $h = ($r - $g) / $d + 4;
                    break;
            }
            $h /= 6;
        }

        return [$h, $s, $l];
    }
}
