jQuery(document).ready(function($) {
	$('.btwl-toggle-btn, .btwl-log-row-summary').on('click', function(e) {
		// 如果点击的是按钮或者行摘要，切换展开状态
		const $item = $(this).closest('.btwl-log-item');
		
		// 如果点击的是 pre 标签内容，不要折叠
		if ($(e.target).closest('.log-body-content').length) return;

		$item.toggleClass('is-open');
		const is_open = $item.hasClass('is-open');
		$item.find('.dashicons-arrow-down-alt2, .dashicons-arrow-up-alt2')
			 .toggleClass('dashicons-arrow-down-alt2', !is_open)
			 .toggleClass('dashicons-arrow-up-alt2', is_open);
	});

	// 防止点击 pre 标签时触发折叠
	$('.log-body-content').on('click', function(e) {
		e.stopPropagation();
	});
});
