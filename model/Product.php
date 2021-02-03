<?php

class ProductException extends Exception
{
}

class Product
{
    private $_id;
    private $_name;
    private $_description;

    public function __construct($id, $name, $description)
    {
        $this->setID($id);
        $this->setName($name);
        $this->setDescription($description);
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


    public function setID($id)
    {
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new TaskException("Task ID error");
        }

        $this->_id = $id;
    }

    public function setName($name)
    {
        if (strlen($name) < 0 || strlen($name) > 255) {
            throw new TaskException("Product name error");
        }

        $this->_name = $name;
    }

    public function setDescription($description)
    {
        if (($description !== null) && (strlen($description) > 16777215)) {
            throw new TaskException("Product description error ");
        }

        $this->_description = $description;
    }

    public function returnProductAsArray()
    {
        $product = array();
        $product['id'] = $this->getId();
        $product['name'] = $this->getName();
        $product['description'] = $this->getDescription();
        return $product;
    }
}
