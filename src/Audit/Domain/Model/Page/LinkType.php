<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

enum LinkType: string
{
    case ANCHOR = 'anchor';
    case IMAGE = 'image';
    case SCRIPT = 'script';
    case STYLESHEET = 'stylesheet';
    case IFRAME = 'iframe';
    case CANONICAL = 'canonical';
    case ALTERNATE = 'alternate';
    case PRELOAD = 'preload';
    case MODULEPRELOAD = 'modulepreload';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case FONT = 'font';
    case PREFETCH = 'prefetch';

    public function isResource(): bool
    {
        return match ($this) {
            self::IMAGE,
            self::SCRIPT,
            self::STYLESHEET,
            self::PRELOAD,
            self::MODULEPRELOAD,
            self::VIDEO,
            self::AUDIO,
            self::FONT,
            self::PREFETCH => true,
            default => false,
        };
    }
}
