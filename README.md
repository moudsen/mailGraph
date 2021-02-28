# mailGraph
Zabbix Media module and scripts for sending e-mail alerts with graphs.

[![](images/Example-mail-message.png?raw=true)](images/Example-mail-message.png)

# WORK IN PROGRESS
Although still under development (consider the current release "beta"), I need feedback and interaction with other users of Zabbix that are looking for the functionality I've developed hence I'm releasing my code to the world.

**List of item to-do**
1. Resolve macro information.
1. Passing dynamic parameters via the Webhook for template/output usage.
1. Finding "beta testers" to assist me in further enhancing the use cases.

# Zabbix enhancements
https://support.zabbix.com/browse/ZBXNEXT-6534
Main ticket asking how to get this Media type onboarded in the Media type section of the manual and the associated Zabbix GitHub directory.

https://support.zabbix.com/browse/ZBXNEXT-6535
One of the major items that needs to be tackled is to resolve Macros that are in names, descriptions, etc. As this does work for the Trigger and TriggerPrototype I've examined the Zabbix source code and from here I would say it's quite "easy" to add the "expandXXX" flags to other types as well (found many existing functions to perform this).
I'm adding this to my testlab setup for Item and ItemPrototype and will share the outcome. If it is succesfull I will also arrange for other types (like Host) eventually giving back the additional code lines to Zabbix for incorporation into a release.

# Installation pre-requisites
The suggested installation path of this script is on the same host where Zabbix lives but outside the actual Zabbix directory, although it is possible to run the script entirely somewhere else (the code is webhook based, picking up information from Zabbix is via the front-end login and API).

# I'm assuming
- you are familiar with "composer"
- you know how to configure a webserver/virtual host
- that you have CURL and PHP installed

# Prepare the installation
- Pick a directory within a (virtual) host of your webserver
- Create the directories "config", "images", "log", "templates" and "tmp" inside this directory
- Copy .htaccess to the main directory _(if not using Apache make sure your webserver denies access to /config!)_
- Copy mailGraph.php to the main directory
- Install SwiftMailer: `composer require swiftmailer/swiftmailer`
- Install TWIG: `composer require twig/twig`
- Copy config/config.php to your /config directory
- Copy config/config.json.template to your /config/ directory and rename to "config.json"
- Copy templates/html.template and templates/plain.template to your templates directory
- Copy mailGraph.xml to a location where you can upload the Media Type to Zabbix

# Configuration
- Goto your /config directory
- Two ways to configure the config.json file: 1) with config.php or 2) with your favorite text editor (note that you must have knowledge of JSON format to use this option)'
- List the available configuration options with "php config.php config.json list"
- Change any option with "php config.php config.json replace 'key_name' 'new_value'" (note the usage of the single quotes from the command-line!)

**"script_baseurl"** should point to the URL of your directory (ie. "https://mydomain.com/mailgraph/"). Note the ending '/'!

**"zabbix_user"** must be a Zabbix SuperAdmin user you create to login to Zabbix (this is for grabbing the images via the regular Zabbix routines).

**"zabbix_user_api"** must also be a Zabbix SuperAdmin user your create to login to the Zabbix API (this is for grabbing the information of the event via the Zabbix API).

**"mail_from"** must be a valid e-mail address which represents the 'from' address in the mails that are sent (ie. "zabbix.mailgraph.noreply@domain.com").

# Load the Media Type "MailGraph" into Zabbix
- Login to your Zabbix instance
- Goto "Administration" => "Media yypes"
- Import the "mailGraph.xml" file
- Edit the new media type
- Configure some of the macros associated

"baseURL" must contain your Zabbix URL (ie. "https://mydomain.com/zabbix/"). Note the ending '/'!

You can set your custom "graphWidth" and "GraphHeight" to your convenience.

You can switch the graph legend on/off with "showLegend" (0=off,1=on).

You can change the "subject" of the e-mail that is sent (note that the markup can be a combination of Zabbix MACRO or TWIG notation!).

"URL" is the url to the mailGraph script (ie. "https://mydomain.com/mailGraph.php").

# Actions configuration
At this point the Media type is ready for configuration under "actions" as per the regular way of Zabbix alert processing. Please refer to the manual how to configure.

# Template adjustments
I've picked TWIG as the template processor, where the following macros are available for your convenience. Feel free to adjust the html.template and plain.template files as you see fit for your situation!

Values available:

{{ baseURL }} - base url of the Zabbix system (use for references to API and login)

{{ TRIGGER_ID }} - id of the applicable trigger

{{ TRIGGER_DESCRIPTION }} - raw trigger description (note: macros are not parsed!)

{{ TRIGGER_COMMENTS }} - comments of the trigger

{{ TRIGGER_URL }} - url of the trigger form

{{ ITEM_ID }} - id of the associated item to the trigger

{{ ITEM_KEY }} - key of the item

{{ ITEM_NAME }} - item name

{{ ITEM_DESCRIPTION }} - description of the item

{{ ITEM_LASTVALUE }} - last value of the item

{{ ITEM_PREVIOUSVALUE }} - the value of the before LASTVALUE

{{ ITEM_URL }} - url of the item form

{{ HOST_ID }} - id of the associated host to the item

{{ HOST_NAME }} - name of the host

{{ HOST_ERROR }} - last error state of the applicable host

{{ HOST_DESCRIPTION }} - description of the host

{{ HOST_URL }} - url of the host form

{{ EVENT_ID }} - id of the associated event

{{ EVENT_NAME }} - name of the event (note: macros are parsed!)

{{ EVENT_OPDATA }} - associated operational data of the vent

{{ EVENT_VALUE }} - event state (0=Recovered, 1=Triggered/Active)

{{ EVENT_SEVERITY }} - severity of the event

{{ EVENT_STATUS }} - status of the event

{{ EVENT_URL }} - url of the event details

{{ GRAPH_ID }} - id of the (first) associated graph that contains the item

{{ GRAPH_NAME }} - name of this graph

{{ GRAPH_URL }} - URL to this graph (assuming script produces to an accessible location)

{{ GRAPH_CID }} - IMG embed string (<img src="{{ GRAPH_CID }}" />)

{{ LOG_HTML }} - script log in HTML format

{{ LOG_PLAIN }} - script log in PLAIN text format

# Troubleshooting
In general if something goes wrong (no output), use the following sequence to identify where the error has occured (and raise an issue in this repository so I can take a look at it):
- Goto Zabbix => Reports => Action Log and search for events with Status "Failed"
- Note the itemId, triggerId and eventId from this event for testing the Media Type manually
- If the popup message says "Syntax Error" something went wrong with the processing during the script. In this case you have to investigate a bit more what is happening.

The easiest way to test what is happening is to now goto Administration => Media types and hit the "Test" at the right hand side for MailGraph.
- Replace relevant macros with information (eventId, triggerId, itemId, recipient, baseUrl and URL) and hit "Test"
- The last line in the result will tell you what the problem is (most likely an access or connectivity issue)
- Fix accordingly and retry

To facilitate troubleshooting, you can (at code level):
- switch on $cDebugMail to receive processing logs as attachment of an e-mail message
- store logs in the /log directory when $cDebug is switched on

In case of an issue that happens before an e-mail is sent, you can also perform a CLI based test:
- php mailGraph.php test

Note that you have to set the configuration items starting with "cli" in config.json with actual values from a previous message to make this work!

Last resort is to raise an issue in this repository and I will try to assist as soon as possible to fix it.
