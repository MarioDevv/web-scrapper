<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use SeoSpider\Audit\Domain\Model\Url;

final readonly class Hreflang
{
    private const array VALID_LANGUAGE_CODES = [
        'aa', 'ab', 'af', 'ak', 'am', 'an', 'ar', 'as', 'av', 'ay', 'az',
        'ba', 'be', 'bg', 'bh', 'bi', 'bm', 'bn', 'bo', 'br', 'bs',
        'ca', 'ce', 'ch', 'co', 'cr', 'cs', 'cu', 'cv', 'cy',
        'da', 'de', 'dv', 'dz',
        'ee', 'el', 'en', 'eo', 'es', 'et', 'eu',
        'fa', 'ff', 'fi', 'fj', 'fo', 'fr', 'fy',
        'ga', 'gd', 'gl', 'gn', 'gu', 'gv',
        'ha', 'he', 'hi', 'ho', 'hr', 'ht', 'hu', 'hy', 'hz',
        'ia', 'id', 'ie', 'ig', 'ii', 'ik', 'io', 'is', 'it', 'iu',
        'ja', 'jv',
        'ka', 'kg', 'ki', 'kj', 'kk', 'kl', 'km', 'kn', 'ko', 'kr', 'ks', 'ku', 'kv', 'kw', 'ky',
        'la', 'lb', 'lg', 'li', 'ln', 'lo', 'lt', 'lu', 'lv',
        'mg', 'mh', 'mi', 'mk', 'ml', 'mn', 'mr', 'ms', 'mt', 'my',
        'na', 'nb', 'nd', 'ne', 'ng', 'nl', 'nn', 'no', 'nr', 'nv', 'ny',
        'oc', 'oj', 'om', 'or', 'os',
        'pa', 'pi', 'pl', 'ps', 'pt',
        'qu',
        'rm', 'rn', 'ro', 'ru', 'rw',
        'sa', 'sc', 'sd', 'se', 'sg', 'si', 'sk', 'sl', 'sm', 'sn', 'so', 'sq', 'sr', 'ss', 'st', 'su', 'sv', 'sw',
        'ta', 'te', 'tg', 'th', 'ti', 'tk', 'tl', 'tn', 'to', 'tr', 'ts', 'tt', 'tw', 'ty',
        'ug', 'uk', 'ur', 'uz',
        've', 'vi', 'vo',
        'wa', 'wo',
        'xh',
        'yi', 'yo',
        'za', 'zh', 'zu',
    ];

    public function __construct(
        private string $language,
        private ?string $region,
        private Url $href,
        private HreflangSource $source,
    ) {
    }

    public function language(): string
    {
        return $this->language;
    }

    public function region(): ?string
    {
        return $this->region;
    }

    public function href(): Url
    {
        return $this->href;
    }

    public function source(): HreflangSource
    {
        return $this->source;
    }

    public function isXDefault(): bool
    {
        return strtolower($this->language) === 'x-default';
    }

    public function languageRegionCode(): string
    {
        if ($this->region !== null) {
            return $this->language . '-' . $this->region;
        }

        return $this->language;
    }

    public function isValidLanguageCode(): bool
    {
        if ($this->isXDefault()) {
            return true;
        }

        return in_array(strtolower($this->language), self::VALID_LANGUAGE_CODES, true);
    }

    public function isValidRegionCode(): bool
    {
        if ($this->region === null) {
            return true;
        }

        return preg_match('/^[A-Z]{2}$/i', $this->region) === 1;
    }

    public function pointsTo(Url $url): bool
    {
        return $this->href->equals($url);
    }
}
