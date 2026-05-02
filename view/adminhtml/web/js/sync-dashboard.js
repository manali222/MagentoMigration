/**
 * MageClone MagentoMigrator Sync Dashboard JS
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

define([
    'jquery',
    'Magento_Ui/js/modal/alert'
], function ($, alert) {
    'use strict';

    return function (config) {
        var syncStartUrl = config.syncStartUrl;
        var statusUrl = config.statusUrl;
        var resyncUrl = config.resyncUrl;
        var refreshInterval = null;

        /**
         * Start sync for a specific entity type or all entities
         *
         * @param {string} entityType
         */
        function startSync(entityType) {
            $.ajax({
                url: syncStartUrl,
                type: 'POST',
                data: {
                    entity_type: entityType,
                    form_key: FORM_KEY
                },
                dataType: 'json',
                beforeSend: function () {
                    setButtonsDisabled(true);
                },
                success: function (response) {
                    if (response.success) {
                        startAutoRefresh();
                    } else {
                        alert({content: response.message});
                    }
                },
                error: function () {
                    alert({content: 'An error occurred while starting the sync.'});
                },
                complete: function () {
                    setButtonsDisabled(false);
                }
            });
        }

        /**
         * Refresh the sync status table via AJAX
         */
        function refreshStatus() {
            $.ajax({
                url: statusUrl,
                type: 'GET',
                data: {form_key: FORM_KEY},
                dataType: 'json',
                success: function (data) {
                    if (data.error) {
                        return;
                    }
                    updateTable(data);

                    var anyRunning = data.some(function (item) {
                        return item.status === 'running';
                    });

                    if (anyRunning) {
                        $('#mageclone-auto-refresh-indicator').show();
                    }

                    if (!anyRunning && refreshInterval) {
                        clearInterval(refreshInterval);
                        refreshInterval = null;
                        $('#mageclone-auto-refresh-indicator').hide();
                    }
                }
            });
        }

        /**
         * Update the status table with fresh data
         *
         * @param {Array} statuses
         */
        function updateTable(statuses) {
            statuses.forEach(function (status) {
                var row = $('#entity-row-' + status.entity_type);

                if (row.length) {
                    row.find('.source-count').text(status.source_count);
                    row.find('.dest-count').text(status.destination_count);
                    row.find('.synced-count').text(status.synced_count);
                    row.find('.failed-count').text(status.failed_count);
                    row.find('.pending-count').text(status.pending_count);
                    row.find('.sync-status')
                        .text(formatStatus(status.status))
                        .attr('class', 'sync-status status-' + status.status);
                }
            });
        }

        /**
         * Format a status string for display
         *
         * @param {string} status
         * @returns {string}
         */
        function formatStatus(status) {
            if (!status) {
                return 'Idle';
            }

            return status.replace(/_/g, ' ').replace(/\b\w/g, function (char) {
                return char.toUpperCase();
            });
        }

        /**
         * Start auto-refresh polling every 5 seconds
         */
        function startAutoRefresh() {
            if (!refreshInterval) {
                $('#mageclone-auto-refresh-indicator').show();
                refreshInterval = setInterval(refreshStatus, 5000);
            }
        }

        /**
         * Resync failed records for a specific entity type or all entities
         *
         * @param {string} entityType
         */
        function resyncFailed(entityType) {
            $.ajax({
                url: resyncUrl,
                type: 'POST',
                data: {
                    entity_type: entityType,
                    form_key: FORM_KEY
                },
                dataType: 'json',
                beforeSend: function () {
                    setButtonsDisabled(true);
                },
                success: function (response) {
                    if (response.success) {
                        startAutoRefresh();
                    } else {
                        alert({content: response.message});
                    }
                },
                error: function () {
                    alert({content: 'An error occurred while resyncing failed records.'});
                },
                complete: function () {
                    setButtonsDisabled(false);
                }
            });
        }

        /**
         * Enable or disable action buttons
         *
         * @param {boolean} disabled
         */
        function setButtonsDisabled(disabled) {
            $('.mageclone-sync-btn, #mageclone-sync-all, #mageclone-resync-failed').prop('disabled', disabled);
        }

        // Bind event handlers
        $(document).on('click', '.mageclone-sync-btn', function () {
            var entityType = $(this).data('entity-type');
            startSync(entityType);
        });

        $('#mageclone-sync-all').on('click', function () {
            startSync('all');
        });

        $('#mageclone-resync-failed').on('click', function () {
            var entityType = $(this).data('entity-type') || 'all';
            resyncFailed(entityType);
        });

        // Initial status load
        refreshStatus();
    };
});
