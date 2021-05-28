<?php
class CategoryException extends Exception
{
}

class Category
{
    private $_id;
    private $_name;
    private $_description;

    public function __construct($id, $name, $description)
    {
        $this->setId($id);
        $this->setName($name);
        $this->setDescription($description);
    }

    public function getId()
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
        if (($id !== null) && (!is_numeric($id) || $id < 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new ProductException("Category ID error");
        }

        $this->_id = $id;
    }

    public function setName($name)
    {
        if (strlen($name) < 0 || strlen($name) > 255) {
            throw new ProductException("Category name error");
        }

        $this->_name = $name;
    }

    public function setDescription($description)
    {
        $this->_description = $description;
    }

    public function returnCategoryAsArray()
    {
        $category = array();
        $category['id'] = $this->getId();
        $category['name'] = $this->getName();
        $category['description'] = $this->getDescription();

        return $category;
    }
}
