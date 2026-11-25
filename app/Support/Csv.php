<?php

declare(strict_types=1);

namespace App\Support;

/*
 * CSV lines for exports. Cells that a spreadsheet would run as a formula (starting with
 * =, +, -, @, tab or carriage return) get a leading apostrophe, so an exported name
 * like "=HYPERLINK(...)" stays plain text.
 */
final class Csv
{
    /**
     * One CSV line, ending in CRLF.
     * @param list<string|int> $cells
     * @return string
     */
    public static function line(array $cells): string
    {
        return implode(',', array_map(self::cell(...), $cells)) . "\r\n";
    }

    /**
     * One quoted cell.
     * @return string
     */
    private static function cell(string|int $value): string
    {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'" . $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}
