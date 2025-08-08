=== Webhook Logger for aaPanel ===
Contributors: GuGuan123
Donate link: https://s1.imagehub.cc/images/2025/03/04/33128a3f3455b55b5c7321ee4c05527c.jpg
Tags: 宝塔, aaPanel, Security
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 0.1.2
Requires PHP: 7.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Receive aaPanel Webhook information and send notifications via email.

== Description ==
Webhook Logger for aaPanel is a WordPress plugin designed to help you easily receive and log Webhook notifications from the aaPanel control panel. This plugin stores the received Webhook requests as custom logs in the WordPress backend, allowing you to view them at any time. Additionally, it supports sending Webhook notifications via email, so you won't miss any important information.

Key features include:


Email Notifications: You can choose to enable email notifications to send a detailed notification email to a specified address every time a Webhook is received.


Log Management: A backend page is provided for users to easily view all Webhook logs, with support for pagination and a one-click function to clear all logs.

== Installation ==
Activate the plugin on the "Plugins" page in your WordPress backend.

After activation, you can find "aaPanel Webhook Settings" under the "Settings" menu and "aaPanel Webhook Logs" under the "Tools" menu.

Go to the "aaPanel Webhook Settings" page to configure your Access Key and email notification options.

In the aaPanel, configure the Webhook address to be the one shown on the plugin settings page (for example: https://www.google.com/search?q=https://yourdomain.com/%3Frest_route%3D/bt-webhook-logger/v1/receive).

== Screenshots ==
![Settings Page](https://s1.imagehub.cc/images/2025/07/25/b24ec480c37d59a8d2fa78510c870512.png)

![Log List Page](https://s1.imagehub.cc/images/2025/07/25/4072bbbee30a63c8b55b3a1207e0ecd7.png)

![Email Alert](https://s1.imagehub.cc/images/2025/07/25/39f5dda8017037045e4094150b250682.png)

== Changelog ==
= 0.1.1 =
Changed to use REST API.

= 0.1.0 =
Initial version released.

Supports receiving and logging aaPanel Webhooks.

Provides Access Key security verification.

Integrated email notification feature.

Implemented backend log viewing and clearing functionality.

== Frequently Asked Questions ==
= I'm not receiving email notifications, what should I do? =
Please make sure you have correctly entered the target email address in "aaPanel Webhook Settings" and checked the "Enable Webhook Email Notifications" option. Additionally, check if your WordPress site has been configured correctly to send emails, for example, by installing and properly setting up an SMTP plugin.

= What is the purpose of the Access Key? =
The Access Key is a security key used to verify the legitimacy of Webhook requests. If you set an Access Key, the plugin will only process the request if it contains the correct key, which prevents unauthorized access. It is recommended that you always set an Access Key.

== Contact ==
If you have any questions or suggestions, please visit my GitHub project page: https://github.com/guguan123/webhook-logger-for-aapanel
