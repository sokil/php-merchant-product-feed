<?php

namespace Sokil\Merchant\ProductFeed\Model;

/**
 * Collection of products
 */
class Feed implements \Countable, \Iterator
{
    /**
     * @var Product[]
     */
    private $products;

    /**
     * @param Product[] $products
     */
    public function __construct(array $products)
    {
        $this->products = $products;
    }

    public function current()
    {
        return current($this->products);
    }

    public function next()
    {
        next($this->products);
    }

    public function key()
    {
        return key($this->products);
    }

    public function valid()
    {
        return key($this->products) === null;
    }

    public function rewind()
    {
        reset($this->products);
    }

    public function count()
    {
        return count($this->products);
    }
}