# API Reference

MageClone exposes a set of GraphQL queries on the source instance for data extraction. All queries are read-only.

## Authentication

Every request must include a bearer token in the `Authorization` header:

```
Authorization: Bearer <admin-api-token>
```

Generate a token using the Magento REST API on the source instance:

```bash
curl -X POST "https://source-store.example.com/rest/V1/integration/admin/token" \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "password"}'
```

## Endpoint

All queries are sent as POST requests to:

```
https://source-store.example.com/graphql
```

Request body format:

```json
{
  "query": "{ ... }",
  "variables": {}
}
```

## Pagination

All entity queries support pagination with these parameters:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `pageSize` | Int | 50 | Number of records per page |
| `currentPage` | Int | 1 | Page number (1-indexed) |
| `updatedSince` | String | null | ISO 8601 datetime filter for incremental sync |

All paginated responses include a `page_info` object:

```json
{
  "page_info": {
    "page_size": 50,
    "current_page": 1,
    "total_pages": 10
  }
}
```

---

## Queries

### magecloneMigrationMetadata

Returns total entity counts for migration planning. No pagination or filters.

**Query:**

```graphql
{
  magecloneMigrationMetadata {
    customer_count
    order_count
    product_count
    category_count
    cms_page_count
    cms_block_count
    store_config_count
  }
}
```

**Example Response:**

```json
{
  "data": {
    "magecloneMigrationMetadata": {
      "customer_count": 15234,
      "order_count": 48210,
      "product_count": 8750,
      "category_count": 342,
      "cms_page_count": 28,
      "cms_block_count": 15,
      "store_config_count": 1200
    }
  }
}
```

---

### magecloneCustomers

Retrieves customers with addresses and custom attributes.

**Query:**

```graphql
{
  magecloneCustomers(pageSize: 10, currentPage: 1, updatedSince: "2024-01-01T00:00:00") {
    items {
      entity_id
      email
      firstname
      lastname
      group_id
      store_id
      website_id
      created_at
      updated_at
      dob
      gender
      prefix
      suffix
      taxvat
      addresses {
        entity_id
        firstname
        lastname
        street
        city
        region
        region_id
        postcode
        country_id
        telephone
        company
        is_default_billing
        is_default_shipping
      }
      custom_attributes {
        attribute_code
        value
      }
    }
    total_count
    page_info {
      page_size
      current_page
      total_pages
    }
  }
}
```

**Example Response:**

```json
{
  "data": {
    "magecloneCustomers": {
      "items": [
        {
          "entity_id": 1,
          "email": "john.doe@example.com",
          "firstname": "John",
          "lastname": "Doe",
          "group_id": 1,
          "store_id": 1,
          "website_id": 1,
          "created_at": "2024-03-15T10:30:00",
          "updated_at": "2024-06-01T14:22:00",
          "dob": "1985-07-20",
          "gender": 1,
          "prefix": null,
          "suffix": null,
          "taxvat": null,
          "addresses": [
            {
              "entity_id": 1,
              "firstname": "John",
              "lastname": "Doe",
              "street": ["123 Main St", "Suite 100"],
              "city": "Springfield",
              "region": "Illinois",
              "region_id": 23,
              "postcode": "62701",
              "country_id": "US",
              "telephone": "555-0100",
              "company": null,
              "is_default_billing": true,
              "is_default_shipping": true
            }
          ],
          "custom_attributes": []
        }
      ],
      "total_count": 15234,
      "page_info": {
        "page_size": 10,
        "current_page": 1,
        "total_pages": 1524
      }
    }
  }
}
```

---

### magecloneOrders

Retrieves orders with line items, addresses, and payment information.

**Query:**

```graphql
{
  magecloneOrders(pageSize: 10, currentPage: 1, updatedSince: "2024-01-01T00:00:00") {
    items {
      entity_id
      increment_id
      state
      status
      store_id
      customer_id
      customer_email
      grand_total
      subtotal
      tax_amount
      shipping_amount
      discount_amount
      total_qty_ordered
      currency_code
      order_currency_code
      shipping_method
      shipping_description
      customer_firstname
      customer_lastname
      created_at
      updated_at
      items {
        item_id
        sku
        name
        qty_ordered
        price
        row_total
        tax_amount
        discount_amount
        product_type
        weight
      }
      billing_address {
        entity_id
        firstname
        lastname
        street
        city
        region
        region_id
        postcode
        country_id
        telephone
        company
        address_type
      }
      shipping_address {
        entity_id
        firstname
        lastname
        street
        city
        region
        region_id
        postcode
        country_id
        telephone
        company
        address_type
      }
      payment {
        method
        additional_information
      }
    }
    total_count
    page_info {
      page_size
      current_page
      total_pages
    }
  }
}
```

**Example Response:**

```json
{
  "data": {
    "magecloneOrders": {
      "items": [
        {
          "entity_id": 1,
          "increment_id": "000000001",
          "state": "complete",
          "status": "complete",
          "store_id": 1,
          "customer_id": 1,
          "customer_email": "john.doe@example.com",
          "grand_total": 129.99,
          "subtotal": 119.99,
          "tax_amount": 10.00,
          "shipping_amount": 5.00,
          "discount_amount": -5.00,
          "total_qty_ordered": 2.0,
          "currency_code": "USD",
          "order_currency_code": "USD",
          "shipping_method": "flatrate_flatrate",
          "shipping_description": "Flat Rate - Fixed",
          "customer_firstname": "John",
          "customer_lastname": "Doe",
          "created_at": "2024-03-20T09:15:00",
          "updated_at": "2024-03-22T11:30:00",
          "items": [
            {
              "item_id": 1,
              "sku": "PROD-001",
              "name": "Sample Product",
              "qty_ordered": 2.0,
              "price": 59.995,
              "row_total": 119.99,
              "tax_amount": 10.00,
              "discount_amount": 5.00,
              "product_type": "simple",
              "weight": 1.5
            }
          ],
          "billing_address": {
            "entity_id": 1,
            "firstname": "John",
            "lastname": "Doe",
            "street": "123 Main St",
            "city": "Springfield",
            "region": "Illinois",
            "region_id": 23,
            "postcode": "62701",
            "country_id": "US",
            "telephone": "555-0100",
            "company": null,
            "address_type": "billing"
          },
          "shipping_address": {
            "entity_id": 2,
            "firstname": "John",
            "lastname": "Doe",
            "street": "123 Main St",
            "city": "Springfield",
            "region": "Illinois",
            "region_id": 23,
            "postcode": "62701",
            "country_id": "US",
            "telephone": "555-0100",
            "company": null,
            "address_type": "shipping"
          },
          "payment": {
            "method": "checkmo",
            "additional_information": []
          }
        }
      ],
      "total_count": 48210,
      "page_info": {
        "page_size": 10,
        "current_page": 1,
        "total_pages": 4821
      }
    }
  }
}
```

---

### magecloneProducts

Retrieves products with media gallery, stock, tier prices, and configurable product data.

**Query:**

```graphql
{
  magecloneProducts(pageSize: 10, currentPage: 1, updatedSince: "2024-01-01T00:00:00") {
    items {
      entity_id
      sku
      name
      type_id
      attribute_set_id
      status
      visibility
      price
      special_price
      special_from_date
      special_to_date
      weight
      url_key
      description
      short_description
      meta_title
      meta_description
      meta_keyword
      created_at
      updated_at
      media_gallery {
        value_id
        file
        media_type
        label
        position
        disabled
      }
      stock_item {
        qty
        is_in_stock
        manage_stock
        min_qty
        min_sale_qty
        max_sale_qty
      }
      tier_prices {
        customer_group_id
        qty
        value
        percentage_value
      }
      category_ids
      custom_attributes {
        attribute_code
        value
      }
      configurable_options {
        attribute_id
        attribute_code
        label
        values {
          value_index
          label
        }
      }
      configurable_children_skus
    }
    total_count
    page_info {
      page_size
      current_page
      total_pages
    }
  }
}
```

**Example Response:**

```json
{
  "data": {
    "magecloneProducts": {
      "items": [
        {
          "entity_id": 42,
          "sku": "TSHIRT-BLUE-L",
          "name": "Blue T-Shirt - Large",
          "type_id": "simple",
          "attribute_set_id": 4,
          "status": 1,
          "visibility": 4,
          "price": 29.99,
          "special_price": null,
          "special_from_date": null,
          "special_to_date": null,
          "weight": 0.3,
          "url_key": "blue-t-shirt-large",
          "description": "<p>A comfortable blue t-shirt in size large.</p>",
          "short_description": "Comfortable blue t-shirt",
          "meta_title": "Blue T-Shirt",
          "meta_description": "Buy a comfortable blue t-shirt",
          "meta_keyword": "t-shirt, blue, apparel",
          "created_at": "2024-02-10T08:00:00",
          "updated_at": "2024-05-15T16:45:00",
          "media_gallery": [
            {
              "value_id": 101,
              "file": "/t/s/tshirt-blue-front.jpg",
              "media_type": "image",
              "label": "Front view",
              "position": 1,
              "disabled": false
            }
          ],
          "stock_item": {
            "qty": 150.0,
            "is_in_stock": true,
            "manage_stock": true,
            "min_qty": 0.0,
            "min_sale_qty": 1.0,
            "max_sale_qty": 10.0
          },
          "tier_prices": [],
          "category_ids": [3, 15],
          "custom_attributes": [],
          "configurable_options": [],
          "configurable_children_skus": []
        }
      ],
      "total_count": 8750,
      "page_info": {
        "page_size": 10,
        "current_page": 1,
        "total_pages": 875
      }
    }
  }
}
```

---

### magecloneCategories

Retrieves categories with hierarchy and custom attributes.

**Query:**

```graphql
{
  magecloneCategories(pageSize: 50, currentPage: 1, updatedSince: "2024-01-01T00:00:00") {
    items {
      entity_id
      name
      parent_id
      path
      level
      position
      is_active
      include_in_menu
      url_key
      description
      meta_title
      meta_description
      created_at
      updated_at
      custom_attributes {
        attribute_code
        value
      }
    }
    total_count
    page_info {
      page_size
      current_page
      total_pages
    }
  }
}
```

**Example Response:**

```json
{
  "data": {
    "magecloneCategories": {
      "items": [
        {
          "entity_id": 3,
          "name": "Clothing",
          "parent_id": 2,
          "path": "1/2/3",
          "level": 2,
          "position": 1,
          "is_active": true,
          "include_in_menu": true,
          "url_key": "clothing",
          "description": "All clothing items",
          "meta_title": "Clothing",
          "meta_description": "Shop our clothing collection",
          "created_at": "2024-01-05T12:00:00",
          "updated_at": "2024-04-10T09:30:00",
          "custom_attributes": []
        }
      ],
      "total_count": 342,
      "page_info": {
        "page_size": 50,
        "current_page": 1,
        "total_pages": 7
      }
    }
  }
}
```

---

### magecloneCmsPages

Retrieves CMS pages with content and store associations.

**Query:**

```graphql
{
  magecloneCmsPages(pageSize: 50, currentPage: 1, updatedSince: "2024-01-01T00:00:00") {
    items {
      page_id
      identifier
      title
      content
      content_heading
      page_layout
      meta_title
      meta_description
      meta_keywords
      is_active
      sort_order
      store_ids
      created_at
      updated_at
    }
    total_count
    page_info {
      page_size
      current_page
      total_pages
    }
  }
}
```

**Example Response:**

```json
{
  "data": {
    "magecloneCmsPages": {
      "items": [
        {
          "page_id": 1,
          "identifier": "about-us",
          "title": "About Us",
          "content": "<div class=\"about-us\"><h2>Our Story</h2><p>...</p></div>",
          "content_heading": "About Us",
          "page_layout": "1column",
          "meta_title": "About Us",
          "meta_description": "Learn about our company",
          "meta_keywords": "about, company",
          "is_active": true,
          "sort_order": 0,
          "store_ids": [0],
          "created_at": "2024-01-01T00:00:00",
          "updated_at": "2024-03-15T10:00:00"
        }
      ],
      "total_count": 28,
      "page_info": {
        "page_size": 50,
        "current_page": 1,
        "total_pages": 1
      }
    }
  }
}
```

---

### magecloneCmsBlocks

Retrieves CMS static blocks.

**Query:**

```graphql
{
  magecloneCmsBlocks(pageSize: 50, currentPage: 1, updatedSince: "2024-01-01T00:00:00") {
    items {
      block_id
      identifier
      title
      content
      is_active
      store_ids
      created_at
      updated_at
    }
    total_count
    page_info {
      page_size
      current_page
      total_pages
    }
  }
}
```

**Example Response:**

```json
{
  "data": {
    "magecloneCmsBlocks": {
      "items": [
        {
          "block_id": 1,
          "identifier": "footer-links",
          "title": "Footer Links",
          "content": "<ul><li><a href=\"/about\">About</a></li></ul>",
          "is_active": true,
          "store_ids": [0],
          "created_at": "2024-01-01T00:00:00",
          "updated_at": "2024-02-20T08:15:00"
        }
      ],
      "total_count": 15,
      "page_info": {
        "page_size": 50,
        "current_page": 1,
        "total_pages": 1
      }
    }
  }
}
```

---

### magecloneStoreConfigs

Retrieves store configuration values by their config paths. This query does not support pagination or `updatedSince`.

**Query:**

```graphql
{
  magecloneStoreConfigs(paths: [
    "general/store_information/name",
    "general/store_information/phone",
    "general/locale/code"
  ]) {
    path
    value
  }
}
```

**Example Response:**

```json
{
  "data": {
    "magecloneStoreConfigs": [
      {
        "path": "general/store_information/name",
        "value": "My Store"
      },
      {
        "path": "general/store_information/phone",
        "value": "555-0100"
      },
      {
        "path": "general/locale/code",
        "value": "en_US"
      }
    ]
  }
}
```

---

### magecloneCustomTableData

Retrieves raw data from a custom database table. Each row is returned as a JSON-encoded string.

**Query:**

```graphql
{
  magecloneCustomTableData(tableName: "custom_loyalty_tiers", pageSize: 50, currentPage: 1) {
    items {
      data
    }
    total_count
    columns
    page_info {
      page_size
      current_page
      total_pages
    }
  }
}
```

**Example Response:**

```json
{
  "data": {
    "magecloneCustomTableData": {
      "items": [
        {
          "data": "{\"tier_id\":1,\"name\":\"Gold\",\"min_points\":1000,\"discount_percent\":10}"
        },
        {
          "data": "{\"tier_id\":2,\"name\":\"Platinum\",\"min_points\":5000,\"discount_percent\":20}"
        }
      ],
      "total_count": 2,
      "columns": ["tier_id", "name", "min_points", "discount_percent"],
      "page_info": {
        "page_size": 50,
        "current_page": 1,
        "total_pages": 1
      }
    }
  }
}
```

---

### magecloneEavAttributes

Retrieves EAV attribute definitions and option values for a given entity type. This query does not support pagination or `updatedSince`.

**Query:**

```graphql
{
  magecloneEavAttributes(entityTypeCode: "catalog_product") {
    attribute_id
    attribute_code
    frontend_input
    frontend_label
    is_required
    is_user_defined
    default_value
    entity_type_code
    options {
      value
      label
    }
  }
}
```

**Example Response:**

```json
{
  "data": {
    "magecloneEavAttributes": [
      {
        "attribute_id": 93,
        "attribute_code": "color",
        "frontend_input": "select",
        "frontend_label": "Color",
        "is_required": false,
        "is_user_defined": true,
        "default_value": null,
        "entity_type_code": "catalog_product",
        "options": [
          { "value": "49", "label": "Black" },
          { "value": "50", "label": "Blue" },
          { "value": "51", "label": "Red" }
        ]
      }
    ]
  }
}
```

---

## Rate Limiting Considerations

MageClone does not enforce rate limiting on the source side. However, be aware of:

- **Magento's built-in rate limiting** -- If your source instance has API rate limiting enabled (common in Adobe Commerce Cloud), you may need to allowlist the destination IP or increase limits.
- **Web server limits** -- Nginx/Apache may have request rate limits or connection limits. Monitor source server logs if you see `429` or `503` responses.
- **Batch size tuning** -- Increasing `pageSize` reduces the number of requests but increases payload size and memory usage. Find the right balance for your environment.

Recommended approach: Start with `pageSize: 50` and increase gradually while monitoring source server performance.

## Error Response Format

When a GraphQL request encounters an error, the response includes an `errors` array:

```json
{
  "errors": [
    {
      "message": "The consumer isn't authorized to access the resource.",
      "locations": [
        { "line": 2, "column": 3 }
      ],
      "path": ["magecloneCustomers"]
    }
  ],
  "data": {
    "magecloneCustomers": null
  }
}
```

Common error messages:

| Message | Cause | Resolution |
|---|---|---|
| `The consumer isn't authorized to access the resource.` | Invalid or expired token | Regenerate the API token |
| `Internal server error` | Unhandled exception on source | Check source `var/log/exception.log` |
| `Cannot query field "X" on type "Y"` | Schema mismatch | Ensure both instances run the same MageClone version |
| `Variable "$pageSize" got invalid value` | Invalid parameter type | Ensure parameters are the correct type (Int, String) |
