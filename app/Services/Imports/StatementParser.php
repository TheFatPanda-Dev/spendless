<?php

namespace App\Services\Imports;

use RuntimeException;

/**
 * Detects the file type and returns a normalised statement.
 */
final class StatementParser
{
    /**
     * @param  array<string, mixed>|null  $mapping  Column mapping for unrecognised CSV files.
     */
    public static function parse(string $path, ?array $mapping = null): ParsedStatement
    {
        if (! is_file($path)) {
            throw new RuntimeException('The uploaded file is no longer available. Please upload it again.');
        }

        $head = (string) file_get_contents($path, length: 4);

        if (! str_starts_with($head, "PK\x03\x04")) {
            $text = TabularFileReader::text($path);

            if (OfxParser::looksLikeOfx($text)) {
                return OfxParser::parse($text);
            }

            if (SantanderTextParser::looksLikeSantanderText($text)) {
                return SantanderTextParser::parse($text);
            }

            if (stripos($text, '!Type:') !== false) {
                throw new RuntimeException('QIF files are not supported. Please download OFX ("Microsoft Money") or CSV instead.');
            }

            return CsvLayouts::parse(TabularFileReader::readDelimited($text), $mapping);
        }

        return CsvLayouts::parse(TabularFileReader::read($path), $mapping);
    }
}
