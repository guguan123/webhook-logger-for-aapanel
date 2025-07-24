<?php
/**
 * logs-page.php
 * BT WebHook 日志展示页面
 */


if (!defined('ABSPATH')) exit; // 防止直接访问
global $wpdb;
$table = $wpdb->prefix . 'btwl_logs';
$page  = max(1, intval($_GET['paged'] ?? 1));
$limit = 20; // 调整每页显示数量，避免过长
$offset = ($page - 1) * $limit;
$rows  = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d",
	$limit,
	$offset
));
$total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
?>
<div class="wrap">
	<h1>BT WebHook 日志</h1>

	<div class="btwl-toolbar">
		<p>共 <?php echo $total; ?> 条记录</p>
		<form method="post" onsubmit="return confirm('确定要清空所有 WebHook 日志吗？此操作不可逆！');">
			<?php wp_nonce_field('btwl_clear_logs_nonce'); ?>
			<input type="submit" name="btwl_clear_logs" class="button button-danger" value="清空所有记录">
		</form>
	</div>

	<table class="widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 150px;">时间</th>
				<th style="width: 120px;">来源 IP</th>
				<th style="width: 100px;">格式</th>
				<th>请求体内容</th>
			</tr>
		</thead>
		<tbody>
		<?php if (!empty($rows)) : ?>
			<?php foreach ($rows as $row): ?>
				<tr>
					<td><?php echo esc_html($row->time); ?></td>
					<td><?php echo esc_html($row->ip); ?></td>
					<td><?php echo esc_html($row->format); ?></td>
					<td>
						<pre style="white-space: pre-wrap; word-break: break-all; margin: 0; padding: 5px; background: #f9f9f9; border: 1px solid #eee; overflow: auto; max-height: 200px;"><?php echo esc_html($row->body); ?></pre>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr>
				<td colspan="4">暂无 WebHook 日志。</td>
			</tr>
		<?php endif; ?>
		</tbody>
	</table>
	<?php
	// 简单分页
	$max = ceil($total / $limit);
	if ($max > 1) {
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo paginate_links([
			'base'    => add_query_arg('paged', '%#%'),
			'format'  => '',
			'current' => $page,
			'total'   => $max,
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
		]);
		echo '</div></div>';
	}
	?>
</div>
<style>
	/* 为日志内容区添加样式，提高可读性 */
	.wrap table pre {
		font-size: 13px;
		line-height: 1.5;
	}
	.btwl-toolbar {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 15px;
	}
	.btwl-toolbar p {
		margin: 0;
	}
	.button-danger {
		background: #dc3232;
		border-color: #dc3232;
		color: #fff;
		box-shadow: 0 1px 0 rgba(0,0,0,.15);
		text-shadow: 0 -1px 1px #b30000, 1px 0 1px #b30000, 0 1px 1px #b30000, -1px 0 1px #b30000;
	}
	.button-danger:hover,
	.button-danger:focus {
		background: #e35a5a;
		border-color: #e35a5a;
		color: #fff;
	}
	.form-table th {
		width: 150px; /* 调整标签列的宽度 */
	}
	.form-table input[type="text"] {
		width: 100%; /* 输入框填充可用宽度 */
		max-width: 400px; /* 设置最大宽度 */
	}
	.btwl-settings-section p {
		font-style: italic;
		color: #666;
		margin-top: 5px;
	}
</style>