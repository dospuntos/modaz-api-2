<?php

class VariantException extends Exception
{
}

class ProductVariant
{
    private $_id;
    private $_product_id;
    private $_size;
    private $_color;
    private $_stock;
    private $_upc;
    private $_item;
    private $_transport_id;

    public function __construct($userId, $row)
    {
        $this->setID(isset($row['id']) ? $row['id'] : null);
        $this->setProduct_id(isset($row['product_id']) ? $row['product_id'] : null);
        $this->setSize(isset($row['size']) ? $row['size'] : 0);
        $this->setColor(isset($row['color']) ? $row['color'] : 0);
        $this->setStock(isset($row['stock']) ? $row['stock'] : 1);
        $this->setUpc(isset($row['upc']) ? $row['upc'] : 0);
        $this->setItem(isset($row['item']) ? $row['item'] : "");
        $this->setTransport_id(isset($row['transport_id']) ? $row['transport_id'] : null);
    }

    public function getID()
    {
        return $this->_id;
    }

    public function getProduct_id()
    {
        return $this->_product_id;
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

    public function getUpc()
    {
        return $this->_upc;
    }

    public function getItem()
    {
        return $this->_item;
    }

    public function getTransport_id()
    {
        return $this->_transport_id;
    }

    public function setID($id)
    {
        if (($id !== null) && (!is_numeric($id) || $id < 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new VariantException("Variant ID error");
        }

        $this->_id = $id;
    }

    public function setProduct_id($id)
    {
        if (($id === null) && (!is_numeric($id) || $id < 0 || $id > 9223372036854775807 || $this->_id === null)) {
            throw new VariantException("Product ID error");
        }

        $this->_product_id = $id;
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

    public function setUpc($upc)
    {
        $this->_upc = $upc;
    }

    public function setItem($item)
    {
        $this->_item = $item;
    }

    public function setTransport_id($id)
    {
        if (($id !== null) && (!is_numeric($id) || $id < 0 || $id > 9223372036854775807 || $this->_transport_id !== null)) {
            throw new VariantException("Transport ID error");
        }

        $this->_transport_id = $id;
    }

    public function returnVariantAsArray()
    {
        $variant = array();
        $variant['id'] = $this->getId();
        $variant['product_id'] = $this->getProduct_id();
        $variant['size'] = $this->getSize();
        $variant['color'] = $this->getColor();
        $variant['stock'] = $this->getStock();
        $variant['upc'] = $this->getUpc();
        $variant['item'] = $this->getItem();
        $variant['transport_id'] = $this->getTransport_id();

        return $variant;
    }
}
