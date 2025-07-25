<?php
/**
 * settings-page.php
 * BT WebHook 设置页
 */

// 防止直接访问此文件
if (!defined('ABSPATH')) exit;

// 获取当前保存的 Access Key 和邮件设置
$current_access_key = get_option($this->option_access_key, '');
$current_enable_email = get_option($this->option_enable_email, '0'); // 默认为禁用
$current_target_email = get_option($this->option_target_email, '');
// 获取 REST API 的基础 URL
$rest_api_base_url = get_rest_url(null, 'bt-webhook-logger/v1/receive');
if (empty($current_access_key)) {
	$webhook_url_example = $rest_api_base_url;
} else {
	$webhook_url_example = add_query_arg('access_key', $current_access_key, $rest_api_base_url);
}
?>
<div class="wrap">
	<h1>BT WebHook 设置</h1>

	<form method="post">
		<?php wp_nonce_field('btwl_settings_nonce'); // Nonce 字段用于安全验证 ?>
		<table class="form-table">
			<tr class="btwl-settings-section">
				<th scope="row"><label for="btwl_access_key">Access Key</label></th>
				<td>
					<input type="text" id="btwl_access_key" name="btwl_access_key" value="<?php echo esc_attr($current_access_key); ?>" class="regular-text">
					<p class="description">设置一个 Access Key 来保护你的 WebHook。留空表示不需要 Access Key（不推荐）。</p>
					<p class="description">你的 WebHook 地址: <?php echo esc_url($webhook_url_example); ?></p>
					<?php if (empty($current_access_key)) : ?>
						<p class="description" style="color: red;">当前未设置 Access Key，WebHook 地址对所有请求开放，存在安全风险。</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="btwl-settings-section">
				<th scope="row">邮件通知</th>
				<td>
					<label for="btwl_enable_email">
						<input type="checkbox" id="btwl_enable_email" name="btwl_enable_email" value="1" <?php checked('1', $current_enable_email); ?>>
						启用 WebHook 邮件通知
					</label>
					<p class="description">勾选此项以在每次收到 WebHook 时发送邮件通知。</p>
				</td>
			</tr>
			<tr class="btwl-settings-section">
				<th scope="row"><label for="btwl_target_email">目标邮箱地址</label></th>
				<td>
					<input type="email" id="btwl_target_email" name="btwl_target_email" value="<?php echo esc_attr($current_target_email); ?>" class="regular-text">
					<p class="description">接收 WebHook 通知邮件的邮箱地址。请确保您的 WordPress 已正确配置邮件发送服务。</p>
					<?php if (!empty($current_enable_email) && !is_email($current_target_email)) : ?>
						<p class="description" style="color: red;">邮件通知已启用，但目标邮箱地址无效，请检查。</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="btwl_save_settings" id="submit" class="button button-primary" value="保存设置">
			<!-- 按钮点击时调用 JavaScript 函数生成随机密钥 -->
			<button type="button" class="button button-secondary" onclick="document.getElementById('btwl_access_key').value = generateRandomKey();">生成随机密钥</button>
		</p>
	</form>
</div>
<script>
	/**
	 * 生成一个指定长度的随机字符串作为密钥
	 * @returns {string} 随机密钥字符串
	 */
	function generateRandomKey() {
		const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		let result = '';
		const charactersLength = characters.length;
		for (let i = 0; i < 40; i++) { // 生成一个40位长的随机字符串
			result += characters.charAt(Math.floor(Math.random() * charactersLength));
		}
		return result;
	}
</script>
<style>
	/* 表单表格的样式调整 */
	.form-table th {
		width: 150px; /* 调整标签列的宽度 */
	}
	.form-table input[type="text"],
	.form-table input[type="email"] { /* 为 email 类型也添加样式 */
		width: 100%; /* 输入框填充可用宽度 */
		max-width: 400px; /* 设置最大宽度 */
	}
	/* 描述文本的样式 */
	.btwl-settings-section p.description {
		font-style: italic;
		color: #666;
		margin-top: 5px;
	}
</style>
