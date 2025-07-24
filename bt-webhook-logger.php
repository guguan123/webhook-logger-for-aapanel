<?php
/*
Plugin Name: BT WebHook Logger (CPT Version)
Description: 接收宝塔面板 WebHook，把请求体写入数据库，并在后台查看。使用自定义文章类型存储日志。
Version:     2.1
Author:      Your Name
*/

if (!defined('ABSPATH')) exit;

/**
 * BT WebHook Logger 主类
 * 封装所有插件功能，避免全局命名空间污染。
 * 使用自定义文章类型 (CPT) 存储日志。
 */
class BT_WebHook_Logger {

	/**
	 * @var string 自定义文章类型 slug
	 */
	private $post_type = 'bt_webhook_log';

	/**
	 * @var string 插件选项名称 for access_key
	 */
	private $option_access_key = 'btwl_access_key';

	/**
	 * @var string 插件选项名称 for enable email notification
	 */
	private $option_enable_email = 'btwl_enable_email';

	/**
	 * @var string 插件选项名称 for target email address
	 */
	private $option_target_email = 'btwl_target_email';

	/**
	 * 构造函数：初始化插件并注册所有钩子。
	 */
	public function __construct() {
		// 注册 CPT
		add_action('init', array($this, 'register_webhook_log_cpt'));

		// 注册 WebHook 接收端
		add_action('parse_request', array($this, 'handle_webhook'));

		// 注册后台菜单钩子
		add_action('admin_menu', array($this, 'admin_menus'));

		// 注册后台操作处理钩子
		add_action('admin_init', array($this, 'handle_clear_logs'));
		add_action('admin_init', array($this, 'handle_settings_save'));

		// 确保管理通知能正常显示
		//add_action('admin_notices', 'settings_errors');
	}

	/**
	 * 注册自定义文章类型 'bt_webhook_log'。
	 */
	public function register_webhook_log_cpt() {
		$labels = array(
			'name'          => 'WebHook 日志',
			'singular_name' => 'WebHook 日志',
			'menu_name'     => 'WebHook 日志',
			'all_items'     => '所有日志',
			'add_new'       => '添加新日志', // 不会实际用到，但需要定义
			'add_new_item'  => '添加新日志',
			'edit_item'     => '编辑日志',
			'new_item'      => '新日志',
			'view_item'     => '查看日志',
			'search_items'  => '搜索日志',
			'not_found'     => '没有找到日志',
			'not_found_in_trash' => '回收站中没有找到日志',
			'parent_item_colon' => '父日志:',
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false, // 不对外公开
			'publicly_queryable'  => false, // 不可通过 URL 直接查询
			'show_ui'             => false, // 不在后台显示独立的菜单项（我们有自己的日志页面）
			'show_in_menu'        => false, // 不在后台显示独立的菜单项
			'query_var'           => false, // 不作为查询变量
			'rewrite'             => false, // 不重写 URL
			'capability_type'     => 'post',
			'has_archive'         => false, // 没有归档页面
			'hierarchical'        => false, // 非层级结构
			'menu_position'       => null,
			'supports'            => array('title', 'custom-fields'), // 支持标题和自定义字段
			'can_export'          => true, // 可以导出
			'delete_with_user'    => false, // 不随用户删除而删除
			'exclude_from_search' => true, // 不在站点搜索中显示
		);

		register_post_type($this->post_type, $args);
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

		$request_body = file_get_contents('php://input');
		$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
		$data_format = 'raw'; // 默认格式为 'raw'
		$request_ip = $_SERVER['REMOTE_ADDR'];
		$request_time = current_time('mysql');

		// 尝试解析 JSON 格式
		if (strpos($content_type, 'application/json') !== false) {
			$decoded_body = json_decode($request_body, true);
			if (json_last_error() === JSON_ERROR_NONE) {
				$request_body_formatted = json_encode($decoded_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
				$data_format = 'json';
			} else {
				$request_body_formatted = $request_body; // JSON 解析失败，保存原始数据
			}
		}
		// 尝试解析 x-www-form-urlencoded 格式
		else if (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
			parse_str($request_body, $decoded_body);
			if (!empty($decoded_body)) {
				$request_body_formatted = print_r($decoded_body, true);
				$data_format = 'urlencoded';
			} else {
				$request_body_formatted = $request_body; // URL-encoded 解析失败，保存原始数据
			}
		} else {
			$request_body_formatted = $request_body; // 其他格式或无 Content-Type，保存原始数据
		}

		// 插入为自定义文章类型
		$post_id = wp_insert_post(array(
			'post_type'     => $this->post_type,
			'post_title'    => 'WebHook Log ' . $request_time . ' from ' . $request_ip, // 更详细的标题
			'post_status'   => 'publish',
			'post_date'     => $request_time,
			'post_date_gmt' => current_time('mysql', 1),
		));

		if ($post_id) {
			// 保存日志数据为文章元数据 (post meta)
			update_post_meta($post_id, '_btwl_ip', $request_ip);
			update_post_meta($post_id, '_btwl_body', $request_body_formatted);
			update_post_meta($post_id, '_btwl_format', $data_format);
		}

		// 发送邮件通知（如果启用）
		$this->send_email_notification($request_ip, $data_format, $request_body_formatted, $request_time);

		// 返回宝塔面板需要的成功响应
		header('Content-Type: application/json; charset=utf-8');
		echo '{"code": 1}';
		exit;
	}

	/**
	 * 发送邮件通知。
	 *
	 * @param string $ip 请求IP。
	 * @param string $format 请求格式。
	 * @param string $body 请求体内容。
	 * @param string $time 请求时间。
	 */
	private function send_email_notification($ip, $format, $body, $time) {
		$enable_email = get_option($this->option_enable_email, '0');
		$target_email = get_option($this->option_target_email, '');

		// 检查是否启用邮件通知且目标邮箱有效
		if ('1' === $enable_email && is_email($target_email)) {
			$subject = 'BT WebHook Logger: New WebHook Received (' . $format . ')';

			$message = "收到新的宝塔 WebHook 日志：\n\n";
			$message .= "时间: " . $time . "\n";
			$message .= "来源 IP: " . $ip . "\n";
			$message .= "格式: " . $format . "\n";
			$message .= "请求体:\n" . $body . "\n\n";
			$message .= "请登录WordPress后台查看更多详情。\n";
			$message .= "日志页面: " . admin_url('tools.php?page=btwl-logs') . "\n";

			// 设置邮件头，确保内容类型为纯文本
			$headers = array('Content-Type: text/plain; charset=UTF-8');

			wp_mail($target_email, $subject, $message, $headers);
		}
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

			// 获取所有指定 CPT 的 ID 并逐一删除
			$args = array(
				'post_type'      => $this->post_type,
				'posts_per_page' => -1, // 获取所有日志
				'fields'         => 'ids', // 只获取文章 ID
				'post_status'    => 'any', // 包括所有状态的日志
				'no_found_rows'  => true, // 优化查询，不计算总行数
			);
			$query = new WP_Query($args);

			if ($query->have_posts()) {
				foreach ($query->posts as $post_id) {
					// 强制删除，绕过回收站
					wp_delete_post($post_id, true);
				}
			}

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

			// 保存 Access Key
			$new_access_key = sanitize_text_field($_POST['btwl_access_key']);
			update_option($this->option_access_key, $new_access_key);

			// 保存邮件通知设置
			$enable_email = isset($_POST['btwl_enable_email']) ? '1' : '0';
			update_option($this->option_enable_email, $enable_email);

			$target_email = sanitize_email($_POST['btwl_target_email']);
			update_option($this->option_target_email, $target_email);

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
