# Troubleshooting

This guide covers common issues encountered when using MageClone and their solutions.

## Connection Issues

### Connection failed

**Symptoms:** The dashboard shows "Disconnected" or sync operations fail immediately with a connection error.

**Possible causes and solutions:**

1. **Incorrect URL format**
   - Ensure the Source URL does not have a trailing slash.
   - Ensure the Source URL does not include `/graphql` -- the module appends this automatically.
   - Correct: `https://source-store.example.com`
   - Incorrect: `https://source-store.example.com/` or `https://source-store.example.com/graphql`

2. **Network or firewall restrictions**
   - Verify the destination server can reach the source server:
     ```bash
     curl -I https://source-store.example.com/graphql
     ```
   - If behind a firewall, ensure the destination IP is allowlisted on the source.
   - Check that port 443 (HTTPS) is open.

3. **SSL certificate issues**
   - If the source uses a self-signed certificate, you may need to configure PHP to trust it or disable SSL verification (not recommended for production).
   - Verify the certificate:
     ```bash
     openssl s_client -connect source-store.example.com:443
     ```

4. **Source module not installed**
   - Verify MageClone is installed and enabled on the source:
     ```bash
     bin/magento module:status MageClone_MagentoMigrator
     ```

### Authentication error

**Symptoms:** Errors like "The consumer isn't authorized to access the resource" or HTTP 401 responses.

**Solutions:**

1. **Regenerate the API token** on the source instance:
   ```bash
   curl -X POST "https://source-store.example.com/rest/V1/integration/admin/token" \
     -H "Content-Type: application/json" \
     -d '{"username": "admin", "password": "your-password"}'
   ```

2. **Check token expiration** -- Admin tokens expire based on the source's OAuth settings. Navigate to **Stores > Configuration > Services > OAuth > Access Token Expiration** on the source and increase the value if needed.

3. **Verify ACL permissions** -- The admin user whose token is used must have the `MageClone_MagentoMigrator::sync` ACL resource. Check the user's role in **System > Permissions > User Roles**.

4. **Ensure the token is entered correctly** -- No extra spaces, no surrounding quotes. Copy-paste directly from the cURL response.

## Performance Issues

### Slow sync

**Symptoms:** Sync operations take much longer than expected.

**Solutions:**

1. **Increase batch size** -- If you are using a small batch size (e.g., 10), increase it to 50 or 100 in **Stores > Configuration > MageClone > Batch Size**.

2. **Use queue consumers for async processing**:
   ```bash
   bin/magento queue:consumers:start mageclone.sync.consumer --max-messages=1000
   ```
   Run multiple consumer processes in parallel for higher throughput.

3. **Check network latency** between the two instances:
   ```bash
   ping source-store.example.com
   curl -o /dev/null -s -w "Total time: %{time_total}s\n" https://source-store.example.com/graphql
   ```
   High latency (>200ms) will significantly impact sync speed with small batch sizes.

4. **Optimize the source instance** -- Ensure the source has adequate resources and is not under heavy load during sync. Consider running syncs during off-peak hours.

5. **Check for slow queries** on the source -- Enable MySQL slow query log and look for queries triggered by MageClone resolvers.

## Data Issues

### Missing data after sync

**Symptoms:** Some records that exist on the source do not appear on the destination after sync.

**Solutions:**

1. **Check that the entity type is enabled** -- Navigate to **Stores > Configuration > MageClone > Enabled Entity Types** and verify the entity type is selected.

2. **Check the updatedSince filter** -- If running an incremental sync, only records modified after `last_synced_at` are fetched. To force a full sync, reset the timestamp:
   ```sql
   UPDATE mageclone_sync_status SET last_synced_at = NULL WHERE entity_type = 'product';
   ```

3. **Check the sync log for skipped records** -- Some records may be skipped if they match an existing record by natural key and the checksum indicates no changes.

4. **Verify total counts** -- Compare the `source_count` on the dashboard with the actual count on the source. If they match, the records were fetched but may have failed or been skipped.

### ID mapping conflicts

**Symptoms:** Errors about duplicate keys or constraint violations during sync.

**Solutions:**

1. **Review existing mappings**:
   ```sql
   SELECT * FROM mageclone_id_mapping WHERE entity_type = 'product' ORDER BY mapping_id DESC LIMIT 20;
   ```

2. **Reset mappings for a specific entity type** (use with caution -- this will cause a full re-sync and may create duplicates):
   ```sql
   DELETE FROM mageclone_id_mapping WHERE entity_type = 'product';
   UPDATE mageclone_sync_status SET last_synced_at = NULL, synced_count = 0 WHERE entity_type = 'product';
   ```

3. **Manual ID resolution** -- If a specific source ID maps to the wrong destination ID, update it manually:
   ```sql
   UPDATE mageclone_id_mapping
   SET destination_id = <correct_destination_id>
   WHERE entity_type = 'product' AND source_id = <source_id>;
   ```

4. **Check for natural key collisions** -- If two different source records share the same natural key (e.g., same SKU), only one will be mapped. Resolve the conflict on the source before syncing.

## Queue Issues

### Queue not processing

**Symptoms:** Sync messages are published (pending count increases) but never processed (synced count stays at zero).

**Solutions:**

1. **Start the queue consumer manually**:
   ```bash
   bin/magento queue:consumers:start mageclone.sync.consumer
   ```

2. **Check if the consumer is already running**:
   ```bash
   ps aux | grep mageclone.sync.consumer
   ```

3. **Verify queue configuration** -- Ensure the message queue tables exist:
   ```sql
   SHOW TABLES LIKE 'queue%';
   ```

4. **Check for errors in the consumer output** -- Run the consumer in the foreground to see error messages:
   ```bash
   bin/magento queue:consumers:start mageclone.sync.consumer --max-messages=1
   ```

5. **Verify the message broker connection** -- MageClone uses the `db` connection by default. If you have configured RabbitMQ, ensure it is running and accessible.

## Resource Issues

### Memory errors (PHP Fatal error: Allowed memory size exhausted)

**Symptoms:** Sync fails with a PHP memory limit error.

**Solutions:**

1. **Reduce batch size** -- Lower the batch size in configuration to reduce memory per batch. Start with 10 and increase gradually.

2. **Increase PHP memory_limit** for CLI:
   ```bash
   php -d memory_limit=2G bin/magento queue:consumers:start mageclone.sync.consumer
   ```
   Or update `php.ini`:
   ```ini
   memory_limit = 2G
   ```

3. **Process fewer entity types at once** -- Sync one entity type at a time rather than all at once.

4. **Check for memory leaks** -- If memory usage grows over time within a single consumer process, restart the consumer periodically using `--max-messages`:
   ```bash
   bin/magento queue:consumers:start mageclone.sync.consumer --max-messages=500
   ```

### Media transfer failures

**Symptoms:** Products sync successfully but their images are missing on the destination.

**Solutions:**

1. **Verify the source media URL**:
   ```bash
   curl -I "https://source-store.example.com/pub/media/catalog/product/t/s/tshirt-blue-front.jpg"
   ```
   If you get a 404, the media URL may be incorrect. Check **Stores > Configuration > MageClone > Source Media URL**.

2. **Check file permissions** on the destination:
   ```bash
   ls -la pub/media/catalog/product/
   ```
   The web server user must have write permissions to the media directory.

3. **Check disk space** on the destination:
   ```bash
   df -h
   ```

4. **CDN or proxy issues** -- If the source serves media through a CDN, ensure the CDN URL is configured as the Source Media URL.

## Cron Issues

### Cron not running incremental sync

**Symptoms:** Incremental sync does not run automatically.

**Solutions:**

1. **Verify Magento cron is configured** in the system crontab:
   ```bash
   crontab -l | grep magento
   ```
   Expected entry:
   ```
   * * * * * /usr/bin/php /path/to/magento/bin/magento cron:run >> /path/to/magento/var/log/cron.log 2>&1
   ```

2. **Check the cron schedule table**:
   ```sql
   SELECT * FROM cron_schedule WHERE job_code LIKE '%mageclone%' ORDER BY scheduled_at DESC LIMIT 10;
   ```

3. **Verify the module's crontab.xml is loaded**:
   ```bash
   bin/magento cron:run --group=default
   ```
   Check `var/log/cron.log` for MageClone entries.

4. **Check for cron lock issues** -- If a previous cron run crashed, it may have left a lock:
   ```sql
   SELECT * FROM cron_schedule WHERE job_code LIKE '%mageclone%' AND status = 'running';
   ```
   If there are stale "running" entries, update them:
   ```sql
   UPDATE cron_schedule SET status = 'error' WHERE job_code LIKE '%mageclone%' AND status = 'running';
   ```

## Checking Logs

MageClone writes log entries to Magento's standard log files.

### Application logs

| Log File | Contents |
|---|---|
| `var/log/system.log` | General MageClone messages, sync progress, warnings |
| `var/log/exception.log` | Unhandled exceptions during sync operations |
| `var/log/cron.log` | Cron execution logs (if cron logging is enabled) |

### Viewing recent MageClone log entries

```bash
grep -i "mageclone\|MagentoMigrator" var/log/system.log | tail -50
```

```bash
grep -i "mageclone\|MagentoMigrator" var/log/exception.log | tail -20
```

### Sync log table

For per-record sync details, query the sync log table directly:

```sql
-- View recent failures
SELECT * FROM mageclone_sync_log
WHERE status = 'failed'
ORDER BY created_at DESC
LIMIT 20;

-- Count failures by entity type
SELECT entity_type, COUNT(*) as fail_count
FROM mageclone_sync_log
WHERE status = 'failed'
GROUP BY entity_type;

-- View logs for a specific batch
SELECT * FROM mageclone_sync_log
WHERE batch_id = 'your-batch-id'
ORDER BY log_id ASC;
```

## Getting Further Help

If you cannot resolve an issue using this guide:

1. Collect the following information:
   - Magento version (`bin/magento --version`)
   - PHP version (`php -v`)
   - MageClone module version (check `composer.json`)
   - Relevant log entries from `var/log/system.log` and `var/log/exception.log`
   - Sync status table contents: `SELECT * FROM mageclone_sync_status;`
2. Open an issue on the project repository with the collected information.
