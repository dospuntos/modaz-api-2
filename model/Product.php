<?php

class ProductException extends Exception
{
}

class Product
{
    private $_id;
    private $_name;
    private $_description;
    private $_images;
    private $_price;
    private $_zinprice;
    private $_price_discount;


    public function __construct($id, $name, $description, $images, $price = 0, $zinprice = 0, $price_discount = 0)
    {
        $this->setID($id);
        $this->setName($name);
        $this->setDescription($description);
        $this->setImages($images);
        $this->setPrice($price);
        $this->setZinprice($zinprice);
        $this->setPriceDiscount($price_discount);
    }

    public function getID()
    {
        return $this->_id;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function getImages()
    {
        return $this->_images;
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

    public function setDescription($description)
    {
        if (($description !== null) && (strlen($description) > 16777215)) {
            throw new ProductException("Product description error ");
        }

        $this->_description = $description;
    }

    public function setImages($images)
    {
        if (!$jsonData = json_decode($images)) {
            $this->_images = json_encode((object)["image" => "default.jpg", "color" => "bold black"]);
            //throw new ProductException("Not a valid JSON format for images - " . $images);
        } else {
            $this->_images = $images;
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

        $this->_price_discount = $price_discount;
    }


    public function returnProductAsArray()
    {
        $product = array();
        $product['id'] = $this->getId();
        $product['name'] = $this->getName();
        $product['description'] = $this->getDescription();
        $product['images'] = $this->getImages();
        $product['price'] = $this->getPrice();
        $product['zinprice'] = $this->getZinprice();
        $product['price_discount'] = $this->getPriceDiscount();

        return $product;
    }
}
