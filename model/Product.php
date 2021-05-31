<?php

class ProductException extends Exception
{
}

class Product
{
    private $_id;
    private $_name;
    private $_state;
    private $_description;
    private $_images;
    private $_category;
    private $_featured;
    private $_orderdate;
    private $_release_date;
    private $_season;
    private $_wholesaleprice;
    private $_msrp;
    private $_price;
    private $_zinprice;
    private $_price_discount;
    private $_tags;
    private $_notes;
    private $_weight;
    private $_composition;
    private $_manufacturer;
    private $_country;

    private $_vid;
    private $_upc;
    private $_size;
    private $_color;
    private $_stock;

    public function __construct($userId, $row)
    {
        $this->setID(isset($row['id']) ? $row['id'] : null);
        $this->setName($row['name']);
        $this->setState(isset($row['state']) ? $row['state'] : 1);
        $this->setDescription(isset($row['description']) ? $row['description'] : null);
        $this->setImages(isset($row['images']) ? $row['images'] : null);
        $this->setCategory(isset($row['category']) ? $row['category'] : 8);
        $this->setFeatured(isset($row['featured']) ? $row['featured'] : 0);
        $this->setOrderdate(isset($row['orderdate']) ? $row['orderdate'] : "0000-00-00");
        $this->setRelease_date(isset($row['release_date']) ? $row['release_date'] : "0000-00-00");
        $this->setSeason(isset($row['season']) ? $row['season'] : "");
        $this->setWholesaleprice(isset($row['wholesaleprice']) ? $row['wholesaleprice'] : 0, $userId);
        $this->setMsrp(isset($row['msrp']) ? $row['msrp'] : 0, $userId);
        $this->setPrice(isset($row['price']) ? $row['price'] : 0);
        $this->setZinprice(isset($row['zinprice']) ? $row['zinprice'] : 0);
        $this->setPriceDiscount(isset($row['price_discount']) ? $row['price_discount'] : 0);
        $this->setTags(isset($row['tags']) ? $row['tags'] : "");
        $this->setNotes(isset($row['notes']) ? $row['notes'] : "");
        $this->setWeight(isset($row['weight']) ? $row['weight'] : "0");
        $this->setComposition(isset($row['composition']) ? $row['composition'] : "");
        $this->setManufacturer(isset($row['manufacturer']) ? $row['manufacturer'] : "");
        $this->setCountry(isset($row['country']) ? $row['country'] : "");
        $this->setVid(isset($row['vid']) ? $row['vid'] : null);
        $this->setUpc(isset($row['upc']) ? $row['upc'] : 0);
        $this->setSize(isset($row['size']) ? $row['size'] : 0);
        $this->setColor(isset($row['color']) ? $row['color'] : 0);
        $this->setStock(isset($row['stock']) ? $row['stock'] : 1);
    }

    public function getID()
    {
        return $this->_id;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function getState()
    {
        return $this->_state;
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function getImages()
    {
        return $this->_images;
    }

    public function getCategory()
    {
        return $this->_category;
    }

    public function getFeatured()
    {
        return $this->_featured;
    }

    public function getOrderdate()
    {
        return $this->_orderdate;
    }

    public function getRelease_date()
    {
        return $this->_release_date;
    }

    public function getTags()
    {
        return $this->_tags;
    }

    public function getNotes()
    {
        return $this->_notes;
    }

    public function getSeason()
    {
        return $this->_season;
    }

    public function getWholesaleprice()
    {
        return $this->_wholesaleprice;
    }

    public function getMsrp()
    {
        return $this->_msrp;
    }

    public function getPrice()
    {
        return $this->_price;
    }

    public function getZinprice()
    {
        return $this->_zinprice;
    }

    public function getPriceDiscount()
    {
        return $this->_price_discount;
    }

    public function getWeight()
    {
        return $this->_weight;
    }

    public function getComposition()
    {
        return $this->_composition;
    }

    public function getManufacturer()
    {
        return $this->_manufacturer;
    }

    public function getCountry()
    {
        return $this->_country;
    }

    public function getVid()
    {
        return $this->_vid;
    }

    public function getUpc()
    {
        return $this->_upc;
    }

    public function getSize()
    {
        return $this->_size;
    }

    public function getColor()
    {
        return $this->_color;
    }

    public function getStock()
    {
        return $this->_stock;
    }

    public function setID($id)
    {
        if (($id !== null) && (!is_numeric($id) || $id < 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new ProductException("Product ID error");
        }

        $this->_id = $id;
    }

    public function setName($name)
    {
        if (strlen($name) < 0 || strlen($name) > 255) {
            throw new ProductException("Product name error");
        }

        $this->_name = $name;
    }

    public function setState($state)
    {
        if (!is_int($state) || $state < -1 || $state > 3) {
            throw new ProductException("Product state error");
        }

        $this->_state = $state;
    }

    public function setDescription($description)
    {
        if (($description !== null) && (strlen($description) > 16777215)) {
            throw new ProductException("Product description error ");
        }

        if ($description === null) {
            $this->_description = "";
        } else {
            $this->_description = $description;
        }
    }

    public function setImages($images)
    {
        if (!$jsonData = json_decode($images)) {
            //$this->_images = array((object)["image" => "default.png", "color" => "bold black"]);
            throw new ProductException("Not a valid JSON format for images for product ID - " . $this->_id . " (" . $images . ")");
        } else {
            $this->_images = $jsonData;
        }
    }

    public function setCategory($category)
    {
        $this->_category = $category;
    }

    public function setFeatured($featured)
    {
        if (!(int)$featured === 0 || !(int)$featured === 1) {
            throw new ProductException("Featured flag error");
        }
        $this->_featured = $featured;
    }

    public function setOrderdate($orderdate)
    {
        $this->_orderdate = $orderdate;
    }

    public function setRelease_date($release_date)
    {
        $this->_release_date = $release_date;
    }

    public function setSeason($season)
    {
        $this->_season = $season;
    }

    public function setWholesaleprice($wholesaleprice, $userId = 0)
    {
        $wholesaleprice = (float) $wholesaleprice;
        if (($wholesaleprice !== null) && (!is_float($wholesaleprice) || $wholesaleprice < 0.00 || $wholesaleprice > 9223372036854775807.00)) {
            throw new ProductException("Wholesale price error - " . $wholesaleprice . " (" . gettype($wholesaleprice) . ")");
        }
        // Set wholesale price to 0 if not authorized
        $this->_wholesaleprice = $userId ? $wholesaleprice : 0;
    }
    public function setMsrp($msrp, $userId = 0)
    {
        $msrp = (int) $msrp;
        if (($msrp !== null) && (!is_numeric($msrp) || $msrp < 0 || $msrp > 9223372036854775807)) {
            throw new ProductException("MSRP price error");
        } else {
            // Set MSRP to 0 if not authorized
            $this->_msrp = $userId ? (int)$msrp : 0;
        }
    }

    public function setPrice($price)
    {
        $price = (int) $price;
        if (($price !== null) && (!is_numeric($price) || $price < 0 || $price > 9223372036854775807)) {
            throw new ProductException("Product price error");
        }

        $this->_price = $price;
    }

    public function setZinprice($zinprice)
    {
        $zinprice = (int) $zinprice;
        if (($zinprice !== null) && (!is_numeric($zinprice) || $zinprice < 0 || $zinprice > 9223372036854775807)) {
            throw new ProductException("Product ZIN price error");
        }

        $this->_zinprice = $zinprice;
    }

    public function setPriceDiscount($price_discount)
    {
        $price_discount = (int) $price_discount;
        if (($price_discount !== null) && (!is_numeric($price_discount) || $price_discount < 0 || $price_discount > 9223372036854775807)) {
            throw new ProductException("Product discount price error");
        }
        // Return 0 if null
        $this->_price_discount = $price_discount ? $price_discount : 0;
    }

    public function setTags($tags)
    {
        $this->_tags = $tags;
    }

    public function setNotes($notes)
    {
        $this->_notes = $notes;
    }

    public function setWeight($weight)
    {
        $this->_weight = $weight;
    }

    public function setComposition($composition)
    {
        $this->_composition = $composition;
    }

    public function setManufacturer($manufacturer)
    {
        $this->_manufacturer = $manufacturer;
    }

    public function setCountry($country)
    {
        $this->_country = $country;
    }

    public function setVid($vid)
    {
        $this->_vid = $vid;
    }

    public function setUpc($upc)
    {
        $this->_upc = $upc;
    }

    public function setSize($size)
    {
        $this->_size = $size;
    }

    public function setColor($color)
    {
        if ($color === null) {
            $this->_color = "";
        } else {
            $this->_color = $color;
        }
    }

    public function setStock($stock)
    {
        $this->_stock = $stock;
    }

    public function returnProductAsArray()
    {
        $product = array();
        $product['id'] = $this->getId();
        $product['name'] = $this->getName();
        $product['state'] = $this->getState();
        $product['description'] = $this->getDescription();
        $product['images'] = $this->getImages();
        $product['category'] = $this->getCategory();
        $product['featured'] = $this->getFeatured();
        $product['orderdate'] = $this->getOrderdate();
        $product['release_date'] = $this->getRelease_date();
        $product['season'] = $this->getSeason();
        $product['wholesaleprice'] = $this->getWholesaleprice();
        $product['msrp'] = $this->getMsrp();
        $product['price'] = $this->getPrice();
        $product['zinprice'] = $this->getZinprice();
        $product['price_discount'] = $this->getPriceDiscount();
        $product['tags'] = $this->getTags();
        $product['notes'] = $this->getNotes();
        $product['weight'] = $this->getWeight();
        $product['composition'] = $this->getComposition();
        $product['manufacturer'] = $this->getManufacturer();
        $product['country'] = $this->getCountry();
        $product['vid'] = $this->getVid();
        $product['upc'] = $this->getUpc();
        $product['size'] = $this->getSize();
        $product['color'] = $this->getColor();
        $product['stock'] = $this->getStock();

        return $product;
    }
}
