<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class SecurityHeaderAnalyzer implements Analyzer
{
    private const array REQUIRED_HEADERS = [
        'csp_missing' => [
            'header' => 'Content-Security-Policy',
            'message' => 'Missing Content-Security-Policy header (XSS protection).',
        ],
        'x_frame_missing' => [
            'header' => 'X-Frame-Options',
            'message' => 'Missing X-Frame-Options header (clickjacking protection).',
        ],
        'x_content_type_missing' => [
            'header' => 'X-Content-Type-Options',
            'message' => 'Missing X-Content-Type-Options header (prevents MIME sniffing).',
        ],
    ];

    private const array SECURE_REFERRER_POLICIES = [
        'no-referrer',
        'no-referrer-when-downgrade',
        'strict-origin',
        'strict-origin-when-cross-origin',
    ];

    public function analyze(PageSignals $signals, IssueCollector $issues): void
    {
        if (!$signals->isHtml() || !$signals->response()->statusCode()->isSuccessful()) {
            return;
        }

        foreach (self::REQUIRED_HEADERS as $code => $config) {
            $value = $signals->response()->header($config['header']);
            if ($value === null || trim($value) === '') {
                $issues->add(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::SECURITY,
                    severity: IssueSeverity::NOTICE,
                    code: $code,
                    message: $config['message'],
                ));
            }
        }

        $this->checkReferrerPolicy($signals, $issues);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::SECURITY;
    }

    private function checkReferrerPolicy(PageSignals $signals, IssueCollector $issues): void
    {
        $value = $signals->response()->header('Referrer-Policy');

        if ($value === null || trim($value) === '') {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::SECURITY,
                severity: IssueSeverity::NOTICE,
                code: 'referrer_policy_missing',
                message: 'Missing Referrer-Policy header (navigation data protection).',
            ));
            return;
        }

        $policy = strtolower(trim($value));
        if (!in_array($policy, self::SECURE_REFERRER_POLICIES, true)) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::SECURITY,
                severity: IssueSeverity::NOTICE,
                code: 'referrer_policy_insecure',
                message: sprintf('Referrer-Policy "%s" may leak data across origins.', $policy),
                context: 'Recommended: strict-origin-when-cross-origin',
            ));
        }
    }
}
