<?php
/**
 * logs-page.php
 * BT WebHook 日志展示页面
 */

// 防止直接访问此文件
if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'btwl_logs';
$page  = max(1, intval($_GET['paged'] ?? 1)); // 获取当前页码
$limit = 20; // 每页显示数量
$offset = ($page - 1) * $limit; // 计算偏移量

// 从数据库获取日志记录
$rows  = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d",
	$limit,
	$offset
));
// 获取总记录数
$total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
?>
<div class="wrap">
	<h1>BT WebHook 日志</h1>

	<div class="btwl-toolbar">
		<p>共 <?php echo $total; ?> 条记录</p>
		<!-- 清空记录表单，包含 Nonce 字段和确认提示 -->
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
						<!-- 使用 pre 标签保留格式，并添加样式控制显示 -->
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
	// 简单分页导航
	$max = ceil($total / $limit);
	if ($max > 1) {
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo paginate_links([
			'base'    => add_query_arg('paged', '%#%'), // 分页链接的基础 URL
			'format'  => '',
			'current' => $page,
			'total'   => $max,
			'prev_text' => '&laquo;', // 上一页文本
			'next_text' => '&raquo;', // 下一页文本
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
	/* 工具栏样式，用于布局日志总数和清空按钮 */
	.btwl-toolbar {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 15px;
	}
	.btwl-toolbar p {
		margin: 0;
	}
	/* 危险按钮样式，用于清空操作 */
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
</style>
