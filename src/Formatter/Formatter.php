<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Formatter;

use Sokil\Merchant\ProductFeed\Formatter\Strategy\ProductNormaliserInterface;
use Sokil\Merchant\ProductFeed\Model\Feed;

class Formatter
{
    /**
     * @var ProductNormaliserInterface[]
     */
    private $productNormalisers;

    /**
     * @var FeedEncoderInterface[]
     */
    private $feedEncoders;

    /**
     * @var ProductNormaliserInterface[]
     */
    private $locatedProductNormalisers = [];

    /**
     * @var FeedEncoderInterface[]
     */
    private $locatedFeedEncoders = [];

    public function __construct(
        array $productNormalisers,
        array $encoders
    ) {
        $this->productNormalisers = $productNormalisers;
        $this->feedEncoders = $encoders;
    }

    public function format(Feed $feed, string $marketingPlatform, string $format): string
    {
        $feedNormaliser = $this->locateProductNormaliser($marketingPlatform);
        $feedEncoder = $this->locateFeedEncoder($marketingPlatform, $format);

        return $feedEncoder->encode($feed, $feedNormaliser);
    }

    private function locateProductNormaliser(string $marketingPlatform): ProductNormaliserInterface
    {
        if (empty($this->locatedProductNormalisers[$marketingPlatform])) {
            foreach ($this->productNormalisers as $productNormaliser) {
                if ($productNormaliser->isSupported($marketingPlatform)) {
                    $this->locatedProductNormalisers[$marketingPlatform] = $productNormaliser;
                }
            }
        }

        if (empty($this->locatedProductNormalisers[$marketingPlatform])) {
            throw new \OutOfRangeException('Marketing platform not supported');
        }

        return $this->locatedProductNormalisers[$marketingPlatform];
    }

    private function locateFeedEncoder(string $marketingPlatform, string $format): FeedEncoderInterface
    {
        if (empty($this->locatedFeedEncoders[$marketingPlatform][$format])) {
            foreach ($this->feedEncoders as $feedEncoder) {
                if ($feedEncoder->isSupported($marketingPlatform, $format)) {
                    $this->locatedFeedEncoders[$marketingPlatform][$format] = $feedEncoder;
                }
            }
        }

        if (empty($this->locatedFeedEncoders[$marketingPlatform][$format])) {
            throw new \OutOfRangeException('Marketing platform not supported');
        }

        return $this->locatedFeedEncoders[$marketingPlatform][$format];
    }
}