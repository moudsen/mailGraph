# mailGraph (v1.22)
Zabbix Media module and scripts for sending e-mail alerts with graphs.

[![](images/Example-mail-message.png?raw=true)](images/Example-mail-message.png)

**List of item to-do**
1. Disassociate fetching the graph from the main routine; this is mainly driven by the fact that other Zabbix Media types face the same challenge for picking up graphs hence my work could also be of great use for including graphs in Telegraph, Slack, etc.
2. Currently only chart graphs are supported. Other graph types will become supported in a later version.

# Zabbix enhancements
https://support.zabbix.com/browse/ZBXNEXT-6534
Main ticket asking how to get this Media type onboarded in the Media type section of the manual and the associated Zabbix GitHub directory. Although the request to get added has been kind of 'declined', I'm still pushing forward with my development as the original ask for such functionality is from **2010** (!).

https://support.zabbix.com/browse/ZBXNEXT-6548
Separation of the Graph generator code to allow for other webhooks to also use the facility for inclusion of graphs in their respective Media types.

# Installation pre-requisites
The suggested installation path of this script is on the same host where Zabbix lives but outside the actual Zabbix directory, although it is possible to run the script entirely somewhere else (the code is webhook based, picking up information from Zabbix is via the front-end login and API).

I've tested my code with Zabbix on Linux with local Postfix. Not sure if it can/will run in any Zabbix versions under 5 or on other environments as I have no facilities nor time available to test on any lower versions.

# I'm assuming
- That you have Composer, CURL and PHP installed
- You are familiar with "composer"
- You know how to configure and secure a webserver/virtual host (Apache, NGINX, etc.)
- That you are familiar with Zabbix 5.x (specifically for setting up "Actions")

This code has been tested with the following software versions:
- Zabbix 5.0.5
- PHP v7.4.6
- Swiftmailer v6.2.5
- Twig v3.3.0
- Curl 7.61.1

# Prepare the installation
- Download or clone this repository
- Pick a directory within a (virtual) host of your webserver
- Create the directories "config", "images", "log", "templates" and "tmp" inside this directory
- Copy .htaccess to the main directory _(if not using Apache make sure your webserver denies http access to /config!)_
- Copy mailGraph.php to the main directory
- Install SwiftMailer: `composer require swiftmailer/swiftmailer`
- Install TWIG: `composer require twig/twig`
- Copy config/config.php to your /config directory
- Copy config/config.json.template to your /config/ directory and rename to "config.json" (we will configure in the next step)
- Copy templates/html.template and templates/plain.template to your templates directory
- Copy mailGraph.xml to a location where you can upload the Media Type to Zabbix (we will load into / configure in Zabbix in the next steps)

# Configuration
- Goto your /config directory
- Two ways to configure the config.json file: 1) with config.php or 2) with your favorite text editor (note that you must have knowledge of JSON format to use the latter option)'
- List the configuration options with "php config.php config.json list"
- Change any option with "php config.php config.json replace 'key_name' 'new_value'" (note the usage of the single quotes!)
- Add options with "php config.php add 'key_name' 'new_value'" (note the usage of the single quotes!)

**"script_baseurl"** should point to the URL of your directory where MailGraph is installed (ie. "https://mydomain.com/"). Note the ending '/'!

**"zabbix_user"** and **zabbix_user_pwd** must be a Zabbix SuperAdmin user/password you need to have/create to login to Zabbix (this is for grabbing the images via the regular Zabbix routines).

**"zabbix_user_api"** and **zabbix_user_pwd** must also be a Zabbix SuperAdmin user/password you need to have/create to login to the Zabbix API (this is for grabbing the information of the event via the Zabbix API).

**"mail_from"** must be a valid e-mail address which represents the 'from' address in the mails that are sent (ie. "zabbix.mailgraph.noreply@domain.com") that is acceptable by your mail server.

**"period"** must be a valid Zabbix period (like "1d", "1w" or "48h") that will be applied to the graph. Default is "48h" when not specified.

**"period_header"** is the header displayed above the graph. For example "Last 48 hours". Defaults to 'period' when not specified.

**"graph_match"** is the matching method while searching for graphs ('exact','none').

**"smtp_server"** and **"smtp_port"** to define the SMTP server and port to be used.

**"smtp_transport"** and **"smtp_strict"** to define the SMTP transport methode ('none','tls','ssl') and whether certificate checking is strict.

# Load the Media Type "MailGraph" into Zabbix
- Login to your Zabbix instance
- Goto "Administration" => "Media yypes"
- Import the "mailGraph.xml" file
- Edit the new media type
- Configure some of the macros associated

**"baseURL"** must contain your Zabbix URL (ie. "https://mydomain.com/zabbix/"). Note the ending '/'!

You can set your custom **"graphWidth"** and **"GraphHeight"** to your convenience.

You can switch the graph legend on/off with **"showLegend"** (0=off,1=on).

You can change the **"subject"** of the e-mail that is sent (note that the markup can be a combination of Zabbix MACRO or TWIG notation!).

**"URL"** is the url to the mailGraph script (ie. "https://mydomain.com/mailGraph.php").

# Actions configuration
At this point the Media type is ready for configuration under "actions" as per the regular way of Zabbix alert processing. Please refer to the manual how to configure.

# Trigger tags
Each Trigger can have it's own specific settings which can configured through Tags:

**"mailGraph.period"** to set a specific graph period for this particular Trigger like "4h" for 4 hours (Zabbix format).

**"mailGraph.period_header"** to set a specific graph period header for this particular Trigger like "Last 4 hours".

**"mailGraph.periods"** to set a specific set of graph periods for this particular Trigger like "10m,4h,12h,7d" (maximum of 4 allowed).

**"mailGraph.periods_headers"** to set a specific set of graph period headers for this particular Trigger like "Last 10 minutes,Last 4 hours,Last 12 hours,Last 7 days".

**"mailGraph.graph"** to set a specific graph to be embedded for this particular Trigger.

**"mailGraph.showLegend"** to set a specific graph to show or hide for this particular Trigger.

**"mailGraph.graphHeight"** to set a specific graph height for this particular Trigger.

**"mailGraph.graphWidth"** to set a specific graph width for this particular Trigger.

**"mailGraph.debug"** to enable the attachment of the log file of MailGraph processing for this particular Trigger to each mail message.

# Updating to a newer version of MailGraph
If a new version comes around:
- Always copy the 'mailGraph.php' code (overwrite the existing version)
- Look for changes in the .xml file (especially if the Javascript code has changed). If unsure, copy at least the Javascript!
- (Optional) Look for new configuration options

# What if there is no graph added to the e-mail?
There is no immediate relationship between a trigger and a graph. This is why the script uses the following technique to find graphs to are associated to the trigger:
1. The trigger API call returns a list of "functions". Each functions holds an "item id".
2. Via the graph API call we can figure out which graphs are associated to the "host id" and to any of the items we've found in the previous step.
3. Traversing this set of grahps, we are looking for graphs that have the actual "item id" associated. If there is no association found, we can still use any graph as it is still relevant to the trigger, but it will have an indirect relationship.
4. Most ideally we pick the graph that has the actual "item id" matched. If not, we pick the first grapgh we've managed to find.

In reality this may means that there are items (like simple "interface up/down" configured) that have no graphs defined. In this occasion there is no graph attached to the message. If you wish a graph to appear at this point, just add a graph using that item and next time this graph will show in the message or add the tag "mailGraph.graph" to the trigger pointing at a graph you would like to display for this trigger.

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

{{ GRAPH_MATCH }} - Whether the located graph directly relates to the main trigger item or not ('Exact','Other')

{{ LOG_HTML }} - script log in HTML format

{{ LOG_PLAIN }} - script log in PLAIN text format

You can define _CUSTOM_ information that is passed from Zabbix by introducing a Macro of which the name starts with 'info'. Each 'infoXXX' will be made available for use in the template as {{ infoXXX }} and will be HTML-escaped automatically. This allows you to add _any_ other information you want to pass from Zabbix straight into the template.

# Troubleshooting
In general if something goes wrong (no output), use the following sequence to identify where the error has occured (and raise an issue in this repository so I can take a look at it):
- Goto Zabbix => Reports => Action Log and search for events with Status "Failed"
- Note the itemId, triggerId and eventId from this event for testing the Media Type manually
- If the popup message says "Syntax Error" something went wrong with the processing during the script. In this case you have to investigate a bit more what is happening.

The easiest way to test what is happening is to now goto Administration => Media types and hit the "Test" at the right hand side for MailGraph.
- Replace relevant macros with information (eventId, triggerId, itemId, recipient, baseUrl and URL) and hit "Test"
- The last line in the result or the log provided at the bottom will tell you what the problem is (most likely an access or connectivity issue)
- Fix accordingly and retry

To facilitate troubleshooting, you can (at code level):
- switch on $cDebugMail to receive processing logs as attachment of an e-mail message
- store logs in the /log directory when $cDebug is switched on

In case of an issue that happens before an e-mail is sent, you can also perform a CLI based test:
- php mailGraph.php test

Note that you have to set the configuration items starting with "cli" in config.json with actual values from a previous message to make this work!

Last resort is to raise an issue in this repository and I will try to assist as soon as possible to fix it!
