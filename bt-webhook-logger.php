<?php
/*
Plugin Name: BT WebHook Logger
Description: 接收宝塔面板 WebHook，把请求体写入数据库，并在后台查看。支持 JSON 和 x-www-form-urlencoded 格式的自动解析。
Version:     1.3
Author:      Your Name
*/

if (!defined('ABSPATH')) exit;

/* ---------- 1. 安装时创建数据表 ---------- */
register_activation_hook(__FILE__, 'btwl_create_table');
function btwl_create_table() {
	global $wpdb;
	$table = $wpdb->prefix . 'btwl_logs';
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT CURRENT_TIMESTAMP,
		ip varchar(64) DEFAULT '',
		body longtext,
		format varchar(20) DEFAULT '',
		PRIMARY KEY (id)
	) $charset;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta($sql);
}

/* ---------- 2. 传统 URL 接收端 ---------- */
add_action('parse_request', 'btwl_handle_webhook');
function btwl_handle_webhook($wp) {
	// 仅在 ?btwebhook=1 且 POST 时进入
	if (!isset($_GET['btwebhook']) || $_GET['btwebhook'] !== '1') return;
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

	// 获取配置的 access_key
	$configured_access_key = get_option('btwl_access_key');

	// 如果配置了 access_key，则进行验证
	if (!empty($configured_access_key)) {
		$request_access_key = $_GET['access_key'] ?? '';
		if ($request_access_key !== $configured_access_key) {
			// 验证失败，返回403并退出
			http_response_code(403);
			exit;
		}
	}

	global $wpdb;
	$request_body = file_get_contents('php://input');
	$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
	$data_format = 'raw'; // 默认格式为 'raw'

	// 尝试解析 JSON 格式
	if (strpos($content_type, 'application/json') !== false) {
		$decoded_body = json_decode($request_body, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			$request_body = json_encode($decoded_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			$data_format = 'json';
		}
	} 
	// 尝试解析 x-www-form-urlencoded 格式
	else if (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
		parse_str($request_body, $decoded_body);
		if (!empty($decoded_body)) {
			$request_body = print_r($decoded_body, true); // 使用 print_r 便于阅读
			$data_format = 'urlencoded';
		}
	}

	// 保存数据
	$wpdb->insert(
		$wpdb->prefix . 'btwl_logs',
		[
			'ip'    => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
			'body'  => $request_body,
			'format' => $data_format,
		],
		['%s', '%s', '%s']
	);

	// 返回宝塔需要的格式
	header('Content-Type: application/json; charset=utf-8');
	echo '{"code": 1}';
	exit;
}

/* ---------- 3. 后台管理菜单 ---------- */
add_action('admin_menu', 'btwl_admin_menu');
function btwl_admin_menu() {
	add_submenu_page(
		'tools.php',
		'BT WebHook 日志',
		'BT WebHook 日志',
		'manage_options',
		'btwl-logs',
		'btwl_logs_page'
	);

	// 为设置页面添加一个子菜单项
	add_submenu_page(
		'options-general.php', // 父菜单
		'BT WebHook 设置', // 页面标题
		'BT WebHook 设置', // 菜单标题
		'manage_options', // 必需的能力
		'btwl-settings', // 菜单 slug
		'btwl_settings_page' // 处理函数
	);
}

// 处理清空日志的请求
add_action('admin_init', 'btwl_handle_clear_logs');
function btwl_handle_clear_logs() {
	if (isset($_POST['btwl_clear_logs']) && current_user_can('manage_options')) {
		check_admin_referer('btwl_clear_logs_nonce'); // 验证 Nonce
		
		global $wpdb;
		$table = $wpdb->prefix . 'btwl_logs';
		$wpdb->query("TRUNCATE TABLE $table"); // 清空表

		// 添加管理通知
		add_action('admin_notices', function() {
			echo '<div class="notice notice-success is-dismissible"><p>WebHook 日志已清空！</p></div>';
		});
	}
}

// 处理保存设置的请求
add_action('admin_init', 'btwl_handle_settings_save');
function btwl_handle_settings_save() {
	if (isset($_POST['btwl_save_settings']) && current_user_can('manage_options')) {
		check_admin_referer('btwl_settings_nonce');

		$new_access_key = sanitize_text_field($_POST['btwl_access_key']);
		update_option('btwl_access_key', $new_access_key);

		add_action('admin_notices', function() {
			add_settings_error(
				'bt-webhook-logger-messages',
				'setting-save',
				'设置已保存！',
				'success'
			);
		});
	}
}

// 后台日志页面
function btwl_logs_page() {
	require_once 'logs-page.php';
}

// 后台设置页面
function btwl_settings_page() {
	require_once 'settings-page.php';
}