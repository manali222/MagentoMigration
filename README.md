# MageClone - Magento 2 to Magento 2 Migration Plugin

<!-- Badges -->
![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![Magento](https://img.shields.io/badge/Magento-2.4.8+-orange.svg)
![PHP](https://img.shields.io/badge/PHP-8.1+-purple.svg)

## Overview

MageClone is a Magento 2 module that enables live data migration between two Magento 2 instances using a **pull model** over GraphQL. The destination instance pulls data from the source instance, supporting customers, orders, products, categories, CMS content, EAV attributes, and custom tables.

Unlike traditional migration tools that rely on direct database access or file dumps, MageClone communicates exclusively through GraphQL, ensuring compatibility, security, and zero downtime on the source instance.

## Key Features

- **GraphQL-Based Communication** -- All data transfer happens over GraphQL. No direct database access required on the source.
- **Admin Dashboard** -- Monitor sync status, entity counts, and failed records from the Magento admin panel.
- **Incremental Sync** -- Only sync records that have changed since the last run using `updatedSince` filters.
- **Queue-Based Async Processing** -- Offload heavy sync operations to Magento's message queue for non-blocking execution.
- **ID Mapping and Conflict Resolution** -- Automatic mapping of source IDs to destination IDs with checksum-based change detection.
- **Natural Key Matching** -- Match entities by natural keys (email for customers, SKU for products, identifier for CMS) to avoid duplicates.
- **Media Transfer** -- Automatically download and transfer product images and media gallery entries from the source.
- **Dependency-Aware Sync Ordering** -- Topological sort ensures entities are synced in the correct dependency order (e.g., categories before products, customers before orders).

## Requirements

| Requirement | Version |
|---|---|
| Magento Open Source / Adobe Commerce | 2.4.8+ |
| PHP | 8.1+ |
| Composer | 2.x |

## Quick Install

```bash
composer require mageclone/magento-migrator
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Quick Setup

MageClone must be installed on **both** the source and destination Magento instances.

**On the source instance:** Simply install the module. It exposes read-only GraphQL endpoints that the destination will query.

**On the destination instance:** After installation, configure the connection to the source:

1. Navigate to **Stores > Configuration > MageClone > General**.
2. Enter the **Source URL** (e.g., `https://source-store.example.com`).
3. Enter the **API Token** generated on the source instance.
4. Select the entity types you want to migrate.
5. Save the configuration and flush cache.

To generate an API token on the source instance:

```bash
curl -X POST "https://source-store.example.com/rest/V1/integration/admin/token" \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}'
```

## Architecture Overview

```
+---------------------+                  +---------------------+
|   SOURCE INSTANCE   |                  | DESTINATION INSTANCE|
|                     |                  |                     |
|  +---------------+  |    GraphQL       |  +---------------+  |
|  | GraphQL       |<-|------ Query -----|--| GraphQlClient |  |
|  | Resolvers     |--|--- Response ---->|->|               |  |
|  +---------------+  |                  |  +-------+-------+  |
|                     |                  |          |           |
|  - Customers        |                  |  +-------v-------+  |
|  - Orders           |                  |  | Sync Engine   |  |
|  - Products         |                  |  | (EntitySync)  |  |
|  - Categories       |                  |  +-------+-------+  |
|  - CMS Pages        |                  |          |           |
|  - CMS Blocks       |                  |  +-------v-------+  |
|  - EAV Attributes   |                  |  | Entity Mapper |  |
|  - Store Configs    |                  |  +-------+-------+  |
|  - Custom Tables    |                  |          |           |
|                     |                  |  +-------v-------+  |
|                     |                  |  | Repository /  |  |
|                     |                  |  | Database      |  |
|                     |                  |  +---------------+  |
|                     |                  |                     |
|                     |                  |  +---------------+  |
|                     |                  |  | ID Mapping    |  |
|                     |                  |  | Table         |  |
|                     |                  |  +---------------+  |
+---------------------+                  +---------------------+
```

## Supported Entity Types

| Entity Type | GraphQL Query | Natural Key | Incremental Sync |
|---|---|---|---|
| Customers | `magecloneCustomers` | `email` | Yes |
| Orders | `magecloneOrders` | `increment_id` | Yes |
| Products | `magecloneProducts` | `sku` | Yes |
| Categories | `magecloneCategories` | `path` | Yes |
| CMS Pages | `magecloneCmsPages` | `identifier` | Yes |
| CMS Blocks | `magecloneCmsBlocks` | `identifier` | Yes |
| EAV Attributes | `magecloneEavAttributes` | `attribute_code` | No |
| Store Configs | `magecloneStoreConfigs` | `path` | No |
| Custom Tables | `magecloneCustomTableData` | Configurable | No |

## Documentation

- [Installation Guide](docs/INSTALLATION.md)
- [Configuration Guide](docs/CONFIGURATION.md)
- [User Guide](docs/USER_GUIDE.md)
- [Architecture](docs/ARCHITECTURE.md)
- [API Reference](docs/API_REFERENCE.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome. To contribute:

1. Fork the repository.
2. Create a feature branch from `main` (`git checkout -b feature/your-feature`).
3. Write tests for any new functionality.
4. Ensure all existing tests pass (`bin/magento dev:tests:run unit`).
5. Follow the Magento 2 coding standards (`vendor/bin/phpcs --standard=Magento2`).
6. Submit a pull request with a clear description of your changes.

Please open an issue first to discuss significant changes before starting work.
