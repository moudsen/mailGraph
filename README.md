## Introduction ##
Over the years I've been using Zabbix as both personal and business solution for monitoring. One of the missing features in Zabbix itself was the ability to have graphs generated and associated to monitoring messages as well as an easy way to jump back into Zabbix when searching for monitoring information for specific events, problems or to have a direct link to the associated Zabbix item or host.

This initiated the development of mailGraph v1 in Zabbix 5.4 delivering an elementary solution for sending HTML enriched messages from Zabbix including graphs.
To facilitate in a very flexible way to setup and format messages including the graphs, Twig made its introduction towards mailGraph v2, the current release branch of mailGraph.

The below message is just an example of what MailGraph is capable of. The template engine in Twig allows for a fully customized message creation to your needs! It is also possible to add more Zabbix fields as any field is passed to Twig when accessible in Zabbix through the macro mechanism. Example: `{ITEM.ID}`.

Example message:

[![](images/Example-mail-message-v122.png?raw=true)](images/Example-mail-message-v122.png)

mailGraph is capable of adding several series of graphs into a single message delivering a unique experience when and how groups of graph images per the requested periods of time are added.
Currently mailGraph supports hosts, (one or more related) items and screens (applicable to Zabbix 5.4 only).

More information can be found in the Wiki.

## Installation ##
Please refer to the Wiki how to get mailGraph installed and configured on your system.

## mailGraph v2.11 release ##
_(2023/07/01)_

_This version has been verified with Zabbix 5.4 and 6.0 LTS and is expected to work with 6.4 and later (based on v2.10 testing)_

Release notes
- Added pre- and postchecking of variables to the Zabbix javascript - this will prevent the 'invalid JSON' messages and provide better feedback for errors
- When testing MailGraph it is now possible to set the eventId to zero - a random problem will be picked up via the API
- Zabbix Media Type XML reverted back to version 5.4 (for backwards compatibility)

Modified files
- mailGraph.php
- mailGraph.xml
- javascript/zabbix.mailGraph.js

For those upgrading to the latest release without installing the media type:
- copy new mailGraph.php over existing mailGraph.php
- open the Media type MailGraph in Zabbix and edit the javascript
-- replace the script contents with the contents of javascript/zabbix.mailGraph.js

Changes are in effect immediately, no need to restart any services.

## mailGraph v2.10 release ##
_(2023/06/30)_

_This version has been verified with Zabbix 5.4, 6.0 LTS and 6.4, PHP 7.4 and 8.2 and recent versions of libraries used via composer._

Minor updates to the mailGraph code
- When not defining zabbix_api_user and zabbix_api_pwd in the configuration file the zbx_user and zbx_user_pwd wll be used

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

## IMPORTANT NOTE for users of mailGraph v1 and Zabbix versions under 5.4 ##
As a result of a major functional change in Zabbix 5.4 (Screens no longer exist and are all moved into Dashboards) the mailGraph.screen macro no longer functions under Zabbix 5.4+. A code rewrite is in progress to deal with detecting the Zabbix version and to pick dashboard.get instead of screen.get as a source list for the graphs that should be included in the mail message.

**v1.x is no longer supported; please upgrade to the current v2 release**
