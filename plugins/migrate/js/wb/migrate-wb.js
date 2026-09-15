/**
 * {literal}
 */
(function ($) {
    "use strict";

    if ($.migrateWb && typeof $.migrateWb.destroy === "function") {
        $.migrateWb.destroy();
    }

    $.migrateWb = {
        init: function ($root) {
            if (!$root || !$root.length) {
                return;
            }

            this.destroyed = false;
            this.$root = $root;
            this.initialToken = this.getToken();
            this.snapshotTimer = null;
            this.snapshotRequest = null;
            this.snapshotInFlight = false;
            this.snapshotGeneration = 0;
            this.accessCheckRequest = null;
            this.accessCheckGeneration = 0;
            this.credentialsTimer = null;
            this.credentialsGeneration = 0;
            this.optionsTimer = null;
            this.optionsInFlight = false;
            this.optionsNeedsSave = false;
            this.optionsCallbacks = [];
            this.optionsLastSaveSucceeded = true;
            this.$optionsChangedControl = $();
            this.mappingInFlight = 0;
            this.mappingBatchSucceeded = true;
            this.mappingCallbacks = [];
            this.runTimer = null;
            this.runInFlight = false;
            this.runId = 0;
            this.runKind = "products";
            this.runActive = false;
            this.imagesImportAvailable = this.isTrue($root.data("images-import-available"));
            this.latestRun = null;
            this.progressDialog = null;
            this.$progressDialog = null;
            this.progressbar = null;
            this.progressFlowTimer = null;
            this.progressFlowSequenceTimers = [];
            this.progressCompletionTimers = [];
            this.progressCompletionRunning = false;
            this.cancelDialog = null;
            this.cleanupDialog = null;
            this.cleanupInFlight = false;
            this.createEntityDialog = null;
            this.createEntityInFlight = false;
            this.modeSwitch = null;
            this.modeSwitchBooting = false;
            this.featureSwitch = null;
            this.featureSwitchBooting = false;
            this.featureSwitchActive = true;
            this.$importActions = this.$root.find(".js-wb-import-actions").first();
            this.$mainSubmit = $();
            this.$mainSubmitOriginalContents = $();
            this.$importActionsPlaceholder = $();
            this.footerHideTimer = null;

            this.attachFooterActions();
            this.bind();
            this.initMappingSections();
            this.initTooltips();
            this.setImagesImportAvailable(this.imagesImportAvailable);
            this.initFeatureSwitch();
            this.initModeSwitch();

            var pendingConnectionResult = $.migrateWbPendingConnectionResult;
            if (pendingConnectionResult) {
                delete $.migrateWbPendingConnectionResult;
                this.renderPermissionResult(
                    pendingConnectionResult.data,
                    pendingConnectionResult.message
                );
            }

            var activeRunId = parseInt(this.$root.data("active-run-id"), 10) || 0;
            if (activeRunId > 0) {
                this.attachActiveRun(
                    activeRunId,
                    this.$root.data("active-run-kind") || "products"
                );
            }
        },

        destroy: function () {
            this.destroyed = true;
            this.stopSnapshotCollection(true);
            this.cancelAccessCheck();
            this.credentialsGeneration++;
            this.clearTimer("credentialsTimer");
            this.clearTimer("optionsTimer");
            this.clearTimer("runTimer");
            this.clearTimer("footerHideTimer");
            this.clearProgressFlowTimers(true);
            this.clearProgressCompletionTimers(true);
            if (this.$root && this.$root.length) {
                this.$root.add(this.$importActions).off(".migrateWb");
            }
            $("#plugin-migrate-transport").off("change.migrateWb");
            this.restoreMainFooter();
            if (this.progressDialog && typeof this.progressDialog.close === "function") {
                this.progressDialog.close();
            }
            if (this.cancelDialog && typeof this.cancelDialog.close === "function") {
                this.cancelDialog.close();
            }
            if (this.cleanupDialog && typeof this.cleanupDialog.close === "function") {
                this.cleanupInFlight = false;
                this.cleanupDialog.close();
            }
            if (this.createEntityDialog && typeof this.createEntityDialog.close === "function") {
                this.createEntityInFlight = false;
                this.createEntityDialog.close();
            }
        },

        bind: function () {
            var self = this;
            var $eventRoots = this.$root.add(this.$importActions);

            $eventRoots.off(".migrateWb");
            $eventRoots.on("click.migrateWb", ".js-wb-save", function () {
                self.saveCredentials($(this));
            });
            $eventRoots.on("click.migrateWb", ".js-wb-test", function () {
                self.testCredentials($(this));
            });
            $eventRoots.on("click.migrateWb", ".js-wb-clean-open", function (event) {
                event.preventDefault();
                self.openCleanupDialog();
            });
            $eventRoots.on("input.migrateWb", "[name=\"wb_api_token\"]", function () {
                delete $.migrateWbPendingConnectionResult;
                self.cancelAccessCheck();
                self.clearStatus(self.$root.find(".js-wb-connection-status"));
                self.clearPermissionWarning();
            });
            $eventRoots.on("keydown.migrateWb", "[name=\"wb_api_token\"]", function (event) {
                if (event.which === 13) {
                    event.preventDefault();
                    self.testCredentials(self.$root.find(".js-wb-test").first());
                }
            });
            $eventRoots.on("click.migrateWb", ".js-wb-load-snapshot", function () {
                self.startSnapshot($(this));
            });
            $eventRoots.on("change.migrateWb", ".js-wb-option", function () {
                self.$optionsChangedControl = $(this);
                self.scheduleOptionsSave();
            });
            $eventRoots.on("change.migrateWb", ".js-wb-feature-mode", function () {
                self.updateFeatureSwitchVisibility();
            });
            $eventRoots.on("focus.migrateWb", ".js-wb-type-map,.js-wb-category-map,.js-wb-stock-map", function () {
                var $select = $(this);
                $select.data("saved-value", $select.val());
                self.populateMappingOptions($select);
            });
            $eventRoots.on("blur.migrateWb", ".js-wb-type-map,.js-wb-category-map,.js-wb-stock-map", function () {
                var $select = $(this);
                window.setTimeout(function () {
                    self.pruneMappingOptions($select);
                }, 0);
            });
            $eventRoots.on("change.migrateWb", ".js-wb-type-map", function () {
                self.saveMapping($(this), "type");
            });
            $eventRoots.on("change.migrateWb", ".js-wb-category-map", function () {
                self.saveMapping($(this), "category");
            });
            $eventRoots.on("change.migrateWb", ".js-wb-stock-map", function () {
                self.saveMapping($(this), "stock");
            });
            $eventRoots.on("click.migrateWb", ".js-wb-create-category", function () {
                self.createCategory($(this));
            });
            $eventRoots.on("click.migrateWb", ".js-wb-create-stock", function () {
                self.createStock($(this));
            });
            $eventRoots.on("click.migrateWb", ".js-wb-start-import", function () {
                self.prepareImport("products", $(this));
            });
            $eventRoots.on("click.migrateWb", ".js-wb-start-images", function () {
                self.prepareImport("images", $(this));
            });
            $eventRoots.on("click.migrateWb", ".js-wb-show-progress", function () {
                self.openProgressDialog();
                if (self.latestRun) {
                    self.renderRun(self.latestRun);
                } else if (self.runId > 0) {
                    self.requestRunStatus();
                }
            });
            $eventRoots.on("click.migrateWb", ".s-wb-mapping-section > summary", function (event) {
                event.preventDefault();
                self.toggleMappingSection($(this).parent("details"));
            });
            $("#plugin-migrate-transport")
                .off("change.migrateWb")
                .on("change.migrateWb", function () {
                    self.destroy();
                });
        },

        initTooltips: function () {
            this.findUi(".js-wb-image-option-tooltip,.js-wb-images-import-tooltip,.js-wb-access-tooltip").each(function () {
                var $tooltip = $(this);
                if (typeof $tooltip.waTooltip === "function") {
                    $tooltip.waTooltip();
                }
            });
        },

        setImagesImportAvailable: function (available) {
            this.imagesImportAvailable = !!available;
            this.$root
                .attr("data-images-import-available", this.imagesImportAvailable ? "1" : "0")
                .data("images-import-available", this.imagesImportAvailable ? 1 : 0);
            this.syncImportButtons();

            var $tooltip = this.findUi(".js-wb-images-import-tooltip").first();
            if (!$tooltip.length) {
                return;
            }
            var tooltipText = String($tooltip.data("disabled-tooltip") || "");
            $tooltip.attr(
                "data-wa-tooltip-content",
                this.imagesImportAvailable ? "" : tooltipText
            );
            if (typeof $tooltip.waTooltip !== "function") {
                return;
            }
            var instance = $tooltip.waTooltip("tooltip");
            if (!instance && !this.imagesImportAvailable) {
                $tooltip.waTooltip();
                instance = $tooltip.waTooltip("tooltip");
            }
            if (!instance || !instance.tippy) {
                return;
            }
            if (typeof instance.tippy.setContent === "function") {
                instance.tippy.setContent(tooltipText);
            }
            if (this.imagesImportAvailable && typeof instance.tippy.disable === "function") {
                instance.tippy.disable();
            } else if (!this.imagesImportAvailable && typeof instance.tippy.enable === "function") {
                instance.tippy.enable();
            }
        },

        syncImportButtons: function () {
            this.findUi(".js-wb-start-import").prop("disabled", !!this.runActive);
            this.findUi(".js-wb-start-images").prop(
                "disabled",
                !!this.runActive || !this.imagesImportAvailable
            );
        },

        releaseImportButton: function ($button) {
            if (!$button || !$button.length) {
                return;
            }
            $button.prop(
                "disabled",
                !!this.runActive || ($button.hasClass("js-wb-start-images") && !this.imagesImportAvailable)
            );
        },

        clearPermissionWarning: function () {
            var $warning = this.$root.find(".js-wb-connection-permission-warning");
            $warning.addClass("hidden");
            $warning.find(".js-wb-permission-intro, .js-wb-permission-outro, .js-wb-permission-notes")
                .empty()
                .addClass("hidden");
            $warning.find(".js-wb-permission-categories")
                .empty()
                .addClass("hidden");
        },

        showPermissionWarning: function (data) {
            data = data || {};

            var $warning = this.$root.find(".js-wb-connection-permission-warning");
            var $intro = $warning.find(".js-wb-permission-intro");
            var $categories = $warning.find(".js-wb-permission-categories");
            var $outro = $warning.find(".js-wb-permission-outro");
            var intro = typeof data.permission_intro === "string"
                ? data.permission_intro
                : String(data.permission_message || data.message || "");
            var outro = String(data.permission_outro || "");
            var missingPermissions = $.isArray(data.missing_permissions) ? data.missing_permissions : [];

            if (data.permission_title) {
                $warning.find(".js-wb-permission-title").text(data.permission_title);
            }
            $intro.text(intro).toggleClass("hidden", !intro);
            $categories.empty();
            $.each(missingPermissions, function (index, permission) {
                permission = permission || {};
                var label = String(permission.label || permission.code || "");
                if (label) {
                    $("<li>").text(label).appendTo($categories);
                }
            });
            $categories.toggleClass("hidden", !$categories.children().length);
            $outro.text(outro).toggleClass("hidden", !outro);
            var $notes = $warning.find(".js-wb-permission-notes").empty();
            $.each(data.permission_warnings || [], function (index, message) {
                $("<p>").text(message).appendTo($notes);
            });
            $notes.toggleClass("hidden", !$notes.children().length);
            $warning.removeClass("hidden");
        },

        renderPermissionResult: function (data, operationMessage) {
            data = data || {};
            this.clearPermissionWarning();

            var accessMessage = String(data.access_message || data.message || "");
            var statusMessage = String(operationMessage || "");
            var pendingChecks = $.isArray(data.pending_permissions) && data.pending_permissions.length > 0;
            if (accessMessage && accessMessage !== statusMessage && (!statusMessage || !pendingChecks)) {
                statusMessage += (statusMessage ? " " : "") + accessMessage;
            }

            if (this.isTrue(data.permission_check_failed)) {
                this.connectionStatus(statusMessage, "error");
                return;
            }

            var accessComplete = this.isTrue(data.access_complete);
            var canCollectProducts = this.isTrue(data.can_collect_products);
            if (!accessComplete || (data.permission_warnings && data.permission_warnings.length)) {
                this.showPermissionWarning(data);
            }
            this.connectionStatus(
                statusMessage,
                !accessComplete && !canCollectProducts
                    ? "error"
                    : (pendingChecks && !operationMessage ? "info" : "success")
            );
        },

        cancelAccessCheck: function () {
            this.accessCheckGeneration++;
            var request = this.accessCheckRequest;
            this.accessCheckRequest = null;
            if (request && typeof request.abort === "function" && request.readyState !== 4) {
                request.abort();
            }
        },

        attachFooterActions: function () {
            var $footer = $("#plugin-migrate-submit").first();
            if (!$footer.length) {
                return;
            }
            if (!this.$importActions.length) {
                var self = this;
                $footer.hide();
                this.footerHideTimer = window.setTimeout(function () {
                    if (!self.destroyed && $.migrateWb === self) {
                        $footer.hide();
                    }
                    self.footerHideTimer = null;
                }, 0);
                return;
            }
            this.$mainSubmit = $footer;
            this.$importActionsPlaceholder = $("<span class=\"js-wb-import-actions-placeholder hidden\" aria-hidden=\"true\"></span>");
            this.$importActions.before(this.$importActionsPlaceholder);
            this.$mainSubmitOriginalContents = $footer.contents().detach();
            this.$importActions.detach().appendTo($footer);
            $footer.show();
        },

        restoreMainFooter: function () {
            if (!this.$mainSubmit || !this.$mainSubmit.length) {
                return;
            }
            if (this.$importActions && this.$importActions.length) {
                this.$importActions.detach();
                if (this.$importActionsPlaceholder && this.$importActionsPlaceholder.length) {
                    this.$importActions.insertBefore(this.$importActionsPlaceholder);
                    this.$importActionsPlaceholder.remove();
                }
            }
            this.$mainSubmit.empty().append(this.$mainSubmitOriginalContents);
            this.$mainSubmit = $();
            this.$mainSubmitOriginalContents = $();
        },

        findUi: function (selector) {
            return this.$root.find(selector).add(this.$importActions.find(selector));
        },

        initMappingSections: function () {
            this.$root.find(".s-wb-mapping-section").each(function () {
                var $section = $(this);
                $section.data("wb-animating", false);
                $section.find(".s-wb-mapping-section__body").first().css("display", "");
            });
        },

        toggleMappingSection: function ($section) {
            if (!$section || !$section.length || $section.data("wb-animating")) {
                return;
            }
            var $body = $section.find(".s-wb-mapping-section__body").first();
            var $arrow = $section.find(".s-wb-mapping-section__arrow").first();
            if (!$body.length) {
                return;
            }

            $section.data("wb-animating", true);
            if ($arrow.length) {
                var arrowAnimationClass = $section.prop("open")
                    ? "is-spinning-counterclockwise"
                    : "is-spinning";
                $arrow.removeClass("is-spinning is-spinning-counterclockwise");
                void $arrow[0].offsetWidth;
                $arrow.addClass(arrowAnimationClass);
                window.setTimeout(function () {
                    $arrow.removeClass("is-spinning is-spinning-counterclockwise");
                }, 300);
            }

            if ($section.prop("open")) {
                $body.stop(true, true).slideUp(300, function () {
                    $section.prop("open", false).data("wb-animating", false);
                    this.style.removeProperty("display");
                });
            } else {
                $section.prop("open", true);
                $body.stop(true, true).hide().slideDown(300, function () {
                    $section.data("wb-animating", false);
                    this.style.removeProperty("display");
                });
            }
        },

        initModeSwitch: function () {
            var self = this;
            var $switch = this.$root.find(".js-wb-mode-switch").first();
            var $checkbox = this.$root.find(".js-wb-mode-checkbox").first();
            if (!$checkbox.length) {
                return;
            }

            var isAuto = $checkbox.is(":checked");
            this.applyMode(isAuto, true);

            if (!$switch.length || typeof $switch.waSwitch !== "function") {
                $checkbox.off("change.migrateWbMode").on("change.migrateWbMode", function () {
                    self.applyMode($(this).is(":checked"), false);
                });
                return;
            }

            this.modeSwitchBooting = true;
            $switch.waSwitch({
                ready: function (waSwitch) {
                    self.modeSwitch = waSwitch;
                    waSwitch.set(isAuto);
                    window.setTimeout(function () {
                        self.modeSwitchBooting = false;
                    }, 0);
                },
                change: function (active) {
                    $checkbox.prop("checked", !!active);
                    self.applyMode(!!active, self.modeSwitchBooting);
                }
            });
        },

        applyMode: function (isAuto, silent) {
            var $label = this.$root.find(".js-wb-mode-label").first();
            var $manualSection = this.$root.find(".js-wb-manual-section").first();
            this.$root.find(".js-wb-mode-checkbox").prop("checked", !!isAuto);
            if ($manualSection.length) {
                if (silent) {
                    $manualSection.stop(true, true).toggle(!isAuto);
                } else {
                    $manualSection.stop(true, false)[isAuto ? "slideUp" : "slideDown"](300);
                    if (!isAuto) {
                        this.scrollToManualSection($manualSection);
                    }
                }
            }
            if ($label.length) {
                $label.text(isAuto ? $label.data("auto-label") : $label.data("manual-label"));
            }
            this.updateFeatureSwitchVisibility();
            if (!silent) {
                this.scheduleOptionsSave();
            }
        },

        scrollToManualSection: function ($manualSection) {
            if (!$manualSection || !$manualSection.length) {
                return;
            }
            var targetTop = Math.max(0, Math.round($manualSection.offset().top - 24));
            var currentTop = $(window).scrollTop();
            if (targetTop > currentTop) {
                $("html, body").stop(true, false).animate({scrollTop: targetTop}, 300);
            }
        },

        initFeatureSwitch: function () {
            var self = this;
            var $switch = this.$root.find(".js-wb-feature-switch").first();
            var $checkbox = this.$root.find(".js-wb-feature-checkbox").first();
            var $tooltip = this.$root.find(".js-wb-feature-switch-tooltip").first();
            if (!$checkbox.length) {
                return;
            }

            this.featureSwitchActive = $checkbox.is(":checked");
            this.updateFeatureSwitchVisual(this.featureSwitchActive);
            this.updateFeatureSwitchVisibility();

            if ($tooltip.length && typeof $tooltip.waTooltip === "function") {
                $tooltip.waTooltip();
            }

            if (!$switch.length || typeof $switch.waSwitch !== "function") {
                $checkbox.off("change.migrateWbFeature").on("change.migrateWbFeature", function () {
                    self.featureSwitchActive = $(this).is(":checked");
                    self.updateFeatureSwitchVisual(self.featureSwitchActive);
                    self.$optionsChangedControl = $(this);
                    self.scheduleOptionsSave();
                });
                return;
            }

            this.featureSwitchBooting = true;
            $switch.waSwitch({
                ready: function (waSwitch) {
                    self.featureSwitch = waSwitch;
                    waSwitch.set(self.featureSwitchActive, false);
                    window.setTimeout(function () {
                        self.featureSwitchBooting = false;
                    }, 0);
                },
                change: function (active) {
                    self.featureSwitchActive = !!active;
                    $checkbox.prop("checked", self.featureSwitchActive);
                    self.updateFeatureSwitchVisual(self.featureSwitchActive);
                    if (!self.featureSwitchBooting) {
                        self.$optionsChangedControl = $checkbox;
                        self.scheduleOptionsSave();
                    }
                }
            });
        },

        updateFeatureSwitchVisibility: function () {
            var featureMode = this.$root.find(".js-wb-feature-mode").first().val() || "auto";
            this.$root.find(".js-wb-feature-switch-wrap").first().toggle(featureMode === "auto");
        },

        updateFeatureSwitchVisual: function (isActive) {
            var $label = this.$root.find(".js-wb-feature-switch-label").first();
            if (!$label.length) {
                return;
            }
            $label.find(".js-wb-feature-switch-label-text").first().text(
                isActive ? $label.data("active-text") : $label.data("inactive-text")
            );
            this.updateFeatureSwitchTooltip(isActive);
        },

        updateFeatureSwitchTooltip: function (isActive) {
            var $label = this.$root.find(".js-wb-feature-switch-label").first();
            var $tooltip = $label.find(".js-wb-feature-switch-tooltip").first();
            if (!$tooltip.length) {
                return;
            }
            var tooltipText = isActive ? ($label.data("active-tooltip") || "") : ($label.data("inactive-tooltip") || "");
            $tooltip.attr("data-wa-tooltip-content", tooltipText);
            if (typeof $tooltip.waTooltip === "function") {
                var instance = $tooltip.waTooltip("tooltip");
                if (instance && instance.tippy && typeof instance.tippy.setContent === "function") {
                    instance.tippy.setContent(tooltipText);
                } else if (!instance) {
                    $tooltip.waTooltip();
                }
            }
        },

        saveCredentials: function ($button) {
            var self = this;
            var collectionWasActive = this.snapshotInFlight
                || String(this.$root.data("snapshot-status") || "") === "building";
            this.stopSnapshotCollection(true);
            this.cancelAccessCheck();
            this.clearPermissionWarning();
            var token = this.getToken();
            if (!token && !parseInt(this.$root.data("has-token"), 10)) {
                this.connectionStatus($_('Enter a Wildberries API token.'), "error");
                return;
            }

            var tokenChanged = token !== "" && token !== this.initialToken;
            var generation = ++this.credentialsGeneration;
            var startedAt = Date.now();
            var stopTimeoutMs = 35000;
            this.clearTimer("credentialsTimer");
            this.setConnectionBusy(true);
            this.connectionStatus(
                collectionWasActive
                    ? $_('Stopping Wildberries data collection...')
                    : $_('Saving...'),
                "loading"
            );

            var finish = function () {
                if (generation !== self.credentialsGeneration) {
                    return;
                }
                self.clearTimer("credentialsTimer");
                self.setConnectionBusy(false);
            };
            var attempt = function () {
                if (self.destroyed || generation !== self.credentialsGeneration) {
                    return;
                }
                self.apiPost(self.$root.data("save-url"), {
                    api_token: token,
                    log_mode: self.$root.find("[name=\"wb_log_mode\"]").val() || "errors"
                }, function (data) {
                    if (generation !== self.credentialsGeneration) {
                        return;
                    }
                    var retryAfterMs = parseInt(data.retry_after_ms, 10);
                    if (!self.isTrue(data.saved) && isFinite(retryAfterMs) && retryAfterMs >= 0) {
                        retryAfterMs = Math.max(200, Math.min(1000, retryAfterMs));
                        if (Date.now() - startedAt + retryAfterMs > stopTimeoutMs) {
                            self.connectionStatus(
                                $_('Could not stop Wildberries data collection. Try saving again.'),
                                "error"
                            );
                            finish();
                            return;
                        }
                        self.connectionStatus(
                            data.message || $_('Stopping Wildberries data collection...'),
                            "loading"
                        );
                        self.credentialsTimer = window.setTimeout(attempt, retryAfterMs);
                        return;
                    }
                    if (!self.isTrue(data.saved)) {
                        self.connectionStatus(
                            data.message || $_('Could not stop Wildberries data collection. Try saving again.'),
                            "error"
                        );
                        finish();
                        return;
                    }

                    if (token !== "") {
                        self.initialToken = token;
                    }
                    self.$root.data("has-token", 1);
                    if (typeof data.collection_revision !== "undefined") {
                        self.$root.data(
                            "collection-revision",
                            parseInt(data.collection_revision, 10) || 0
                        );
                    }
                    if (tokenChanged
                        || token === ""
                        || collectionWasActive
                        || self.isTrue(data.collection_stopped)
                    ) {
                        var currentSnapshotId = parseInt(data.current_snapshot_id, 10) || 0;
                        self.$root.data("snapshot-id", currentSnapshotId);
                        self.$root.data("snapshot-status", currentSnapshotId > 0 ? "ready" : "");
                    }
                    self.renderPermissionResult(data, data.message || $_('API token saved.'));
                    if (tokenChanged
                        || token === ""
                        || collectionWasActive
                        || self.isTrue(data.collection_stopped)
                    ) {
                        $.migrateWbPendingConnectionResult = {
                            data: data,
                            message: data.message || $_('API token saved.')
                        };
                        window.setTimeout(function () {
                            if (!self.destroyed && generation === self.credentialsGeneration) {
                                self.reloadTransport();
                            }
                        }, 250);
                        return;
                    }
                    finish();
                }, function (message) {
                    if (generation !== self.credentialsGeneration) {
                        return;
                    }
                    self.connectionStatus(message, "error");
                    finish();
                }, null, {
                    timeout: 40000
                });
            };

            attempt();
        },

        testCredentials: function () {
            var self = this;
            if (this.snapshotInFlight) {
                return;
            }
            var token = this.getToken();
            if (!token && !parseInt(this.$root.data("has-token"), 10)) {
                this.connectionStatus($_('Enter a Wildberries API token first.'), "error");
                return;
            }

            delete $.migrateWbPendingConnectionResult;
            this.cancelAccessCheck();
            this.clearPermissionWarning();
            var generation = this.accessCheckGeneration;
            this.setConnectionBusy(true);
            this.connectionStatus($_('Checking Wildberries API access...'), "loading");
            var request = this.apiPost(this.$root.data("test-url"), {
                api_token: token,
                log_mode: this.$root.find("[name=\"wb_log_mode\"]").val() || "errors"
            }, function (data) {
                if (generation !== self.accessCheckGeneration || token !== self.getToken()) {
                    return;
                }
                self.renderPermissionResult(data);
            }, function (message) {
                if (generation !== self.accessCheckGeneration) {
                    return;
                }
                self.clearPermissionWarning();
                self.connectionStatus(message, "error");
            }, function () {
                if (generation !== self.accessCheckGeneration) {
                    return;
                }
                self.accessCheckRequest = null;
                self.setConnectionBusy(false);
            }, {
                timeout: 40000
            });
            this.accessCheckRequest = request;
        },

        setConnectionBusy: function (busy) {
            this.$root.find(".js-wb-save").prop("disabled", !!busy);
            this.$root.find(".js-wb-test").prop("disabled", !!busy || this.snapshotInFlight);
            this.$root.find(".s-wb-token-input,.js-wb-log-option").prop("disabled", !!busy);
            this.$root.find(".js-wb-load-snapshot").prop(
                "disabled",
                !!busy || this.snapshotInFlight || !parseInt(this.$root.data("has-token"), 10)
            );
        },

        getToken: function () {
            if (!this.$root || !this.$root.length) {
                return "";
            }
            return $.trim(this.$root.find("[name=\"wb_api_token\"]").val() || "");
        },

        connectionStatus: function (message, state) {
            this.showStatus(this.$root.find(".js-wb-connection-status"), message, state);
        },

        openCleanupDialog: function () {
            var $template = this.$root.find(".js-wb-clean-dialog-template").first();
            if (!$template.length || typeof $.waDialog !== "function") {
                return;
            }
            if (this.cleanupDialog && typeof this.cleanupDialog.close === "function") {
                this.cleanupDialog.close();
            }

            var self = this;
            this.cleanupDialog = $.waDialog({
                html: $template.html(),
                esc: false,
                onOpen: function ($dialog, dialogInstance) {
                    self.bindCleanupDialog($dialog, dialogInstance);
                },
                onClose: function () {
                    if (self.cleanupInFlight) {
                        return false;
                    }
                    self.cleanupDialog = null;
                }
            });
        },

        bindCleanupDialog: function ($dialog, dialogInstance) {
            var self = this;
            var $checkAll = $dialog.find(".js-wb-clean-check-all").first();
            var $tableChecks = $dialog.find(".js-wb-clean-table");

            var syncCheckAll = function () {
                var checkedCount = $tableChecks.filter(":checked").length;
                $checkAll
                    .prop("checked", $tableChecks.length > 0 && checkedCount === $tableChecks.length)
                    .prop("indeterminate", checkedCount > 0 && checkedCount < $tableChecks.length);
            };

            $dialog.on("click", ".js-wb-clean-cancel", function (event) {
                event.preventDefault();
                if (self.cleanupInFlight) {
                    return;
                }
                dialogInstance.close();
            });
            $dialog.on("change", ".js-wb-clean-check-all", function () {
                $tableChecks.prop("checked", $(this).is(":checked"));
                $checkAll.prop("indeterminate", false);
            });
            $dialog.on("change", ".js-wb-clean-table", syncCheckAll);
            $dialog.on("click", ".js-wb-clean-submit", function (event) {
                event.preventDefault();

                var tables = [];
                $tableChecks.filter(":checked").each(function () {
                    tables.push($(this).val());
                });
                if (!tables.length) {
                    window.alert($_('Select at least one table.'));
                    return;
                }
                if (!window.confirm($_('This action permanently clears the selected Wildberries migration tables. Continue only if you know what you are doing.'))) {
                    return;
                }

                var $buttons = $dialog.find(".js-wb-clean-submit,.js-wb-clean-cancel");
                self.cleanupInFlight = true;
                $buttons.prop("disabled", true).attr("aria-disabled", "true");
                self.apiPost(self.$root.data("clean-url"), {
                    tables: tables
                }, function (data) {
                    self.cleanupInFlight = false;
                    dialogInstance.close();
                    self.connectionStatus(
                        data.message || $_('Selected Wildberries migration tables were cleared.'),
                        "success"
                    );
                    window.setTimeout(function () {
                        if (!self.destroyed) {
                            self.reloadTransport();
                        }
                    }, 500);
                }, function (message) {
                    self.cleanupInFlight = false;
                    $buttons.prop("disabled", false).removeAttr("aria-disabled");
                    window.alert(message);
                });
            });

            syncCheckAll();
        },

        startSnapshot: function ($button) {
            var self = this;
            if (this.snapshotInFlight) {
                return;
            }
            if (!parseInt(this.$root.data("has-token"), 10)) {
                this.showStatus(
                    this.$root.find(".js-wb-snapshot-status"),
                    $_('Save a Wildberries API token first.'),
                    "error"
                );
                return;
            }
            if (this.getToken() !== this.initialToken) {
                this.showStatus(
                    this.$root.find(".js-wb-snapshot-status"),
                    $_('Save the API token before loading a snapshot.'),
                    "error"
                );
                return;
            }

            var status = String(this.$root.data("snapshot-status") || "");
            var snapshotId = status === "building"
                ? (parseInt(this.$root.data("snapshot-id"), 10) || 0)
                : 0;
            var collectionRevision = parseInt(this.$root.data("collection-revision"), 10) || 0;
            var generation = ++this.snapshotGeneration;

            this.snapshotInFlight = true;
            $button.prop("disabled", true);
            this.$root.find(".js-wb-test").prop("disabled", true);
            this.showStatus(
                this.$root.find(".js-wb-snapshot-status"),
                $_('Loading Wildberries snapshot...'),
                "loading"
            );

            var requestBatch = function () {
                if (self.destroyed
                    || !self.snapshotInFlight
                    || generation !== self.snapshotGeneration
                ) {
                    return;
                }
                var request = self.apiPost(self.$root.data("load-url"), {
                    snapshot_id: snapshotId,
                    log_mode: self.$root.find("[name=\"wb_log_mode\"]").val() || "errors",
                    collection_revision: collectionRevision
                }, function (data) {
                    if (self.destroyed
                        || !self.snapshotInFlight
                        || generation !== self.snapshotGeneration
                    ) {
                        return;
                    }
                    if (self.isTrue(data.collection_stopped)) {
                        // A Save in another tab cancelled this loop. Keep the
                        // new revision only for a later, explicit button click.
                        // Never retry this request with the updated revision.
                        self.$root.data(
                            "collection-revision",
                            parseInt(data.collection_revision, 10) || 0
                        );
                        self.$root.data("snapshot-id", 0);
                        self.$root.data("snapshot-status", "");
                        self.snapshotGeneration++;
                        self.finishSnapshot(false);
                        self.$root.find(".js-wb-load-snapshot").text($_('Get snapshot'));
                        self.showStatus(
                            self.$root.find(".js-wb-snapshot-status"),
                            data.message || $_('Wildberries data collection was stopped. Start it again.'),
                            "error"
                        );
                        return;
                    }
                    var retryAfterMs = parseInt(data && data.retry_after_ms, 10);
                    data = self.unwrapDataObject(data, "snapshot");
                    if (!isFinite(retryAfterMs)) {
                        retryAfterMs = parseInt(data.retry_after_ms, 10);
                    }
                    snapshotId = parseInt(data.snapshot_id || data.id, 10) || snapshotId;
                    if (snapshotId > 0) {
                        self.$root.data("snapshot-id", snapshotId);
                    }

                    var responseStatus = String(data.status || "");
                    var message = data.message || $_('Loading Wildberries snapshot...');
                    self.showStatus(
                        self.$root.find(".js-wb-snapshot-status"),
                        message,
                        responseStatus === "failed" ? "error" : "loading"
                    );

                    if (responseStatus === "failed") {
                        self.$root.data("snapshot-status", "failed");
                        self.$root.data("snapshot-id", 0);
                        self.finishSnapshot(false);
                        return;
                    }
                    if (self.isTrue(data.done) || responseStatus === "ready") {
                        self.finishSnapshot(true);
                        self.showStatus(
                            self.$root.find(".js-wb-snapshot-status"),
                            data.message || $_('Wildberries snapshot is ready.'),
                            "success"
                        );
                        self.reloadTransport();
                        return;
                    }
                    if (snapshotId <= 0) {
                        self.finishSnapshot(false);
                        self.showStatus(
                            self.$root.find(".js-wb-snapshot-status"),
                            $_('Snapshot progress is invalid. Start loading again.'),
                            "error"
                        );
                        return;
                    }
                    var nextDelay = isFinite(retryAfterMs) && retryAfterMs >= 0
                        ? retryAfterMs
                        : 650;
                    self.snapshotTimer = window.setTimeout(requestBatch, nextDelay);
                }, function (message) {
                    if (self.destroyed
                        || !self.snapshotInFlight
                        || generation !== self.snapshotGeneration
                    ) {
                        return;
                    }
                    self.finishSnapshot(false);
                    self.showStatus(
                        self.$root.find(".js-wb-snapshot-status"),
                        message,
                        "error"
                    );
                }, function () {
                    if (self.snapshotRequest === request) {
                        self.snapshotRequest = null;
                    }
                });
                self.snapshotRequest = request;
            };

            requestBatch();
        },

        finishSnapshot: function (success) {
            this.snapshotInFlight = false;
            this.clearTimer("snapshotTimer");
            this.$root.find(".js-wb-load-snapshot").prop("disabled", false);
            this.$root.find(".js-wb-test").prop("disabled", false);
            if (!success) {
                this.$root.removeClass("is-loading");
            }
        },

        stopSnapshotCollection: function (silent) {
            var wasActive = !!this.snapshotInFlight || this.snapshotTimer !== null;
            var request = this.snapshotRequest;
            this.snapshotGeneration++;
            this.snapshotInFlight = false;
            this.clearTimer("snapshotTimer");
            this.snapshotRequest = null;
            if (request && typeof request.abort === "function" && request.readyState !== 4) {
                wasActive = true;
                request.abort();
            }
            if (!silent && this.$root && this.$root.length) {
                this.clearStatus(this.$root.find(".js-wb-snapshot-status"));
            }
            if (this.$root && this.$root.length) {
                this.$root.removeClass("is-loading");
                this.$root.find(".js-wb-load-snapshot").prop(
                    "disabled",
                    !parseInt(this.$root.data("has-token"), 10)
                );
                this.$root.find(".js-wb-test").prop("disabled", false);
            }
            return wasActive;
        },

        scheduleOptionsSave: function () {
            var self = this;
            this.clearTimer("optionsTimer");
            this.optionsTimer = window.setTimeout(function () {
                self.saveOptions();
            }, 250);
        },

        saveOptions: function (onSuccess, onFailure) {
            this.clearTimer("optionsTimer");
            if (typeof onSuccess === "function" || typeof onFailure === "function") {
                this.optionsCallbacks.push({
                    success: onSuccess,
                    failure: onFailure
                });
            }
            if (this.optionsInFlight) {
                this.optionsNeedsSave = true;
                return;
            }
            this.performOptionsSave();
        },

        performOptionsSave: function () {
            var self = this;
            if (!this.$root.find(".js-wb-option").length) {
                this.flushOptionsCallbacks(true);
                return;
            }

            this.optionsInFlight = true;
            this.optionsNeedsSave = false;
            this.optionsLastSaveSucceeded = false;
            var $statusTargets = this.optionsStatusTargets(this.$optionsChangedControl);
            this.$optionsChangedControl = $();
            this.showStatus(
                $statusTargets,
                $_('Saving settings...'),
                "loading"
            );
            this.apiPost(this.$root.data("options-url"), this.serializeOptions(), function () {
                self.optionsLastSaveSucceeded = true;
                self.showStatus(
                    $statusTargets,
                    $_('Settings saved.'),
                    "success"
                );
            }, function (message) {
                self.optionsLastSaveSucceeded = false;
                self.showStatus(
                    $statusTargets,
                    message,
                    "error"
                );
            }, function () {
                self.optionsInFlight = false;
                if (self.optionsNeedsSave) {
                    self.performOptionsSave();
                    return;
                }
                self.flushOptionsCallbacks(self.optionsLastSaveSucceeded);
            });
        },

        flushOptionsCallbacks: function (succeeded) {
            var callbacks = this.optionsCallbacks.slice(0);
            this.optionsCallbacks = [];
            $.each(callbacks, function (_, callback) {
                var fn = succeeded ? callback.success : callback.failure;
                if (typeof fn === "function") {
                    fn();
                }
            });
        },

        optionsStatusTargets: function ($control) {
            if ($control && $control.length && $control.is("[name=\"wb_log_mode\"]")) {
                return this.$root.find(".js-wb-log-options-status");
            }
            return this.$root.find(".js-wb-options-status");
        },

        serializeOptions: function () {
            var result = {};
            var $mode = this.$root.find(".js-wb-mode-checkbox");
            var $logMode = this.$root.find("[name=\"wb_log_mode\"]");
            var $featureMode = this.$root.find("[name=\"wb_feature_mode\"]");
            var $featureForceText = this.$root.find(".js-wb-feature-checkbox").first();
            var $imageMode = this.$root.find("[name=\"wb_image_mode\"]:checked");

            if ($mode.length) {
                result.mode = $mode.is(":checked") ? "auto" : "manual";
            }
            if ($logMode.length) {
                result.log_mode = $logMode.val() || "errors";
            }
            if ($featureMode.length) {
                result.feature_mode = $featureMode.val() || "auto";
            }
            if ($featureForceText.length) {
                result.feature_force_text = $featureForceText.is(":checked") ? 0 : 1;
            }
            if ($imageMode.length) {
                result.image_mode = $imageMode.val() || "immediate";
            }
            return result;
        },

        saveMapping: function ($select, kind) {
            var self = this;
            var url = this.mappingUrl(kind);
            if (!url) {
                return;
            }

            var previousValue = $select.data("saved-value");
            var payload = this.mappingPayload($select, kind);
            this.beginMappingRequest();
            $select.prop("disabled", true);
            this.showStatus(
                this.$root.find(".js-wb-mapping-status"),
                $_('Saving mapping...'),
                "loading"
            );
            this.apiPost(url, payload, function () {
                $select.data("saved-value", $select.val());
                self.showStatus(
                    self.$root.find(".js-wb-mapping-status"),
                    $_('Mapping saved.'),
                    "success"
                );
            }, function (message) {
                self.mappingBatchSucceeded = false;
                if (typeof previousValue !== "undefined") {
                    $select.val(previousValue);
                }
                self.showStatus(
                    self.$root.find(".js-wb-mapping-status"),
                    message,
                    "error"
                );
            }, function () {
                $select.prop("disabled", false);
                self.pruneMappingOptions($select);
                self.endMappingRequest();
            });
        },

        mappingKind: function ($select) {
            if ($select.is(".js-wb-type-map")) {
                return "type";
            }
            if ($select.is(".js-wb-category-map")) {
                return "category";
            }
            return "stock";
        },

        populateMappingOptions: function ($select) {
            var kind = this.mappingKind($select);
            var $source = this.$root.find(".js-wb-map-options-source[data-kind=\"" + kind + "\"]").first();
            if (!$source.length) {
                return;
            }
            $source.find("option").each(function () {
                var $option = $(this);
                var value = String($option.val());
                if (!$select.find("option[value=\"" + value.replace(/\"/g, "\\\"") + "\"]").length) {
                    $option.clone().appendTo($select);
                }
            });
            $select.val(String($select.data("saved-value") || $select.val() || ""));
        },

        pruneMappingOptions: function ($select) {
            if (!$select || !$select.length || $select.is(":focus") || $select.prop("disabled")) {
                return;
            }
            var selected = String($select.val() || "");
            $select.find("option").each(function () {
                var value = String($(this).val() || "");
                if (value !== "" && value !== "create" && value !== "skip" && value !== selected) {
                    $(this).remove();
                }
            });
        },

        beginMappingRequest: function () {
            if (this.mappingInFlight === 0) {
                this.mappingBatchSucceeded = true;
            }
            this.mappingInFlight++;
        },

        endMappingRequest: function () {
            this.mappingInFlight = Math.max(0, this.mappingInFlight - 1);
            if (this.mappingInFlight > 0) {
                return;
            }
            var callbacks = this.mappingCallbacks.slice(0);
            var succeeded = this.mappingBatchSucceeded;
            this.mappingCallbacks = [];
            $.each(callbacks, function (_, callback) {
                var fn = succeeded ? callback.success : callback.failure;
                if (typeof fn === "function") {
                    fn();
                }
            });
        },

        waitForMappings: function (onSuccess, onFailure) {
            if (this.mappingInFlight <= 0) {
                (this.mappingBatchSucceeded ? onSuccess : onFailure)();
                return;
            }
            this.mappingCallbacks.push({success: onSuccess, failure: onFailure});
        },

        mappingUrl: function (kind) {
            var keys = {
                type: "type-map-url",
                category: "category-map-url",
                stock: "stock-map-url"
            };
            return keys[kind] ? this.$root.data(keys[kind]) : "";
        },

        mappingPayload: function ($select, kind) {
            var choice = this.mappingChoice($select.val());
            var payload = {
                snapshot_id: parseInt(this.$root.data("snapshot-id"), 10) || 0,
                mode: "manual",
                action: choice.action
            };

            if (kind === "type") {
                payload.parent_id = parseInt($select.data("parent-id"), 10) || 0;
                payload.subject_id = parseInt($select.data("subject-id"), 10) || 0;
                payload.shop_type_id = choice.id;
            } else if (kind === "category") {
                payload.entity_type = $select.data("entity-type") || "subject";
                payload.wb_id = parseInt($select.data("wb-id"), 10) || 0;
                payload.parent_id = parseInt($select.data("parent-id"), 10) || 0;
                payload.wb_parent_id = payload.parent_id;
                payload.shop_category_id = choice.id;
            } else if (kind === "stock") {
                payload.warehouse_key = String($select.data("warehouse-key") || "");
                payload.shop_stock_id = choice.id;
            }
            return payload;
        },

        mappingChoice: function (value) {
            value = String(value || "");
            if (value === "") {
                return {action: "auto", id: 0};
            }
            if (value === "create" || value === "skip") {
                return {action: value, id: 0};
            }
            return {action: "map", id: parseInt(value, 10) || 0};
        },

        createCategory: function ($button) {
            var $row = $button.closest("tr");
            var $select = $row.find(".js-wb-category-map").first();
            var defaultName = $.trim(String($row.data("create-name") || ""));
            if (!defaultName) {
                defaultName = $.trim($row.find(".js-wb-source-name").text());
            }
            var entityType = $select.data("entity-type") || "subject";
            var wbParentId = parseInt($select.data("parent-id"), 10) || 0;
            var shopParentId = 0;
            if (entityType === "subject" && wbParentId > 0) {
                this.$root.find(".js-wb-category-map").each(function () {
                    var $candidate = $(this);
                    if (
                        String($candidate.data("entity-type")) === "parent"
                        && (parseInt($candidate.data("wb-id"), 10) || 0) === wbParentId
                    ) {
                        var selectedParent = parseInt($candidate.val(), 10) || 0;
                        if (selectedParent > 0) {
                            shopParentId = selectedParent;
                        }
                        return false;
                    }
                });
            }
            this.openCreateEntityDialog({
                kind: "category",
                templateSelector: ".js-wb-create-category-dialog-template",
                url: this.$root.data("create-category-url"),
                defaultName: defaultName,
                defaultParentId: shopParentId,
                $select: $select,
                $button: $button,
                payload: {
                    snapshot_id: parseInt(this.$root.data("snapshot-id"), 10) || 0,
                    entity_type: entityType,
                    wb_id: parseInt($select.data("wb-id"), 10) || 0,
                    wb_parent_id: wbParentId
                }
            });
        },

        createStock: function ($button) {
            var $row = $button.closest("tr");
            var $select = $row.find(".js-wb-stock-map").first();
            var defaultName = $.trim($row.find(".js-wb-source-name").text());
            this.openCreateEntityDialog({
                kind: "stock",
                templateSelector: ".js-wb-create-stock-dialog-template",
                url: this.$root.data("create-stock-url"),
                defaultName: defaultName,
                $select: $select,
                $button: $button,
                payload: {
                    snapshot_id: parseInt(this.$root.data("snapshot-id"), 10) || 0,
                    warehouse_key: String($select.data("warehouse-key") || "")
                }
            });
        },

        openCreateEntityDialog: function (options) {
            var self = this;
            var $template = this.$root.find(options.templateSelector).first();
            if (!$template.length || typeof $.waDialog !== "function") {
                this.showStatus(
                    this.$root.find(".js-wb-mapping-status"),
                    $_('This Shop-Script version cannot open the creation dialog.'),
                    "error"
                );
                return;
            }
            if (this.createEntityDialog) {
                return;
            }
            this.createEntityDialog = $.waDialog({
                html: $template.html(),
                onOpen: function ($dialog, dialogInstance) {
                    self.createEntityDialog = dialogInstance;
                    self.bindCreateEntityDialog($dialog, dialogInstance, options);
                },
                onClose: function () {
                    if (self.createEntityInFlight) {
                        return false;
                    }
                    self.createEntityDialog = null;
                }
            });
        },

        bindCreateEntityDialog: function ($dialog, dialogInstance, options) {
            var self = this;
            var $form = $dialog.find(".js-wb-create-dialog-form").first();
            var $name = $dialog.find(".js-wb-create-name").first();
            var $parent = $dialog.find(".js-wb-create-parent").first();
            var $parentHint = $dialog.find(".js-wb-create-parent-hint").first();
            var $error = $dialog.find(".js-wb-create-dialog-error").first();
            var $submit = $dialog.find(".js-wb-create-dialog-submit").first();
            var $cancel = $dialog.find(".js-wb-create-dialog-cancel");

            $name.val(options.defaultName || "");
            if ($parent.length) {
                if (options.payload.entity_type === "subject") {
                    $parent.find("option[value=\"0\"]").text($_('Wildberries parent category (automatic)'));
                    $parentHint.text($_('The Wildberries parent category will be matched or created automatically.'));
                }
                this.$root.find(".js-wb-map-options-source[data-kind=\"category\"] option").each(function () {
                    $(this).clone().appendTo($parent);
                });
                $parent.val(String(options.defaultParentId || 0));
                if ($parent.val() === null) {
                    $parent.val("0");
                }
            }
            window.setTimeout(function () {
                $name.trigger("focus").trigger("select");
            }, 0);

            $cancel.on("click", function (event) {
                event.preventDefault();
                if (!self.createEntityInFlight) {
                    dialogInstance.close();
                }
            });
            $form.on("submit", function (event) {
                event.preventDefault();
                if (self.createEntityInFlight) {
                    return;
                }
                var name = $.trim($name.val() || "");
                if (!name) {
                    self.showStatus($error, options.kind === "category" ? $_('Enter a category name.') : $_('Enter a stock name.'), "error");
                    $name.trigger("focus");
                    return;
                }

                var payload = $.extend({}, options.payload, {name: name});
                if ($parent.length) {
                    payload.shop_parent_id = parseInt($parent.val(), 10) || 0;
                }
                self.createEntityInFlight = true;
                self.beginMappingRequest();
                options.$button.prop("disabled", true);
                $name.add($parent).add($submit).add($cancel).prop("disabled", true);
                self.showStatus($error, $_('Creating...'), "loading");

                self.apiPost(options.url, payload, function (data) {
                    var entity = self.createdEntity(data, options.kind, name);
                    if (!entity.id) {
                        self.mappingBatchSucceeded = false;
                        self.showStatus($error, options.kind === "category"
                            ? $_('The category was created, but its ID was not returned.')
                            : $_('The stock was created, but its ID was not returned.'), "error");
                        return;
                    }
                    self.appendOptionToMaps(options.kind, options.$select, entity.id, entity.name);
                    options.$select.val(String(entity.id)).data("saved-value", String(entity.id));
                    if (options.kind === "category" && options.payload.entity_type === "subject") {
                        self.syncCreatedCategoryParent(options.payload.wb_parent_id, data);
                    }
                    self.showStatus(self.$root.find(".js-wb-mapping-status"), $_('Mapping saved.'), "success");
                    self.createEntityInFlight = false;
                    dialogInstance.close();
                }, function (message) {
                    self.mappingBatchSucceeded = false;
                    self.showStatus($error, message, "error");
                }, function () {
                    if (self.createEntityDialog) {
                        $name.add($parent).add($submit).add($cancel).prop("disabled", false);
                    }
                    options.$button.prop("disabled", false);
                    self.createEntityInFlight = false;
                    self.endMappingRequest();
                });
            });
        },

        createdEntity: function (data, kind, fallbackName) {
            data = data || {};
            var nested = data[kind] || data.entity || data;
            var id = parseInt(
                nested.id ||
                nested[kind + "_id"] ||
                nested["shop_" + kind + "_id"] ||
                data[kind + "_id"] ||
                data["shop_" + kind + "_id"],
                10
            ) || 0;
            return {
                id: id,
                name: nested.name || data.name || fallbackName
            };
        },

        syncCreatedCategoryParent: function (wbParentId, data) {
            var self = this;
            var parent = data && data.parent_category ? data.parent_category : {};
            var parentId = parseInt(parent.id || data.shop_parent_id, 10) || 0;
            var parentName = parent.name || data.shop_parent_name || "";
            if (!parentId || !wbParentId) {
                return;
            }
            this.$root.find(".js-wb-category-map").each(function () {
                var $select = $(this);
                if (
                    String($select.data("entity-type")) === "parent"
                    && (parseInt($select.data("wb-id"), 10) || 0) === parseInt(wbParentId, 10)
                ) {
                    self.appendOptionToMaps("category", $select, parentId, parentName);
                    $select.val(String(parentId)).data("saved-value", String(parentId));
                    return false;
                }
            });
        },

        appendOptionToMaps: function (kind, $select, id, name) {
            var selector = "option[value=\"" + id + "\"]";
            var $source = this.$root.find(".js-wb-map-options-source[data-kind=\"" + kind + "\"]").first();
            if ($source.length && !$source.find(selector).length) {
                $("<option></option>").val(String(id)).text(name).appendTo($source);
            }
            if (!$select.find(selector).length) {
                $("<option></option>").val(String(id)).text(name).appendTo($select);
            }
        },

        prepareImport: function (kind, $button) {
            var self = this;
            if (this.runId > 0) {
                this.openProgressDialog();
                if (this.latestRun) {
                    this.renderRun(this.latestRun);
                }
                return;
            }
            var snapshotId = parseInt(this.$root.data("snapshot-id"), 10) || 0;
            if (snapshotId <= 0 || String(this.$root.data("snapshot-status")) !== "ready") {
                this.showStatus(
                    this.findUi(".js-wb-import-status"),
                    $_('Load a completed Wildberries snapshot first.'),
                    "error"
                );
                return;
            }
            if (this.getToken() !== this.initialToken) {
                this.showStatus(
                    this.findUi(".js-wb-import-status"),
                    $_('Save the API token before starting import.'),
                    "error"
                );
                return;
            }

            $button.prop("disabled", true);
            this.showStatus(
                this.findUi(".js-wb-import-status"),
                $_('Preparing import...'),
                "loading"
            );
            var failPreparation = function () {
                self.releaseImportButton($button);
                self.showStatus(
                    self.findUi(".js-wb-import-status"),
                    $_('Save all mappings and import settings before starting.'),
                    "error"
                );
            };
            this.waitForMappings(function () {
                self.saveOptions(function () {
                    self.startImport(kind, $button);
                }, failPreparation);
            }, failPreparation);
        },

        startImport: function (kind, $button) {
            var self = this;
            var payload = this.serializeOptions();
            payload.mode = this.$root.find(".js-wb-mode-checkbox").is(":checked") ? "auto" : "manual";
            payload.log_mode = this.$root.find("[name=\"wb_log_mode\"]").val() || "errors";
            payload.feature_mode = this.$root.find("[name=\"wb_feature_mode\"]").val() || "auto";
            payload.feature_force_text = this.$root.find(".js-wb-feature-checkbox").first().is(":checked") ? 0 : 1;
            payload.image_mode = this.$root.find("[name=\"wb_image_mode\"]:checked").val() || "immediate";
            payload.snapshot_id = parseInt(this.$root.data("snapshot-id"), 10) || 0;
            payload.kind = kind;

            this.apiPost(this.$root.data("import-start-url"), payload, function (data) {
                var run = self.normalizeRun(data);
                if (!run.run_id) {
                    self.releaseImportButton($button);
                    self.showStatus(
                        self.findUi(".js-wb-import-status"),
                        $_('The server did not return an import run ID.'),
                        "error"
                    );
                    return;
                }
                self.runId = run.run_id;
                self.runKind = run.kind || kind;
                self.latestRun = run;
                self.setRunActive(true);
                self.openProgressDialog();
                self.updateRun(run);
                if (!self.runIsTerminal(run)) {
                    self.scheduleAdvance(100);
                }
            }, function (message) {
                self.releaseImportButton($button);
                self.showStatus(
                    self.findUi(".js-wb-import-status"),
                    message,
                    "error"
                );
            });
        },

        attachActiveRun: function (runId, kind) {
            this.runId = parseInt(runId, 10) || 0;
            this.runKind = kind || "products";
            if (!this.runId) {
                return;
            }
            this.setRunActive(true);
            this.openProgressDialog();
            this.requestRunStatus();
        },

        requestRunStatus: function () {
            var self = this;
            if (this.destroyed || this.runId <= 0) {
                return;
            }
            if (this.runInFlight) {
                this.scheduleRunStatus(350);
                return;
            }
            this.runInFlight = true;
            this.apiPost(this.$root.data("import-status-url"), {
                run_id: this.runId
            }, function (data) {
                var run = self.normalizeRun(data);
                self.updateRun(run);
                if (!self.runIsTerminal(run)) {
                    self.scheduleAdvance(150);
                }
            }, function (message) {
                self.renderRunError(message);
                self.scheduleRunStatus(1800);
            }, function () {
                self.runInFlight = false;
            });
        },

        advanceRun: function () {
            var self = this;
            if (this.destroyed || this.runId <= 0) {
                return;
            }
            if (this.runInFlight) {
                this.scheduleAdvance(350);
                return;
            }

            this.runInFlight = true;
            this.apiPost(this.$root.data("import-advance-url"), {
                run_id: this.runId
            }, function (data) {
                var run = self.normalizeRun(data);
                self.updateRun(run);
                if (!self.runIsTerminal(run)) {
                    self.scheduleAdvance(150);
                }
            }, function (message) {
                self.renderRunError(message);
                self.scheduleRunStatus(1800);
            }, function () {
                self.runInFlight = false;
            });
        },

        scheduleAdvance: function (delay) {
            var self = this;
            this.clearTimer("runTimer");
            if (this.destroyed || this.runId <= 0) {
                return;
            }
            this.runTimer = window.setTimeout(function () {
                self.advanceRun();
            }, delay || 0);
        },

        scheduleRunStatus: function (delay) {
            var self = this;
            this.clearTimer("runTimer");
            if (this.destroyed || this.runId <= 0) {
                return;
            }
            this.runTimer = window.setTimeout(function () {
                self.requestRunStatus();
            }, delay || 0);
        },

        updateRun: function (run) {
            if (!run.run_id) {
                run.run_id = this.runId;
            }
            this.runId = run.run_id || this.runId;
            this.runKind = run.kind || this.runKind;
            this.latestRun = run;
            var currentSnapshotId = parseInt(this.$root.data("snapshot-id"), 10) || 0;
            if (run.kind === "products" && parseInt(run.snapshot_id, 10) === currentSnapshotId) {
                var created = parseInt(run.counters.created, 10) || 0;
                var updated = parseInt(run.counters.updated, 10) || 0;
                if (created + updated > 0) {
                    this.setImagesImportAvailable(true);
                }
            }
            this.renderRun(run);
            this.showStatus(
                this.findUi(".js-wb-import-status"),
                run.message || $_('Import is running.'),
                this.runIsTerminal(run)
                    ? (run.status === "completed" ? "success" : (run.status === "failed" ? "error" : "info"))
                    : "loading"
            );
            if (this.runIsTerminal(run)) {
                this.finishRun(run);
            }
        },

        finishRun: function (run) {
            this.clearTimer("runTimer");
            this.setRunActive(false);
            this.renderRun(run);
            this.runId = 0;
            this.$root.data("active-run-id", 0);
        },

        setRunActive: function (active) {
            this.runActive = !!active;
            this.syncImportButtons();
            this.findUi(".js-wb-show-progress").toggle(!!active);
            if (active) {
                this.$root.data("active-run-id", this.runId);
            }
        },

        normalizeRun: function (data) {
            data = this.unwrapDataObject(data, "run");
            var counters = data.counters && typeof data.counters === "object"
                ? data.counters
                : {};
            var total = parseInt(data.total, 10);
            if (!isFinite(total)) {
                total = parseInt(counters.total, 10) || 0;
            }
            var processed = parseInt(data.processed, 10);
            if (!isFinite(processed)) {
                processed = parseInt(counters.processed, 10) || 0;
            }
            var progress = parseFloat(data.progress);
            if (!isFinite(progress)) {
                progress = total > 0 ? processed * 100 / total : 0;
            }
            progress = Math.max(0, Math.min(100, progress));

            return $.extend({}, data, {
                run_id: parseInt(data.run_id || data.id, 10) || this.runId || 0,
                kind: data.kind || this.runKind || "products",
                status: String(data.status || "running"),
                done: this.isTrue(data.done),
                cancel_requested: this.isTrue(data.cancel_requested),
                cancelled: this.isTrue(data.cancelled),
                total: Math.max(0, total),
                processed: Math.max(0, processed),
                progress: progress,
                counters: counters
            });
        },

        runIsTerminal: function (run) {
            return !!run.done || $.inArray(run.status, ["completed", "cancelled", "failed"]) !== -1;
        },

        openProgressDialog: function () {
            var self = this;
            if (this.$progressDialog && this.$progressDialog.length) {
                return;
            }
            if (typeof $.waDialog !== "function") {
                this.showStatus(
                    this.findUi(".js-wb-import-status"),
                    this.latestRun && this.latestRun.message
                        ? this.latestRun.message
                        : $_('Import is running.'),
                    "loading"
                );
                return;
            }

            this.progressDialog = $.waDialog({
                html: this.progressDialogHtml(),
                onOpen: function ($dialog, dialogInstance) {
                    self.$progressDialog = $dialog;
                    self.progressDialog = dialogInstance;
                    self.bindProgressDialog($dialog, dialogInstance);
                    self.initProgressbar();
                    if (self.latestRun) {
                        self.renderRun(self.latestRun);
                    } else {
                        self.scheduleProgressFlowScene();
                    }
                },
                onClose: function () {
                    self.clearProgressFlowTimers(true);
                    self.clearProgressCompletionTimers(true);
                    self.$progressDialog = null;
                    self.progressDialog = null;
                    self.progressbar = null;
                    /* Closing or pressing Esc deliberately does not touch the run timer. */
                }
            });
        },

        bindProgressDialog: function ($dialog, dialogInstance) {
            var self = this;
            $dialog.on("click", ".js-wb-progress-close", function (event) {
                event.preventDefault();
                dialogInstance.close();
            });
            $dialog.on("click", ".js-wb-progress-cancel", function (event) {
                event.preventDefault();
                self.openCancelDialog();
            });
            $dialog.on("click", ".s-wb-import-flow__icon,.s-wb-import-flow__arrows", function () {
                var $flow = $(this).closest(".s-wb-import-flow");
                if ($flow.hasClass("is-completion-scene") || $flow.hasClass("is-complete")) {
                    return;
                }
                self.clearProgressCompletionTimers(true);
                self.runProgressFlowScene(true);
            });
        },

        progressDialogHtml: function () {
            var wbIcon = this.escapeHtml(String(this.$root.data("wb-icon-url") || ""));
            var shopIcon = this.escapeHtml(String(this.$root.data("shop-icon-url") || ""));
            var triangleSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" focusable="false"><path d="M160 352C147.1 352 135.4 359.8 130.4 371.8C125.4 383.8 128.2 397.5 137.4 406.6L297.4 566.6C309.9 579.1 330.2 579.1 342.7 566.6L502.7 406.6C511.9 397.4 514.6 383.7 509.6 371.7C504.6 359.7 492.9 352 480 352L160 352z"></path></svg>';
            var title = this.runKind === "images"
                ? $_('Wildberries image import')
                : $_('Wildberries product import');
            return [
                '<div class="dialog" id="js-wb-progress-dialog">',
                    '<div class="dialog-background"></div>',
                    '<div class="dialog-body">',
                        '<a href="#" class="dialog-close js-wb-progress-close" aria-label="' + this.escapeHtml($_('Close')) + '"><i class="fas fa-times"></i></a>',
                        '<header class="dialog-header"><h2>' + this.escapeHtml(title) + '</h2></header>',
                        '<div class="dialog-content">',
                            '<div class="s-wb-import-flow">',
                                '<div class="s-wb-import-flow__icon s-wb-import-flow__icon--wb">',
                                    '<img class="s-wb-import-flow__glow" src="' + wbIcon + '" alt="" aria-hidden="true">',
                                    '<img class="s-wb-import-flow__logo" src="' + wbIcon + '" alt="' + this.escapeHtml($_('Wildberries')) + '">',
                                '</div>',
                                '<div class="s-wb-import-flow__arrows" aria-hidden="true">',
                                    '<span class="s-wb-import-flow__chev s-wb-import-flow__chev--1"><span class="s-wb-import-flow__chev-motion">' + triangleSvg + '</span></span>',
                                    '<span class="s-wb-import-flow__chev s-wb-import-flow__chev--2"><span class="s-wb-import-flow__chev-motion">' + triangleSvg + '</span></span>',
                                    '<span class="s-wb-import-flow__chev s-wb-import-flow__chev--3"><span class="s-wb-import-flow__chev-motion">' + triangleSvg + '</span></span>',
                                '</div>',
                                 '<div class="s-wb-import-flow__icon s-wb-import-flow__icon--shop">',
                                     '<img class="s-wb-import-flow__glow" src="' + shopIcon + '" alt="" aria-hidden="true">',
                                     '<img class="s-wb-import-flow__logo" src="' + shopIcon + '" alt="' + this.escapeHtml($_('Shop-Script')) + '">',
                                 '</div>',
                                 '<div class="s-wb-import-flow__completion-arrows" aria-hidden="true"></div>',
                                 '<div class="s-wb-import-flow__completion-stars" aria-hidden="true"></div>',
                             '</div>',
                            '<div class="s-wb-progress-wrap">',
                                '<div class="progressbar js-wb-progressbar"></div>',
                                '<div class="s-wb-progress-text js-wb-progress-text">' + this.escapeHtml($_('Preparing import...')) + '</div>',
                            '</div>',
                            '<div class="s-wb-progress-message js-wb-progress-message"></div>',
                            '<div class="s-wb-progress-details js-wb-progress-details"></div>',
                            '<div class="s-wb-progress-errors js-wb-progress-errors" role="alert" style="display:none;"></div>',
                            '<p class="hint s-wb-dialog-note">' + this.escapeHtml($_('Closing this window does not stop the import.')) + '</p>',
                            '<p class="s-wb-unpriced-summary js-wb-unpriced-summary" role="status" style="display:none;"></p>',
                        '</div>',
                        '<footer class="dialog-footer">',
                             '<button type="button" class="button red js-wb-progress-cancel">' + this.escapeHtml($_('Cancel import')) + '</button> ',
                             '<button type="button" class="button light-gray js-wb-progress-close">' + this.escapeHtml($_('Close')) + '</button>',
                         '</footer>',
                    '</div>',
                '</div>'
            ].join("");
        },

        scheduleProgressFlowScene: function (delay) {
            var self = this;
            if (this.progressFlowTimer || this.progressFlowSequenceTimers.length) {
                return;
            }
            if (!this.isProgressFlowSceneAvailable()) {
                return;
            }
            var sceneDelay = typeof delay === "number"
                ? delay
                : 40000 + Math.floor(Math.random() * 10001);
            this.progressFlowTimer = setTimeout(function () {
                self.progressFlowTimer = null;
                self.runProgressFlowScene();
            }, sceneDelay);
        },

        runProgressFlowScene: function (force) {
            var self = this;
            if (!force && !this.isProgressFlowSceneAvailable()) {
                this.clearProgressFlowTimers(true);
                return;
            }
            if (force && (!this.$progressDialog || !this.$progressDialog.length ||
                !$.contains(document, this.$progressDialog[0]))) {
                return;
            }
            this.clearProgressFlowTimers(true);

            var $flow = this.$progressDialog.find(".s-wb-import-flow").first();
            var $arrows = $flow.find(".s-wb-import-flow__arrows").first();
            if (!$flow.length || !$arrows.length) {
                return;
            }

            if (force) {
                $flow.removeClass("is-complete");
            }
            var arrowLayoutIndex = Math.floor(Math.random() * 3);
            var arrowLayoutClass = arrowLayoutIndex === 0
                ? "is-arrow-layout-1"
                : (arrowLayoutIndex === 1
                    ? "is-arrow-layout-2"
                    : (Math.random() < 0.5 ? "is-arrow-layout-3a" : "is-arrow-layout-3b"));
            var arrowFirst = Math.random() < 0.7;
            $flow.addClass("is-rare-scene");
            $arrows.addClass(arrowLayoutClass);
            this.triggerProgressFlowKick($flow, "wb");

            this.addProgressFlowSequenceTimer(function () {
                $arrows.addClass(arrowFirst ? "is-arrow-spread" : "is-triforce is-triforce-forming");
            }, 360);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "shop");
            }, 1140);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "wb");
            }, 1740);
            this.addProgressFlowSequenceTimer(function () {
                if (arrowFirst) {
                    self.applyProgressFlowArrowStep($arrows, "", "is-arrow-step-1");
                } else {
                    $arrows.removeClass("is-triforce-forming");
                    self.applyProgressFlowTriforceStep($arrows);
                }
            }, 2100);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "shop");
            }, 2720);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "wb");
            }, 3320);
            this.addProgressFlowSequenceTimer(function () {
                if (arrowFirst) {
                    self.applyProgressFlowArrowStep($arrows, "is-arrow-step-1", "is-arrow-step-2");
                } else {
                    self.applyProgressFlowTriforceStep($arrows);
                }
            }, 3680);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "shop");
            }, 4300);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "wb");
            }, 4900);
            this.addProgressFlowSequenceTimer(function () {
                if (arrowFirst) {
                    self.applyProgressFlowArrowStep($arrows, "is-arrow-step-2", "is-arrow-step-3");
                } else {
                    $arrows
                        .removeClass("is-triforce is-triforce-forming is-triforce-impact")
                        .addClass("is-arrow-spread");
                }
            }, 5260);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "shop");
            }, 5980);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "wb");
            }, 6580);
            this.addProgressFlowSequenceTimer(function () {
                if (arrowFirst) {
                    $arrows
                        .removeClass("is-arrow-spread is-arrow-step-1 is-arrow-step-2 is-arrow-step-3 is-chevron-spin is-arrow-leveling is-arrow-impact is-arrow-shake-only")
                        .addClass("is-triforce is-triforce-forming");
                } else {
                    self.applyProgressFlowArrowStep($arrows, "", "is-arrow-step-1");
                }
            }, 6940);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "shop");
            }, 7560);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "wb");
            }, 8160);
            this.addProgressFlowSequenceTimer(function () {
                if (arrowFirst) {
                    $arrows.removeClass("is-triforce-forming");
                    self.applyProgressFlowTriforceStep($arrows);
                } else {
                    self.applyProgressFlowArrowStep($arrows, "is-arrow-step-1", "is-arrow-step-2");
                }
            }, 8520);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "shop");
            }, 9140);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "wb");
            }, 9740);
            this.addProgressFlowSequenceTimer(function () {
                if (arrowFirst) {
                    self.applyProgressFlowTriforceStep($arrows);
                } else {
                    self.applyProgressFlowArrowStep($arrows, "is-arrow-step-2", "is-arrow-step-3");
                }
            }, 10100);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "shop");
            }, 10720);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "wb");
            }, 11320);
            this.addProgressFlowSequenceTimer(function () {
                $arrows
                    .removeClass("is-triforce is-triforce-forming is-triforce-impact is-arrow-step-1 is-arrow-step-2 is-arrow-step-3 is-arrow-spread is-chevron-spin is-arrow-leveling is-arrow-impact is-arrow-shake-only")
                    .addClass("is-returning");
            }, 11680);
            this.addProgressFlowSequenceTimer(function () {
                self.triggerProgressFlowKick($flow, "shop");
            }, 13130);
            this.addProgressFlowSequenceTimer(function () {
                $flow.removeClass("is-rare-scene");
                $arrows.removeClass("is-returning is-chevron-spin is-arrow-leveling is-arrow-impact is-arrow-shake-only is-arrow-layout-1 is-arrow-layout-2 is-arrow-layout-3a is-arrow-layout-3b");
                self.restartProgressFlowAnimations();
                self.progressFlowSequenceTimers = [];
                if (self.latestRun && self.runIsTerminal(self.latestRun)) {
                    $flow.addClass("is-complete");
                }
                /* 13.78 seconds have already elapsed: keep scene starts 40-50 seconds apart. */
                self.scheduleProgressFlowScene(26220 + Math.floor(Math.random() * 10001));
            }, 13780);
        },

        runProgressCompletionScene: function () {
            if (!this.$progressDialog || !this.$progressDialog.length ||
                !$.contains(document, this.$progressDialog[0])) {
                return;
            }

            var self = this;
            var $flow = this.$progressDialog.find(".s-wb-import-flow").first();
            if (!$flow.length) {
                return;
            }
            if ($flow.hasClass("is-completion-scene") || this.progressCompletionRunning) {
                return;
            }

            this.clearProgressFlowTimers(true);
            this.clearProgressCompletionTimers(true);
            this.buildProgressCompletionArrows($flow);
            this.buildProgressCompletionStars($flow);

            var $wb = $flow.find(".s-wb-import-flow__icon--wb").first();
            var $shop = $flow.find(".s-wb-import-flow__icon--shop").first();
            if (!$wb.length || !$shop.length) {
                return;
            }
            var flowRect = $flow[0].getBoundingClientRect();
            var wbRect = $wb[0].getBoundingClientRect();
            var shopRect = $shop[0].getBoundingClientRect();
            var wbShiftX = (shopRect.left + shopRect.width / 2) - (wbRect.left + wbRect.width / 2);
            var shopShiftX = (flowRect.left + flowRect.width / 2) - (shopRect.left + shopRect.width / 2);
            $flow[0].style.setProperty("--wb-completion-shift-x", wbShiftX + "px");
            $flow[0].style.setProperty("--shop-completion-shift-x", shopShiftX + "px");

            this.progressCompletionRunning = true;
            $flow.removeClass("is-complete").addClass("is-completion-scene");

            this.addProgressCompletionTimer(function () {
                $flow.addClass("is-completion-transfer");
            }, 1250);
            this.addProgressCompletionTimer(function () {
                $flow.removeClass("is-completion-transfer").addClass("is-completion-absorb");
            }, 5650);
            this.addProgressCompletionTimer(function () {
                $flow.addClass("is-completion-center");
            }, 6750);
            this.addProgressCompletionTimer(function () {
                $flow.addClass("is-completion-stars");
            }, 7870);
            this.addProgressCompletionTimer(function () {
                $flow.addClass("is-completion-finished is-complete");
                self.progressCompletionRunning = false;
                self.progressCompletionTimers = [];
            }, 11350);
        },

        buildProgressCompletionArrows: function ($flow) {
            var $layer = $flow.find(".s-wb-import-flow__completion-arrows").first().empty();
            var sourceSvg = $flow.find(".s-wb-import-flow__chev svg").first()[0];
            if (!$layer.length || !sourceSvg) {
                return;
            }

            var count = 42;
            for (var i = 0; i < count; i++) {
                var particle = document.createElement("span");
                var delay = (i / (count - 1)) * 3.22 + Math.random() * 0.12;
                particle.className = "s-wb-import-flow__completion-arrow";
                particle.style.setProperty("--particle-x", (5 + Math.random() * 30).toFixed(1) + "px");
                particle.style.setProperty("--particle-y", (-62 + Math.random() * 94).toFixed(1) + "px");
                particle.style.setProperty("--particle-travel", (158 + Math.random() * 38).toFixed(1) + "px");
                particle.style.setProperty("--particle-drop", (10 + Math.random() * 25).toFixed(1) + "px");
                particle.style.setProperty("--particle-size", (7 + Math.random() * 7).toFixed(1) + "px");
                particle.style.setProperty("--particle-tilt", (-7 + Math.random() * 14).toFixed(1) + "deg");
                particle.style.setProperty("--particle-delay", delay.toFixed(3) + "s");
                particle.style.setProperty("--particle-duration", (0.56 + Math.random() * 0.28).toFixed(3) + "s");
                particle.appendChild(sourceSvg.cloneNode(true));
                $layer[0].appendChild(particle);
            }
        },

        buildProgressCompletionStars: function ($flow) {
            var $layer = $flow.find(".s-wb-import-flow__completion-stars").first().empty();
            if (!$layer.length) {
                return;
            }

            var count = 34;
            for (var i = 0; i < count; i++) {
                var star = document.createElement("span");
                var angle = Math.random() * Math.PI * 2;
                var distance = 42 + Math.random() * 82;
                star.className = "s-wb-import-flow__completion-star";
                star.style.setProperty("--star-x", (Math.cos(angle) * distance).toFixed(1) + "px");
                star.style.setProperty("--star-y", (Math.sin(angle) * distance * 0.68 - 8).toFixed(1) + "px");
                star.style.setProperty("--star-size", (5 + Math.random() * 10).toFixed(1) + "px");
                star.style.setProperty("--star-scale", (0.72 + Math.random() * 0.7).toFixed(2));
                star.style.setProperty("--star-rotate", (120 + Math.random() * 320).toFixed(1) + "deg");
                star.style.setProperty("--star-delay", (Math.random() * 1.02).toFixed(3) + "s");
                star.style.setProperty("--star-duration", (1.45 + Math.random() * 0.62).toFixed(3) + "s");
                $layer[0].appendChild(star);
            }
        },

        addProgressCompletionTimer: function (callback, delay) {
            this.progressCompletionTimers.push(setTimeout(callback, delay));
        },

        applyProgressFlowArrowStep: function ($arrows, previousStep, nextStep) {
            var nextClasses = nextStep;
            var motion = Math.floor(Math.random() * 4);
            var spinChevrons = motion === 0 || motion === 2;
            var levelArrows = motion === 2;
            var shakeOnly = motion === 3;
            var shakeArrows = shakeOnly || Math.random() < 0.35;
            var startAngle = 0;
            if (!$arrows.hasClass("is-arrow-leveling")) {
                startAngle = previousStep === "is-arrow-step-1" ? -12 :
                    (previousStep === "is-arrow-step-2" ? 12 : 0);
            }
            // Keep the full group turn in the existing variant, even after a level pose.
            if (motion === 1 && nextStep === "is-arrow-step-2") {
                startAngle -= 360;
            }
            if (spinChevrons) {
                nextClasses += " is-chevron-spin";
            }
            if (levelArrows) {
                nextClasses += " is-arrow-leveling";
            }
            if (shakeArrows) {
                nextClasses += " is-arrow-impact";
            }
            if (shakeOnly) {
                nextClasses += " is-arrow-shake-only";
            }
            $arrows
                .removeClass((previousStep ? previousStep + " " : "") + "is-chevron-spin is-arrow-leveling is-arrow-impact is-arrow-shake-only")
                .css("--s-wb-arrow-start-angle", startAngle + "deg")
                .css("--s-wb-arrow-end-scale", nextStep === "is-arrow-step-3" ? "1.04" : "1");
            if ((spinChevrons || shakeArrows) && $arrows.length) {
                void $arrows[0].offsetWidth;
            }
            $arrows.addClass(nextClasses);
        },

        applyProgressFlowTriforceStep: function ($arrows) {
            $arrows.removeClass("is-triforce-forming is-triforce-impact");
            if ($arrows.length) {
                void $arrows[0].offsetWidth;
            }
            $arrows.addClass("is-triforce-impact");
        },

        triggerProgressFlowKick: function ($flow, target) {
            if (!$flow || !$flow.length) {
                return;
            }
            var className = target === "shop" ? "is-shop-kick" : "is-wb-kick";
            $flow.removeClass("is-wb-kick is-shop-kick");
            void $flow[0].offsetWidth;
            $flow.addClass(className);
            this.addProgressFlowSequenceTimer(function () {
                $flow.removeClass(className);
            }, 500);
        },

        addProgressFlowSequenceTimer: function (callback, delay) {
            this.progressFlowSequenceTimers.push(setTimeout(callback, delay));
        },

        restartProgressFlowAnimations: function () {
            if (!this.$progressDialog || !this.$progressDialog.length) {
                return;
            }
            var $flow = this.$progressDialog.find(".s-wb-import-flow").first();
            if (!$flow.length) {
                return;
            }
            $flow.addClass("is-animation-reset");
            void $flow[0].offsetWidth;
            $flow.removeClass("is-animation-reset");
        },

        isProgressFlowSceneAvailable: function () {
            if (!this.$progressDialog || !this.$progressDialog.length ||
                !$.contains(document, this.$progressDialog[0])) {
                return false;
            }
            if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
                return false;
            }
            if (this.latestRun && this.runIsTerminal(this.latestRun)) {
                return false;
            }
            var $flow = this.$progressDialog.find(".s-wb-import-flow").first();
            return $flow.length && !$flow.hasClass("is-complete") && !$flow.hasClass("is-completion-scene");
        },

        clearProgressCompletionTimers: function (resetClasses) {
            $.each(this.progressCompletionTimers || [], function (index, timer) {
                clearTimeout(timer);
            });
            this.progressCompletionTimers = [];
            this.progressCompletionRunning = false;

            if (resetClasses !== false && this.$progressDialog && this.$progressDialog.length) {
                var $flow = this.$progressDialog.find(".s-wb-import-flow").first();
                $flow.removeClass("is-completion-scene is-completion-transfer is-completion-absorb is-completion-center is-completion-stars is-completion-finished is-complete");
                if ($flow.length) {
                    $flow[0].style.removeProperty("--wb-completion-shift-x");
                    $flow[0].style.removeProperty("--shop-completion-shift-x");
                }
                $flow.find(".s-wb-import-flow__completion-arrows,.s-wb-import-flow__completion-stars").empty();
            }
        },

        clearProgressFlowTimers: function (resetClasses) {
            if (this.progressFlowTimer) {
                clearTimeout(this.progressFlowTimer);
                this.progressFlowTimer = null;
            }
            $.each(this.progressFlowSequenceTimers || [], function (index, timer) {
                clearTimeout(timer);
            });
            this.progressFlowSequenceTimers = [];

            if (resetClasses !== false && this.$progressDialog && this.$progressDialog.length) {
                this.$progressDialog.find(".s-wb-import-flow")
                    .removeClass("is-rare-scene is-animation-reset is-wb-kick is-shop-kick");
                this.$progressDialog.find(".s-wb-import-flow__arrows")
                    .removeClass("is-triforce is-triforce-forming is-triforce-impact is-arrow-spread is-arrow-flight is-arrow-step-1 is-arrow-step-2 is-arrow-step-3 is-chevron-spin is-arrow-leveling is-arrow-impact is-arrow-shake-only is-returning is-arrow-layout-1 is-arrow-layout-2 is-arrow-layout-3a is-arrow-layout-3b is-rare-orbit is-rare-wave is-rare-prism");
            }
        },

        initProgressbar: function () {
            if (!this.$progressDialog || !this.$progressDialog.length) {
                return;
            }
            var $bar = this.$progressDialog.find(".js-wb-progressbar").first();
            this.progressbar = null;
            if (typeof $bar.waProgressbar === "function") {
                $bar.waProgressbar({
                    percentage: 0,
                    display_text: false
                });
                this.progressbar = $bar.data("progressbar") || null;
            } else {
                $bar.html('<div class="progressbar-line-wrapper"><div class="progressbar-outer"><div class="progressbar-inner" style="width:0%;"></div></div></div>');
            }
        },

        renderRun: function (run) {
            if (!this.$progressDialog || !this.$progressDialog.length || !run) {
                return;
            }
            var progress = Math.max(0, Math.min(100, parseFloat(run.progress) || 0));
            var $bar = this.$progressDialog.find(".js-wb-progressbar").first();
            if (this.progressbar && typeof this.progressbar.set === "function") {
                this.progressbar.set({percentage: progress});
            } else {
                $bar.find(".progressbar-inner").css("width", progress + "%");
            }
            $bar.attr("aria-valuenow", progress);

            var progressText = run.total > 0
                ? run.processed + " / " + run.total + " (" + this.formatPercent(progress) + "%)"
                : this.formatPercent(progress) + "%";
            this.$progressDialog.find(".js-wb-progress-text").text(progressText);
            this.$progressDialog.find(".js-wb-progress-message").text(
                run.message || this.runFallbackMessage(run)
            );
            this.$progressDialog.find(".js-wb-progress-details").text(
                this.runDetails(run)
            );
            var errorText = this.formatErrors(run.errors || []);
            this.$progressDialog.find(".js-wb-progress-errors")
                .text(errorText)
                .toggle(!!errorText);

            var terminal = this.runIsTerminal(run);
            this.renderUnpricedSummary(run, terminal);
            var $flow = this.$progressDialog.find(".s-wb-import-flow").first();
            if (terminal) {
                this.clearProgressFlowTimers(true);
                if (run.status === "completed") {
                    this.runProgressCompletionScene();
                } else {
                    this.clearProgressCompletionTimers(true);
                    $flow.addClass("is-complete");
                }
            } else {
                if ($flow.hasClass("is-completion-scene")) {
                    this.clearProgressCompletionTimers(true);
                }
                $flow.removeClass("is-complete");
                this.scheduleProgressFlowScene();
            }
            var cancelling = run.cancel_requested || run.status === "cancel_requested";
            var $cancel = this.$progressDialog.find(".js-wb-progress-cancel");
            $cancel.prop("disabled", cancelling || terminal).toggle(!terminal);
            if (cancelling) {
                $cancel.text($_('Cancellation requested'));
            }
        },

        renderRunError: function (message) {
            if (this.$progressDialog && this.$progressDialog.length) {
                this.$progressDialog.find(".js-wb-progress-message").text(
                    message || $_('Connection lost. Retrying...')
                );
            }
            this.showStatus(
                this.findUi(".js-wb-import-status"),
                message || $_('Connection lost. Retrying...'),
                "error"
            );
        },

        renderUnpricedSummary: function (run, terminal) {
            var $summary = this.$progressDialog.find(".js-wb-unpriced-summary").empty().hide();
            var hash = String(run.unpriced_products_hash || "");
            if (!terminal || run.kind !== "products" || !(parseInt(run.unpriced_products_count, 10) > 0)
                || !run.unpriced_products_message || !/^migrate_wb_unpriced\/[1-9]\d*$/.test(hash)) {
                return;
            }
            var productsUrl = String(this.$root.data("products-url") || "?action=products");
            $summary.text(run.unpriced_products_message + " ");
            $("<a></a>")
                .attr("href", productsUrl + "#/products/hash=" + encodeURIComponent(hash))
                .attr("target", "_blank")
                .attr("rel", "noopener")
                .text($_('View products'))
                .appendTo($summary);
            $summary.show();
        },

        runFallbackMessage: function (run) {
            if (run.status === "completed") {
                return run.kind === "images"
                    ? $_('Wildberries product images have been imported.')
                    : $_('Wildberries products have been imported into Shop-Script.');
            }
            if (run.status === "cancelled") {
                return $_('Wildberries import was cancelled after completing the current product.');
            }
            if (run.status === "failed") {
                return $_('Wildberries import failed.');
            }
            if (run.cancel_requested || run.status === "cancel_requested") {
                return $_('Cancellation requested. The current product will be completed first.');
            }
            return $_('Import is running.');
        },

        runDetails: function (run) {
            var counters = run.counters || {};
            var parts = [];
            if (typeof counters.created !== "undefined") {
                parts.push($_('Created') + ": " + (parseInt(counters.created, 10) || 0));
            }
            if (typeof counters.updated !== "undefined") {
                parts.push($_('Updated') + ": " + (parseInt(counters.updated, 10) || 0));
            }
            if (typeof counters.skipped !== "undefined") {
                parts.push($_('Skipped') + ": " + (parseInt(counters.skipped, 10) || 0));
            }
            if (typeof counters.failed !== "undefined") {
                parts.push($_('Failed') + ": " + (parseInt(counters.failed, 10) || 0));
            }
            if (typeof counters.images_imported !== "undefined") {
                parts.push($_('Images imported') + ": " + (parseInt(counters.images_imported, 10) || 0));
            }
            if (typeof counters.image_errors !== "undefined" && parseInt(counters.image_errors, 10) > 0) {
                parts.push($_('Image errors') + ": " + parseInt(counters.image_errors, 10));
            }
            return parts.join(", ");
        },

        openCancelDialog: function () {
            var self = this;
            if (this.runId <= 0 || this.runIsTerminal(this.latestRun || {})) {
                return;
            }
            if (typeof $.waDialog !== "function") {
                if (window.confirm($_('Cancel the import after the current product is completed?'))) {
                    this.requestCancel();
                }
                return;
            }
            if (this.cancelDialog) {
                return;
            }

            this.cancelDialog = $.waDialog({
                html: this.cancelDialogHtml(),
                onOpen: function ($dialog, dialogInstance) {
                    self.cancelDialog = dialogInstance;
                    $dialog.on("click", ".js-wb-cancel-dismiss", function (event) {
                        event.preventDefault();
                        dialogInstance.close();
                    });
                    $dialog.on("click", ".js-wb-cancel-confirm", function (event) {
                        event.preventDefault();
                        self.requestCancel($dialog, dialogInstance);
                    });
                },
                onClose: function () {
                    self.cancelDialog = null;
                }
            });
        },

        cancelDialogHtml: function () {
            return [
                '<div class="dialog" id="js-wb-cancel-dialog">',
                    '<div class="dialog-background"></div>',
                    '<div class="dialog-body">',
                        '<a href="#" class="dialog-close js-wb-cancel-dismiss" aria-label="' + this.escapeHtml($_('Close')) + '"><i class="fas fa-times"></i></a>',
                        '<header class="dialog-header"><h2>' + this.escapeHtml($_('Cancel Wildberries import?')) + '</h2></header>',
                        '<div class="dialog-content">',
                            '<p>' + this.escapeHtml($_('The importer will finish the product currently being processed and then stop.')) + '</p>',
                            '<p class="hint">' + this.escapeHtml($_('Products completed before cancellation will remain in Shop-Script.')) + '</p>',
                            '<div class="s-wb-dialog-error js-wb-cancel-error"></div>',
                        '</div>',
                        '<footer class="dialog-footer">',
                            '<button type="button" class="button red js-wb-cancel-confirm">' + this.escapeHtml($_('Yes, cancel import')) + '</button> ',
                            '<button type="button" class="button light-gray js-wb-cancel-dismiss">' + this.escapeHtml($_('Continue import')) + '</button>',
                        '</footer>',
                    '</div>',
                '</div>'
            ].join("");
        },

        requestCancel: function ($dialog, dialogInstance) {
            var self = this;
            if (this.runId <= 0) {
                return;
            }
            if ($dialog && $dialog.length) {
                $dialog.find(".js-wb-cancel-confirm,.js-wb-cancel-dismiss").prop("disabled", true);
                $dialog.find(".js-wb-cancel-error").text($_('Requesting cancellation...'));
            }
            this.apiPost(this.$root.data("import-cancel-url"), {
                run_id: this.runId
            }, function (data) {
                var run = self.normalizeRun(data);
                self.updateRun(run);
                if (dialogInstance && typeof dialogInstance.close === "function") {
                    dialogInstance.close();
                }
                if (!self.runIsTerminal(run)) {
                    self.scheduleRunStatus(150);
                }
            }, function (message) {
                if ($dialog && $dialog.length) {
                    $dialog.find(".js-wb-cancel-confirm,.js-wb-cancel-dismiss").prop("disabled", false);
                    $dialog.find(".js-wb-cancel-error").text(message);
                } else {
                    self.renderRunError(message);
                }
            });
        },

        apiPost: function (url, data, onSuccess, onFailure, onAlways, requestOptions) {
            var self = this;
            if (!url) {
                if (typeof onFailure === "function") {
                    onFailure($_('The action URL is missing.'));
                }
                if (typeof onAlways === "function") {
                    onAlways();
                }
                return null;
            }

            return $.ajax($.extend({}, {
                url: url,
                method: "POST",
                data: data || {},
                dataType: "json"
            }, requestOptions || {})).done(function (response) {
                if (self.destroyed) {
                    return;
                }
                if (response && response.status === "ok") {
                    if (typeof onSuccess === "function") {
                        onSuccess(response.data || {}, response);
                    }
                    return;
                }
                var message = self.responseError(response);
                if (typeof onFailure === "function") {
                    onFailure(message, response || {});
                }
            }).fail(function (xhr) {
                if (self.destroyed) {
                    return;
                }
                var message = $_('Request failed.');
                if (xhr && xhr.responseJSON) {
                    message = self.responseError(xhr.responseJSON);
                }
                if (typeof onFailure === "function") {
                    onFailure(message, xhr);
                }
            }).always(function () {
                if (!self.destroyed && typeof onAlways === "function") {
                    onAlways();
                }
            });
        },

        responseError: function (response) {
            if (response && response.errors) {
                var errors = this.formatErrors(response.errors);
                if (errors) {
                    return errors;
                }
            }
            if (response && response.error) {
                return String(response.error);
            }
            if (response && response.message) {
                return String(response.message);
            }
            return $_('Invalid server response.');
        },

        formatErrors: function (errors) {
            var messages = [];
            var collect = function (value) {
                if (value === null || typeof value === "undefined") {
                    return;
                }
                if ($.isArray(value)) {
                    $.each(value, function (_, item) {
                        collect(item);
                    });
                    return;
                }
                if (typeof value === "object") {
                    $.each(value, function (_, item) {
                        collect(item);
                    });
                    return;
                }
                value = $.trim(String(value));
                if (value) {
                    messages.push(value);
                }
            };
            collect(errors);
            return messages.join(", ");
        },

        showStatus: function ($target, message, state) {
            if (!$target || !$target.length) {
                return;
            }
            var iconClass = "";
            if (state === "loading") {
                iconClass = "fas fa-spinner wa-animation-spin text-gray";
            } else if (state === "success") {
                iconClass = "fas fa-check-circle text-green";
            } else if (state === "error") {
                iconClass = "fas fa-exclamation-circle text-red";
            } else if (state === "info") {
                iconClass = "fas fa-info-circle text-gray";
            }

            $target.empty().removeClass("text-green text-red text-gray");
            if (state === "success") {
                $target.addClass("text-green");
            } else if (state === "error") {
                $target.addClass("text-red");
            } else {
                $target.addClass("text-gray");
            }
            if (iconClass) {
                $("<i></i>").addClass(iconClass).appendTo($target);
                $target.append(document.createTextNode(" "));
            }
            $target.append(document.createTextNode(message || ""));
        },

        clearStatus: function ($target) {
            if ($target && $target.length) {
                $target.empty().removeClass("text-green text-red text-gray");
            }
        },

        reloadTransport: function () {
            var self = this;
            var $fields = $("#plugin-migrate-transport-fields");
            var transport = $("#plugin-migrate-transport").val();
            if (!$fields.length || !transport) {
                window.location.reload();
                return;
            }

            this.$root.css("min-height", this.$root.outerHeight()).addClass("is-loading");
            $.ajax({
                url: "?plugin=migrate&action=transport",
                method: "GET",
                data: {transport: transport},
                dataType: "html"
            }).done(function (response) {
                // Keep scripts inert while replacing the transport HTML, then
                // execute each one exactly once in document order.
                var $response = $("<div></div>").append($.parseHTML(response, document, true));
                var $scripts = $response.find("script").filter(function () {
                    var type = ($(this).attr("type") || "").toLowerCase().split(";")[0];
                    return !type || type === "text/javascript" || type === "application/javascript";
                }).remove();
                $fields.empty().append($response.contents());
                $scripts.each(function () {
                    var $script = $(this);
                    if ($script.attr("src")) {
                        $.ajax({
                            url: $script.attr("src"),
                            dataType: "script",
                            cache: true,
                            async: false
                        });
                    } else {
                        $.globalEval($script.html());
                    }
                });
            }).fail(function () {
                delete $.migrateWbPendingConnectionResult;
                self.$root.removeClass("is-loading").css("min-height", "");
                self.setConnectionBusy(false);
                self.showStatus(
                    self.$root.find(".js-wb-snapshot-status"),
                    $_('Unable to refresh the import screen.'),
                    "error"
                );
            });
        },

        unwrapDataObject: function (data, key) {
            if (data && data[key] && typeof data[key] === "object") {
                return data[key];
            }
            return data || {};
        },

        formatPercent: function (value) {
            value = Math.round((parseFloat(value) || 0) * 10) / 10;
            var result = value % 1 === 0 ? String(Math.round(value)) : String(value);
            return this.isRussianLocale() ? result.replace(".", ",") : result;
        },

        isRussianLocale: function () {
            var locale = String(this.$root && this.$root.data("locale") || "").toLowerCase();
            return locale === "ru" || locale.indexOf("ru_") === 0 || locale.indexOf("ru-") === 0;
        },

        isTrue: function (value) {
            return value === true || value === 1 || value === "1" || value === "true";
        },

        escapeHtml: function (value) {
            return $("<div></div>").text(value === null || typeof value === "undefined" ? "" : String(value)).html();
        },

        clearTimer: function (name) {
            if (this[name]) {
                window.clearTimeout(this[name]);
                this[name] = null;
            }
        }
    };
})(jQuery);
/**
 * {/literal}
 */
