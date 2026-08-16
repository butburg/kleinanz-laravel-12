<?php

namespace App\Services;

use Generator;
use RuntimeException;

class LegacyAdsSqlReader
{
    /**
     * @return Generator<int, list<string|null>>
     */
    public function rows(string $path, string $table): Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Could not open SQL export: {$path}");
        }

        $collecting = false;
        $statement = '';

        try {
            while (($line = fgets($handle)) !== false) {
                if (! $collecting) {
                    $needle = "INSERT INTO `{$table}`";

                    if (! str_contains($line, $needle)) {
                        continue;
                    }

                    $valuesPosition = stripos($line, 'VALUES');
                    $collecting = true;
                    $statement = $valuesPosition === false ? '' : substr($line, $valuesPosition + 6);
                } else {
                    $statement .= $line;
                }

                if (! str_ends_with(rtrim($statement), ';')) {
                    continue;
                }

                foreach ($this->parseValues($statement) as $row) {
                    yield $row;
                }

                $collecting = false;
                $statement = '';
            }
        } finally {
            fclose($handle);
        }

        if ($collecting) {
            throw new RuntimeException("Unterminated INSERT statement in {$path}");
        }
    }

    /**
     * @return list<list<string|null>>
     */
    private function parseValues(string $values): array
    {
        $rows = [];
        $length = strlen($values);
        $offset = 0;

        while ($offset < $length) {
            while ($offset < $length && ! in_array($values[$offset], ['(', ';'], true)) {
                $offset++;
            }

            if ($offset >= $length || $values[$offset] === ';') {
                break;
            }

            $offset++;
            $row = [];

            while (true) {
                while ($offset < $length && ctype_space($values[$offset])) {
                    $offset++;
                }

                [$value, $offset] = $this->parseValue($values, $offset);
                $row[] = $value;

                while ($offset < $length && ctype_space($values[$offset])) {
                    $offset++;
                }

                if ($offset >= $length) {
                    throw new RuntimeException('Unexpected end of SQL values.');
                }

                if ($values[$offset] === ')') {
                    $offset++;
                    break;
                }

                if ($values[$offset] !== ',') {
                    throw new RuntimeException('Expected a comma or closing parenthesis in SQL values.');
                }

                $offset++;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array{0: string|null, 1: int}
     */
    private function parseValue(string $values, int $offset): array
    {
        $length = strlen($values);

        if ($offset >= $length) {
            throw new RuntimeException('Unexpected end of SQL value.');
        }

        if ($values[$offset] === "'") {
            $offset++;
            $value = '';

            while ($offset < $length) {
                $character = $values[$offset++];

                if ($character === '\\') {
                    if ($offset >= $length) {
                        throw new RuntimeException('Invalid escape sequence in SQL string.');
                    }

                    $value .= $this->unescapeCharacter($values[$offset++]);

                    continue;
                }

                if ($character === "'") {
                    return [$value, $offset];
                }

                $value .= $character;
            }

            throw new RuntimeException('Unterminated SQL string.');
        }

        $start = $offset;

        while ($offset < $length && ! in_array($values[$offset], [',', ')', ';', "\n", "\r", "\t", ' '], true)) {
            $offset++;
        }

        $value = substr($values, $start, $offset - $start);

        return [strtoupper($value) === 'NULL' ? null : $value, $offset];
    }

    private function unescapeCharacter(string $character): string
    {
        return match ($character) {
            '0' => "\0",
            'b' => "\x08",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'Z' => "\x1a",
            default => $character,
        };
    }
}
