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
    private $_weight;
    private $_composition;
    private $_manufacturer;
    private $_country;

    private $_vid;
    private $_upc;
    private $_size;
    private $_color;
    private $_stock;

    public function __construct(
        $userId,
        $id,
        $name,
        $state,
        $description,
        $images,
        $category,
        $featured,
        $orderdate,
        $release_date,
        $season,
        $wholesaleprice,
        $msrp,
        $price,
        $zinprice,
        $price_discount,
        $weight,
        $composition,
        $manufacturer,
        $country,
        $vid,
        $upc,
        $size,
        $color,
        $stock
    ) {
        $this->setID($id);
        $this->setName($name);
        $this->setState($state);
        $this->setDescription($description);
        $this->setImages($images);
        $this->setCategory($category);
        $this->setFeatured($featured);
        $this->setOrderdate($orderdate);
        $this->setRelease_date($release_date);
        $this->setSeason($season);
        $this->setWholesaleprice($wholesaleprice, $userId);
        $this->setMsrp($msrp, $userId);
        $this->setPrice($price);
        $this->setZinprice($zinprice);
        $this->setPriceDiscount($price_discount);
        $this->setWeight($weight);
        $this->setComposition($composition);
        $this->setManufacturer($manufacturer);
        $this->setCountry($country);
        $this->setVid($vid);
        $this->setUpc($upc);
        $this->setSize($size);
        $this->setColor($color);
        $this->setStock($stock);
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
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
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
            $this->_images = (object)["image" => "default.jpg", "color" => "bold black"];
            //throw new ProductException("Not a valid JSON format for images - " . $images);
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
        if (($wholesaleprice !== null) && (!is_numeric($wholesaleprice) || $wholesaleprice < 0 || $wholesaleprice > 9223372036854775807 || $this->_wholesaleprice !== null)) {
            throw new ProductException("Wholesale price error");
        }
        // Set wholesale price to 0 if not authorized
        $this->_wholesaleprice = $userId ? $wholesaleprice : 0;
    }
    public function setMsrp($msrp, $userId = 0)
    {
        if (($msrp !== null) && (!is_numeric($msrp) || $msrp < 0 || $msrp > 9223372036854775807 || $this->_msrp !== null)) {
            $this->_msrp = 0;
            //throw new ProductException("MSRP price error");
        } else {
            // Set MSRP to 0 if not authorized
            $this->_msrp = $userId ? (int)$msrp : 0;
        }
    }

    public function setPrice($price)
    {
        if (($price !== null) && (!is_numeric($price) || $price < 0 || $price > 9223372036854775807 || $this->_price !== null)) {
            throw new ProductException("Product price error");
        }

        $this->_price = $price;
    }

    public function setZinprice($zinprice)
    {
        if (($zinprice !== null) && (!is_numeric($zinprice) || $zinprice < 0 || $zinprice > 9223372036854775807 || $this->_zinprice !== null)) {
            throw new ProductException("Product ZIN price error");
        }

        $this->_zinprice = $zinprice;
    }

    public function setPriceDiscount($price_discount)
    {
        if (($price_discount !== null) && (!is_numeric($price_discount) || $price_discount < 0 || $price_discount > 9223372036854775807 || $this->_price_discount !== null)) {
            throw new ProductException("Product discount price error");
        }
        // Return 0 if null
        $this->_price_discount = $price_discount ? $price_discount : 0;
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
