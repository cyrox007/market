<?php

namespace App\Services\Product;

use App\Models\Product\Manufacturer;
use App\Models\Product\Product;
use Illuminate\Support\Facades\Cache;

final class CatalogSearchCorrection
{
    private const ENGLISH = "qwertyuiop[]asdfghjkl;'zxcvbnm,.\x60";
    private const RUSSIAN = 'йцукенгшщзхъфывапролджэячсмитьбюё';
    private const MAX_PROBES = 8;

    /**
     * @return iterable<list<string>> Candidate groups with equal priority.
     */
    public function candidates(string $query): iterable
    {
        $words = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY);
        if (! $words || count($words) > 6 || mb_strlen($query) > 120) {
            return;
        }

        $layout = [];
        foreach ($words as $index => $word) {
            $alternative = $this->switchLayout($word);
            if ($alternative !== null) {
                $layout[$index] = $alternative;
            }
        }

        $layouts = [$words];
        foreach ($layout as $index => $alternative) {
            foreach ($layouts as $variant) {
                $variant[$index] = $alternative;
                $layouts[] = $variant;
            }
        }

        if ($layout !== []) {
            $choices = array_slice($layouts, 1, self::MAX_PROBES - 1);
            $choices[] = $layouts[array_key_last($layouts)];
            yield array_values(array_unique(array_map(fn (array $variant) => implode(' ', $variant), $choices)));
        }

        $dictionary = null;
        $corrections = [];
        $nearby = [];
        foreach ($layouts as $variant) {
            foreach ($variant as $index => $word) {
                $lower = mb_strtolower($word);
                $alphabet = match (true) {
                    preg_match('/^[а-яё]{5,32}$/uD', $lower) === 1 => self::RUSSIAN,
                    preg_match('/^[a-z]{5,32}$/D', $lower) === 1 => 'abcdefghijklmnopqrstuvwxyz',
                    default => null,
                };
                if ($alphabet === null) {
                    continue;
                }

                $dictionary ??= $this->dictionary();
                if (isset($dictionary[$lower])) {
                    continue;
                }

                $nearby[$lower] ??= array_intersect_key($dictionary, array_fill_keys(
                    iterator_to_array($this->oneEditWords($lower, $alphabet), false),
                    true,
                ));

                foreach ($nearby[$lower] as $candidate => $frequency) {
                    $corrected = $variant;
                    $corrected[$index] = $candidate;
                    $corrections[implode(' ', $corrected)] = $frequency;
                }
            }
        }

        arsort($corrections);
        if ($corrections !== []) {
            yield array_slice(array_keys($corrections), 0, self::MAX_PROBES);
        }
    }

    private function switchLayout(string $word): ?string
    {
        $word = mb_strtolower($word);
        if (preg_match('/^[а-яё]{2,32}$/uD', $word)) {
            return strtr($word, array_combine(mb_str_split(self::RUSSIAN), str_split(self::ENGLISH)));
        }
        if (preg_match("/^[a-z\\[\\];',.\x60]{2,32}$/D", $word) && preg_match('/[a-z].*[a-z]/', $word)) {
            return strtr($word, array_combine(str_split(self::ENGLISH), mb_str_split(self::RUSSIAN)));
        }

        return null;
    }

    /** @return iterable<string> */
    private function oneEditWords(string $word, string $alphabet): iterable
    {
        $letters = mb_str_split($word);
        $alphabet = mb_str_split($alphabet);
        $length = count($letters);
        for ($index = 0; $index <= $length; $index++) {
            $prefix = implode('', array_slice($letters, 0, $index));
            $suffix = implode('', array_slice($letters, $index));
            $tail = implode('', array_slice($letters, $index + 1));

            if ($index < $length) {
                yield $prefix.$tail;
            }
            if ($index + 1 < $length) {
                yield $prefix.$letters[$index + 1].$letters[$index].implode('', array_slice($letters, $index + 2));
            }

            foreach ($alphabet as $letter) {
                yield $prefix.$letter.$suffix;
                if ($index < $length && $letter !== $letters[$index]) {
                    yield $prefix.$letter.$tail;
                }
            }
        }
    }

    /** @return array<string, int> */
    private function dictionary(): array
    {
        return Cache::remember('catalog-search-correction-dictionary-v1', now()->addMinutes(15), function (): array {
            $counts = [];
            $names = Product::query()->select('id', 'name')->active()->lazyById(500);
            foreach ($names->concat(Manufacturer::query()->select('id', 'name')->lazyById(500)) as $record) {
                preg_match_all('/[а-яё]{4,33}|[a-z]{4,33}/u', mb_strtolower($record->name), $matches);
                foreach (array_unique($matches[0]) as $word) {
                    $counts[$word] = ($counts[$word] ?? 0) + 1;
                }
            }

            return $counts;
        });
    }
}
