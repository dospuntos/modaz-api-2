<?php
class ImagesException extends Exception
{
}

class Images
{
    private $_url;
    private $_path;

    public function __construct($id, $title, $path)
    {
        $this->setId($id);
        $this->setTitle($title);
        $this->setPath($path);
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getTitle()
    {
        return $this->_title;
    }

    public function getPath()
    {
        return $this->_path;
    }

    public function setID($id)
    {
        if (($id !== null) && (!is_numeric($id) || $id < 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new ProductException("Category ID error");
        }

        $this->_id = $id;
    }

    public function setTitle($title)
    {
        if (strlen($title) < 0 || strlen($title) > 255) {
            throw new ProductException("Category title error");
        }

        $this->_title = $title;
    }

    public function setPath($path)
    {
        $this->_path = $path;
    }

    public function returnCategoryAsArray()
    {
        $category = array();
        $category['id'] = $this->getId();
        $category['title'] = $this->getTitle();
        $category['path'] = $this->getPath();

        return $category;
    }
}
