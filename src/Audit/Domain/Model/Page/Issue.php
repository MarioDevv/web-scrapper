<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

final class Issue
{
    public function __construct(
        private readonly IssueId $id,
        private readonly IssueCategory $category,
        private readonly IssueSeverity $severity,
        private readonly string $code,
        private readonly string $message,
        private readonly ?string $context = null,
    ) {
    }

    public function id(): IssueId
    {
        return $this->id;
    }

    public function category(): IssueCategory
    {
        return $this->category;
    }

    public function severity(): IssueSeverity
    {
        return $this->severity;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function context(): ?string
    {
        return $this->context;
    }

    public function isError(): bool
    {
        return $this->severity === IssueSeverity::ERROR;
    }

    public function isWarning(): bool
    {
        return $this->severity === IssueSeverity::WARNING;
    }
}
