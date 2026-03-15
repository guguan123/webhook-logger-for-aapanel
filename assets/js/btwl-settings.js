/**
 * 生成一个指定长度的随机字符串作为密钥
 * @returns {string} 随机密钥字符串
 */
function generateRandomKey() {
	const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	let result = '';
	const charactersLength = characters.length;
	for (let i = 0; i < 40; i++) {
		result += characters.charAt(Math.floor(Math.random() * charactersLength));
	}
	return result;
}

document.addEventListener('DOMContentLoaded', function () {
	const btn = document.querySelector('.button-secondary');
	if (btn) {
		btn.addEventListener('click', function () {
			const input = document.getElementById('btwl_access_key');
			if (input) input.value = generateRandomKey();
		});
	}

	// 控制邮箱项显示/隐藏的逻辑
	const enableEmailCheckbox = document.getElementById('btwl_enable_email');
	const targetEmailRow = document.getElementById('target_email_row');
	if (enableEmailCheckbox && targetEmailRow) {
		// 定义切换函数
		const toggleEmailRow = () => {
			if (enableEmailCheckbox.checked) {
				// 显示
				targetEmailRow.style.display = 'table-row';
			} else {
				// 隐藏
				targetEmailRow.style.display = 'none';
			}
		};
		toggleEmailRow();
		enableEmailCheckbox.addEventListener('change', toggleEmailRow);
	}
});
