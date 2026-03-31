jQuery(document).ready(function($) {
	// 如果点击的是按钮或者行摘要，切换展开状态
	$('.btwl-log-row-summary').on('click', function() {
		const $item = $(this).closest('.btwl-log-item');
		const $btn = $item.find('.btwl-toggle-btn .dashicons');

		$item.toggleClass('is-open');

		// 切换图标
		if ($item.hasClass('is-open')) {
			$btn.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
		} else {
			$btn.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
		}
	});
});
