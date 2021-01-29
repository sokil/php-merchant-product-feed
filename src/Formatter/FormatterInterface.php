<?php

namespace Sokil\Merchant\ProductFeed\Formatter;

use Sokil\Merchant\ProductFeed\Feed;

interface FormatterInterface
{
    public function format(Feed $feed): string;
}