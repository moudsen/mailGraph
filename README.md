## Introduction ##
Over the years I've been using Zabbix as both personal and business solution for monitoring. One of the missing features in Zabbix itself was the ability to have graphs generated and associated to monitoring messages as well as an easy way to jump back into Zabbix when searching for monitoring information for specific events, problems or to have a direct link to the associated Zabbix item or host.

This initiated the development of mailGraph v1 in Zabbix 5.4 delivering an elementary solution for sending HTML enriched messages from Zabbix including graphs.
To facilitate in a very flexible way to setup and format messages including the graphs, Twig made its introduction towards mailGraph v2, the current release branch of mailGraph.

The below message is just an example of what MailGraph is capable of. The template engine in Twig allows for a fully customized message creation to your needs! It is also possible to add more Zabbix fields as any field is passed to Twig when accessible in Zabbix through the macro mechanism.

Note that all Zabbix host, item or screen related information is made available to Twig automatically.

Example message:

![Example image](https://github.com/moudsen/mailGraph/assets/16045332/0cb671d9-464f-493b-b250-15dd550fd84a)

mailGraph is capable of adding several series of graphs into a single message delivering a unique experience when and how groups of graph images per the requested periods of time are added.
Currently mailGraph supports hosts, (one or more related) items and screens (applicable to Zabbix 5.4 only).

More information can be found in the Wiki.

## Installation ##
Please refer to the Wiki how to get mailGraph installed and configured on your system.

## Reference websites ##
[Extensive GERMAN installation from scratch](https://znil.net/index.php?title=Zabbix_mailGraph_installieren_-_Trigger_Alerts_Emails_mit_Grafiken) - nice work from Bernard Linz

## Announcements ##
_(2025/02/04)_

Started on coding and testing mailGraph v3.

_(2025/01/26)_

mailGraph v2.20 now supporting Zabbix 7.0 (LTS) and Zabbix 7.2 (in particular the new API bearer token authentication method).

_(2024/12/01)_

As per December 2024 PHP 7 and older is no longer supported. Please upgrade to a supported PHP 8 version.
Note that mailGraph is expected to function in older PHP versions.

_(2023/11/01)_

As per November 2023 the maintenance on mailGraph v2.x for Zabbix 5.x will stop in conjunction with the Zabbix lifecycle policy (https://www.zabbix.com/life_cycle_and_release_policy) as Zabbix 5 is nearing it's end of life.

Principal bug fixing on mailGraph v2.x (logic failure or similar) will continue but only for Zabbix 6.x onwards.

## Ideas and improvements ##

I'm open to new feature requests - please raise an issue for this in this Github space.

[#50 - Docker support](https://github.com/moudsen/mailGraph/issues/50) - Docker support inserted shortly after testing; will be pushed into release 3 shortly.
_Please refer to the `docker` directory for the first release of the docker image on Docker Hub (hoppa66/zabbix-mailgraph)_

## Special thank you ##
I would like to express my gratitude to the following people that have actively contributed to bring bugs and improvements to my attention:
- [pqvindesland](https://github.com/pqvindesland)
- [BernardLinz](https://github.com/BernhardLinz)
- [WMP](https://github.com/WMP)
- [dima-online](https://github.com/dima-online)
- [tadeuszkura](https://github.com/tadeuszkura)

## IMPORTANT NOTE for users of mailGraph v2 and older and Zabbix versions under 6.0 ##
As per November 2023, mailGraph is no longer maintained for Zabbix 5 and older.
