# Product API
Product API for Moda Z. Currently not for public use, but products can be retrieved without login. New users will be disabled by default.

# Access points

Coming soon...

# Direct database queries (internal use):

### Find variants without corresponding product ID
```
SELECT v.*
 FROM nxhnk_modaz_product_variants v
 WHERE v.product_id NOT IN ( SELECT p.id
                       FROM nxhnk_modaz_products p
                      WHERE p.id IS NOT NULL
                   )
```

### Delete variants without corresponding product ID
```
DELETE v.*
 FROM nxhnk_modaz_product_variants v
 WHERE v.product_id NOT IN ( SELECT p.id
                       FROM nxhnk_modaz_products p
                      WHERE p.id IS NOT NULL
                   )
```


