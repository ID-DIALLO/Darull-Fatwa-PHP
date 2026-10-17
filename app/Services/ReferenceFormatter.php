<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Références structurées d'une réponse (§4.2.4), stockées en JSON :
 * {"type":"quran","surah":2,"ayah_from":255,"ayah_to":255}, {"type":"hadith","collection":"bukhari","number":"1","grade":"sahih"},
 * {"type":"book","title":"…","author":"…","page":"…"}. Affichage normalisé dans la langue courante.
 */
final class ReferenceFormatter
{
    /** @param array<string, mixed> $ref */
    public static function format(array $ref): string
    {
        $type = (string) ($ref['type'] ?? 'book');
        switch ($type) {
            case 'quran':
                $surah = (int) ($ref['surah'] ?? 0);
                $name = Surahs::name($surah);
                $from = (int) ($ref['ayah_from'] ?? 0);
                $to = (int) ($ref['ayah_to'] ?? $from);
                $verses = $to > $from ? $from . '-' . $to : (string) $from;

                return __('references.quran', ['surah' => $name, 'number' => $surah, 'verses' => $verses]);
            case 'hadith':
                $collection = (string) ($ref['collection'] ?? 'other');
                $label = \App\Core\Lang::has('references.collections.' . $collection) ? __('references.collections.' . $collection) : (string) ($ref['collection_name'] ?? $collection);
                $text = __('references.hadith', ['collection' => $label, 'number' => (string) ($ref['number'] ?? '')]);
                if (!empty($ref['grade'])) {
                    $text .= ' (' . $ref['grade'] . ')';
                }

                return $text;
            default:
                $parts = array_filter([(string) ($ref['title'] ?? ''), (string) ($ref['author'] ?? ''), !empty($ref['page']) ? __('references.page', ['page' => $ref['page']]) : '']);

                return implode(', ', $parts);
        }
    }
}
