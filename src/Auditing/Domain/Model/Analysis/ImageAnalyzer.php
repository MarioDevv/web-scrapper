<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Link;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class ImageAnalyzer implements Analyzer
{
    private const int ALT_MAX_LENGTH = 125;

    public function analyze(PageSignals $signals, IssueCollector $issues): void
    {
        if (!$signals->isHtml()) {
            return;
        }

        $images = array_values(array_filter(
            $signals->links(),
            static fn (Link $link): bool => $link->type() === 'image',
        ));

        if ($images === []) {
            return;
        }

        $missingAlt = 0;
        $missingDimensions = [];

        foreach ($images as $image) {
            $alt = $image->anchorText();

            if ($alt === null || trim($alt) === '') {
                $missingAlt++;
            } elseif (mb_strlen($alt) > self::ALT_MAX_LENGTH) {
                $issues->add(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::CONTENT,
                    severity: IssueSeverity::INFO,
                    code: 'img_alt_too_long',
                    message: sprintf('Image alt is %d chars. Screen readers may truncate past ~%d; tighten for accessibility (not a direct SEO factor).', mb_strlen($alt), self::ALT_MAX_LENGTH),
                    context: $image->targetUrl(),
                ));
            }

            if (!$image->hasDimensions()) {
                $missingDimensions[] = $image;
            }
        }

        if ($missingAlt > 0) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::WARNING,
                code: 'img_alt_missing',
                message: sprintf('%d image(s) missing alt attribute.', $missingAlt),
            ));
        }

        if ($missingDimensions !== []) {
            $sample = array_map(
                static fn (Link $img): string => $img->targetUrl(),
                array_slice($missingDimensions, 0, 5),
            );
            $count = count($missingDimensions);

            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                code: 'image_missing_dimensions',
                message: sprintf('%d image(s) missing width/height attributes (causes layout shift, hurts CLS).', $count),
                context: implode(', ', $sample) . ($count > 5 ? sprintf(' (+%d more)', $count - 5) : ''),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::CONTENT;
    }
}
