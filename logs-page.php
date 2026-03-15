<?php
/**
 * logs-page.php
 * aaPanel WebHook 日志展示页面
 */

// 防止直接访问此文件
if (!defined('ABSPATH')) exit;

$page  = max(1, intval($_GET['paged'] ?? 1)); // 获取当前页码
$limit = 20; // 每页显示数量
$offset = ($page - 1) * $limit; // 计算偏移量

// 使用 WP_Query 查询自定义文章类型
$log_query = new WP_Query(array(
	'post_type'      => self::POST_TYPE,
	'posts_per_page' => $limit,
	'paged'          => $page,
	'post_status'    => 'publish', // 只获取已发布的日志
	'order'          => 'DESC',
	'orderby'        => 'date',
));

$rows = $log_query->posts; // 获取文章对象数组
$total = $log_query->found_posts; // 获取总日志数（用于分页）
$max = $log_query->max_num_pages; // 获取总页数
?>
<div class="wrap btwl-logs-wrapper">
	<h1><?php echo esc_html(__('BT WebHook 日志', 'webhook-logger-for-aapanel')); ?></h1>

	<div class="btwl-toolbar">
		<div class="btwl-stats">
			<span class="dashicons dashicons-list-view"></span>
			<?php printf(esc_html(/* translators: %s: total number of logs */ _n('%s log', '%s logs', $total, 'webhook-logger-for-aapanel')), '<strong>' . number_format_i18n($total) . '</strong>'); ?>
		</div>
		<!-- 清空记录表单 -->
		<form method="post" onsubmit="return confirm('<?php echo esc_attr(__('确定要清空所有 WebHook 日志吗？此操作不可逆！', 'webhook-logger-for-aapanel')); ?>');">
			<?php wp_nonce_field('btwl_clear_logs_nonce'); ?>
			<button type="submit" name="btwl_clear_logs" class="button button-link-delete">
				<span class="dashicons dashicons-trash"></span> <?php echo esc_html(__('清空所有记录', 'webhook-logger-for-aapanel')); ?>
			</button>
		</form>
	</div>

	<!-- 全新设计的自适应日志列表 -->
	<div class="btwl-log-list">
		<!-- 列表头部（仅桌面端显示） -->
		<div class="btwl-log-header">
			<div class="col-time"><?php esc_html_e('时间', 'webhook-logger-for-aapanel'); ?></div>
			<div class="col-ip"><?php esc_html_e('来源 IP', 'webhook-logger-for-aapanel'); ?></div>
			<div class="col-format"><?php esc_html_e('格式', 'webhook-logger-for-aapanel'); ?></div>
			<div class="col-content"><?php esc_html_e('请求体内容', 'webhook-logger-for-aapanel'); ?></div>
		</div>

		<div class="btwl-log-body">
		<?php if ($log_query->have_posts()) : ?>
			<?php foreach ($rows as $post):
				$log_ip = get_post_meta($post->ID, '_btwl_ip', true);
				$log_body = get_post_meta($post->ID, '_btwl_body', true);
				$log_format = get_post_meta($post->ID, '_btwl_format', true);
				$display_body = is_array($log_body) ? wp_json_encode($log_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $log_body;
			?>
				<div class="btwl-log-item">
					<div class="btwl-log-row-summary">
						<div class="col-time">
							<span class="dashicons dashicons-clock mobile-only"></span>
							<?php echo esc_html($post->post_date); ?>
						</div>
						<div class="col-ip">
							<span class="mobile-label"><?php esc_html_e('来源 IP:', 'webhook-logger-for-aapanel'); ?></span>
							<code><?php echo esc_html($log_ip); ?></code>
						</div>
						<div class="col-format">
							<span class="mobile-label"><?php esc_html_e('格式:', 'webhook-logger-for-aapanel'); ?></span>
							<span class="btwl-badge"><?php echo esc_html($log_format); ?></span>
						</div>
						<div class="col-toggle">
							<button type="button" class="btwl-toggle-btn" title="<?php esc_attr_e('展开/收起详情', 'webhook-logger-for-aapanel'); ?>">
								<span class="dashicons dashicons-arrow-down-alt2"></span>
							</button>
						</div>
					</div>
					<div class="btwl-log-details">
						<div class="col-content">
							<div class="content-header">
								<span class="dashicons dashicons-editor-code"></span> <?php esc_html_e('请求体内容', 'webhook-logger-for-aapanel'); ?>
							</div>
							<pre class="log-body-content"><?php echo esc_html($display_body); ?></pre>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="btwl-no-logs">
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e('暂无 WebHook 日志。', 'webhook-logger-for-aapanel'); ?>
			</div>
		<?php endif; ?>
		</div>
	</div>

	<!-- 分页 -->
	<?php if ($max > 1) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php echo paginate_links(array(
					'base'      => add_query_arg('paged', '%#%'),
					'format'    => '',
					'current'   => $page,
					'total'     => $max,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				)); ?>
			</div>
		</div>
	<?php endif; ?>
</div>
