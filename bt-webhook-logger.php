<?php
/*
Plugin Name: BT WebHook Logger
Description: 接收宝塔面板 WebHook，把请求体写入数据库，并在后台查看。支持 JSON 和 x-www-form-urlencoded 格式的自动解析。
Version:     1.5
Author:      Your Name
*/

if (!defined('ABSPATH')) exit;

/**
 * BT WebHook Logger 主类
 * 封装所有插件功能，避免全局命名空间污染。
 */
class BT_WebHook_Logger {

	/**
	 * @var string 数据库表名
	 */
	private $table_name;

	/**
	 * @var string 插件选项名称 for access_key
	 */
	private $option_access_key = 'btwl_access_key';

	/**
	 * 构造函数：初始化插件并注册所有钩子。
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'btwl_logs';

		// 注册激活钩子
		register_activation_hook(__FILE__, array($this, 'activate'));

		// 注册 WebHook 接收端
		add_action('parse_request', array($this, 'handle_webhook'));

		// 注册后台菜单钩子
		add_action('admin_menu', array($this, 'admin_menus'));

		// 注册后台操作处理钩子
		add_action('admin_init', array($this, 'handle_clear_logs'));
		add_action('admin_init', array($this, 'handle_settings_save'));

		// 确保管理通知能正常显示
		add_action('admin_notices', 'settings_errors');
	}

	/**
	 * 插件激活时创建数据表。
	 */
	public function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			time datetime DEFAULT CURRENT_TIMESTAMP,
			ip varchar(64) DEFAULT '',
			body longtext,
			format varchar(20) DEFAULT '',
			PRIMARY KEY (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * 处理宝塔面板 WebHook 请求。
	 */
	public function handle_webhook() {
		// 仅在 ?btwebhook=1 且 POST 时进入
		if (!isset($_GET['btwebhook']) || $_GET['btwebhook'] !== '1') {
			return;
		}
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
		}

		// 获取配置的 access_key
		$configured_access_key = get_option($this->option_access_key);

		// 如果配置了 access_key，则进行验证
		if (!empty($configured_access_key)) {
			$request_access_key = $_GET['access_key'] ?? '';
			if ($request_access_key !== $configured_access_key) {
				// 验证失败，返回403状态码并退出
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
				$request_body = print_r($decoded_body, true);
				$data_format = 'urlencoded';
			}
		}

		// 保存数据到数据库
		$wpdb->insert(
			$this->table_name,
			[
				'ip'    => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
				'body'  => $request_body,
				'format' => $data_format,
			],
			['%s', '%s', '%s']
		);

		// 返回宝塔面板需要的成功响应
		header('Content-Type: application/json; charset=utf-8');
		echo '{"code": 1}';
		exit;
	}

	/**
	 * 注册后台管理菜单。
	 */
	public function admin_menus() {
		// 添加日志查看页面到“工具”菜单
		add_submenu_page(
			'tools.php',
			'BT WebHook 日志',
			'BT WebHook 日志',
			'manage_options',
			'btwl-logs',
			array($this, 'display_logs_page') // 调用类方法
		);

		// 添加设置页面到“设置”菜单
		add_submenu_page(
			'options-general.php', // 父菜单 slug，通常是常规设置
			'BT WebHook 设置',
			'BT WebHook 设置',
			'manage_options',
			'btwl-settings',
			array($this, 'display_settings_page') // 调用类方法
		);
	}

	/**
	 * 处理清空日志的请求。
	 */
	public function handle_clear_logs() {
		if (isset($_POST['btwl_clear_logs']) && current_user_can('manage_options')) {
			check_admin_referer('btwl_clear_logs_nonce');

			global $wpdb;
			$wpdb->query("TRUNCATE TABLE {$this->table_name}");

			// 添加管理通知
			add_settings_error(
				'bt-webhook-logger-messages',
				'log-cleared',
				'WebHook 日志已清空！',
				'success'
			);
		}
	}

	/**
	 * 处理保存设置的请求。
	 */
	public function handle_settings_save() {
		if (isset($_POST['btwl_save_settings']) && current_user_can('manage_options')) {
			check_admin_referer('btwl_settings_nonce');

			$new_access_key = sanitize_text_field($_POST['btwl_access_key']);
			update_option($this->option_access_key, $new_access_key);

			// 添加管理通知
			add_settings_error(
				'bt-webhook-logger-messages',
				'setting-save',
				'设置已保存！',
				'success'
			);
		}
	}

	/**
	 * 渲染后台日志页面。
	 * 从外部文件加载模板。
	 */
	public function display_logs_page() {
		// 使用 plugin_dir_path(__FILE__) 获取插件的绝对路径，更安全可靠
		require_once plugin_dir_path(__FILE__) . 'logs-page.php';
	}

	/**
	 * 渲染后台设置页面。
	 * 从外部文件加载模板。
	 */
	public function display_settings_page() {
		require_once plugin_dir_path(__FILE__) . 'settings-page.php';
	}
}

// 实例化插件类，启动插件功能
if (class_exists('BT_WebHook_Logger')) {
	new BT_WebHook_Logger();
}
