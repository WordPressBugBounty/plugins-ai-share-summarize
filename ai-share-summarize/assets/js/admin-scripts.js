/**
 * Share Buttons & AI-powered Summaries - Admin Scripts
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
		return this.$table.find('tbody tr').not('.aiss-no-data');
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

	// Platform brand colors.
	var platformColors = {
		'twitter':    '#000000',
		'linkedin':   '#0077b5',
		'facebook':   '#1877f2',
		'telegram':   '#26A5E4',
		'whatsapp':   '#25d366',
		'email':      '#6c757d',
		'raindrop':   '#0F66E3',
		'reddit':     '#FF4500',
		'bluesky':    '#0085FF',
		'line':       '#00C300',
		'mastodon':   '#6364FF',
		'threads':    '#000000',
		'pinterest':  '#BD081C',
		'claude':     '#D97757',
		'chatgpt':    '#10a37f',
		'google_ai':  '#E84430',
		'gemini':     '#8E75B2',
		'grok':       '#000000',
		'perplexity': '#20808D',
		'deepseek':   '#4D6BFE',
		'mistral':    '#F7D046',
		'copilot':    '#6264A7',
		'qwen':       '#615CED',
		'meta_ai':    '#0081FB'
	};

	// Platform type classification.
	var platformTypes = {
		'twitter':    'social',
		'linkedin':   'social',
		'facebook':   'social',
		'telegram':   'social',
		'whatsapp':   'social',
		'email':      'social',
		'raindrop':   'social',
		'reddit':     'social',
		'bluesky':    'social',
		'line':       'social',
		'mastodon':   'social',
		'threads':    'social',
		'pinterest':  'social',
		'claude':     'ai',
		'chatgpt':    'ai',
		'google_ai':  'ai',
		'gemini':     'ai',
		'grok':       'ai',
		'perplexity': 'ai',
		'deepseek':   'ai',
		'mistral':    'ai',
		'copilot':    'ai',
		'qwen':       'ai',
		'meta_ai':    'ai'
	};

	// Platform display names.
	var platformLabels = {
		'twitter':    'X (Twitter)',
		'linkedin':   'LinkedIn',
		'facebook':   'Facebook',
		'telegram':   'Telegram',
		'whatsapp':   'WhatsApp',
		'email':      'Email',
		'raindrop':   'Raindrop',
		'reddit':     'Reddit',
		'bluesky':    'Bluesky',
		'line':       'LINE',
		'mastodon':   'Mastodon',
		'threads':    'Threads',
		'pinterest':  'Pinterest',
		'claude':     'Claude',
		'chatgpt':    'ChatGPT',
		'google_ai':  'Google AI',
		'gemini':     'Gemini',
		'grok':       'Grok',
		'perplexity': 'Perplexity',
		'deepseek':   'DeepSeek',
		'mistral':    'Mistral',
		'copilot':    'Copilot',
		'qwen':       'Qwen',
		'meta_ai':    'Meta AI'
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {

		// Initialize color picker fields (v1.6.0).
		if ($.fn.wpColorPicker) {
			$('.ayudawp-color-field').wpColorPicker();
		}

		// Toggle custom colors visibility when style changes (v1.6.0).
		$('input[name="ayudawp_aiss_options[button_colors]"]').on('change', function() {
			if ($(this).val() === 'custom') {
				$('#ayudawp-custom-colors').show();
			} else {
				$('#ayudawp-custom-colors').hide();
			}
		});

		// Toggle the AI summary custom colors when its style changes (v2.1.0).
		$('input[name="ayudawp_aiss_summary_options[ai_summary_style]"]').on('change', function() {
			if ($(this).val() === 'custom') {
				$('#ayudawp-aiss-summary-custom-colors').show();
			} else {
				$('#ayudawp-aiss-summary-custom-colors').hide();
			}
		});

		// Init jQuery UI Sortable for both group lists (v1.6.0).
		if ($.fn.sortable) {
			$('.ayudawp-sortable-list').sortable({
				axis: 'y',
				cursor: 'grabbing',
				handle: '.dashicons-menu',
				placeholder: 'ayudawp-sortable-placeholder',
				opacity: 0.7
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
	function apiRequest(endpoint, params, callback) {
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
			'<span class="aiss-tooltip-vs">' + escapeHtml(vsText) + '</span>' +
			'<span>' + formatDateForTooltip(compareDate) + '</span>' +
			'</div>';

		html += '<div class="aiss-tooltip-body">';
		html += '<div class="aiss-tooltip-row">' +
			'<span class="aiss-tooltip-label"><span class="aiss-tooltip-dot" style="background:' + brandColor + '"></span>' +
			escapeHtml(aissAdminData.strings.currentPeriod || 'Current period') + '</span>' +
			'<span class="aiss-tooltip-value">' + formatNumber(currentVal) + '</span></div>';
		html += '<div class="aiss-tooltip-row">' +
			'<span class="aiss-tooltip-label"><span class="aiss-tooltip-dot" style="background:#999"></span>' +
			escapeHtml(aissAdminData.strings.previousPeriod || 'Previous period') + '</span>' +
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
		html += escapeHtml(label);
		html += '</span></td>';
		html += '<td class="aiss-col-type"><span class="' + typeCss + '">';
		html += '<span class="dashicons ' + typeIcon + '"></span>';
		html += escapeHtml(typeLabel);
		html += '</span></td>';
		html += '<td class="num">' + formatNumber(row.click_count) + '</td>';
		html += '</tr>';

		return html;
	}

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
			$thead.append('<th class="num">' + (aissAdminData.strings.aiCrawls || 'AI Crawls') + '</th>');
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
		html += '<td title="' + escapeHtml(title) + '">';
		html += '<strong>' + escapeHtml(truncTitle) + '</strong>';
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
				var csvTitle = siteName + ' - Share Buttons & AI-powered Summaries Analytics - ' + startDate + ' to ' + endDate;
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
	 * Escape HTML entities
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
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