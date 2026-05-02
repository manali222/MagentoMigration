# Installation Guide

## Prerequisites

Before installing MageClone, ensure your environment meets the following requirements:

- **Magento Open Source or Adobe Commerce** 2.4.8 or later
- **PHP** 8.1 or later
- **Composer** 2.x
- SSH or command-line access to both source and destination Magento instances
- Network connectivity between the two instances (the destination must be able to reach the source over HTTPS)

## Step 1: Install on the Source Instance

The source instance is the Magento store you are migrating data **from**. MageClone must be installed here to expose the GraphQL endpoints that serve entity data.

```bash
cd /path/to/source-magento-root

composer require mageclone/magento-migrator

bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

No additional configuration is required on the source instance. The module registers GraphQL resolvers that respond to authenticated queries from the destination.

## Step 2: Install on the Destination Instance

The destination instance is the Magento store you are migrating data **to**. This is where you will configure the connection and run sync operations.

```bash
cd /path/to/destination-magento-root

composer require mageclone/magento-migrator

bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Step 3: Post-Installation Verification

After installation on both instances, verify the module is active:

```bash
bin/magento module:status MageClone_MagentoMigrator
```

Expected output:

```
Module is enabled
```

You can also verify the database tables were created:

```bash
bin/magento setup:db:status
```

The following tables should exist:

| Table Name | Purpose |
|---|---|
| `mageclone_sync_status` | Tracks sync progress per entity type |
| `mageclone_id_mapping` | Maps source entity IDs to destination entity IDs |
| `mageclone_sync_log` | Stores detailed sync log entries |

## Step 4: Generate an API Token on the Source Instance

The destination needs a bearer token to authenticate GraphQL requests against the source. Generate one using the Magento REST API:

```bash
curl -X POST "https://source-store.example.com/rest/V1/integration/admin/token" \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "your-admin-password"}'
```

This returns a token string such as:

```
"abc123def456ghi789jkl012mno345pq"
```

Save this token. You will enter it in the destination instance configuration.

**Security note:** Use a dedicated admin account with only the necessary ACL permissions (`MageClone_MagentoMigrator::sync`) rather than the primary admin account.

## Step 5: Configure the Destination Instance

Navigate to **Stores > Configuration > MageClone > General** in the destination admin panel and enter:

- **Source URL**: The full base URL of the source instance (e.g., `https://source-store.example.com`)
- **API Token**: The bearer token generated in Step 4
- **Batch Size**: Number of records per page (default: 50)

See the [Configuration Guide](CONFIGURATION.md) for full details on all settings.

## Uninstallation

To remove MageClone from an instance:

```bash
bin/magento module:disable MageClone_MagentoMigrator
bin/magento setup:upgrade

composer remove mageclone/magento-migrator

bin/magento setup:di:compile
bin/magento cache:flush
```

To also remove the database tables:

```sql
DROP TABLE IF EXISTS mageclone_sync_status;
DROP TABLE IF EXISTS mageclone_id_mapping;
DROP TABLE IF EXISTS mageclone_sync_log;
```

## Troubleshooting Installation Issues

### Module not found after composer require

Ensure the package repository is configured in your project's `composer.json` or that the package is available on Packagist. If using a private repository:

```bash
composer config repositories.mageclone vcs https://github.com/mageclone/magento-migrator.git
```

### setup:upgrade fails with schema errors

This can occur if the database user does not have CREATE TABLE permissions. Verify your database user has the required privileges:

```sql
GRANT ALL PRIVILEGES ON your_database.* TO 'your_user'@'localhost';
FLUSH PRIVILEGES;
```

### di:compile fails with class not found

Clear the generated code directory and retry:

```bash
rm -rf generated/code/*
bin/magento setup:di:compile
```

### Module shows as disabled after installation

Enable it manually:

```bash
bin/magento module:enable MageClone_MagentoMigrator
bin/magento setup:upgrade
```

### PHP version mismatch

Verify your PHP version meets the minimum requirement:

```bash
php -v
```

MageClone requires PHP 8.1 or later. If your CLI PHP differs from your web server PHP, ensure both meet the requirement.
