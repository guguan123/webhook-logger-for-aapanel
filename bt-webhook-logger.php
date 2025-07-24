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
            // 验证失败，返回错误信息并退出
            header('Content-Type: application/json; charset=utf-8');
            echo '{"code": 0, "msg": "Access Denied: Invalid access_key"}';
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
        'tools.php', // 父菜单
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
            echo '<div class="notice notice-success is-dismissible"><p>设置已保存！</p></div>';
        });
    }
}

// 后台日志页面
function btwl_logs_page() {
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
    <?php
}

// 后台设置页面
function btwl_settings_page() {
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
    <?php
}