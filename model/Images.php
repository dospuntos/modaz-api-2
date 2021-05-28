<?php
class ImagesException extends Exception
{
}

class Images
{
    private $_id; // Product ID for the image
    private $_imageArray = array(); // Will hold an array of all image items.

    public function __construct($id, $imgJson, $checkFile = false)
    {
        $this->setId($id);
        $jsonError = false; // Flag to check if there was an error in the JSON-data
        if (!$imgArray = json_decode($imgJson, true)) {
            //throw new ImagesException("Image JSON error (Data: >>>" . $imgArray . "<<<)");
            $imgArray = array("image" => "default.png", "color" => "bold-black");
            $jsonError = true;
        }
        foreach ($imgArray as $image) {
            $this->setImageArray($image, $jsonError, $checkFile);
        }
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getImageArray()
    {
        return $this->_imageArray;
    }

    public function setID($id)
    {
        if (($id !== null) && (!is_numeric($id) || $id < 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new ImagesException("Image ID error");
        }

        $this->_id = $id;
    }

    public function setImageArray($image, $jsonError = false, $check = false)
    {
        if (is_string($image)) {
            $image = array();
            $jsonError = true;
        };
        if (!isset($image['image'])) $image['image'] = "default.png";
        if (!isset($image['color'])) $image['color'] = "color-error";
        $item = array(
            "image" => $image['image'],
            "color" => $image['color']
        );

        // Include debug data
        if ($check) {
            $item['isFile'] = $this->checkIfImageExists($image['image']);
            $item['jsonError'] = $jsonError;
        };

        $this->_imageArray[] = $item;
    }

    public function checkIfImageExists($image)
    {
        return file_exists(__DIR__ . "/../../modaz_backup/images/products/" . $image); // Staging server
        //return file_exists(__DIR__ . "/../../modaz_backup/images/products/" . $image); // Live server
    }

    public function returnImageAsArray()
    {
        $image = array();
        $image['product_id'] = $this->getId();
        $image['items'] = $this->getImageArray();

        return $image;
    }
}
