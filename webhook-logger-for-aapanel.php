<?php
/**
 * @wordpress-plugin
 * Plugin Name:         WebHook Logger for aaPanel
 * Plugin URI:          https://github.com/guguan123/webhook-logger-for-aapanel
 * Description:         接收宝塔面板 WebHook 信息，并发送邮件通知
 * Version:             0.1.1
 * Author:              GuGuan123
 * Author URI:          https://github.com/guguan123
 * License:             MIT
 * License URI:         https://choosealicense.com/licenses/mit/
 * Text Domain:         webhook-logger-for-aapanel
 * Requires at least:   6.0
 * Tested up to:        6.8
 * PHP Version:         8.2
 * Requires PHP:        7.0
 * Changelog:           https://github.com/guguan123/webhook-logger-for-aapanel/releases
 * Support:             https://github.com/guguan123/webhook-logger-for-aapanel/issues
 */

// 防止直接访问此文件
if (!defined('ABSPATH')) exit;

/**
 * BT WebHook Logger 主类
 */
class WebHook_Logger_for_aaPanel {

	/**
	 * @var string 自定义文章类型 slug
	 */
	const POST_TYPE = 'bt_webhook_log';

	/**
	 * @var string 插件选项名称 for access_key
	 */
	const OPTION_ACCESS_KEY = 'btwl_access_key';

	/**
	 * @var string 插件选项名称 for enable email notification
	 */
	const OPTION_ENABLE_EMAIL = 'btwl_enable_email';

	/**
	 * @var string 插件选项名称 for target email address
	 */
	const OPTION_TARGET_EMAIL = 'btwl_target_email';

	/**
	 * 构造函数：初始化插件并注册所有钩子。
	 */
	public function __construct() {
		// 注册 CPT
		add_action('init', array($this, 'register_webhook_log_cpt'));

		// 注册 WebHook 接收端 (REST API)
		add_action('rest_api_init', array($this, 'register_webhook_rest_route')); // 修改点

		// 注册后台菜单钩子
		add_action('admin_menu', array($this, 'admin_menus'));

		// 注册后台操作处理钩子
		add_action('admin_init', array($this, 'handle_clear_logs'));
		add_action('admin_init', array($this, 'handle_settings_save'));

		// 注册管理页面脚本
		add_action('admin_enqueue_scripts', function($hook) {
			// 只在 BT WebHook 设置页加载
			if ($hook == 'settings_page_btwl-settings') {
				wp_enqueue_script(
					'btwl-settings-js',
					plugins_url('assets/js/btwl-settings.js', __FILE__),
					array(),
					'0.1.0',
					true
				);
				wp_enqueue_style(
					'btwl-settings-css',
					plugins_url('assets/css/btwl-settings.css', __FILE__),
					array(),
					'0.1.0'
				);
			} elseif ($hook == 'tools_page_btwl-logs') {
				wp_enqueue_style(
					'btwl-logs',
					plugins_url('assets/css/btwl-logs.css', __FILE__),
					array(),
					'0.1.0'
				);
			}
		});

		// 卸载插件后清理缓存数据
		register_uninstall_hook(__FILE__, ['BT_WebHook_Logger', 'btwl_uninstall']);
	}

	// 删除日志和配置
	public static function btwl_uninstall() {
		delete_option(self::OPTION_ACCESS_KEY);
		delete_option(self::OPTION_ENABLE_EMAIL);
		delete_option(self::OPTION_TARGET_EMAIL);
		self::delete_logs();
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

		register_post_type(self::POST_TYPE, $args);
	}

	/**
	 * 注册 WebHook 的 REST API 路由。
	 */
	public function register_webhook_rest_route() {
		register_rest_route(
			'bt-webhook-logger/v1', // 命名空间
			'/receive', // 路由
			array(
				'methods'             => 'POST', // 只允许 POST 请求
				'callback'            => array($this, 'handle_webhook'), // 处理请求的回调函数
				'permission_callback' => array($this, 'webhook_permission_check'), // 权限检查回调
			)
		);
	}

	/**
	 * WebHook 权限检查。
	 * 用于验证 access_key。
	 *
	 * @param WP_REST_Request $request 当前请求对象。
	 * @return bool|WP_Error 如果验证通过返回 true，否则返回 WP_Error 对象。
	 */
	public function webhook_permission_check(WP_REST_Request $request) {
		$configured_access_key = get_option(self::OPTION_ACCESS_KEY);

		// 如果配置了 access_key，则进行验证
		if (!empty($configured_access_key)) {
			// 从请求参数中获取 access_key
			$request_access_key = $request->get_param('access_key');
			if ($request_access_key !== $configured_access_key) {
				return new WP_Error(
					'bt_webhook_auth_failed',
					'Access Key 验证失败',
					array('status' => 403)
				);
			}
		}
		return true; // 没有配置 access_key 或验证通过
	}

	/**
	 * 处理宝塔面板 WebHook 请求。
	 *
	 * @param WP_REST_Request $request 当前请求对象。
	 * @return WP_REST_Response 返回 JSON 响应。
	 */
	public function handle_webhook(WP_REST_Request $request) {
		// 从请求对象获取原始请求体和内容类型
		$request_body = $request->get_body();
		$content_type = $request->get_header('content-type');
		$data_format = 'raw'; // 默认格式为 'raw'
		$request_ip = $_SERVER['REMOTE_ADDR'];
		$request_time = current_time('mysql');

		// 尝试解析 JSON 格式
		if (strpos($content_type, 'application/json') !== false) {
			$decoded_body = json_decode($request_body, true);
			if (json_last_error() === JSON_ERROR_NONE) {
				$data_format = 'json';
				$request_body = $decoded_body;
			}
		}
		// 尝试解析 x-www-form-urlencoded 格式
		else if (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
			parse_str($request_body, $decoded_body);
			if (!empty($decoded_body)) {
				$data_format = 'urlencoded';
				$request_body = $decoded_body;
			}
		}
		// 如果没有成功解析为 JSON 或 URL-encoded，则保持为 raw 格式

		// 插入为自定义文章类型
		$post_id = wp_insert_post(array(
			'post_type'     => self::POST_TYPE,
			'post_title'    => 'WebHook Log ' . $request_time . ' from ' . $request_ip, // 更详细的标题
			'post_status'   => 'publish',
			'post_date'     => $request_time,
			'post_date_gmt' => current_time('mysql', 1),
		));

		if ($post_id) {
			// 保存日志数据为文章元数据 (post meta)
			update_post_meta($post_id, '_btwl_ip', $request_ip);
			update_post_meta($post_id, '_btwl_body', $request_body);
			update_post_meta($post_id, '_btwl_format', $data_format);
		}

		// 检查是否启用邮件通知
		if (get_option(self::OPTION_ENABLE_EMAIL, '0') == 1) {
			// 发送邮件通知（如果启用）
			$this->send_email_notification($request_ip, $data_format, $request_body, $request_time);
		}

		// 返回宝塔面板需要的成功响应
		return new WP_REST_Response(array('code' => 1), 200);
	}

	/**
	 * 发送邮件通知。
	 *
	 * @param string $ip 请求IP。
	 * @param string $format 请求格式。
	 * @param string|array $body 请求体内容。
	 * @param string $time 请求时间。
	 */
	private function send_email_notification($ip, $format, $body, $time) {
		$target_email = get_option(self::OPTION_TARGET_EMAIL, '');

		// 检查目标邮箱是否有效
		if (is_email($target_email)) {
			$subject = 'BT WebHook Logger: New WebHook Received (' . $format . ')';

			$message = "收到新的宝塔 WebHook 日志：\n\n";
			$message .= "时间: " . $time . "\n";
			$message .= "来源 IP: " . $ip . "\n";
			$message .= "格式: " . $format . "\n";

			// 根据解析后的数据结构，格式化请求体
			$message .= "--------------------\n";
			$message .= "WebHook 内容详情:\n";
			if (!empty($body) && is_array($body) && isset($body['title']) && isset($body['type']) && isset($body['msg'])) {
				$message .= "标题: {$body['title']}\n";
				$message .= "类型: {$body['type']}\n";
				$message .= "正文:\n";
				$message .= $body['msg'] . "\n";
			} else {
				// 如果解析失败或不是预期的结构，则使用原始格式化的请求体
				$message .= "原始内容:\n";
				$message .= print_r($body, true) . "\n";
			}
			$message .= "--------------------\n\n";

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
			'aaPanel WebHook 日志',
			'aaPanel WebHook 日志',
			'manage_options',
			'btwl-logs',
			array($this, 'display_logs_page') // 调用类方法
		);

		// 添加设置页面到“设置”菜单
		add_submenu_page(
			'options-general.php', // 父菜单 slug，通常是常规设置
			'aaPanel WebHook 设置',
			'aaPanel WebHook 设置',
			'manage_options',
			'btwl-settings',
			array($this, 'display_settings_page') // 调用类方法
		);
	}

	/**
	 * 删除所有日志
	 */
	private static function delete_logs() {
		// 获取所有指定 CPT 的 ID 并逐一删除
		$args = array(
			'post_type'      => self::POST_TYPE,
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
	}

	/**
	 * 处理清空日志的请求。
	 */
	public function handle_clear_logs() {
		if (isset($_POST['btwl_clear_logs']) && current_user_can('manage_options')) {
			check_admin_referer('btwl_clear_logs_nonce');

			// 删除所有记录
			self::delete_logs();

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

			// 校验输入的邮箱格式是否正确
			if (is_email($_POST['btwl_target_email'])) {
				// 保存 Access Key
				$new_access_key = sanitize_text_field($_POST['btwl_access_key']);
				update_option(self::OPTION_ACCESS_KEY, $new_access_key);

				// 保存邮件通知设置
				$enable_email = isset($_POST['btwl_enable_email']) ? '1' : '0';
				update_option(self::OPTION_ENABLE_EMAIL, $enable_email);

				$target_email = sanitize_email($_POST['btwl_target_email']);
				update_option(self::OPTION_TARGET_EMAIL, $target_email);

				// 添加管理通知
				add_settings_error(
					'bt-webhook-logger-messages',
					'setting-save',
					'设置已保存！',
					'success'
				);
			} else {
				// 报告错误
				add_settings_error(
					'bt-webhook-logger-messages',
					'setting-save',
					'邮箱格式不正确！',
					'error'
				);
			}
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
if (class_exists('WebHook_Logger_for_aaPanel')) {
	new WebHook_Logger_for_aaPanel();
}
