<?php

declare(strict_types=1);

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * HTML riche nettoyé à l'enregistrement avec HTMLPurifier (§12.1) : liste blanche de balises.
 * Deux profils : « basic » (membres : forum, précisions) et « rich » (professeurs, administration :
 * titres, images, blocs « verset »).
 */
final class Html
{
    /** @var array<string, HTMLPurifier> */
    private static array $purifiers = [];

    public static function purify(?string $html, string $profile = 'basic'): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim(self::purifier($profile)->purify($html));
    }

    /** Texte brut (saisie d'un membre) → paragraphes HTML sûrs, direction automatique conservée. */
    public static function fromPlainText(?string $text): string
    {
        $text = trim(str_replace("\r\n", "\n", (string) $text));
        if ($text === '') {
            return '';
        }
        $paragraphs = preg_split('/\n{2,}/', $text) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $html .= '<p>' . nl2br(e(trim($paragraph)), false) . '</p>';
        }

        return $html;
    }

    private static function purifier(string $profile): HTMLPurifier
    {
        if (!isset(self::$purifiers[$profile])) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Core.Encoding', 'UTF-8');
            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            $config->set('Cache.SerializerPath', self::cachePath());
            $config->set('AutoFormat.RemoveEmpty', true);
            $config->set('AutoFormat.AutoParagraph', false);
            $config->set('HTML.Nofollow', true);
            $config->set('HTML.TargetBlank', true);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('Attr.AllowedRel', ['nofollow', 'noopener']);
            $config->set('Attr.EnableID', false);
            if ($profile === 'rich') {
                $config->set('HTML.Allowed', 'p[dir|lang|class],br,strong,b,em,i,u,s,ul,ol,li,blockquote[dir|lang|class],a[href|title|rel|target],h2,h3,h4,span[dir|lang|class],img[src|alt|width|height],hr,sup,sub,pre,code');
                $config->set('Attr.AllowedClasses', ['df-ayah', 'df-hadith', 'ql-align-center', 'ql-align-right', 'ql-direction-rtl', 'ql-indent-1', 'ql-indent-2']);
            } else {
                $config->set('HTML.Allowed', 'p[dir|lang],br,strong,b,em,i,ul,ol,li,blockquote[dir|lang],a[href|rel|target],span[dir|lang]');
            }
            self::$purifiers[$profile] = new HTMLPurifier($config);
        }

        return self::$purifiers[$profile];
    }

    private static function cachePath(): ?string
    {
        $dir = storage_path('cache/htmlpurifier');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return is_writable($dir) ? $dir : null;
    }
}
