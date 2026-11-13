<?php

class CategoryException extends Exception {}

class Category
{
    private $_id;
    private $_parentId;
    private $_name;
    private $_alias;
    private $_description;
    private $_image;
    private $_published;
    private $_ordering;

    public function __construct($row)
    {
        if (!is_array($row)) {
            throw new CategoryException("Invalid category data");
        }

        $this->setId($row['id'] ?? null);
        $this->setParentId($row['parent_id'] ?? null);
        $this->setName($row['name'] ?? '');
        $this->setAlias($row['alias'] ?? '');
        $this->setDescription($row['description'] ?? null);
        $this->setImage($row['image'] ?? null);
        $this->setPublished($row['published'] ?? 1);
        $this->setOrdering($row['ordering'] ?? 0);
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getParentId()
    {
        return $this->_parentId;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function getAlias()
    {
        return $this->_alias;
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function getImage()
    {
        return $this->_image;
    }

    public function getPublished()
    {
        return $this->_published;
    }

    public function getOrdering()
    {
        return $this->_ordering;
    }

    public function setId($id)
    {
        if ($id === null) {
            $this->_id = null;
            return;
        }

        if (filter_var($id, FILTER_VALIDATE_INT) === false || $id < 1) {
            throw new CategoryException("Category ID error");
        }

        $this->_id = (int) $id;
    }

    public function setParentId($parentId)
    {
        if ($parentId === null || $parentId === '') {
            $this->_parentId = null;
            return;
        }

        if (
            filter_var($parentId, FILTER_VALIDATE_INT) === false
            || $parentId < 1
        ) {
            throw new CategoryException("Category parent ID error");
        }

        $this->_parentId = (int) $parentId;
    }

    public function setName($name)
    {
        if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 255) {
            throw new CategoryException("Category name error");
        }

        $this->_name = trim($name);
    }

    public function setAlias($alias)
    {
        if (!is_string($alias) || trim($alias) === '' || mb_strlen($alias) > 255) {
            throw new CategoryException("Category alias error");
        }

        $this->_alias = trim($alias);
    }

    public function setDescription($description)
    {
        if ($description !== null && !is_string($description)) {
            throw new CategoryException("Category description error");
        }

        $this->_description = $description;
    }

    public function setImage($image)
    {
        if ($image !== null && !is_string($image)) {
            throw new CategoryException("Category image error");
        }

        $this->_image = $image;
    }

    public function setPublished($published)
    {
        if (!in_array($published, [0, 1, '0', '1'], true)) {
            throw new CategoryException("Category published status error");
        }

        $this->_published = (int) $published;
    }

    public function setOrdering($ordering)
    {
        if (filter_var($ordering, FILTER_VALIDATE_INT) === false) {
            throw new CategoryException("Category ordering error");
        }

        $this->_ordering = (int) $ordering;
    }

    public function returnCategoryAsArray()
    {
        return [
            'id' => $this->getId(),
            'parent_id' => $this->getParentId(),
            'name' => $this->getName(),
            'alias' => $this->getAlias(),
            'description' => $this->getDescription(),
            'image' => $this->getImage(),
            'published' => $this->getPublished(),
            'ordering' => $this->getOrdering()
        ];
    }
}
