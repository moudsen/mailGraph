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

## Announcement - Zabbix 5.x maintenance for mailGraph end-of-life - mailGraph 3.x development in progress ##
_(2023/11/01)_
As per November 2023 the maintenance on mailGraph v2.x for Zabbix 5.x will stop in conjunction with the Zabbix lifecycle policy ([https://www.zabbix.com/life_cycle_and_release_policy]) as Zabbix 5 is nearing it's end of life.

In conjunction mailGraph v2.x is now frozen and I'm setting up a roadmap for mailGraph v3.x starting new development in Alfa mode as November 2023. This new code is not expected to be published until start of 2024.

Principal bug fixing on mailGraph v2.x (logic failure or similar) will continue but only for Zabbix 6.x.

I'm open to new feature requests - please raise an issue for this in this Github space.

## Special thank you ##
I would like to express my gratitude to the following people that have actively contributed to bring bugs and improvements to my attention:
- [pqvindesland](https://github.com/pqvindesland)

## IMPORTANT NOTE for users of mailGraph v2 and older and Zabbix versions under 6.0 ##
As per November 2023, mailGraph is no longer maintained for Zabbix 5 and older.
