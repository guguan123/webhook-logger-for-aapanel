=== BaoTa WebHook Logger ===
Contributors: GuGuan123
Donate link: https://s1.imagehub.cc/images/2025/03/04/33128a3f3455b55b5c7321ee4c05527c.jpg
Tags: 宝塔, BT, Security
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 0.1.1
Requires PHP: 7.4
License: MIT
License URI: https://opensource.org/licenses/MIT

接收宝塔面板 WebHook 信息，并发送邮件通知

== Description ==
BaoTa WebHook Logger 是一款 WordPress 插件，旨在帮助您轻松接收和记录来自宝塔面板的 WebHook 通知。
该插件将收到的 WebHook 请求存储为 WordPress 后台的自定义日志，方便您随时查看。
此外，它还支持通过邮件发送 WebHook 通知，确保您不会错过任何重要信息。

主要功能包括：
* 邮件通知: 可选择启用邮件通知功能，在每次收到 WebHook 时，向指定的邮箱地址发送详细的通知邮件。
* 日志管理: 提供后台页面，方便用户查看所有 WebHook 日志，并支持分页浏览和一键清空所有日志的功能。

== Installation ==
1.  在 WordPress 后台的“插件”页面激活插件。
2.  激活后，您可以在“设置”菜单下找到“BT WebHook 设置”，在“工具”菜单下找到“BT WebHook 日志”。
3.  进入“BT WebHook 设置”页面，配置您的 Access Key 和邮件通知选项。
4.  在宝塔面板中配置 WebHook 地址为插件设置页面中显示的地址（例如：https://您的域名/?rest_route=/bt-webhook-logger/v1/receive）。

== Screenshots ==
1. 设置页面
![设置页面截图](https://s1.imagehub.cc/images/2025/07/25/b24ec480c37d59a8d2fa78510c870512.png)
2. 日志列表页面
![日志列表截图](https://s1.imagehub.cc/images/2025/07/25/4072bbbee30a63c8b55b3a1207e0ecd7.png)
3. 邮件提醒
![邮件内容截图](https://s1.imagehub.cc/images/2025/07/25/39f5dda8017037045e4094150b250682.png)

== Changelog ==
= 0.1.1 =
* 改成使用REST API

= 0.1.0 =
* 初始版本发布。
* 支持接收和记录宝塔 WebHook。
* 提供 Access Key 安全验证。
* 集成邮件通知功能。
* 实现后台日志查看和清空功能。

== Frequently Asked Questions ==
= 我收不到邮件通知怎么办？ =
请确保您在“BT WebHook 设置”中正确填写了目标邮箱地址并勾选了“启用 WebHook 邮件通知”。
同时，请检查您的 WordPress 站点是否已正确配置邮件发送功能，例如是否安装了 SMTP 插件并进行了正确设置。

= Access Key 的作用是什么？ =
Access Key 是一个安全密钥，用于验证 WebHook 请求的合法性。如果您设置了 Access Key，只有在 WebHook 请求中包含正确的 Access Key 时，插件才会处理该请求，从而防止未经授权的访问。建议您始终设置一个 Access Key。

== Contact ==
如果您有任何问题或建议，欢迎访问我的 GitHub 项目页面：[https://github.com/guguan123/bt-webhook-logger](https://github.com/guguan123/bt-webhook-logger)
