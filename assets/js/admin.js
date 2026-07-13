/**
 * Speed Doctor Admin Dashboard Scripts.
 *
 * Handles sidebar tab switching, dashboard quick triggers, and client-side light/dark theme toggles.
 */
jQuery(document).ready(function($) {
	// Tab switching logic
	$('.spdr-tab-btn').on('click', function() {
		var target = $(this).data('target');
		
		// Toggle buttons
		$('.spdr-tab-btn').removeClass('active');
		$(this).addClass('active');
		
		// Toggle panels
		$('.spdr-tab-panel').removeClass('active');
		$('#panel-' + target).addClass('active');
	});

	// Dashboard quick action links
	$('.spdr-trigger-tab').on('click', function(e) {
		e.preventDefault();
		var tabTarget = $(this).data('tab');
		$('.spdr-tab-btn[data-target="' + tabTarget + '"]').trigger('click');
	});

	// Light/Dark mode toggle logic
	$('#spdr-theme-toggle').on('click', function() {
		var wrap = $('#spdr-wrap');
		if (wrap.hasClass('spdr-light-mode')) {
			wrap.removeClass('spdr-light-mode');
			localStorage.setItem('spdr_theme', 'dark');
		} else {
			wrap.addClass('spdr-light-mode');
			localStorage.setItem('spdr_theme', 'light');
		}
	});
});
