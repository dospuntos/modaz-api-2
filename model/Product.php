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

    public function __construct($id, $name, $description, $images)
    {
        $this->setID($id);
        $this->setName($name);
        $this->setDescription($description);
        $this->setImages($images);
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
            $this->_images = json_encode($images);
            //throw new ProductException("Not a valid JSON format for images - " . $images);
        } else {
            $this->_images = $images;
        }
    }

    public function returnProductAsArray()
    {
        $product = array();
        $product['id'] = $this->getId();
        $product['name'] = $this->getName();
        $product['description'] = $this->getDescription();
        $product['images'] = $this->getImages();
        return $product;
    }
}
