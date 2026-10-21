=== Telegram Bot Notification ===
Contributors: piprapay
Donate link: https://piprapay.com/donate
Tags: notification, bot notification, admin notification
Requires at least: 1.0.0
Tested up to: 1.0.0
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Telegram Bot Notification plugin for PipraPay delivers real-time transaction alerts directly to your Telegram via a bot integration.

Connect the bot with a bot token and a chat ID. Create a bot with @BotFather, copy the bot token and paste it together with the chat ID (your user, group or channel ID) in the plugin settings. Use "Send Test Notification" to confirm the connection.

**Key Features:**

* Transaction and invoice alerts sent to your Telegram chat
* Alerts for a submitted payment (pending) and a completed payment, each switchable on or off
* Optional alert for every new checkout, off by default since most checkouts are abandoned
* Transaction alerts include the PipraPay ID, status, amount, payment method, customer account, gateway transaction ID, product and time
* Status emoji on every alert (new, pending, completed, failed, refunded)
* Customer receipts are sent as a photo together with the transaction details
* Works with users, groups, supergroups and channels
* Enable or disable the alerts at any time

**Why Use Telegram Bot Notification?**

== Changelog ==

= 1.0.0 =
* Initial release
* Reworked bot connection to token + chat ID (no webhook or authorization code required)