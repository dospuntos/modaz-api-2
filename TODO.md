# API Development TODO

## Categories

- [ ] Ensure category aliases are unique, including for top-level categories where `parent_id` is `NULL`.
- [ ] Convert category IDs from comma-separated strings to arrays throughout the Product model and API.
- [ ] Allow products to have no categories assigned.
- [ ] Review category validation when creating and updating products.

## Database

- [ ] Implement SQL transactions for operations involving multiple related tables.
- [ ] Review transaction handling for product creation, including categories and variants.
- [ ] Review transaction handling when updating product-category relationships.

## Products

- [ ] Remove remaining dependencies on the old product category column.
- [ ] Review all product SELECT queries to ensure category data comes from the relationship table.
- [ ] Review product deletion and foreign-key cascade behavior.
- [ ] Improve filtering for anonymous users (remove pricing and unpublished items)

## General

- [ ] Review error handling and logging throughout the API.
- [ ] Review database constraints and indexes.
