# User Guide

This guide covers the day-to-day use of MageClone from the Magento admin panel.

## Accessing the Dashboard

Navigate to **Admin > MageClone > Migration Dashboard** in the Magento admin menu.

The dashboard is the central interface for monitoring and controlling all migration operations. Access requires the `MageClone_MagentoMigrator::sync` ACL permission.

## Understanding the Dashboard

The dashboard provides an at-a-glance view of migration progress across all entity types.

### Connection Status

At the top of the dashboard, a connection indicator shows whether the destination can successfully reach the source instance's GraphQL endpoint. A successful connection confirms that the Source URL and API Token are correctly configured.

- **Connected** -- The source instance is reachable and the token is valid.
- **Disconnected** -- Check your Source URL and API Token in Stores > Configuration > MageClone.

### Entity Counts

For each enabled entity type, the dashboard displays:

| Column | Description |
|---|---|
| Entity Type | The type of data (customer, order, product, etc.) |
| Source Count | Total records available on the source instance |
| Destination Count | Total records that exist on the destination |
| Synced | Number of records successfully synced |
| Failed | Number of records that failed during sync |
| Pending | Number of records queued but not yet processed |
| Status | Current sync state: `idle`, `running`, `completed`, `failed` |
| Last Synced | Timestamp of the most recent successful sync |

### Sync Status Values

| Status | Meaning |
|---|---|
| `idle` | No sync is currently running for this entity type |
| `running` | Sync is actively processing records |
| `completed` | The most recent sync run finished successfully |
| `failed` | The most recent sync run encountered errors |

## Running Your First Sync

1. Ensure the connection status shows **Connected**.
2. Verify the entity counts from the source look correct.
3. Select the entity type you want to sync.
4. Click the **Sync** button for that entity type.
5. The status will change to `running` and progress counters will update.

For a first-time migration, follow the recommended sync order below.

## Sync Order and Dependencies

Entities have dependencies on each other. Syncing them in the wrong order will result in broken foreign key references. MageClone handles dependency resolution automatically when using the "Sync All" feature, but if syncing individual entity types manually, follow this order:

```
1. EAV Attributes      (no dependencies)
2. Categories           (no dependencies, but hierarchical -- parents first)
3. Customers            (no dependencies)
4. Products             (depends on: categories, EAV attributes)
5. CMS Blocks           (no dependencies)
6. CMS Pages            (no dependencies)
7. Orders               (depends on: customers, products)
8. Store Configs        (no dependencies)
9. Custom Tables        (varies)
```

The dependency engine uses topological sorting to determine the correct order. When you click "Sync All," entities are processed in this sequence automatically.

## Monitoring Sync Progress

During an active sync operation:

- The dashboard auto-refreshes to show updated counters.
- The **Synced** count increments as batches complete.
- The **Failed** count increments if individual records fail.
- The **Pending** count shows how many records are queued for processing.

For large datasets, sync operations run in batches determined by the configured batch size. Each batch is a separate GraphQL request to the source.

## Handling Failed Records

Records can fail for various reasons: validation errors, missing dependencies, constraint violations, or network issues.

### Viewing Failed Records

1. Navigate to the dashboard.
2. Check the **Failed** column for any non-zero values.
3. Click on the failed count to view detailed log entries.

Each log entry includes:

- **Entity Type** -- Which type of entity failed
- **Source ID** -- The ID of the record on the source instance
- **Status** -- `failed`
- **Message** -- A detailed error message explaining why the record failed
- **Batch ID** -- The batch identifier, useful for correlating related failures

### Using "Resync Failed"

The **Resync Failed** button retries only the records that previously failed. This is useful after fixing the underlying issue (e.g., adding a missing category that a product depends on).

1. Identify the entity type with failed records.
2. Fix the root cause if possible (check the error message for guidance).
3. Click **Resync Failed** for that entity type.
4. MageClone re-fetches the failed records from the source and attempts to sync them again.

## Viewing Sync Logs

Detailed sync logs are stored in the `mageclone_sync_log` table and accessible from the admin panel.

### Log Entry Fields

| Field | Description |
|---|---|
| Log ID | Auto-increment identifier |
| Entity Type | The entity type this log entry relates to |
| Source ID | The source instance entity ID |
| Status | `success`, `failed`, or `skipped` |
| Message | Descriptive message (error details for failures) |
| Batch ID | Groups log entries by sync batch |
| Created At | Timestamp of the log entry |

### Filtering Logs

You can filter logs by:

- Entity type
- Status (to see only failures)
- Batch ID (to review a specific sync run)
- Date range

## Incremental Sync

After the initial full migration, MageClone supports incremental sync to keep the destination up to date with changes made on the source.

### How It Works

1. Each entity type tracks a `last_synced_at` timestamp in the `mageclone_sync_status` table.
2. On subsequent sync runs, MageClone passes this timestamp as the `updatedSince` filter to the GraphQL query.
3. Only records created or modified after this timestamp are returned by the source.
4. The destination processes only these changed records, significantly reducing sync time.

### Automated Incremental Sync via Cron

When Magento cron is configured, MageClone can run incremental syncs automatically. The cron schedule is defined in the module's `crontab.xml`.

Ensure Magento cron is running:

```bash
crontab -e
# Add or verify this entry:
* * * * * /usr/bin/php /path/to/magento/bin/magento cron:run >> /path/to/magento/var/log/cron.log 2>&1
```

## Best Practices

### Before Migration

1. **Test on a staging environment first.** Never run a first-time migration directly on production.
2. **Take a database backup** of the destination instance before starting.
3. **Verify entity counts** on the dashboard match what you expect from the source.

### During Migration

4. **Sync in dependency order** when syncing entity types individually.
5. **Monitor memory usage** on the destination, especially for large product catalogs with many images.
6. **Watch the failed count** -- address failures before proceeding to dependent entity types.

### After Migration

7. **Review sync logs** for any skipped or failed records.
8. **Spot-check data** on the destination -- verify a sample of customers, orders, and products.
9. **Reindex** the destination instance after migration:
   ```bash
   bin/magento indexer:reindex
   ```
10. **Flush cache** on the destination:
    ```bash
    bin/magento cache:flush
    ```

### Ongoing Operations

11. **Use incremental sync** for ongoing data synchronization rather than full re-syncs.
12. **Monitor logs regularly** at `var/log/system.log` for any warnings or errors from the MageClone module.
13. **Regenerate the API token** periodically on the source instance for security.
