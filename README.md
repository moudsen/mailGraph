## Minor bugfixes to the code (2021/10/06)
Code improvements to prevent possible errors leading into a non-functional mailGraph ... (typical error within log or Zabbix: "json error").
Please inform me in case you have PHP related errors in your logs - this should no longer be the case with v1.31.

## mailGraph (v1.31)
Zabbix Media module and scripts for sending e-mail alerts with graphs.

**Please use the Wiki for information on how to install, configure and use MailGraph in Zabbix**

## UPGRADE NOTES
### v1.31
Updated: mailGraph.pgp

### v1.19
Updated: mailGraph.php

### v1.27
If you upgrade to v1.27 please be aware of the additional features for adding Tags to Trigger and Host to add additional graphs and the associated `html.template` updates that come alone with it (otherwise the new graphs will not show ...).

### v1.25 and higher
Template data provisioning and code has fundamentally changed. If you upgrade from an earlier version as v1.25, make sure you understand the changes in templates/html.template (now making use of arrays for lists of items).

## Example message
The below message is just an example of what MailGraph is capable of. The template engine used ("Twig") however allows for a fully customized message creation to your needs!

[![](images/Example-mail-message-v122.png?raw=true)](images/Example-mail-message-v122.png)
