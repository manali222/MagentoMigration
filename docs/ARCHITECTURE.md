# Architecture

This document describes the technical architecture of MageClone, including its components, data flow, database schema, and design decisions.

## System Overview

```
+----------------------------+                     +----------------------------+
|     SOURCE INSTANCE        |                     |   DESTINATION INSTANCE     |
|                            |                     |                            |
|  +----------------------+  |                     |  +----------------------+  |
|  |   Magento GraphQL    |  |     HTTPS/GraphQL   |  |    GraphQlClient     |  |
|  |   Framework          |  |<----- Query --------|--|                      |  |
|  |                      |--|---- Response ------->|->|  (Bearer Token Auth) |  |
|  +----------+-----------+  |                     |  +----------+-----------+  |
|             |              |                     |             |              |
|  +----------v-----------+  |                     |  +----------v-----------+  |
|  | MageClone Resolvers  |  |                     |  |    SyncService       |  |
|  |                      |  |                     |  |    (Orchestrator)    |  |
|  | - SyncMetadata       |  |                     |  +----------+-----------+  |
|  | - Customers          |  |                     |             |              |
|  | - Orders             |  |                     |  +----------v-----------+  |
|  | - Products           |  |                     |  |    EntitySync        |  |
|  | - Categories         |  |                     |  |    (per entity type) |  |
|  | - CmsPages           |  |                     |  +----------+-----------+  |
|  | - CmsBlocks          |  |                     |             |              |
|  | - StoreConfigs       |  |                     |  +----------v-----------+  |
|  | - CustomTableData    |  |                     |  |    Entity Mapper     |  |
|  | - EavAttributes      |  |                     |  |  (data transform)   |  |
|  +----------------------+  |                     |  +----------+-----------+  |
|                            |                     |             |              |
|                            |                     |  +----------v-----------+  |
|                            |                     |  | Magento Repositories |  |
|                            |                     |  | + ID Mapping Table   |  |
|                            |                     |  +----------------------+  |
|                            |                     |                            |
|                            |                     |  +----------------------+  |
|                            |                     |  | Message Queue        |  |
|                            |                     |  | (async processing)   |  |
|                            |                     |  +----------------------+  |
+----------------------------+                     +----------------------------+
```

## Pull Model

MageClone uses a **pull model**: the destination instance initiates all communication. The source instance is entirely passive -- it only responds to incoming GraphQL queries.

This design offers several advantages:

- **No outbound connections from source** -- The source instance does not need to know about the destination. No webhooks, no push configuration.
- **Source remains unmodified** -- The source only needs the module installed. No configuration, no credentials stored.
- **Destination controls the pace** -- The destination decides when to sync, how many records to fetch per batch, and which entity types to include.
- **Firewall-friendly** -- Only one direction of connectivity is required (destination to source).

## Components

### GraphQL Resolvers (Source Side)

Located in `Model/Resolver/`, these classes implement Magento's `ResolverInterface` and provide read-only access to entity data.

| Resolver | Query | Description |
|---|---|---|
| `SyncMetadata` | `magecloneMigrationMetadata` | Returns entity counts for migration planning |
| `Customers` | `magecloneCustomers` | Paginated customers with addresses and custom attributes |
| `Orders` | `magecloneOrders` | Paginated orders with items, addresses, and payment |
| `Products` | `magecloneProducts` | Paginated products with media, stock, tier prices, and configurable data |
| `Categories` | `magecloneCategories` | Paginated categories with hierarchy information |
| `CmsPages` | `magecloneCmsPages` | Paginated CMS pages |
| `CmsBlocks` | `magecloneCmsBlocks` | Paginated CMS blocks |
| `StoreConfigs` | `magecloneStoreConfigs` | Store configuration values by path |
| `CustomTableData` | `magecloneCustomTableData` | Raw data from custom database tables |
| `EavAttributes` | `magecloneEavAttributes` | EAV attribute definitions and option values |

All paginated queries accept `pageSize`, `currentPage`, and `updatedSince` parameters.

### GraphQlClient (Destination Side)

`Model/GraphQlClient.php` implements `GraphQlClientInterface` and handles all communication with the source instance.

Responsibilities:
- Constructs GraphQL POST requests with query and variables
- Attaches the bearer token in the `Authorization` header
- Sends requests via cURL with a 120-second timeout
- Parses JSON responses and handles error conditions
- Provides a `testConnection()` method for the dashboard connection check

### Sync Engine (Destination Side)

`Model/SyncService.php` implements `SyncServiceInterface` and orchestrates the overall sync process.

Responsibilities:
- Determines sync order based on entity dependencies
- Iterates through enabled entity types
- Delegates to entity-specific sync classes
- Updates sync status records
- Publishes messages to the queue for async processing

### Entity Mappers (Destination Side)

Located in `Model/Mapper/`, these classes transform source data into a format suitable for the destination.

| Mapper | Entity | Key Responsibilities |
|---|---|---|
| `CustomerMapper` | Customers | Maps customer data, matches by email |
| `OrderMapper` | Orders | Maps order data, resolves customer ID references |
| `ProductMapper` | Products | Maps product data, handles media gallery, resolves category IDs |
| `CategoryMapper` | Categories | Maps category data, resolves parent ID references |
| `CmsMapper` | CMS Pages/Blocks | Maps CMS content, matches by identifier |
| `AttributeMapper` | EAV Attributes | Maps attribute definitions and option values |

All mappers implement `EntityMapperInterface`.

### Queue System (Destination Side)

MageClone uses Magento's built-in message queue system for async processing.

| Component | File | Purpose |
|---|---|---|
| `SyncMessage` | `Model/Queue/SyncMessage.php` | Message DTO carrying entity type and batch data |
| `SyncPublisher` | `Model/Queue/SyncPublisher.php` | Publishes sync messages to the queue |
| `SyncConsumer` | (consumer config) | Processes queued sync messages |

Queue configuration files in `etc/`:
- `communication.xml` -- Defines the topic
- `queue_topology.xml` -- Defines the exchange and binding
- `queue_publisher.xml` -- Defines the publisher
- `queue_consumer.xml` -- Defines the consumer (`mageclone.sync.consumer`)

The consumer processes up to 100 messages per invocation and uses the `db` connection.

### ID Mapping (Destination Side)

The `mageclone_id_mapping` table maintains a bidirectional mapping between source and destination entity IDs. This is critical because entity IDs (auto-increment primary keys) will differ between the two instances.

When a product on the source has `entity_id = 42` and is created on the destination with `entity_id = 107`, the mapping table records:

```
entity_type = "product"
source_id   = 42
destination_id = 107
checksum    = "a1b2c3d4..."
```

The checksum is used for change detection during incremental sync. If the source record's checksum differs from the stored checksum, the record has changed and needs to be re-synced.

## Data Flow

The complete data flow for a sync operation:

```
1. Dashboard / Cron triggers SyncService
           |
2. SyncService determines entity order (topological sort)
           |
3. For each entity type:
     |
     +---> GraphQlClient sends paginated query to source
     |           |
     |     Source resolvers fetch data from Magento repositories
     |           |
     |     Response returned as JSON
     |           |
     +---> EntitySync receives raw data
     |           |
     +---> EntityMapper transforms data:
     |       - Resolves foreign key IDs via ID mapping table
     |       - Matches existing records by natural key
     |       - Computes checksum for change detection
     |           |
     +---> Repository saves entity to destination database
     |           |
     +---> ID mapping record created/updated
     |           |
     +---> SyncLog entry written (success or failure)
     |           |
     +---> SyncStatus counters updated
     |           |
     +---> Next page (if more records exist)
```

## Database Schema

### mageclone_sync_status

Tracks the overall sync state for each entity type.

| Column | Type | Description |
|---|---|---|
| `status_id` | INT (PK, auto) | Primary key |
| `entity_type` | VARCHAR(64), unique | Entity type identifier |
| `source_count` | INT unsigned | Total records on source |
| `destination_count` | INT unsigned | Total records on destination |
| `synced_count` | INT unsigned | Successfully synced count |
| `failed_count` | INT unsigned | Failed record count |
| `pending_count` | INT unsigned | Queued but not yet processed |
| `status` | VARCHAR(32) | Current state: `idle`, `running`, `completed`, `failed` |
| `last_synced_at` | TIMESTAMP, nullable | Last successful sync timestamp (used for incremental) |
| `created_at` | TIMESTAMP | Row creation time |
| `updated_at` | TIMESTAMP | Last modification time (auto-updated) |

### mageclone_id_mapping

Maps source entity IDs to destination entity IDs.

| Column | Type | Description |
|---|---|---|
| `mapping_id` | INT (PK, auto) | Primary key |
| `entity_type` | VARCHAR(64) | Entity type identifier |
| `source_id` | INT unsigned | Entity ID on the source instance |
| `destination_id` | INT unsigned | Entity ID on the destination instance |
| `checksum` | VARCHAR(64), nullable | Data checksum for change detection |
| `created_at` | TIMESTAMP | Row creation time |

Unique constraint on (`entity_type`, `source_id`) prevents duplicate mappings.

### mageclone_sync_log

Detailed log of sync operations at the individual record level.

| Column | Type | Description |
|---|---|---|
| `log_id` | INT (PK, auto) | Primary key |
| `entity_type` | VARCHAR(64) | Entity type identifier |
| `source_id` | INT unsigned, nullable | Source entity ID (null for batch-level entries) |
| `status` | VARCHAR(32) | `success`, `failed`, or `skipped` |
| `message` | TEXT, nullable | Descriptive message or error detail |
| `batch_id` | VARCHAR(64), nullable | Groups entries by sync batch |
| `created_at` | TIMESTAMP | Row creation time |

Indexed on `entity_type`, `status`, and `batch_id` for efficient filtering.

## Dependency Resolution and Topological Sort

Entity types have implicit dependencies based on foreign key relationships:

```
eav_attribute  -->  (no deps)
category       -->  (no deps, but self-referential via parent_id)
customer       -->  (no deps)
product        -->  eav_attribute, category
cms_page       -->  (no deps)
cms_block      -->  (no deps)
order          -->  customer, product
store_config   -->  (no deps)
```

The SyncService performs a topological sort on these dependencies to determine the execution order. This ensures that when a product references a category ID, that category has already been synced and its ID mapping is available.

For categories, which are self-referential (each category has a `parent_id`), the sync processes them in order of ascending `level` so that parent categories are created before their children.

## Natural Key Matching Strategy

To avoid creating duplicate records on the destination, MageClone uses natural keys to check whether a source entity already exists:

| Entity Type | Natural Key | Matching Logic |
|---|---|---|
| Customer | `email` | Look up customer by email address |
| Product | `sku` | Look up product by SKU |
| Category | `url_key` + `level` | Match by URL key within the same tree level |
| CMS Page | `identifier` | Match by page identifier |
| CMS Block | `identifier` | Match by block identifier |
| EAV Attribute | `attribute_code` | Match by attribute code within entity type |
| Order | `increment_id` | Match by order increment ID |

When a natural key match is found, the existing destination entity is updated rather than creating a duplicate. The ID mapping table is also populated with the matched IDs.

## Error Handling and Retry Strategy

MageClone handles errors at multiple levels:

1. **Network errors** -- `GraphQlClient` catches cURL exceptions and wraps them in `GraphQlClientException`. The sync engine logs the error and can retry the batch.

2. **GraphQL errors** -- If the source returns GraphQL errors in the response, they are collected and thrown as a single `GraphQlClientException`.

3. **Individual record failures** -- If a single entity fails to save (e.g., validation error), the failure is logged to `mageclone_sync_log` and the sync continues with the next record. The `failed_count` on the sync status is incremented.

4. **Batch-level failures** -- If an entire batch fails (e.g., network timeout), the batch is logged as failed and can be retried.

5. **Retry** -- The "Resync Failed" feature re-fetches and re-processes only records that have a `failed` log entry, allowing targeted recovery without repeating the entire sync.

## Security

MageClone is designed with security as a priority:

- **Bearer token authentication** -- All GraphQL requests from the destination include an `Authorization: Bearer` header. The source validates this token through Magento's standard authentication.

- **ACL permissions** -- Access to MageClone features in the admin panel requires the `MageClone_MagentoMigrator::sync` ACL resource. The API token should belong to a user with only this permission.

- **No direct database access** -- The source instance never exposes database credentials or direct SQL access. All data is served through GraphQL resolvers that use Magento's repository layer.

- **Read-only on source** -- The source module only provides GraphQL query resolvers. No mutations are defined. The source data cannot be modified by the destination.

- **HTTPS recommended** -- While not enforced at the module level, HTTPS should always be used in production to encrypt data in transit, especially since customer PII and order data are being transferred.
