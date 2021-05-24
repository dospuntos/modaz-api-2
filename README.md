# Product API
Product API for Moda Z. Currently not for public use, but products can be retrieved without login. New users will be disabled by default.

# Access points
<details><summary>
/USERS (<kbd>POST</kbd>)
</summary>
<div>

**Create user**
----
  Returns json data user ID.

* **URL**

  /users

* **Method:**

  `POST`
* **Request header - REQUIRED**

    `"Content-Type", "application/json"`

*  **Request body (JSON) - REQUIRED**

    ```json
    {
        "fullname": [string],
        "username": [string],
        "password": [string]
    }
    ```

* **Success Response:**

  * **Code:** 200 <br />
    **Content:** `{
    "statusCode": 201,
    "success": true,
    "messages": [
        "User created"
    ],
    "data": {
        "user_id": "1",
        "fullname": "John Snow",
        "username": "jsnow"
    }
}`

* **Error Response:**

  * **Code:** 400 Bad Request  <br />
    **Content:** `{
    "statusCode": 400,
    "success": false,
    "messages": [
        [
            "(ERROR MESSAGE)"
        ]
    ],
    "data": null
}`

  OR

  * **Code:** 409 Conflict<br />
    **Content:** `{
    "statusCode": 409,
    "success": false,
    "messages": [
        "Username already exists"
    ],
    "data": null
}`

* **Sample Call:**

  ```javascript
    var myHeaders = new Headers();
    myHeaders.append("Content-Type", "application/json");

    var raw = JSON.stringify({
    "fullname": "Johan",
    "username": "asdfasdf",
    "password": "test"
    });

    var requestOptions = {
    method: 'POST',
    headers: myHeaders,
    body: raw
    };

    fetch("http://api.test/users", requestOptions)
    .then(response => response.text())
    .then(result => console.log(result))
    .catch(error => console.log('error', error));
  ```

</div></details>

<details><summary>
/PRODUCTS (<kbd>GET</kbd>, <kbd>POST</kbd>, <kbd>DELETE</kbd>, <kbd>PATCH</kbd>)
</summary>
<div>

## Products
**Coming soon...**

</div></details>

&nbsp;
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


