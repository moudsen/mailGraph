## mailGraph (v1.27)
Zabbix Media module and scripts for sending e-mail alerts with graphs.

**Please use the Wiki for information on how to install, configure and use MailGraph in Zabbix.**

## Upgrade notes
### v1.26
If you upgrade to v1.26 please be aware of the additional features for adding Tags to Trigger and Host to add additional graphs and the associated `html.template` updates that come alone with it (otherwise the new graphs will not show ...).

### v1.25 and higher
Template data provisioning and code has fundamentally changed. If you upgrade from an earlier version as v1.25, make sure you understand the changes in templates/html.template (now making use of arrays for lists of items).

## Example message
[![](images/Example-mail-message-v122.png?raw=true)](images/Example-mail-message-v122.png)
