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

        function formatNumber(n) {
            return Number(n).toLocaleString();
        }

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
                    updateSummaryCards(data);

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

        function updateTable(statuses) {
            statuses.forEach(function (status) {
                var row = $('#entity-row-' + status.entity_type);

                if (row.length) {
                    var srcCount = parseInt(status.source_count) || 0;
                    var syncedCount = parseInt(status.synced_count) || 0;
                    var failedCount = parseInt(status.failed_count) || 0;
                    var pct = srcCount > 0 ? Math.round((syncedCount / srcCount) * 100) : 0;

                    row.find('.source-count').text(formatNumber(srcCount));
                    row.find('.synced-count').text(formatNumber(syncedCount));
                    row.find('.failed-count').html(failedCount > 0 ? formatNumber(failedCount) : '&mdash;');

                    // Update progress bar
                    row.find('.bar-fill').css('width', pct + '%');
                    row.find('.mageclone-timestamp').first().text(pct + '%');

                    // Update status badge
                    var statusVal = status.status || 'idle';
                    var badgeHtml = '<span class="mageclone-status-badge badge-' + statusVal + '">' +
                        statusVal.replace(/_/g, ' ').replace(/\b\w/g, function(c){ return c.toUpperCase(); }) +
                        '</span>';
                    row.find('.mageclone-status-badge').replaceWith(badgeHtml);

                    // Update last synced
                    if (status.last_synced_at) {
                        row.find('.mageclone-timestamp').last().text(status.last_synced_at);
                    }
                }
            });
        }

        function updateSummaryCards(statuses) {
            var totalSource = 0, totalSynced = 0, totalFailed = 0, totalPending = 0;

            statuses.forEach(function (s) {
                totalSource += parseInt(s.source_count) || 0;
                totalSynced += parseInt(s.synced_count) || 0;
                totalFailed += parseInt(s.failed_count) || 0;
                totalPending += parseInt(s.pending_count) || 0;
            });

            $('#mageclone-total-source').text(formatNumber(totalSource));
            $('#mageclone-total-synced').text(formatNumber(totalSynced));
            $('#mageclone-total-failed').text(formatNumber(totalFailed));
            $('#mageclone-total-pending').text(formatNumber(totalPending));
        }

        function startAutoRefresh() {
            if (!refreshInterval) {
                $('#mageclone-auto-refresh-indicator').show();
                refreshInterval = setInterval(refreshStatus, 5000);
            }
        }

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

        function setButtonsDisabled(disabled) {
            $('.mageclone-sync-btn, .mageclone-table-btn, #mageclone-sync-all, #mageclone-resync-failed').prop('disabled', disabled);
        }

        // Bind event handlers
        $(document).on('click', '.mageclone-sync-btn, .mageclone-table-btn', function () {
            var entityType = $(this).data('entity-type');
            if (entityType) {
                startSync(entityType);
            }
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
