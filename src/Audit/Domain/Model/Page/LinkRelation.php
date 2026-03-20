<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

enum LinkRelation: string
{
    case FOLLOW = 'follow';
    case NOFOLLOW = 'nofollow';
    case SPONSORED = 'sponsored';
    case UGC = 'ugc';
}
