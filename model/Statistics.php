
<?php

class StatisticsException extends Exception {}

class Statistics
{
    private $_readDB;

    public function __construct($readDB)
    {
        $this->_readDB = $readDB;
    }

    public function getStatistics()
    {
        try {
            $stats = array();

            // Count products
            $query = $this->_readDB->prepare(
                "SELECT COUNT(*) FROM {$this->_readDB->tblproducts}"
            );
            $query->execute();
            $stats['products'] = (int)$query->fetchColumn();

            // Count variants
            $query = $this->_readDB->prepare(
                "SELECT COUNT(*) FROM {$this->_readDB->tblproductvariants}"
            );
            $query->execute();
            $stats['variants'] = (int)$query->fetchColumn();

            // Count orders
            $query = $this->_readDB->prepare(
                "SELECT COUNT(*) FROM {$this->_readDB->tblorders}
                 WHERE state > 0"
            );
            $query->execute();
            $stats['orders'] = (int)$query->fetchColumn();

            // Count active users
            $query = $this->_readDB->prepare(
                "SELECT COUNT(*) FROM {$this->_readDB->tblusers} WHERE useractive = 'Y'"
            );
            $query->execute();
            $stats['users'] = (int)$query->fetchColumn();

            // Calculate sold items and total income
            $query = $this->_readDB->prepare(
                "SELECT
                    COALESCE(SUM(d.order_item_quantity), 0) AS sold_items,
                    COALESCE(
                        SUM(d.order_item_quantity * d.order_item_price),
                        0
                    ) AS sold_total
                 FROM {$this->_readDB->tblorders} o
                 INNER JOIN {$this->_readDB->tblorderdetails} d
                    ON o.id = d.order_id
                 WHERE o.order_state = 5"
            );
            $query->execute();
            $sales = $query->fetch(PDO::FETCH_ASSOC);

            $stats['sold_items'] = (int)$sales['sold_items'];
            $stats['sold_total'] = (float)$sales['sold_total'];

            // Calculate value of available stock
            $query = $this->_readDB->prepare(
                "SELECT
                    COALESCE(SUM(v.stock * p.wholesaleprice), 0) AS total_value,
                    COALESCE(SUM(v.stock * p.price), 0) AS sales_value
                 FROM {$this->_readDB->tblproducts} p
                 INNER JOIN {$this->_readDB->tblproductvariants} v
                    ON p.id = v.product_id
                 WHERE p.state > 0
                    AND v.stock > 0"
            );
            $query->execute();
            $stock = $query->fetch(PDO::FETCH_ASSOC);

            $stats['total_value'] = (float)$stock['total_value'];
            $stats['sales_value'] = (float)$stock['sales_value'];

            return $stats;
        } catch (PDOException $ex) {
            throw new StatisticsException(
                "Failed to retrieve statistics",
                0,
                $ex
            );
        }
    }
}
