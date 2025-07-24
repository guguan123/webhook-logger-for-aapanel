<?php
/**
 * settings-page.php
 * BT WebHook 设置页
 */

if (!defined('ABSPATH')) exit; // 防止直接访问
$current_access_key = get_option('btwl_access_key', '');
?>
<div class="wrap">
	<h1>BT WebHook 设置</h1>

	<form method="post">
		<?php wp_nonce_field('btwl_settings_nonce'); ?>
		<table class="form-table">
			<tr class="btwl-settings-section">
				<th scope="row"><label for="btwl_access_key">Access Key</label></th>
				<td>
					<input type="text" id="btwl_access_key" name="btwl_access_key" value="<?php echo esc_attr($current_access_key); ?>" class="regular-text">
					<p class="description">设置一个 Access Key 来保护你的 WebHook。留空表示不需要 Access Key（不推荐）。</p>
					<p class="description">例如：`<?php echo esc_url(site_url('/?btwebhook=1&access_key=your_secret_key')); ?>`</p>
					<?php if (empty($current_access_key)) : ?>
						<p class="description" style="color: red;">当前未设置 Access Key，WebHook 地址对所有请求开放，存在安全风险。</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="btwl_save_settings" id="submit" class="button button-primary" value="保存设置">
			<button type="button" class="button button-secondary" onclick="document.getElementById('btwl_access_key').value = generateRandomKey();">生成随机密钥</button>
		</p>
	</form>
</div>
<script>
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
