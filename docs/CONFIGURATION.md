# Configuration Guide

All MageClone configuration is managed through the Magento admin panel at **Stores > Configuration > MageClone**. Configuration is only required on the **destination** instance.

## System Configuration Paths

All settings live under the `mageclone_migrator/general` configuration group. The following table lists every configuration path:

| Configuration Path | Admin Label | Type | Default |
|---|---|---|---|
| `mageclone_migrator/general/source_url` | Source URL | Text | (empty) |
| `mageclone_migrator/general/api_token` | API Token | Password | (empty) |
| `mageclone_migrator/general/batch_size` | Batch Size | Number | 50 |
| `mageclone_migrator/general/enabled_entities` | Enabled Entity Types | Multiselect | (all) |
| `mageclone_migrator/general/source_media_url` | Source Media URL | Text | (auto) |
| `mageclone_migrator/general/custom_tables` | Custom Tables | Textarea | (empty) |

## Source Configuration

### Source URL

The base URL of the source Magento instance. MageClone appends `/graphql` to this URL when making requests.

**Format:** `https://source-store.example.com`

- Use HTTPS in production.
- Do **not** include a trailing slash.
- Do **not** include `/graphql` -- the module appends this automatically.

**Examples:**
- Correct: `https://source-store.example.com`
- Incorrect: `https://source-store.example.com/`
- Incorrect: `https://source-store.example.com/graphql`

### API Token

The bearer token used to authenticate GraphQL requests against the source instance. This token is sent in the `Authorization: Bearer <token>` header with every request.

#### How to Generate an Admin API Token on the Source Instance

Use the Magento REST API to generate a token:

```bash
curl -X POST "https://source-store.example.com/rest/V1/integration/admin/token" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "migration-admin",
    "password": "SecurePassword123!"
  }'
```

The response is a plain JSON string containing the token:

```json
"r8k2j3h4g5f6d7s8a9p0q1w2e3r4t5y6"
```

Copy this token (without the surrounding quotes) and paste it into the API Token field.

**Best practices:**
- Create a dedicated admin user for migration with a strong password.
- Assign only the `MageClone_MagentoMigrator::sync` ACL resource to this user's role.
- Regenerate the token periodically for security.
- Admin tokens expire based on the source instance's **Stores > Configuration > Services > OAuth > Access Token Expiration** setting. The default is 4 hours. For long migrations, increase this value or regenerate tokens as needed.

### Batch Size

The number of records fetched per GraphQL request. This value is passed as the `pageSize` parameter to all paginated queries.

| Value | Use Case |
|---|---|
| 10-25 | Low-memory environments, complex entities (orders with many items) |
| 50 | Default -- good balance for most setups |
| 100-200 | High-bandwidth, high-memory environments |
| 500+ | Not recommended -- may cause timeouts or memory exhaustion |

Adjust based on entity complexity. Orders with many line items consume more memory per record than simple CMS pages.

## Media Settings

### Source Media URL

The base URL for downloading product media files from the source instance. If left blank, MageClone defaults to `{source_url}/pub/media`.

**Format:** `https://source-store.example.com/pub/media`

Override this if your source instance serves media from a CDN or a non-standard path.

## Entity Selection

### Enabled Entity Types

Select which entity types to include in the migration. Available types:

- `customer` -- Customer accounts and addresses
- `order` -- Orders with line items, addresses, and payment info
- `product` -- Products with media, stock, tier prices, and configurable data
- `category` -- Category tree with hierarchy
- `cms_page` -- CMS pages
- `cms_block` -- CMS static blocks
- `eav_attribute` -- EAV attribute definitions and options
- `store_config` -- Store configuration values

Only selected entity types will appear on the migration dashboard and be processed during sync operations.

## Custom Table Configuration

### Custom Tables

A comma-separated list of database table names to include in the migration. These tables are queried using the `magecloneCustomTableData` GraphQL endpoint, which returns raw row data as JSON.

**Format:** `custom_table_one,custom_table_two,custom_table_three`

**Requirements:**
- Tables must exist on the source instance.
- Table names are validated against an allowlist on the source to prevent unauthorized data access.
- Data is transferred as raw key-value pairs without transformation.

**Example:**

```
amasty_rewards_points,custom_loyalty_tiers,custom_shipping_rules
```

## Incremental Sync Settings

Incremental sync is controlled by the `updatedSince` parameter passed to each GraphQL query. MageClone automatically tracks the `last_synced_at` timestamp per entity type in the `mageclone_sync_status` table.

On subsequent syncs, only records updated after the last sync timestamp are fetched. This happens automatically -- no configuration is needed.

To force a full re-sync of an entity type, reset its `last_synced_at` value:

```sql
UPDATE mageclone_sync_status SET last_synced_at = NULL WHERE entity_type = 'customer';
```

## Recommended Production Settings

| Setting | Recommended Value | Reason |
|---|---|---|
| Source URL | HTTPS URL | Encrypted data in transit |
| API Token | Dedicated user token | Principle of least privilege |
| Batch Size | 50-100 | Balance between speed and memory |
| Enabled Entities | Only what you need | Reduce unnecessary load |
| Source Media URL | CDN URL if available | Faster media downloads |

### Additional Production Recommendations

1. **Run sync during off-peak hours** to minimize impact on both instances.
2. **Increase PHP memory_limit** to at least 1GB for the destination instance CLI.
3. **Configure queue consumers** to process sync messages asynchronously:
   ```bash
   bin/magento queue:consumers:start mageclone.sync.consumer --max-messages=1000
   ```
4. **Set up Magento cron** on the destination for automated incremental sync.
5. **Monitor disk space** on the destination for media file downloads.
