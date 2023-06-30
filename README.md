## mailGraph v2.10 release ##
_(2023/06/30)_

_This version has been verified with Zabbix 5.4, 6.0 LTS and 6.4._

Minor updates to the mailGraph code
- When not defining zabbix_api_user and zabbix_api_pwd in the configuration file the zbx_user and zbx_user_pwd wll be used

Modified files
- mailGraph.php
- mailGraph.xml
- javascript/zabbix.mailGraph.js

For those upgrading to the latest release without installing the media type:
- copy new mailGraph.php over existing mailGraph.php
- open the Media type MailGraph in Zabbix and edit the javascript
-- replace the script contents with the contents of javascript/zabbix.mailGraph.js

Changes are in effect immediately, no need to restart any services.

## Zabbix 6.4.x testing ##
_(2023/06/30)_

Zabbix 6.4 verification has succesfully completed.
- Refactored code to remove deprecated and removed functions since Zabbix 6.4.0
-- Zabbix Javascript now using HttpRequest instead of CurlHttpRequest (function name changes implemented)
-- Zabbix API user.login is now based on "username" (instead of "user")

Sidenotes
- Zabbix logging still shows deprecation messages however it is believed these are internal to Zabbix and not related to mailGraph

_(2023/06/29)_

Zabbix 6.4.x verification is in progress. Required intermediate release to fix one major issue (Zabbix login parameters deprecation) and some minor coding updates.
Expect to continue with automatic configuration within the next 2 months.

## Zabbix 6.2.x testing ##
_(2022/10/10)_

Testing completed. No immediate issues found.

_(2022/08/22)_

Zabbix 6.2.x testing is in progress. Once completed the next effort will be to deliver on automatic configuration detection and automatic updates.

## IMPORTANT NOTE ##
As a result of a major functional change in Zabbix 5.4 (Screens no longer exist and are all moved into Dashboards) the mailGraph.screen macro no longer functions under Zabbix 5.4+. A code rewrite is in progress to deal with detecting the Zabbix version and to pick dashboard.get instead of screen.get as a source list for the graphs that should be included in the mail message.

**v1.x is no longer supported; please upgrade to the current v2 release**
**Please use the Wiki for information on how to install, configure and use MailGraph in Zabbix**

## Example message
The below message is just an example of what MailGraph is capable of. The template engine ("Twig") allows for a fully customized message creation to your needs! It is also possible to add more Zabbix fields. If you need additional fields just raise an issue ticket and ask and I'll see what I can do.

[![](images/Example-mail-message-v122.png?raw=true)](images/Example-mail-message-v122.png)
