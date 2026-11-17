<?php

namespace App\Services\Imports;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads CSV/TSV files (any common encoding and delimiter) and the first sheet of an
 * .xlsx workbook into rows of trimmed strings.
 */
final class TabularFileReader
{
    private const MAX_ROWS = 20000;

    /**
     * @return list<list<string>>
     */
    public static function read(string $path): array
    {
        $head = (string) file_get_contents($path, length: 4);

        return str_starts_with($head, "PK\x03\x04") ? self::readXlsx($path) : self::readDelimited(self::text($path));
    }

    /**
     * File contents as UTF-8 without BOM and with "\n" line endings.
     */
    public static function text(string $path): string
    {
        $contents = (string) file_get_contents($path);

        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        } elseif (str_starts_with($contents, "\xFF\xFE") || str_starts_with($contents, "\xFE\xFF")) {
            $contents = (string) mb_convert_encoding($contents, 'UTF-8', 'UTF-16');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            // UK bank exports (e.g. Nationwide) are often Windows-1252, where "£" is 0xA3.
            $contents = (string) mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        return str_replace(["\r\n", "\r"], "\n", $contents);
    }

    /**
     * @return list<list<string>>
     */
    public static function readDelimited(string $text): array
    {
        $delimiter = self::detectDelimiter($text);
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Could not read the file.');
        }

        fwrite($stream, $text);
        rewind($stream);
        $rows = [];

        while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false && count($rows) < self::MAX_ROWS) {
            $cells = array_map(fn (mixed $cell): string => trim((string) $cell, " \t\u{00A0}"), $row);

            if (array_filter($cells, fn (string $cell): bool => $cell !== '') === []) {
                continue;
            }

            $rows[] = $cells;
        }

        fclose($stream);

        return $rows;
    }

    private static function detectDelimiter(string $text): string
    {
        $sample = implode("\n", array_slice(explode("\n", $text), 0, 30));
        $counts = [];

        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $counts[$delimiter] = substr_count($sample, $delimiter);
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @return list<list<string>>
     */
    private static function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Excel files need the PHP zip extension. Export as CSV instead.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('This Excel file could not be opened.');
        }

        $sharedStrings = [];
        $stringsXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($stringsXml !== false) {
            $strings = self::xml($stringsXml);

            foreach ($strings->si as $item) {
                $text = (string) $item->t;

                foreach ($item->r as $run) {
                    $text .= (string) $run->t;
                }

                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);

                if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                    $sheetXml = $zip->getFromName($name);

                    break;
                }
            }
        }

        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('This Excel file has no worksheet.');
        }

        $rows = [];

        foreach (self::xml($sheetXml)->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $column = self::columnIndex((string) $cell['r']);
                $type = (string) $cell['t'];
                $value = match ($type) {
                    's' => $sharedStrings[(int) $cell->v] ?? '',
                    'inlineStr' => (string) $cell->is->t,
                    default => (string) $cell->v,
                };

                $cells[$column] = trim($value);
            }

            if ($cells === [] || array_filter($cells, fn (string $cell): bool => $cell !== '') === []) {
                continue;
            }

            $width = max(array_keys($cells)) + 1;
            $rows[] = array_map(fn (int $column): string => $cells[$column] ?? '', range(0, $width - 1));

            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        return $rows;
    }

    private static function xml(string $xml): SimpleXMLElement
    {
        $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);

        if ($element === false) {
            throw new RuntimeException('This Excel file is damaged.');
        }

        return $element;
    }

    private static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/\d+/', '', $reference) ?? 'A';
        $index = 0;

        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }
}
