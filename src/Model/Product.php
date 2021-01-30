<?php

namespace Sokil\Merchant\ProductFeed\Model;

use Sokil\Merchant\ProductFeed\Model\ProductField\Availability;
use Sokil\Merchant\ProductFeed\Model\ProductField\Condition;
use Sokil\Merchant\ProductFeed\Model\ProductField\DateTimeRange;
use Sokil\Merchant\ProductFeed\Model\ProductField\Price;
use Sokil\Merchant\ProductFeed\Model\ProductField\Url;

/**
 * Element of feed
 */
class Product
{
    /**
     * Unique ID for item. Can be a variant for a product. Use the SKU if you can.
     *
     * For Facebook must be less or equal 100 chars
     *
     * @var string
     */
    private $id;

    /**
     * A specific, relevant title for the item
     *
     * For Facebook must be less or equal 150 chars
     *
     * @var string
     */
    private $title;

    /**
     * A short, relevant description of the item. Include specific or unique product features, such as like material or color.
     *
     * For Facebook must be less or equal 5000 chars
     *
     * @link https://www.facebook.com/business/help/2302017289821154?id=663946777378466 Facebook requirements
     *
     * @var string
     */
    private $description;

    /**
     * Current availability of the item in your store
     *
     * @var Availability
     */
    private $availability;

    /**
     * Condition of the item in your store
     *
     * @var Condition
     */
    private $condition;

    /**
     * Current price of the item
     *
     * @var Price
     */
    private $price;

    /**
     * Optional. Required if sale.
     * Discounted price and currency of the item, if the item is on sale.
     *
     * @var Price|null
     */
    private $salePrice;

    /**
     * URL of the specific product page where people can buy the item.
     *
     * @var Url
     */
    private $link;

    /**
     * URL for the main image of your item
     *
     * @link https://www.facebook.com/business/help/686259348512056 Facebook recommendations
     *
     * @var Url
     */
    private $imageLink;

    /**
     * Brand name, unique manufacturer part number (MPN), or Global Trade Item Number (GTIN) of the item.
     *
     * @var string
     */
    private $brand;

    /**
     * @var string|int|null
     */
    private $facebookProductCategory;

    /**
     * @var string|int|null
     */
    private $googleProductCategory;

    /**
     * @var string|null
     */
    private $internalProductCategory;

    /**
     * @var int|null
     */
    private $inventory;

    /**
     * @var DateTimeRange|null
     */
    private $salePriceEffectiveDate;

    /**
     * @param string $id
     * @param string $title
     * @param string $description
     * @param Availability $availability
     * @param Condition $condition
     * @param Price $price
     * @param Url $link
     * @param Url $imageLink
     * @param string $brand
     */
    public function __construct(
        string $id,
        string $title,
        string $description,
        Availability $availability,
        Condition $condition,
        Price $price,
        Url $link,
        Url $imageLink,
        string $brand
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
        $this->availability = $availability;
        $this->condition = $condition;
        $this->price = $price;
        $this->link = $link;
        $this->imageLink = $imageLink;
        $this->brand = $brand;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return Availability
     */
    public function getAvailability(): Availability
    {
        return $this->availability;
    }

    /**
     * @return Condition
     */
    public function getCondition(): Condition
    {
        return $this->condition;
    }

    /**
     * @return Price
     */
    public function getPrice(): Price
    {
        return $this->price;
    }

    /**
     * @return Url
     */
    public function getLink(): Url
    {
        return $this->link;
    }

    /**
     * @return Url
     */
    public function getImageLink(): Url
    {
        return $this->imageLink;
    }

    /**
     * @return string
     */
    public function getBrand(): string
    {
        return $this->brand;
    }

    /**
     * @return int|string|null
     */
    public function getFacebookProductCategory()
    {
        return $this->facebookProductCategory;
    }

    /**
     * Optional, may be useful only for Facebook feed
     *
     * @link https://developers.facebook.com/docs/commerce-platform/catalog/categories/#fb-prod-cat
     *
     * The Facebook product category represents the item according to the Facebook product taxonomy.
     * This taxonomy organizes products for sale into categories and subcategories.
     * For example, Health & Beauty > Beauty > Makeup > Eye Makeup > Mascara.
     *
     * To provide a Facebook product category for your items, add the fb_product_category field in your data feed file.
     * In this field, enter a supported category from the list below. Facebook product categories are available in
     * multiple languages.
     *
     * List of categories in your language below:
     * @link https://www.facebook.com/products/categories/en_US.txt
     * @link https://www.facebook.com/products/categories/en_US
     *
     * For each category, you can provide either the taxonomy path (such as Health & Beauty > Beauty > Makeup > Eye Makeup > Mascara) or the category ID number (such as 281). Category names are not case sensitive.
     * When you provide a Facebook product category, you can also use additional fields specific to that category to provide more detailed information about your items.
     *
     * @param int|string $facebookProductCategory
     */
    public function setFacebookProductCategory($facebookProductCategory): void
    {
        if (($facebookProductCategory) || !is_int($facebookProductCategory) || !is_string($facebookProductCategory)) {
            throw new \InvalidArgumentException('Facebook product category must me taxonomy path or int');
        }

        $this->facebookProductCategory = $facebookProductCategory;
    }

    /**
     * @return int|string|null
     */
    public function getGoogleProductCategory()
    {
        return $this->googleProductCategory;
    }

    /**
     * Optional, may be used both for Facebook and Google product feeds.
     *
     * For Facebook:
     *      Optional for Instagram Shopping and Page Shops, but required to enable onsite checkout on these
     *      channels (U.S. only). Required for Marketplace (U.S. only).
     *
     *      The Google product category (GPC) (google_product_category) represents the item according to the
     *      Google's product taxonomy. Use the category's taxonomy path or its category ID number:
     *      @link https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt
     *
     * For Google:
     *      ...
     *
     * Example: Apparel & Accessories > Clothing > Shirts & Tops or 212
     *
     * @param int|string $googleProductCategory
     */
    public function setGoogleProductCategory($googleProductCategory): void
    {
        if (($googleProductCategory) || !is_int($googleProductCategory) || !is_string($googleProductCategory)) {
            throw new \InvalidArgumentException('Google product category must me taxonomy path or int');
        }

        $this->googleProductCategory = $googleProductCategory;
    }

    /**
     * @return string|null
     */
    public function getInternalProductCategory(): ?string
    {
        return $this->internalProductCategory;
    }

    /**
     * Category the item belongs to, according to your business's product categorization system, if you have one.
     * You can also enter a Google product category. For commerce, represents the product category in your
     *
     * @link https://developers.facebook.com/docs/marketing-api/catalog/guides/product-categories
     *
     * Example: Home & Garden > Kitchen & Dining > Appliances > Refrigerators
     *
     * @param string|null $internalProductCategory
     */
    public function setInternalProductCategory(?string $internalProductCategory): void
    {
        $this->internalProductCategory = $internalProductCategory;
    }

    /**
     * @return int|null
     */
    public function getInventory(): ?int
    {
        return $this->inventory;
    }

    /**
     * Optional.
     *
     * Quantity of an item in your inventory. People can't buy this item unless the inventory is 1 or higher.
     *
     * For Facebook:
     *      Required for Instagram Shopping with checkout, Page Shops, and Marketplace.
     *      Optional for Instagram Shopping with product tagging only.
     *
     * @param int $inventory
     */
    public function setInventory(int $inventory): void
    {
        if ($inventory < 1) {
            throw new \OutOfBoundsException('Must be positive');
        }

        $this->inventory = $inventory;
    }

    /**
     * @return Price|null
     */
    public function getSalePrice(): ?Price
    {
        return $this->salePrice;
    }

    /**
     * Optional. Required if sale.
     * Discounted price and currency of the item, if the item is on sale.
     *
     * @param Price $salePrice
     */
    public function setSalePrice(Price $salePrice): void
    {
        $this->salePrice = $salePrice;
    }

    /**
     * @return DateTimeRange|null
     */
    public function getSalePriceEffectiveDate(): ?DateTimeRange
    {
        return $this->salePriceEffectiveDate;
    }

    /**
     * Optional. Required if checkout.
     *
     * Time range for your sale period, including the date, time, and time zone when your sale starts and ends.
     * If you don't enter sale dates, any items with a {@see $salePrice} remains on sale until
     * you remove their sale price.
     *
     * @param DateTimeRange|null $salePriceEffectiveDate
     */
    public function setSalePriceEffectiveDate(?DateTimeRange $salePriceEffectiveDate): void
    {
        $this->salePriceEffectiveDate = $salePriceEffectiveDate;
    }
}