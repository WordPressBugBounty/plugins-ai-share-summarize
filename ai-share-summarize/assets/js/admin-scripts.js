/**
 * AI Summaries & Share Buttons - Admin Scripts
 *
 * Reusable paginator class, analytics dashboard with period comparison,
 * chart rendering (per-platform + comparison modes), export dropdown.
 *
 * @package AiShareSummarize
 * @since 1.5.0
 */

/* global aissAdminData, Chart, jQuery */

/**
 * Reusable client-side paginator for wp-list-table tables.
 * Exposed on window so other plugin scripts can use it.
 *
 * @since 1.7.0
 */
window.AissPaginator = (function($) {
	'use strict';

	/**
	 * @param {Object} options
	 * @param {string} options.table       - CSS selector for the table.
	 * @param {number} options.pageSize    - Rows per page (default 10).
	 * @param {string} options.pager       - Selector for top pager container.
	 * @param {string} options.pagerBottom - Selector for bottom pager container (optional).
	 */
	function Paginator(options) {
		this.$table      = $(options.table);
		this.pageSize    = options.pageSize || 10;
		this.$pager      = $(options.pager);
		this.$pagerBottom = options.pagerBottom ? $(options.pagerBottom) : null;
		this.currentPage = 1;

		// Merge both pager containers into one jQuery collection.
		this.$allPagers = (this.$pagerBottom && this.$pagerBottom.length)
			? this.$pager.add(this.$pagerBottom)
			: this.$pager;

		this._buildControls(this.$allPagers);
		this.$table.data('aissPaginator', this);
	}

	/**
	 * Inject navigation buttons and range indicator into pager containers
	 */
	Paginator.prototype._buildControls = function($containers) {
		var self = this;
		var html = '<button type="button" class="aiss-pager-btn aiss-pager-first" title="First">&laquo;</button>' +
			'<button type="button" class="aiss-pager-btn aiss-pager-prev" title="Previous">&lsaquo;</button>' +
			'<span class="aiss-pager-info"></span>' +
			'<button type="button" class="aiss-pager-btn aiss-pager-next" title="Next">&rsaquo;</button>' +
			'<button type="button" class="aiss-pager-btn aiss-pager-last" title="Last">&raquo;</button>';

		$containers.each(function() {
			$(this).html(html);
		});

		// Event delegation on all pager containers.
		$containers.on('click', '.aiss-pager-first', function() { self.goToPage(1); });
		$containers.on('click', '.aiss-pager-prev',  function() { self.goToPage(self.currentPage - 1); });
		$containers.on('click', '.aiss-pager-next',  function() { self.goToPage(self.currentPage + 1); });
		$containers.on('click', '.aiss-pager-last',  function() { self.goToPage(self.totalPages()); });
	};

	/**
	 * Get data rows (excluding loading/empty placeholders)
	 */
	Paginator.prototype._getRows = function() {
		// The breakdown rows opened under a platform (v2.6.0) are not data rows.
		return this.$table.find('tbody tr').not('.aiss-no-data').not('.aiss-platforms-drawer');
	};

	/**
	 * Total number of pages
	 */
	Paginator.prototype.totalPages = function() {
		var rows = this._getRows().length;
		return Math.max(1, Math.ceil(rows / this.pageSize));
	};

	/**
	 * Navigate to a specific page (clamped to valid range)
	 */
	Paginator.prototype.goToPage = function(n) {
		n = Math.max(1, Math.min(n, this.totalPages()));
		this.currentPage = n;
		// Told to the table (v2.6.0), so rows opened under a data row can
		// close. A click handler on the pager buttons would miss the last
		// page: the button is disabled within the same click, and jQuery does
		// not pass clicks on disabled elements to delegated handlers.
		this.$table.trigger('aiss:pagechange');
		this._render();
	};

	/**
	 * Recalculate and render. Call after adding/removing rows.
	 */
	Paginator.prototype.refresh = function() {
		if (this.currentPage > this.totalPages()) {
			this.currentPage = this.totalPages();
		}
		this._render();
	};

	/**
	 * Show/hide rows for current page and update controls
	 */
	Paginator.prototype._render = function() {
		var rows       = this._getRows();
		var total      = rows.length;
		var totalPages = this.totalPages();
		var start      = (this.currentPage - 1) * this.pageSize;
		var end        = start + this.pageSize;
		var self       = this;

		// Toggle row visibility.
		rows.each(function(i) {
			$(this).toggle(i >= start && i < end);
		});

		// Update all pager containers.
		this.$allPagers.each(function() {
			var $p = $(this);

			// Hide pager entirely when no data rows.
			if (total === 0) {
				$p.hide();
				return;
			}

			$p.show();

			// Range text: "1–10 of 85".
			var rangeStr = (typeof aissAdminData !== 'undefined' && aissAdminData.strings.pagerRange)
				? aissAdminData.strings.pagerRange
				: '%1$s\u2013%2$s of %3$s';

			var rangeStart = start + 1;
			var rangeEnd   = Math.min(end, total);

			$p.find('.aiss-pager-info').text(
				rangeStr
					.replace('%1$s', rangeStart)
					.replace('%2$s', rangeEnd)
					.replace('%3$s', total)
			);

			// Show/hide arrow buttons: only when more than 1 page.
			var $btns = $p.find('.aiss-pager-btn');
			if (totalPages <= 1) {
				$btns.hide();
			} else {
				$btns.show();
				$p.find('.aiss-pager-first, .aiss-pager-prev').prop('disabled', self.currentPage === 1);
				$p.find('.aiss-pager-next, .aiss-pager-last').prop('disabled', self.currentPage === totalPages);
			}
		});
	};

	return Paginator;
})(jQuery);


/* ===== Main analytics IIFE ===== */

(function($) {
	'use strict';

	// Chart instance.
	var timelineChart = null;

	// Current date range settings.
	var currentDays    = 30;
	var customDateFrom = null;
	var customDateTo   = null;

	// Paginator instances (v1.7.0, replace Load more).
	var platformsPaginator = null;
	var contentPaginator   = null;

	// VigIA integration flag.
	var vigiaActive = false;

	// Comparison state (v1.7.0).
	var compareEnabled         = false;
	var compareType            = 'previous';
	var customCompareDateFrom  = null;
	var customCompareDateTo    = null;
	var currentTimelineData    = null;
	var currentTimelineMode    = 'platform'; // 'platform' or 'total'
	var compareTimelineData    = null;

	/*
	 * Colour, type and display name of every platform (v2.6.0). They come from
	 * PHP, from the same list the settings screen uses, instead of three maps
	 * written by hand here: those missed the buttons added after them, so Copy
	 * link and Native share showed up as raw keys, in grey, since 2.5.0.
	 */
	var platformColors = {};
	var platformTypes  = {};
	var platformLabels = {};
	$.each(aissAdminData.platforms || {}, function(key, data) {
		platformColors[key] = data.color;
		platformTypes[key]  = data.type;
		platformLabels[key] = data.name;
	});

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {

		// Initialize color picker fields (v1.6.0).
		if ($.fn.wpColorPicker) {
			/*
			 * The picker writes the value straight into the input without
			 * firing an input/change event, so the live previews never saw the
			 * new colour until the page was saved and reloaded. Its own change
			 * and clear callbacks are the hook that does fire.
			 */
			$('.ayudawp-color-field').wpColorPicker({
				change: function (event, ui) {
					var field = $(event.target);
					field.val(ui.color.toString());
					field.trigger('aiss:colorchange');
				},
				clear: function (event) {
					$(event.target).closest('.wp-picker-input-wrap').find('.ayudawp-color-field').trigger('aiss:colorchange');
				}
			});
		}

		// Toggle the AI summary custom colors when its style changes (v2.1.0).
		$('input[name="ayudawp_aiss_summary_options[ai_summary_style]"]').on('change', function() {
			if ($(this).val() === 'custom') {
				$('#ayudawp-aiss-summary-custom-colors').show();
			} else {
				$('#ayudawp-aiss-summary-custom-colors').hide();
			}
		});

		/*
		 * Init jQuery UI Sortable for the order lists (v1.6.0). Since 2.8.0
		 * they are rows of chips, one list per group and section, so the
		 * drag is free in both axes and stays inside its list. A new order
		 * is announced as a change of the form, which is what the previews
		 * listen to.
		 */
		if ($.fn.sortable) {
			$('.ayudawp-sortable-list').sortable({
				cursor: 'grabbing',
				items: '> li',
				tolerance: 'pointer',
				placeholder: 'ayudawp-sortable-placeholder',
				opacity: 0.7,
				update: function () {
					var form = this.closest('form');
					if (form) {
						form.dispatchEvent(new Event('change', { bubbles: true }));
					}
				}
			});
		}

		// Delete all analytics data handler (settings tab).
		$('#ayudawp-aiss-delete-all-data').on('click', function() {
			var confirmMsg = (typeof aissAdminData !== 'undefined' && aissAdminData.strings.confirmDeleteAll)
				? aissAdminData.strings.confirmDeleteAll
				: 'Are you sure you want to delete ALL analytics data? This action cannot be undone.';

			if (!confirm(confirmMsg)) {
				return;
			}

			var $btn = $(this);
			var originalText = $btn.text();
			var deletingText = (typeof aissAdminData !== 'undefined' && aissAdminData.strings.deleting)
				? aissAdminData.strings.deleting
				: 'Deleting...';

			$btn.prop('disabled', true).text(deletingText);

			$.ajax({
				url: aissAdminData.ajaxUrl,
				method: 'POST',
				data: {
					action: 'ayudawp_aiss_delete_all_data',
					nonce: aissAdminData.nonce
				},
				success: function(response) {
					if (response.success) {
						var successMsg = aissAdminData.strings.deleteSuccess || 'All analytics data has been deleted.';
						$btn.text(successMsg);
						setTimeout(function() {
							$btn.prop('disabled', false).text(originalText);
						}, 3000);
					} else {
						var errorMsg = aissAdminData.strings.deleteError || 'Error deleting data.';
						$btn.prop('disabled', false).text(originalText);
						alert(errorMsg);
					}
				},
				error: function() {
					var errorMsg = aissAdminData.strings.deleteError || 'Error deleting data.';
					$btn.prop('disabled', false).text(originalText);
					alert(errorMsg);
				}
			});
		});

		// Only run analytics logic if on analytics tab.
		if ($('#aiss-timeline-chart').length === 0) {
			return;
		}

		// Load initial data.
		loadAllData();

		// Date range change handler.
		$('#aiss-date-range').on('change', function() {
			var value = $(this).val();

			if (value === 'custom') {
				$('#aiss-custom-dates').show();
			} else {
				$('#aiss-custom-dates').hide();
				customDateFrom = null;
				customDateTo = null;
				currentDays = parseInt(value, 10);

				// Disable comparison for "All time" (no sensible previous period).
				if (currentDays === 0) {
					$('#aiss-compare-toggle').prop('checked', false).trigger('change');
					$('#aiss-compare-toggle').prop('disabled', true);
				} else {
					$('#aiss-compare-toggle').prop('disabled', false);
				}

				loadAllData();
			}
		});

		// Custom date apply handler.
		$('#aiss-apply-custom-dates').on('click', function() {
			customDateFrom = $('#aiss-date-from').val();
			customDateTo = $('#aiss-date-to').val();

			if (customDateFrom && customDateTo) {
				currentDays = 'custom';
				$('#aiss-compare-toggle').prop('disabled', false);
				loadAllData();
			}
		});

		// Compare toggle handler (v1.7.0).
		$('#aiss-compare-toggle').on('change', function() {
			compareEnabled = this.checked;

			if (compareEnabled) {
				$('#aiss-compare-range').removeAttr('disabled');
				loadTimeline(); // Switch to total view.
				loadCompareStats();
				loadCompareTimeline();
			} else {
				$('#aiss-compare-range').attr('disabled', 'disabled');
				$('#aiss-compare-custom-dates').hide();
				clearCompareStats();
				clearCompareTimeline();
				loadTimeline(); // Switch back to per-platform view.
			}
		});

		// Compare range change handler (v1.7.0).
		$('#aiss-compare-range').on('change', function() {
			compareType = $(this).val();

			if (compareType === 'custom') {
				$('#aiss-compare-custom-dates').show();
			} else {
				$('#aiss-compare-custom-dates').hide();
				customCompareDateFrom = null;
				customCompareDateTo = null;
				if (compareEnabled) {
					loadCompareStats();
					loadCompareTimeline();
				}
			}
		});

		// Custom compare date apply handler (v1.7.0).
		$('#aiss-apply-compare-dates').on('click', function() {
			customCompareDateFrom = $('#aiss-compare-date-from').val();
			customCompareDateTo = $('#aiss-compare-date-to').val();

			if (customCompareDateFrom && customCompareDateTo && compareEnabled) {
				loadCompareStats();
				loadCompareTimeline();
			}
		});

		// Export dropdown toggle (v1.7.0).
		$('#aiss-export-csv').on('click', function(e) {
			e.stopPropagation();
			$('#aiss-export-menu').toggleClass('open');
		});

		// Close dropdown when clicking outside.
		$(document).on('click', function() {
			$('#aiss-export-menu').removeClass('open');
		});

		// Export options handlers (v1.7.0).
		$('.aiss-export-option').on('click', function(e) {
			e.stopPropagation();
			var exportType = $(this).data('export');
			$('#aiss-export-menu').removeClass('open');
			aissExportCsv(exportType);
		});

		// VigIA tip dismiss handler.
		$(document).on('click', '.aiss-vigia-tip .notice-dismiss', function() {
			$.ajax({
				url: aissAdminData.ajaxUrl,
				method: 'POST',
				data: {
					action: 'aiss_dismiss_vigia_tip',
					nonce: aissAdminData.nonce
				}
			});
		});
	});

	/* ===== Period text & indicators ===== */

	function getPeriodText() {
		if (currentDays === 'custom' && customDateFrom && customDateTo) {
			var fromDate = new Date(customDateFrom + 'T00:00:00');
			var toDate = new Date(customDateTo + 'T00:00:00');
			var options = { day: 'numeric', month: 'short', year: 'numeric' };
			return fromDate.toLocaleDateString(undefined, options) + ' - ' + toDate.toLocaleDateString(undefined, options);
		} else if (currentDays === 0) {
			return aissAdminData.strings.allTime || 'All time';
		} else if (currentDays === 1) {
			return aissAdminData.strings.today || 'Today';
		} else {
			var template = aissAdminData.strings.lastDays || 'Last %d days';
			return template.replace('%d', currentDays);
		}
	}

	function updatePeriodIndicators() {
		var periodText = getPeriodText();
		$('#aiss-timeline-period').text(periodText);
		$('#aiss-platforms-period').text(periodText);
		$('#aiss-content-period').text(periodText);
	}

	/* ===== Data loading ===== */

	function loadAllData() {
		updatePeriodIndicators();
		loadStats();
		loadTimeline();
		loadPlatforms();
		loadContent();

		// Reload comparison data if active (v1.7.0).
		if (compareEnabled) {
			loadCompareStats();
			loadCompareTimeline();
		}
	}

	/**
	 * Make REST API request
	 */
	function apiRequest(endpoint, params, callback, onError) {
		var data = params || {};

		if (currentDays === 'custom' && customDateFrom && customDateTo) {
			data.start_date = customDateFrom;
			data.end_date = customDateTo;
		} else {
			data.days = currentDays;
		}

		$.ajax({
			url: aissAdminData.restUrl + endpoint,
			method: 'GET',
			data: data,
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', aissAdminData.restNonce);
			},
			success: callback,
			error: function() {
				/* eslint-disable-next-line no-console */
				console.error('AISS API request failed:', endpoint);
				if (onError) {
					onError();
				}
			}
		});
	}

	/**
	 * Load statistics cards
	 */
	function loadStats() {
		apiRequest('stats', {}, function(data) {
			$('#aiss-total-clicks').text(formatNumber(data.total_clicks));
			$('#aiss-unique-platforms').text(formatNumber(data.unique_platforms));
			$('#aiss-unique-posts').text(formatNumber(data.unique_posts));
		});
	}

	/* ===== Comparison data loading (v1.7.0) ===== */

	/**
	 * Build comparison request params
	 */
	function getCompareParams() {
		var params = { compare: compareType };
		if (compareType === 'custom' && customCompareDateFrom && customCompareDateTo) {
			params.compare_date_from = customCompareDateFrom;
			params.compare_date_to = customCompareDateTo;
		}
		return params;
	}

	/**
	 * Load comparison stats for stat cards
	 */
	function loadCompareStats() {
		apiRequest('stats/compare', getCompareParams(), function(data) {
			updateCompareDisplay('aiss-total-clicks-compare',
				data.total_clicks_change, data.total_clicks_previous);
			updateCompareDisplay('aiss-unique-platforms-compare',
				data.unique_platforms_change, data.unique_platforms_previous);
			updateCompareDisplay('aiss-unique-posts-compare',
				data.unique_posts_change, data.unique_posts_previous);
		});
	}

	/**
	 * Load comparison timeline for chart overlay
	 */
	function loadCompareTimeline() {
		apiRequest('timeline/compare', getCompareParams(), function(data) {
			compareTimelineData = data;
			renderChart();
		});
	}

	/**
	 * Clear comparison stats from stat cards
	 */
	function clearCompareStats() {
		$('.aiss-stat-compare').text('').removeClass('positive negative neutral');
	}

	/**
	 * Clear comparison timeline data and re-render chart
	 */
	function clearCompareTimeline() {
		compareTimelineData = null;
	}

	/**
	 * Update a single stat card comparison indicator
	 */
	function updateCompareDisplay(elementId, change, previousValue) {
		var $element   = $('#' + elementId);
		var changeNum  = parseFloat(change);
		var text       = '';
		var className  = 'aiss-stat-compare ';

		if (changeNum > 0) {
			text = '\u2191 ' + changeNum.toFixed(1) + '% vs ' + formatNumber(previousValue);
			className += 'positive';
		} else if (changeNum < 0) {
			text = '\u2193 ' + Math.abs(changeNum).toFixed(1) + '% vs ' + formatNumber(previousValue);
			className += 'negative';
		} else {
			text = '= ' + formatNumber(previousValue);
			className += 'neutral';
		}

		$element.text(text).attr('class', className);
	}

	/* ===== Timeline chart ===== */

	/**
	 * Load timeline data (switches between per-platform and total modes)
	 */
	function loadTimeline() {
		if (compareEnabled) {
			// Total clicks for comparison mode (zero-filled for alignment).
			currentTimelineMode = 'total';
			apiRequest('timeline', { filled: 1 }, function(data) {
				currentTimelineData = data;
				renderChart();
			});
		} else {
			// Per-platform breakdown for normal mode.
			currentTimelineMode = 'platform';
			apiRequest('timeline-by-platform', {}, function(data) {
				currentTimelineData = data;
				renderChart();
			});
		}
	}

	/**
	 * Render chart based on current mode and data
	 */
	function renderChart() {
		if (!currentTimelineData) {
			return;
		}

		if (compareEnabled && currentTimelineMode === 'total') {
			renderTimelineChartComparison(currentTimelineData, compareTimelineData);
		} else {
			renderTimelineChartByPlatform(currentTimelineData);
		}
	}

	/**
	 * Render per-platform timeline chart (default mode)
	 */
	function renderTimelineChartByPlatform(data) {
		var ctx = document.getElementById('aiss-timeline-chart');
		if (!ctx) {
			return;
		}

		if (timelineChart) {
			timelineChart.destroy();
		}

		// Remove comparison tooltip if present.
		var oldTooltip = ctx.parentNode.querySelector('.aiss-chart-tooltip');
		if (oldTooltip) {
			oldTooltip.remove();
		}

		if (!data || data.length === 0) {
			timelineChart = new Chart(ctx, {
				type: 'line',
				data: { labels: [], datasets: [] },
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { display: false } }
				}
			});
			return;
		}

		// Collect unique sorted dates and platforms.
		var dateSet     = {};
		var platformSet = {};

		data.forEach(function(row) {
			dateSet[row.date]         = true;
			platformSet[row.platform] = true;
		});

		var dates     = Object.keys(dateSet).sort();
		var platforms = Object.keys(platformSet);

		// Build lookup: platform -> date -> count.
		var lookup = {};
		data.forEach(function(row) {
			if (!lookup[row.platform]) {
				lookup[row.platform] = {};
			}
			lookup[row.platform][row.date] = parseInt(row.click_count, 10);
		});

		// Sort platforms by total clicks descending.
		platforms.sort(function(a, b) {
			var totalA = Object.values(lookup[a]).reduce(function(s, v) { return s + v; }, 0);
			var totalB = Object.values(lookup[b]).reduce(function(s, v) { return s + v; }, 0);
			return totalB - totalA;
		});

		var maxLines     = 8;
		var topPlatforms = platforms.slice(0, maxLines);

		var datasets = topPlatforms.map(function(platform) {
			var color  = platformColors[platform] || '#999999';
			var values = dates.map(function(date) {
				return lookup[platform][date] || 0;
			});

			return {
				label:            platformLabels[platform] || platform,
				data:             values,
				borderColor:      color,
				backgroundColor:  color,
				borderWidth:      2,
				tension:          0.3,
				pointRadius:      dates.length > 60 ? 0 : 3,
				pointHoverRadius: 5,
				fill:             false
			};
		});

		timelineChart = new Chart(ctx, {
			type: 'line',
			data: {
				labels:   dates,
				datasets: datasets
			},
			options: {
				responsive:          true,
				maintainAspectRatio: false,
				interaction: {
					mode:      'index',
					intersect: false
				},
				plugins: {
					legend: {
						display:  topPlatforms.length > 1,
						position: 'bottom',
						labels: {
							boxWidth:   10,
							boxHeight:  10,
							padding:    16,
							usePointStyle: true,
							pointStyle: 'circle'
						}
					},
					tooltip: {
						callbacks: {
							title: function(context) {
								var dateStr = dates[context[0].dataIndex];
								return formatDateForTooltip(dateStr);
							}
						}
					}
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: {
							maxTicksLimit: 12,
							callback: function(value, index) {
								var dateStr = dates[index];
								if (!dateStr) {
									return '';
								}
								var dateObj = new Date(dateStr + 'T00:00:00');
								return dateObj.toLocaleDateString(undefined, {
									month: 'short',
									day:   'numeric'
								});
							}
						}
					},
					y: {
						beginAtZero: true,
						ticks: { precision: 0 }
					}
				}
			}
		});
	}

	/**
	 * Render comparison timeline chart (single total line + dashed comparison line)
	 *
	 * @param {Array} data        Current period zero-filled timeline.
	 * @param {Array} compareData Comparison period zero-filled timeline (may be null).
	 */
	function renderTimelineChartComparison(data, compareData) {
		var ctx = document.getElementById('aiss-timeline-chart');
		if (!ctx) {
			return;
		}

		if (timelineChart) {
			timelineChart.destroy();
		}

		if (!data || data.length === 0) {
			timelineChart = new Chart(ctx, {
				type: 'line',
				data: { labels: [], datasets: [] },
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { display: false } }
				}
			});
			return;
		}

		var labels = data.map(function(item) { return item.date; });
		var values = data.map(function(item) { return parseInt(item.click_count, 10); });

		var brandColor = getComputedStyle(document.documentElement)
			.getPropertyValue('--aiss-brand').trim() || '#7224D1';

		var datasets = [{
			label:           aissAdminData.strings.currentPeriod || 'Current period',
			data:            values,
			borderColor:     brandColor,
			backgroundColor: brandColor + '1A', // ~10% opacity
			borderWidth:     2,
			fill:            true,
			tension:         0.3,
			pointRadius:     labels.length > 60 ? 0 : 3,
			pointHoverRadius: 5
		}];

		// Comparison dates for tooltip.
		var compareDates  = [];
		var compareValues = [];

		if (compareData && compareData.length > 0) {
			compareDates = compareData.map(function(item) { return item.date; });
			compareValues = compareData.map(function(item) { return parseInt(item.click_count, 10); });

			datasets.push({
				label:           aissAdminData.strings.previousPeriod || 'Previous period',
				data:            compareValues,
				borderColor:     '#999999',
				backgroundColor: 'transparent',
				borderWidth:     2,
				borderDash:      [5, 5],
				fill:            false,
				tension:         0.3,
				pointRadius:     labels.length > 60 ? 0 : 2,
				pointHoverRadius: 4
			});
		}

		timelineChart = new Chart(ctx, {
			type: 'line',
			data: {
				labels:   labels,
				datasets: datasets
			},
			options: {
				responsive:          true,
				maintainAspectRatio: false,
				interaction: {
					mode:      'index',
					intersect: false
				},
				plugins: {
					legend: {
						display:  compareData && compareData.length > 0,
						position: 'bottom',
						labels: {
							usePointStyle: true,
							padding: 15
						}
					},
					tooltip: {
						enabled: !(compareData && compareData.length > 0), // Disable default tooltip when comparing.
						external: (compareData && compareData.length > 0)
							? function(context) {
								externalCompareTooltip(context, labels, values, compareDates, compareValues, brandColor);
							}
							: null
					}
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: {
							maxTicksLimit: 12,
							callback: function(value, index) {
								var dateStr = labels[index];
								if (!dateStr) {
									return '';
								}
								var dateObj = new Date(dateStr + 'T00:00:00');
								return dateObj.toLocaleDateString(undefined, {
									month: 'short',
									day:   'numeric'
								});
							}
						}
					},
					y: {
						beginAtZero: true,
						ticks: { precision: 0 }
					}
				}
			}
		});
	}

	/**
	 * External HTML tooltip for comparison chart (two-column layout)
	 */
	function externalCompareTooltip(context, labels, values, compareDates, compareValues, brandColor) {
		var tooltipModel = context.tooltip;

		// Get or create tooltip element inside chart wrapper.
		var chartWrapper = context.chart.canvas.parentNode;
		var tooltip = chartWrapper.querySelector('.aiss-chart-tooltip');
		if (!tooltip) {
			tooltip = document.createElement('div');
			tooltip.className = 'aiss-chart-tooltip';
			chartWrapper.appendChild(tooltip);
		}

		// Hide if no tooltip.
		if (tooltipModel.opacity === 0) {
			tooltip.style.opacity = '0';
			return;
		}

		var index        = tooltipModel.dataPoints[0].dataIndex;
		var currentDate  = labels[index] || '';
		var currentVal   = values[index] || 0;
		var compareDate  = compareDates[index] || '';
		var compareVal   = compareValues[index] || 0;
		var diff         = currentVal - compareVal;
		var changeClass  = 'neutral';
		var changeText   = '= ' + formatNumber(compareVal);

		if (diff > 0) {
			var pctUp = compareVal > 0 ? ((diff / compareVal) * 100).toFixed(1) : '100.0';
			changeText = '\u2191 ' + pctUp + '% (+' + formatNumber(diff) + ')';
			changeClass = 'positive';
		} else if (diff < 0) {
			var pctDown = compareVal > 0 ? ((Math.abs(diff) / compareVal) * 100).toFixed(1) : '0.0';
			changeText = '\u2193 ' + pctDown + '% (' + formatNumber(diff) + ')';
			changeClass = 'negative';
		}

		var vsText = aissAdminData.strings.vs || 'vs';

		var html = '<div class="aiss-tooltip-header">' +
			'<span>' + formatDateForTooltip(currentDate) + '</span>' +
			'<span class="aiss-tooltip-vs">' + escHtml(vsText) + '</span>' +
			'<span>' + formatDateForTooltip(compareDate) + '</span>' +
			'</div>';

		html += '<div class="aiss-tooltip-body">';
		html += '<div class="aiss-tooltip-row">' +
			'<span class="aiss-tooltip-label"><span class="aiss-tooltip-dot" style="background:' + brandColor + '"></span>' +
			escHtml(aissAdminData.strings.currentPeriod || 'Current period') + '</span>' +
			'<span class="aiss-tooltip-value">' + formatNumber(currentVal) + '</span></div>';
		html += '<div class="aiss-tooltip-row">' +
			'<span class="aiss-tooltip-label"><span class="aiss-tooltip-dot" style="background:#999"></span>' +
			escHtml(aissAdminData.strings.previousPeriod || 'Previous period') + '</span>' +
			'<span class="aiss-tooltip-value">' + formatNumber(compareVal) + '</span></div>';
		html += '</div>';

		html += '<div class="aiss-tooltip-footer ' + changeClass + '">' + changeText + '</div>';

		tooltip.innerHTML = html;

		// Smart positioning: center horizontally, above by default, flip below if clipped.
		tooltip.style.opacity = '0';
		tooltip.style.left = '0';
		tooltip.style.top = '0';
		tooltip.style.display = 'block';

		var tipWidth    = tooltip.offsetWidth;
		var tipHeight   = tooltip.offsetHeight;
		var wrapWidth   = chartWrapper.offsetWidth;
		var caretX      = tooltipModel.caretX;
		var caretY      = tooltipModel.caretY;
		var margin      = 8;

		// Horizontal: center on caret, clamp to container edges.
		var posX = caretX - (tipWidth / 2);
		if (posX < 0) {
			posX = 0;
		} else if (posX + tipWidth > wrapWidth) {
			posX = wrapWidth - tipWidth;
		}

		// Vertical: above the point by default, flip below if no room.
		var posY = caretY - tipHeight - margin;
		if (posY < 0) {
			posY = caretY + margin;
		}

		tooltip.style.left    = posX + 'px';
		tooltip.style.top     = posY + 'px';
		tooltip.style.opacity = '1';
	}

	/* ===== Top platforms table ===== */

	/**
	 * Load top platforms table (all results, client-side pagination)
	 */
	function loadPlatforms() {
		var $tbody = $('#aiss-platforms-table tbody');
		$tbody.html('<tr class="aiss-no-data"><td colspan="3" class="aiss-loading">' + (aissAdminData.strings.loading || 'Loading...') + '</td></tr>');

		apiRequest('platforms', { limit: 500, offset: 0 }, function(response) {
			var data = response.items || [];

			if (data.length === 0) {
				$tbody.html('<tr class="aiss-no-data"><td colspan="3" class="aiss-loading">' + (aissAdminData.strings.noData || 'No data yet.') + '</td></tr>');
				if (platformsPaginator) {
					platformsPaginator.currentPage = 1;
					platformsPaginator.refresh();
				}
				return;
			}

			var html = '';
			data.forEach(function(row) {
				html += renderPlatformRow(row);
			});
			$tbody.html(html);

			// Init or refresh paginator.
			if (!platformsPaginator) {
				platformsPaginator = new window.AissPaginator({
					table:       '#aiss-platforms-table',
					pageSize:    10,
					pager:       '#aiss-platforms-pager',
					pagerBottom: '#aiss-platforms-pager-bottom'
				});
			}
			platformsPaginator.currentPage = 1;
			platformsPaginator.refresh();
		});
	}

	/**
	 * Render a single platform row
	 */
	function renderPlatformRow(row) {
		var platform  = row.platform;
		var label     = platformLabels[platform] || platform;
		var color     = platformColors[platform] || '#999999';
		var type      = platformTypes[platform] || 'social';
		var typeIcon  = type === 'ai' ? 'dashicons-lightbulb' : 'dashicons-share';
		var typeLabel = type === 'ai'
			? (aissAdminData.strings.typeAI || 'AI')
			: (aissAdminData.strings.typeSocial || 'Social');
		var typeCss   = 'aiss-type-badge aiss-type-' + type;

		var html = '<tr>';
		html += '<td><span class="aiss-platform-name">';
		html += '<span class="aiss-platform-dot" style="background-color:' + color + '"></span>';
		html += escHtml(label);
		html += '</span></td>';
		html += '<td class="aiss-col-type"><span class="' + typeCss + '">';
		html += '<span class="dashicons ' + typeIcon + '"></span>';
		html += escHtml(typeLabel);
		html += '</span></td>';
		// The count opens the posts this button was clicked from (v2.6.0).
		html += '<td class="num">';
		html += '<button type="button" class="aiss-expand-platforms" data-platform="' + escAttr(platform) + '" aria-expanded="false" aria-label="' + escAttr(aissAdminData.strings.showPosts || 'Show the posts it was clicked from') + '">';
		html += '<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
		html += '<span class="aiss-expand-count">' + formatNumber(row.click_count) + '</span>';
		html += '</button></td>';
		html += '</tr>';

		return html;
	}

	/**
	 * Close every open breakdown under the platforms table (v2.6.0)
	 *
	 * Called when the table paginates, so a breakdown never stays under a row
	 * of another page.
	 */
	function collapsePlatformDrawers() {
		$('#aiss-platforms-table .aiss-platforms-drawer').remove();
		$('#aiss-platforms-table .aiss-expand-platforms[aria-expanded="true"]').each(function() {
			$(this).attr('aria-expanded', 'false')
				.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
		});
	}

	/**
	 * Breakdown of one platform: the posts it was clicked from (v2.6.0)
	 *
	 * @param {Array}   items Rows with title, url and clicks.
	 * @param {boolean} more  Whether the list was cut.
	 * @return {string} HTML.
	 */
	function renderPlatformPosts(items, more) {
		var strings = aissAdminData.strings;
		var inner   = '<div class="aiss-platforms-breakdown">';
		inner += '<span class="aiss-platforms-breakdown-label">' + escHtml(strings.postsForButton || 'Posts it was clicked from') + '</span>';

		if (!items.length) {
			inner += '<p>' + escHtml(strings.noData || 'No data yet.') + '</p>';
		} else {
			inner += '<ol class="aiss-platforms-posts">';
			items.forEach(function(item) {
				var title = String(item.title || '');
				var url   = String(item.url || '');
				inner += '<li>';
				// Only http(s) reaches the href; escaping the quotes would not
				// stop a javascript: address.
				if (/^https?:\/\//i.test(url)) {
					inner += '<a href="' + escAttr(url) + '" target="_blank" rel="noopener noreferrer">' + escHtml(title) + '</a>';
				} else {
					inner += '<span>' + escHtml(title) + '</span>';
				}
				inner += ' <strong>' + formatNumber(item.clicks) + '</strong></li>';
			});
			inner += '</ol>';
			if (more) {
				inner += '<p class="description">' + escHtml(strings.topPostsOnly || 'Only the 20 posts with the most clicks are listed. The CSV export has all of them.') + '</p>';
			}
		}

		return inner + '</div>';
	}

	// Open or close the breakdown of a platform, loaded on demand (v2.6.0).
	$(document).on('click', '#aiss-platforms-table .aiss-expand-platforms', function() {
		var $btn     = $(this);
		var $row     = $btn.closest('tr');
		var $icon    = $btn.find('.dashicons');
		var platform = String($btn.data('platform') || '');

		if ('true' === $btn.attr('aria-expanded')) {
			$btn.attr('aria-expanded', 'false');
			$icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
			$row.next('.aiss-platforms-drawer').remove();
			return;
		}

		$btn.attr('aria-expanded', 'true');
		$icon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');

		var colspan = $row.children('td').length;
		var $drawer = $('<tr class="aiss-platforms-drawer"><td colspan="' + colspan + '"><div class="aiss-platforms-breakdown">' + escHtml(aissAdminData.strings.loading || 'Loading...') + '</div></td></tr>');
		$row.after($drawer);

		apiRequest('platforms/posts', { platform: platform }, function(response) {
			$drawer.find('td').html(renderPlatformPosts((response && response.items) || [], !!(response && response.more)));
		}, function() {
			$drawer.find('td').html('<div class="aiss-platforms-breakdown">' + escHtml(aissAdminData.strings.loadError || 'Could not load the data. Please try again.') + '</div>');
		});
	});

	// Close the breakdowns when the platforms table changes page.
	$(document).on('aiss:pagechange', '#aiss-platforms-table', collapsePlatformDrawers);

	/* ===== Top content table ===== */

	/**
	 * Load top content table (all results, client-side pagination)
	 */
	function loadContent() {
		var colCount = vigiaActive ? 4 : 3;
		var $tbody = $('#aiss-content-table tbody');
		$tbody.html('<tr class="aiss-no-data"><td colspan="' + colCount + '" class="aiss-loading">' + (aissAdminData.strings.loading || 'Loading...') + '</td></tr>');

		apiRequest('content', { limit: 500, offset: 0 }, function(response) {
			var data = response.items || [];
			vigiaActive = response.vigia_active || false;
			var crawlData = response.crawl_data || {};

			if (data.length === 0) {
				$tbody.html('<tr class="aiss-no-data"><td colspan="' + colCount + '" class="aiss-loading">' + (aissAdminData.strings.noData || 'No data yet.') + '</td></tr>');
				if (contentPaginator) {
					contentPaginator.currentPage = 1;
					contentPaginator.refresh();
				}
				return;
			}

			// Update table header if VigIA status changed.
			updateContentTableHeader();

			var html = '';
			data.forEach(function(row) {
				html += renderContentRow(row, crawlData);
			});
			$tbody.html(html);

			// Init or refresh paginator.
			if (!contentPaginator) {
				contentPaginator = new window.AissPaginator({
					table:       '#aiss-content-table',
					pageSize:    10,
					pager:       '#aiss-content-pager',
					pagerBottom: '#aiss-content-pager-bottom'
				});
			}
			contentPaginator.currentPage = 1;
			contentPaginator.refresh();
		});
	}

	/**
	 * Update content table header based on VigIA status
	 */
	function updateContentTableHeader() {
		var $thead = $('#aiss-content-table thead tr');
		var hasAiCol = $thead.find('th').length > 3;

		if (vigiaActive && !hasAiCol) {
			$thead.append('<th class="num aiss-col-count">' + (aissAdminData.strings.aiCrawls || 'AI Crawls') + '</th>');
		} else if (!vigiaActive && hasAiCol) {
			$thead.find('th:last').remove();
		}
	}

	/**
	 * Render a single content row
	 */
	function renderContentRow(row, crawlData) {
		var title     = row.post_title || '(#' + row.post_id + ')';
		var truncTitle = title.length > 50 ? title.substring(0, 50) + '...' : title;
		var editUrl   = aissAdminData.adminUrl + 'post.php?post=' + row.post_id + '&action=edit';

		var html = '<tr>';
		html += '<td title="' + escAttr(title) + '">';
		html += '<strong>' + escHtml(truncTitle) + '</strong>';
		html += ' <a href="' + editUrl + '" class="aiss-edit-link" target="_blank" rel="noopener noreferrer">';
		html += aissAdminData.strings.edit || 'Edit';
		html += '</a>';
		html += '</td>';
		html += '<td class="num aiss-col-id">' + row.post_id + '</td>';
		html += '<td class="num">' + formatNumber(row.click_count) + '</td>';

		if (vigiaActive) {
			var crawls = crawlData[row.post_id] || 0;
			html += '<td class="num">' + formatNumber(crawls) + '</td>';
		}

		html += '</tr>';
		return html;
	}

	/* ===== Export CSV (v1.7.0 dropdown) ===== */

	/**
	 * Export analytics data as CSV file
	 *
	 * @param {string} exportType 'current' for full period data, 'timeline' for daily summary.
	 */
	function aissExportCsv(exportType) {
		var $btn = $('#aiss-export-csv');
		var originalHtml = $btn.html();

		$btn.prop('disabled', true).html(
			'<span class="dashicons dashicons-update spin"></span> ' +
			(aissAdminData.strings.loading || 'Loading...')
		);

		if (exportType === 'timeline') {
			// Timeline summary export (server-side CSV, supports comparison).
			var params = {};
			if (compareEnabled) {
				params.compare = compareType;
				if (compareType === 'custom' && customCompareDateFrom && customCompareDateTo) {
					params.compare_date_from = customCompareDateFrom;
					params.compare_date_to = customCompareDateTo;
				}
			}

			apiRequest('export/timeline', params, function(response) {
				if (!response || !response.content) {
					alert(aissAdminData.strings.exportError || 'Export failed. Please try again.');
					$btn.prop('disabled', false).html(originalHtml);
					return;
				}

				triggerDownload(response.content, response.filename);
				$btn.prop('disabled', false).html(originalHtml);
			});
		} else {
			// Current period export (client-side CSV, existing logic).
			apiRequest('export', {}, function(response) {
				if (!response || !response.rows || response.rows.length === 0) {
					alert(aissAdminData.strings.noData || 'No data yet.');
					$btn.prop('disabled', false).html(originalHtml);
					return;
				}

				var rows      = response.rows;
				var startDate = response.start_date || '';
				var endDate   = response.end_date || '';

				var siteName = aissAdminData.siteName || '';
				var csvTitle = siteName + ' - AI Summaries & Share Buttons Analytics - ' + startDate + ' to ' + endDate;
				var csvLines = [];
				csvLines.push(csvEscapeField(csvTitle));
				csvLines.push('');

				csvLines.push([
					'Date', 'Platform', 'Type', 'Post Title', 'Post URL', 'Clicks'
				].map(csvEscapeField).join(','));

				rows.forEach(function(row) {
					var platform = row.platform || '';
					var type = platformTypes[platform] === 'ai' ? 'AI' : 'Social';

					csvLines.push([
						row.date || '',
						platform,
						type,
						row.post_title || '',
						row.post_url || '',
						row.clicks || 0
					].map(csvEscapeField).join(','));
				});

				var csvContent = '\uFEFF' + csvLines.join('\r\n');
				var filename = 'aiss-analytics-' + startDate + '-' + endDate + '.csv';

				triggerDownload(csvContent, filename);
				$btn.prop('disabled', false).html(originalHtml);
			});
		}

		// Safety timeout to re-enable button.
		setTimeout(function() {
			if ($btn.prop('disabled')) {
				$btn.prop('disabled', false).html(originalHtml);
			}
		}, 15000);
	}

	/**
	 * Trigger file download from string content
	 */
	function triggerDownload(content, filename) {
		var blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
		var link = document.createElement('a');

		if (typeof link.download !== 'undefined') {
			var url = URL.createObjectURL(blob);
			link.setAttribute('href', url);
			link.setAttribute('download', filename);
			link.style.visibility = 'hidden';
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			URL.revokeObjectURL(url);
		} else {
			// Fallback for IE11.
			window.navigator.msSaveBlob(blob, filename);
		}
	}

	/* ===== Helpers ===== */

	/**
	 * Format number with locale separators
	 */
	function formatNumber(num) {
		return parseInt(num, 10).toLocaleString();
	}

	/**
	 * Format date string for tooltip display
	 */
	function formatDateForTooltip(dateStr) {
		if (!dateStr) {
			return '';
		}
		var dateObj = new Date(dateStr + 'T00:00:00');
		return dateObj.toLocaleDateString(undefined, {
			weekday: 'short',
			year:    'numeric',
			month:   'short',
			day:     'numeric'
		});
	}

	/**
	 * Escape a value for HTML *text* position (between tags).
	 *
	 * WARNING: this does NOT encode quotes, so it is unsafe inside an
	 * attribute value. For attributes (title="...", etc.) use escAttr().
	 */
	function escHtml(text) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(text));
		return div.innerHTML;
	}

	/**
	 * Escape a value for use inside a quoted HTML attribute.
	 *
	 * Encodes quotes as well as & < >, which escHtml() does not, so an
	 * attacker-controlled value cannot break out of the attribute.
	 */
	function escAttr(str) {
		return String(str).replace(/[&"'<>]/g, function (m) {
			return { '&': '&amp;', '"': '&quot;', "'": '&#39;', '<': '&lt;', '>': '&gt;' }[m];
		});
	}

	/**
	 * Escape a field value for CSV output
	 */
	function csvEscapeField(value) {
		var str = String(value);
		if (str.indexOf(',') !== -1 || str.indexOf('"') !== -1 || str.indexOf('\n') !== -1) {
			str = '"' + str.replace(/"/g, '""') + '"';
		}
		return str;
	}

})(jQuery);
/**
 * Settings screen interactions (v2.5.0)
 *
 * Small things, all optional: the form works with this file blocked.
 *
 *   1. The buttons tab (v2.8.0): the catalog reaches the order lists of both
 *      sections at once, what depends on a choice is hidden until it applies,
 *      the floating bar section folds away while the bar is off, and a sample
 *      of the bar follows every change before saving.
 *   6. The buttons block preview (v2.8.0): the last row of "In-content
 *      buttons", where the server draws the real block from the unsaved form,
 *      laid out at a real width and scaled to fit.
 *   3. The summary block sample repaints as its style changes.
 *   4. A position whose destination is unticked is dimmed.
 */
(function () {
	'use strict';

	/** The + of the "More" button, same path as the front end. Constant. */
	var BAR_PLUS = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/></svg>';

	/** A page of grey blocks for the sample bar to sit on. Constant. */
	var BAR_PAGE = '<div class="aiss-bar-page"><div class="aiss-bar-page-top"><span></span><span></span></div>'
		+ '<div class="aiss-bar-page-body"><div class="aiss-bar-page-main"><i class="is-title"></i><i></i><i></i><i></i><i></i><i class="is-img"></i><i></i><i></i><i></i><i></i><i></i><i></i></div>'
		+ '<div class="aiss-bar-page-side"><b></b><b></b><b></b></div></div></div>';

	/**
	 * Replace %s, %1$s and %2$s in a translated string.
	 *
	 * @param {string} text Template.
	 * @param {Array}  args Values, in order.
	 * @return {string}
	 */
	function fill(text, args) {
		var n = 0;
		return String(text || '').replace(/%(\d+)\$s|%s/g, function (m, pos) {
			var i = pos ? parseInt(pos, 10) - 1 : n++;
			return undefined !== args[i] ? String(args[i]) : '';
		});
	}

	/**
	 * The buttons tab (v2.8.0).
	 *
	 * "In-content buttons" and "Floating bar" are set up the same way, and
	 * everything here keeps them in step with the form as it is now, before
	 * saving: what is ticked in the catalog shows up at once in the order lists
	 * of both sections, a setting that depends on another is hidden (not
	 * dimmed) until it applies, the bar's own lists and look start as the
	 * content's the first time they are chosen, the bar section folds away
	 * while the bar is off, and the bar preview is drawn the way the front end
	 * draws the bar (ayudawp_aiss_get_floating_bar_buttons(),
	 * ayudawp_aiss_split_floating_bar_buttons() and
	 * ayudawp_aiss_get_look_classes()).
	 *
	 * @param {Element} form The settings form.
	 */
	function initButtonsTab(form) {
		var catalog = form.querySelectorAll('input[name="ayudawp_aiss_options[enabled_buttons][]"]');
		var contentBoard = form.querySelector('.aiss-order-board[data-section="content"]');
		var barBoard = form.querySelector('.aiss-order-board[data-section="bar"]');
		var barOwn = form.querySelector('.aiss-bar-own');
		var barOwnLook = form.querySelector('.aiss-bar-own-look');
		var section = form.querySelector('.aiss-bar-section');
		var dock = form.querySelector('.aiss-bar-dock');
		var stage = dock ? dock.querySelector('.aiss-bar-stage') : null;
		var readout = dock ? dock.querySelector('.aiss-bar-readout') : null;
		var tpl = dock ? dock.querySelector('.aiss-bar-tpl') : null;
		var googleSelect = form.querySelector('#aiss-google-placement');
		var googleOff = !!dock && '1' === dock.getAttribute('data-google-off');
		var strings = (window.aissAdminData && window.aissAdminData.strings) || {};
		var expanded = false;
		var viewPicked = false;

		if (!catalog.length) {
			return;
		}

		// One sample per button key, cloned into the bar preview.
		var samples = {};
		if (tpl && tpl.content) {
			var tplNodes = tpl.content.querySelectorAll('[data-key]');
			for (var t = 0; t < tplNodes.length; t++) {
				samples[tplNodes[t].getAttribute('data-key')] = tplNodes[t];
			}
		}

		function each(list, fn) {
			Array.prototype.forEach.call(list || [], fn);
		}

		function radio(name, fallback) {
			var input = form.querySelector('input[name="ayudawp_aiss_options[' + name + ']"]:checked');
			return input ? input.value : fallback;
		}

		function setRadio(name, value) {
			var input = form.querySelector('input[name="ayudawp_aiss_options[' + name + ']"][value="' + value + '"]');
			if (input) {
				input.checked = true;
			}
		}

		function enabledKeys() {
			var out = [];
			each(catalog, function (box) {
				if (box.checked) {
					out.push(box.value);
				}
			});
			return out;
		}

		function chips(board, group) {
			return board ? board.querySelectorAll('ul[data-group="' + group + '"] > .aiss-order-chip') : [];
		}

		function pickBox(chip) {
			return chip.querySelector('.aiss-order-pick');
		}

		function picking(board) {
			return !!board && board.classList.contains('is-picking');
		}

		/*
		 * A section's list, in its order: the chips of each group as they are
		 * now, available and (while choosing) ticked, then the groups in the
		 * order chosen. "ai", "social" and "mixed" are the bar's words; the
		 * content's button_order is turned into them first.
		 */
		function sectionList(board, groups) {
			var enabled = enabledKeys();
			var choose = picking(board);
			var take = function (group) {
				var out = [];
				each(chips(board, group), function (chip) {
					var key = chip.getAttribute('data-key');
					var box = pickBox(chip);
					if (enabled.indexOf(key) !== -1 && (!choose || (box && box.checked))) {
						out.push(key);
					}
				});
				return out;
			};
			var ai = take('ai');
			var social = take('social');
			var list = [];

			if ('social' === groups) {
				list = social.concat(ai);
			} else if ('mixed' === groups) {
				for (var m = 0; m < Math.max(ai.length, social.length); m++) {
					if (social[m]) { list.push(social[m]); }
					if (ai[m]) { list.push(ai[m]); }
				}
			} else {
				list = ai.concat(social);
			}

			if (googleOff || (googleSelect && 'outside' === googleSelect.value)) {
				list = list.filter(function (k) { return 'google_preferred' !== k; });
			}
			return list;
		}

		function contentGroups() {
			var order = radio('button_order', 'ayudawp_ai_first');
			if ('ayudawp_social_first' === order) {
				return 'social';
			}
			return 'ayudawp_mixed' === order ? 'mixed' : 'ai';
		}

		function contentList() {
			return sectionList(contentBoard, contentGroups());
		}

		function barList() {
			if ('pick' !== radio('floating_bar_buttons', 'inherit') || !barBoard) {
				return contentList();
			}
			var list = sectionList(barBoard, radio('floating_bar_groups', 'ai'));
			return list.length ? list : contentList();
		}

		// --- Show what applies, hide what does not ----------------------------

		function syncCatalog() {
			var enabled = enabledKeys();
			each([contentBoard, barBoard], function (board) {
				each(board ? board.querySelectorAll('.aiss-order-chip') : [], function (chip) {
					chip.hidden = enabled.indexOf(chip.getAttribute('data-key')) === -1;
				});
			});
		}

		function syncGoogle() {
			var place = googleSelect ? googleSelect.value : 'social';
			var googleOn = enabledKeys().indexOf('google_preferred') !== -1;
			var title = form.querySelector('.aiss-google-title-field');
			if (title) {
				title.hidden = 'section' !== place;
			}
			each(form.querySelectorAll('.aiss-google-order-note'), function (note) {
				note.hidden = !googleOn || note.getAttribute('data-placement') !== place;
			});
		}

		function syncModes() {
			if (contentBoard) {
				contentBoard.classList.toggle('is-picking', 'pick' === radio('content_buttons_mode', 'all'));
			}
			if (barOwn) {
				barOwn.hidden = 'pick' !== radio('floating_bar_buttons', 'inherit');
			}
			var own = 'own' === radio('floating_bar_look', 'inherit');
			if (barOwnLook) {
				barOwnLook.hidden = !own;
			}
			var pickers = form.querySelector('#ayudawp-custom-colors');
			if (pickers) {
				pickers.hidden = !('custom' === radio('button_colors', 'minimal') || (own && 'custom' === radio('floating_bar_color', 'minimal')));
			}
		}

		function barOn() {
			var on = false;
			each(section ? section.querySelectorAll('.aiss-placement-toggle') : [], function (box) {
				if (box.checked) {
					on = true;
				}
			});
			return on;
		}

		function syncBarSection() {
			var on = barOn();
			each(section ? section.querySelectorAll('tr.aiss-bar-extra') : [], function (row) {
				row.hidden = !on;
			});
		}

		// --- Starting points ------------------------------------------------

		/*
		 * The bar's own list, the first time "Choose and order its own" is
		 * picked: the content's groups, order and buttons, as the form has
		 * them now.
		 */
		function seedBarButtons() {
			if (!barOwn || !barBoard || '1' !== barOwn.getAttribute('data-fresh')) {
				return;
			}
			barOwn.removeAttribute('data-fresh');
			setRadio('floating_bar_groups', contentGroups());

			var list = contentList();
			['ai', 'social'].forEach(function (group) {
				var ul = barBoard.querySelector('ul[data-group="' + group + '"]');
				if (!ul) {
					return;
				}
				each(chips(contentBoard, group), function (source) {
					var chip = ul.querySelector('.aiss-order-chip[data-key="' + source.getAttribute('data-key') + '"]');
					if (chip) {
						ul.appendChild(chip);
					}
				});
			});
			each(barBoard.querySelectorAll('.aiss-order-chip'), function (chip) {
				var box = pickBox(chip);
				if (box) {
					box.checked = list.indexOf(chip.getAttribute('data-key')) !== -1;
				}
			});
		}

		// The bar's own look, the first time "Its own" is picked: the content's.
		function seedBarLook() {
			if (!barOwnLook || '1' !== barOwnLook.getAttribute('data-fresh')) {
				return;
			}
			barOwnLook.removeAttribute('data-fresh');
			var size = radio('button_size', 'normal');
			setRadio('floating_bar_color', radio('button_colors', 'minimal'));
			setRadio('floating_bar_corners', radio('button_corners', 'rounded'));
			setRadio('floating_bar_size', 'fluid' === size ? 'normal' : size);
			setRadio('floating_bar_dark', radio('dark_mode_adaptation', 'disabled'));
		}

		// "Choose" with none left: tick the ones the section shows now.
		function seedPicks(board) {
			if (!picking(board)) {
				return;
			}
			var any = false;
			each(board.querySelectorAll('.aiss-order-chip:not([hidden]) .aiss-order-pick'), function (box) {
				if (box.checked) {
					any = true;
				}
			});
			if (!any) {
				each(board.querySelectorAll('.aiss-order-chip:not([hidden]) .aiss-order-pick'), function (box) {
					box.checked = true;
				});
			}
		}

		// --- The bar preview -------------------------------------------------

		function sample(key) {
			return samples[key] ? samples[key].cloneNode(true) : document.createElement('span');
		}

		function barLook() {
			if ('own' === radio('floating_bar_look', 'inherit')) {
				return {
					colors: radio('floating_bar_color', 'minimal'),
					corners: radio('floating_bar_corners', 'rounded'),
					size: radio('floating_bar_size', 'normal'),
					dark: radio('floating_bar_dark', 'disabled')
				};
			}
			var size = radio('button_size', 'normal');
			return {
				colors: radio('button_colors', 'minimal'),
				corners: radio('button_corners', 'rounded'),
				size: 'fluid' === size ? 'normal' : size,
				dark: radio('dark_mode_adaptation', 'disabled')
			};
		}

		function currentView() {
			var picked = dock ? dock.querySelector('input[name="aiss_bar_view"]:checked') : null;
			if (viewPicked && picked) {
				return picked.value;
			}
			// Until one is picked, the position of the first destination ticked.
			var view = 'right';
			var boxes = section ? section.querySelectorAll('.aiss-placement-toggle') : [];
			for (var i = 0; i < boxes.length; i++) {
				if (boxes[i].checked) {
					var row = boxes[i].closest('.aiss-placement-row');
					var select = row ? row.querySelector('select') : null;
					if (select && ['left', 'right', 'bottom'].indexOf(select.value) !== -1) {
						view = select.value;
					}
					break;
				}
			}
			var input = dock ? dock.querySelector('input[name="aiss_bar_view"][value="' + view + '"]') : null;
			if (input) {
				input.checked = true;
			}
			return view;
		}

		function buttonSize(view, size) {
			if ('compact' === size) { return 36; }
			if ('large' === size) { return 54; }
			return 'phone' === view ? 40 : 44;
		}

		function panelColumns(view, count) {
			if ('bottom' === view) { return Math.min(count, 6); }
			if ('phone' === view) { return Math.min(count, 5); }
			if (count <= 4) { return 1; }
			return count <= 10 ? 2 : 3;
		}

		function drawBar() {
			if (!stage) {
				return;
			}
			var list = barList();
			var view = currentView();
			var look = barLook();
			var mode = radio('floating_bar_compact', 'partial');
			var countInput = form.querySelector('input[name="ayudawp_aiss_options[floating_bar_compact_visible]"]');
			var visible = Math.max(1, Math.min(5, parseInt(countInput ? countInput.value : '4', 10) || 4));
			var shown = list;
			var rest = [];
			if ('single' === mode && list.length > 1) {
				shown = [];
				rest = list;
			} else if ('partial' === mode && list.length > visible + 1) {
				shown = list.slice(0, visible);
				rest = list.slice(visible);
			}
			if (!rest.length) {
				expanded = false;
			}

			var darkBox = dock.querySelector('input[name="aiss_bar_dark"]');
			var darkPage = !!(darkBox && darkBox.checked);

			// The classes of ayudawp_aiss_get_look_classes(), always icons.
			var cls = ['ayudawp-share-buttons'];
			if ('normal' !== look.size) { cls.push('size-' + look.size); }
			cls.push('icons-only');
			if ('minimal' !== look.colors) { cls.push(look.colors); }
			if ('rounded' !== look.corners) { cls.push('corners-' + look.corners); }
			cls.push('ayudawp-aiss-bar', 'aiss-bar-mock', 'aiss-bar-mock--' + view);
			if (rest.length) { cls.push('has-more'); }
			if (!shown.length) { cls.push('is-single'); }
			if (darkPage && 'auto' === look.dark && 'dark' !== look.colors && 'custom' !== look.colors) { cls.push('is-on-dark'); }

			stage.className = 'aiss-bar-stage' + ('phone' === view ? ' is-phone' : '') + (darkPage ? ' is-dark' : '');
			stage.innerHTML = BAR_PAGE;

			if (list.length) {
				var bar = document.createElement('div');
				bar.className = cls.join(' ');
				if ('custom' === look.colors) {
					var bg = document.getElementById('ayudawp_custom_bg');
					var fg = document.getElementById('ayudawp_custom_text');
					if (bg && bg.value) { bar.style.setProperty('--ayudawp-custom-bg', bg.value); }
					bar.style.setProperty('--ayudawp-custom-text', fg && fg.value ? fg.value : '#ffffff');
				}
				var box = document.createElement('div');
				box.className = 'ayudawp-buttons-container';
				shown.forEach(function (k) { box.appendChild(sample(k)); });

				if (rest.length) {
					var single = !shown.length;
					var wrap = document.createElement('span');
					wrap.className = 'ayudawp-aiss-bar-more' + (single ? ' is-single' : '') + (expanded ? ' is-open' : '');
					var more = single ? sample('__share') : document.createElement('span');
					more.className = 'ayudawp-share-btn ayudawp-aiss-bar-more-btn';
					more.removeAttribute('data-key');
					more.title = String(single ? (strings.barShareLabel || 'Share (%d)') : (strings.barMoreLabel || 'More (%d)')).replace('%d', String(rest.length));
					if (!single) {
						more.innerHTML = BAR_PLUS;
					}
					more.addEventListener('click', function () {
						expanded = !expanded;
						drawBar();
					});
					wrap.appendChild(more);

					if (expanded) {
						var panel = document.createElement('div');
						panel.className = 'ayudawp-aiss-bar-panel';
						panel.style.setProperty('--aiss-cols', String(panelColumns(view, rest.length)));
						rest.forEach(function (k) { panel.appendChild(sample(k)); });
						wrap.appendChild(panel);
					}
					box.appendChild(wrap);
				}

				bar.appendChild(box);
				stage.appendChild(bar);
			}

			if (!readout) {
				return;
			}
			if (!list.length) {
				readout.textContent = strings.barNone || '';
				return;
			}
			var text = fill(strings.barVisible || '%1$s / %2$s', [shown.length, list.length]);
			if (rest.length) {
				text += ' · ' + fill(strings.barMore || '+ %s', [rest.length]);
			}
			if ('phone' !== view) {
				var count = shown.length + (rest.length ? 1 : 0);
				var length = count * buttonSize(view, look.size) + (count - 1) * 8 + 16;
				text += ' · ' + fill('bottom' === view ? strings.barWide : strings.barTall, [length]);
			}
			readout.textContent = text;
		}

		function repaint() {
			syncCatalog();
			syncGoogle();
			syncModes();
			syncBarSection();
			if (barOn()) {
				drawBar();
			}
		}

		// --- Events ------------------------------------------------------------

		form.addEventListener('change', function (e) {
			var target = e.target;
			var name = target && target.name ? target.name : '';

			// Ticked in the catalog: chosen too wherever the section shows all
			// of them, or takes them from the content, so switching to "Choose"
			// later starts from what is seen.
			if ('ayudawp_aiss_options[enabled_buttons][]' === name && target.checked) {
				each([contentBoard, 'pick' === radio('floating_bar_buttons', 'inherit') ? null : barBoard], function (board) {
					var chip = board ? board.querySelector('.aiss-order-chip[data-key="' + target.value + '"]') : null;
					var box = chip ? pickBox(chip) : null;
					if (box && (board !== contentBoard || !picking(board))) {
						box.checked = true;
					}
				});
			}

			// "Choose" keeps at least one ticked: a section with nothing in it
			// is the section turned off, and that is what "Where" is for.
			if (target && target.classList && target.classList.contains('aiss-order-pick') && !target.checked) {
				var board = target.closest('.aiss-order-board');
				if (board && !board.querySelector('.aiss-order-chip:not([hidden]) .aiss-order-pick:checked')) {
					target.checked = true;
				}
			}

			if ('ayudawp_aiss_options[content_buttons_mode]' === name) {
				syncModes();
				seedPicks(contentBoard);
			}
			if ('ayudawp_aiss_options[floating_bar_buttons]' === name && 'pick' === target.value) {
				seedBarButtons();
				syncModes();
				seedPicks(barBoard);
			}
			if ('ayudawp_aiss_options[floating_bar_look]' === name && 'own' === target.value) {
				seedBarLook();
			}
			if ('aiss_bar_view' === name) {
				viewPicked = true;
				expanded = false;
			}
			if ('ayudawp_aiss_options[floating_bar_compact]' === name || 'ayudawp_aiss_options[floating_bar_buttons]' === name) {
				expanded = false;
			}

			repaint();
		});

		// Typing a number of buttons in sight picks "A few and More".
		each(form.querySelectorAll('.aiss-compact-count'), function (input) {
			var pick = function () {
				var partial = input.closest('fieldset') ? input.closest('fieldset').querySelector('input[type="radio"][value="partial"]') : null;
				if (partial && !partial.checked) {
					partial.checked = true;
					partial.dispatchEvent(new Event('change', { bubbles: true }));
				}
			};
			input.addEventListener('focus', pick);
			input.addEventListener('input', function () {
				pick();
				drawBar();
			});
		});

		if (window.jQuery) {
			window.jQuery('#ayudawp_custom_bg, #ayudawp_custom_text').on('change keyup aiss:colorchange', drawBar);
		}

		if (dock) {
			dock.hidden = false;
		}
		repaint();
	}

	/**
	 * Encode a value for an HTML attribute (quotes included).
	 *
	 * @param {string} text Value.
	 * @return {string}
	 */
	function escAttr(text) {
		return String(null === text || undefined === text ? '' : text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	/** The page the block sits on inside the frame. Constant. */
	var BLOCK_PAGE_CSS = 'html,body{margin:0}'
		+ 'body{padding:10px 18px;background:#fff;color:#1d2327;font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}'
		+ 'body.is-dark{background:#1e1e1e;color:#e6e6e6}'
		+ '.aiss-pg>i{display:block;width:60%;height:8px;border-radius:3px;background:#eef0f3}'
		+ 'body.is-dark .aiss-pg>i{background:#3a3a3a}'
		// Less air around the block than a theme usually gives it, so the
		// sample is the block and not the gap above it.
		+ '.aiss-pg .ayudawp-share-buttons{margin:14px 0}'
		+ '.aiss-pg-empty{margin:14px 0;color:#646970;font-size:14px}';

	/** Width the block is laid out at: a content column, and a phone. */
	var BLOCK_WIDTH = { desktop: 720, phone: 375 };

	/** Largest width the desktop sample is shown at, and the phone scale. */
	var BLOCK_SHOWN_MAX = 600;
	var BLOCK_PHONE_SCALE = 0.8;

	/**
	 * The buttons block preview (v2.8.0).
	 *
	 * A row at the end of "The buttons", aligned with the table. It sends the
	 * form as it is now to the server, which runs it through the same sanitizer
	 * as Save and the same renderer as the site, and draws the answer in an
	 * iframe laid out at a real width (a content column, or a phone) and
	 * scaled to fit: the site stylesheet applies its own phone rules to it, so
	 * the compact mode is the real one.
	 *
	 * @param {Element} dock The preview row.
	 */
	function initBlockPreview(dock) {
		var frame = dock.querySelector('.aiss-block-iframe');
		var box = dock.querySelector('.aiss-block-frame');
		var body = dock;
		var data = window.aissAdminData || {};
		var strings = data.strings || {};
		var form = dock.closest('form');
		if (!frame || !box || !form || !data.ajaxUrl || !data.blockPreviewNonce) {
			return;
		}

		var status = dock.querySelector('.aiss-block-status');
		var darkBox = dock.querySelector('input[name="aiss_block_dark"]');
		var lastHtml = null;
		var seq = 0;
		var timer = null;

		function view() {
			var radio = dock.querySelector('input[name="aiss_block_view"]:checked');
			return radio ? radio.value : 'desktop';
		}

		function scale() {
			if ('phone' === view()) {
				return BLOCK_PHONE_SCALE;
			}
			var room = body ? body.clientWidth : BLOCK_SHOWN_MAX;
			return Math.min(1, Math.min(room, BLOCK_SHOWN_MAX) / BLOCK_WIDTH.desktop);
		}

		function setStatus(text) {
			if (status) {
				status.textContent = text || '';
			}
		}

		// Lay the frame out at its real width and scale it down to fit. The
		// height is measured on the body: inside an iframe the document is
		// never shorter than the frame, so measuring it could only grow.
		function fit() {
			var width = BLOCK_WIDTH[view()];
			var k = scale();
			var doc = frame.contentDocument;
			var height = (doc && doc.body) ? Math.ceil(doc.body.getBoundingClientRect().height) : 120;

			frame.style.width = width + 'px';
			frame.style.height = height + 'px';
			frame.style.transform = 'scale(' + k + ')';
			box.style.width = Math.ceil(width * k) + 'px';
			box.style.height = Math.ceil(height * k) + 'px';
		}

		// What the front-end script does to a block, minus the tracking: open
		// and close the compact mode, and keep links from leaving the frame.
		function wireFrame() {
			var doc = frame.contentDocument;
			if (!doc) {
				return;
			}

			var block = doc.querySelector('.ayudawp-share-buttons');
			var darkRadio = form.querySelector('input[name="ayudawp_aiss_options[dark_mode_adaptation]"]:checked');
			if (block && darkBox && darkBox.checked && darkRadio && 'auto' === darkRadio.value) {
				block.classList.add('is-on-dark');
			}

			if (navigator.share) {
				var hidden = doc.querySelectorAll('.ayudawp-aiss-needs-share-api[hidden]');
				for (var h = 0; h < hidden.length; h++) {
					hidden[h].removeAttribute('hidden');
				}
			}

			doc.addEventListener('click', function (e) {
				var target = e.target && e.target.closest ? e.target.closest('.ayudawp-aiss-more-btn, a, button') : null;
				if (!target) {
					return;
				}
				e.preventDefault();
				if (!target.classList.contains('ayudawp-aiss-more-btn')) {
					return;
				}
				var owner = target.closest('.ayudawp-share-buttons');
				if (!owner) {
					return;
				}
				var open = owner.classList.toggle('is-expanded');
				target.setAttribute('aria-expanded', open ? 'true' : 'false');
				var label = target.querySelector('.ayudawp-button-text');
				if (label) {
					if (!target.hasAttribute('data-collapsed-label')) {
						target.setAttribute('data-collapsed-label', label.textContent);
					}
					label.textContent = open
						? (owner.classList.contains('compact-single') ? strings.compactClose || 'Close' : strings.compactLess || 'Less')
						: target.getAttribute('data-collapsed-label');
				}
				fit();
			});

			fit();
		}

		function draw() {
			if (null === lastHtml) {
				return;
			}
			var dark = !!(darkBox && darkBox.checked);
			var content = lastHtml
				? lastHtml
				: '<p class="aiss-pg-empty">' + escAttr(strings.blockEmpty || '') + '</p>';

			box.classList.toggle('is-phone', 'phone' === view());
			frame.onload = wireFrame;
			frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8">'
				+ '<meta name="viewport" content="width=device-width, initial-scale=1">'
				+ '<link rel="stylesheet" href="' + escAttr(data.frontCss || '') + '">'
				+ '<style>' + BLOCK_PAGE_CSS + '</style></head>'
				+ '<body' + (dark ? ' class="is-dark"' : '') + '><div class="aiss-pg"><i></i>'
				+ content
				+ '<i></i></div></body></html>';
			fit();
		}

		function request() {
			var mine = ++seq;
			var payload = new FormData();
			payload.append('action', 'ayudawp_aiss_block_preview');
			payload.append('nonce', data.blockPreviewNonce);

			// The form as it is now; the server keeps only what shapes the block.
			var fields = new FormData(form);
			fields.forEach(function (value, name) {
				if (0 === name.indexOf('ayudawp_aiss_options[')) {
					payload.append('options' + name.slice('ayudawp_aiss_options'.length), value);
				}
			});

			setStatus(strings.blockUpdating || '');
			fetch(data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload })
				.then(function (response) {
					return response.text().then(function (body) {
						var text = String(body || '').trim();
						// -1: the nonce has expired (check_ajax_referer). 0: the
						// session is gone, and admin-ajax.php has no such action
						// for visitors. Both are fixed by reloading, so say that.
						if ('-1' === text || '0' === text) {
							var expired = new Error('expired');
							expired.expired = true;
							throw expired;
						}
						return JSON.parse(text);
					});
				})
				.then(function (json) {
					if (mine !== seq) {
						return;
					}
					if (!json || !json.success || !json.data || 'string' !== typeof json.data.html) {
						throw new Error('bad response');
					}
					lastHtml = json.data.html;
					setStatus('');
					draw();
				})
				.catch(function (err) {
					if (mine === seq) {
						setStatus(err && err.expired ? (strings.blockExpired || '') : (strings.blockError || ''));
					}
				});
		}

		function schedule() {
			window.clearTimeout(timer);
			timer = window.setTimeout(request, 300);
		}

		dock.addEventListener('change', function (e) {
			var name = e.target && e.target.name;
			if ('aiss_block_view' === name || 'aiss_block_dark' === name) {
				e.stopPropagation();
				draw();
			}
		});

		form.addEventListener('change', schedule);
		form.addEventListener('input', function (e) {
			if (e.target && 'text' === e.target.type) {
				schedule();
			}
		});
		if (window.jQuery) {
			window.jQuery('#ayudawp_custom_bg, #ayudawp_custom_text').on('change keyup aiss:colorchange', schedule);
		}

		var resizing = null;
		window.addEventListener('resize', function () {
			window.clearTimeout(resizing);
			resizing = window.setTimeout(fit, 150);
		});

		dock.hidden = false;
		request();
	}

	function init() {
		// --- 1. The buttons tab (v2.8.0) ---------------------------------------
		var tabForm = document.querySelector('.aiss-order-board') ? document.querySelector('.aiss-order-board').closest('form') : null;
		if (tabForm) {
			initButtonsTab(tabForm);
		}

		// --- 6. Buttons block preview (v2.8.0) --------------------------------
		var blockDock = document.querySelector('.aiss-block-dock');
		if (blockDock) {
			initBlockPreview(blockDock);
		}

		// --- 3. Live preview of the summary block ----------------------------
		var sample = document.getElementById('aiss-summary-sample');
		if (sample) {
			var styleRadios = document.querySelectorAll('.aiss-summary-style-input');
			var iconRadios = document.querySelectorAll('.aiss-summary-icon-input');
			var collapsedBox = document.querySelector('.aiss-summary-collapsed-input');
			var labelField = document.querySelector('.aiss-summary-label-input');
			var labelEl = sample.querySelector('.ayudawp-aiss-summary-label');
			var details = sample.querySelector('details');
			var defaultLabel = labelEl ? labelEl.textContent : '';

			var repaintSummary = function () {
				var style = 'minimal';
				for (var i = 0; i < styleRadios.length; i++) {
					if (styleRadios[i].checked) { style = styleRadios[i].value; }
				}
				var icon = 'left';
				for (var j = 0; j < iconRadios.length; j++) {
					if (iconRadios[j].checked) { icon = iconRadios[j].value; }
				}

				var classes = ['ayudawp-aiss-summary'];
				if (['outline', 'brand', 'dark', 'custom'].indexOf(style) !== -1) {
					classes.push('style-' + style);
				}
				if ('right' === icon) {
					classes.push('icon-right');
				} else if ('hidden' === icon) {
					classes.push('icon-hidden');
				}
				sample.className = classes.join(' ');

				if (details && collapsedBox) {
					details.open = !collapsedBox.checked;
				}

				// The colour pickers only matter for the custom style, but
				// keeping the variables in sync costs nothing.
				var bg = document.getElementById('ayudawp_aiss_summary_bg');
				var fg = document.getElementById('ayudawp_aiss_summary_text');
                if (bg && bg.value) { sample.style.setProperty('--ayudawp-aiss-bg', bg.value); }
                if (fg && fg.value) { sample.style.setProperty('--ayudawp-aiss-text', fg.value); }
			};

			for (var i = 0; i < styleRadios.length; i++) {
				styleRadios[i].addEventListener('change', repaintSummary);
			}
			for (var j = 0; j < iconRadios.length; j++) {
				iconRadios[j].addEventListener('change', repaintSummary);
			}
			if (collapsedBox) { collapsedBox.addEventListener('change', repaintSummary); }
			if (labelField && labelEl) {
				labelField.addEventListener('input', function () {
					labelEl.textContent = labelField.value || defaultLabel;
				});
			}
			if (window.jQuery) {
				window.jQuery('#ayudawp_aiss_summary_bg, #ayudawp_aiss_summary_text').on('change keyup aiss:colorchange', repaintSummary);
			}

			repaintSummary();
		}

		// --- 4. Dim the position of an unticked destination -------------------
		var rows = document.querySelectorAll('.aiss-placement-row');
		for (var r = 0; r < rows.length; r++) {
			(function (row) {
				var box = row.querySelector('input[type="checkbox"]');
				if (!box) { return; }
				box.addEventListener('change', function () {
					row.classList.toggle('is-on', box.checked);
				});
			})(rows[r]);
		}
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
