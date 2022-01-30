## Major rework announcement (2021/12/16)
I've decided to take v2.0 forward through a series of code rewrites to facilitate some requests and to start with the code separation for obtaining Graphs through stand-alone code.

Also looking at the "Zabbix Web Agenet" possibilities recently added to Zabbix 5.4 at the same time. Still no clue why Zabbix does not also move the Graph generation into such module and to provision an API on it ...

## Major changes to the code due to Zabbix 5.4 recode by Zabbix team(2021/12/16)
A major change was required to deal with some API changes found in Zabbix 5.4.
- itemId is no longer passed by default by Zabbix. As a result the itemId is now picked up from the Trigger information (itemId from the first element in "functions").
- Some parameters moved under "params" in JSON.
- Care is taken about Some (unneccessry) warnings and index errors due to lack of testing of information available.

To upgrade from v1.x to v2.00 you only have to replace mailGraph.php. No changes made to other files in this release.

## IMPORTANT NOTE ##
As a result of a major functional change in Zabbix 5.4 (Screens no longer exist and are all moved into Dashboards) the mailGraph.screen macro no longer functions under Zabbix 5.4+. A code rewrite is in progress to deal with detecting the Zabbix version and to pick dashboard.get instead of screen.get as a source list for the graphs that should be included in the mail message.

## Minor bugfixes to the code (2021/10/06)
Code improvements to prevent possible errors leading into a non-functional mailGraph ... (typical error within log or Zabbix: "json error").

Please inform me (raise an issue) in case you have PHP related errors in your logs - this should no longer be the case with v1.31.

## mailGraph (v2.02)
Zabbix Media module and scripts for sending e-mail alerts with graphs.

**v1.x is no longer supported; please upgrade to the current v2 release**
**Please use the Wiki for information on how to install, configure and use MailGraph in Zabbix**

## UPGRADE NOTES
### v2.01
Updated: mailGraph.php
### v2.00
Updated: mailGraph.php

### v1.31
Updated: mailGraph.php

### v1.29
Updated: mailGraph.php

### v1.27
If you upgrade to v1.27 please be aware of the additional features for adding Tags to Trigger and Host to add additional graphs and the associated `html.template` updates that come alone with it (otherwise the new graphs will not show ...).

### v1.25 and higher
Template data provisioning and code has fundamentally changed. If you upgrade from an earlier version as v1.25, make sure you understand the changes in templates/html.template (now making use of arrays for lists of items).

## Example message
The below message is just an example of what MailGraph is capable of. The template engine ("Twig") allows for a fully customized message creation to your needs! It is also possible to add more Zabbix fields. If you need additional fields just raise an issue ticket and ask and I'll see what I can do.

[![](images/Example-mail-message-v122.png?raw=true)](images/Example-mail-message-v122.png)
