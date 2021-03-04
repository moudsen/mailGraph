<?php
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    //
    // MAILGRAPH
    // =========
    // Script that provides a Media Type for Zabbix that will add a graph to an e-mail that is sent out
    // upon an alert message.
    //
    // ------------------------------------------------------------------------------------------------------
    // 1.00 2021/02/26 - Mark Oudsen - MVP version, ready for distribution
    // 1.01 2021/02/27 - Mark Oudsen - Enhanced search for associated graphs to an item // bug fixes
    // 1.10 2021/02/27 - Mark Oudsen - Moved all configuration outside code
    // 1.11 2021/02/28 - Mark Oudsen - Bugfixes
    // 1.12 2021/03/01 - Mark Oudsen - Bugfixes
    //                                 Adding mail server configuration via config.json
    // 1.13 2021/03/01 - Mark Oudsen - Added smtp options to encrypt none,ssl,tls
    // 1.14 2021/03/01 - Mark Oudsen - Added smtp strict certificates yes|no via config.json
    // 1.15 2021/03/01 - Mark Oudsen - Revised relevant graph locator; allowing other item graphs if current
    //                                 item does not have a graph associated
    // 1.16 2021/03/02 - Mark Oudsen - Found issue with graph.get not returning graphs to requested item ids
    //                                 Workaround programmed (fetch host graphs, search for certain itemids)
    // 1.17 2021/03/02 - Mark Oudsen - Added ability to specify period of time displayed in the graph
    // 1.18 2021/03/04 - Mark Oudsen - Added ability to specify a deviating period of time per trigger
    //                                 Added ability to specify different graph to be embedded
    // ------------------------------------------------------------------------------------------------------
    //
    // (C) M.J.Oudsen, mark.oudsen@puzzl.nl
    // MIT License
    //
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    //
    // MAIN SEQUENCE
    // -------------
    // 1) Fetch trigger, item, host, graph, event information via Zabbix API via CURL
    // 2) Fetch Graph associated to the item/trigger (if any) via Zabbix URL login via CURL
    // 3) Build and send mail message from template using Swift/TWIG
    //
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    // CONSTANTS

    $cVersion = 'v1.18';
    $cCRLF = chr(10).chr(13);
    $maskDateTime = 'Y-m-d H:i:s';

    // DEBUG SETTINGS
    // -- Should be FALSE for production level use

    $cDebug = TRUE;          // Comprehensive debug logging including log storage
    $cDebugMail = TRUE;      // Include log in the mail message? (attachments)
    $showLog = FALSE;        // Display the log - !! switch to TRUE when performing CLI debugging only !!!

    // INCLUDE REQUIRED LIBRARIES (Composer)
    // (configure at same location as the script is running or load in your own central library)
    // -- swiftmailer/swiftmailer       https://swiftmailer.symfony.com/docs/introduction.html
    // -- twig/twig                     https://twig.symfony.com/doc/3.x/templates.html

    // Change only required if you decide to use a local/central library, otherwise leave as is
    include(getcwd().'/vendor/autoload.php');

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Fetch the HTML source of the given URL
    // --- Redirects will be honored
    // --- Enforces use of IPv4
    // --- Caller must verify if the return string is JSON or ERROR

    function postJSON($url,$data)
    {
        global $cCRLF;
        global $cVersion;
        global $cDebug;

        // Initialize Curl instance
        _log('% postJSON: '.$url);
        if ($cDebug) { _log('> POST data'.json_encode($data)); }

        $ch = curl_init();

        // Set options
        curl_setopt($ch, CURLOPT_USERAGENT, 'Zabbix-mailGraph - '.$cVersion);

        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

        // Execute Curl
        $data = curl_exec($ch);

        if ($data===FALSE)
        {
            _log('! Failed: '.curl_error($ch));

            $data = 'An error occurred while retreiving the requested page.'.$cCRLF;
            $data .= 'Requested page = '.$url.$cCRLF;
            $data .= 'Error = '.curl_error($ch).$cCRLF;
        }
		else
		{
            _log('> Received '.strlen($data).' bytes');
			$data = json_decode($data,TRUE);
		}

        // Close Curl
        curl_close($ch);

        // Return received response
        return $data;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Fetch the given Zabbix image
    // --- Store with unique name
    // --- Pass filename back to caller

    function GraphImageById ($graphid, $width = 400, $height = 100, $graphType = 0, $showLegend = 0, $period = '48h')
    {
        global $z_server;
        global $z_user;
        global $z_pass;
        global $z_tmp_cookies;
        global $z_images_path;
        global $z_url_api;
        global $cVersion;
        global $cCRLF;

        // Unique names
        $thisTime = time();

        // Relative web calls
        $z_url_index   = $z_server ."index.php";
        $z_url_graph   = $z_server ."chart2.php";
        $z_url_fetch   = $z_url_graph ."?graphid=" .$graphid ."&width=" .$width ."&height=" .$height .
                                       "&graphtype=".$graphType."&legend=".$showLegend."&profileIdx=web.graphs.filter".
                                       "&from=now-".$period."&to=now";

        // Prepare POST login
        $z_login_data  = array('name' => $z_user, 'password' => $z_pass, 'enter' => "Sign in");

        // Cookie and image names
        $filename_cookie = $z_tmp_cookies ."zabbix_cookie_" .$graphid . "." .$thisTime. ".txt";
        $filename = "zabbix_graph_" .$graphid . "." . $thisTime . ".png";
        $image_name = $z_images_path . $filename;

        // Configure CURL
        _log('% GraphImageById: '.$z_url_fetch);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $z_url_index);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Zabbix-mailGraph - '.$cVersion);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $z_login_data);

        curl_setopt($ch, CURLOPT_COOKIEJAR, $filename_cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $filename_cookie);

        // Login to Zabbix
        $login = curl_exec($ch);

        if ($login!='')
        {
            echo 'Error logging in to Zabbix!'.$cCRLF;
            die;
        }

        // Get the graph
        curl_setopt($ch, CURLOPT_URL, $z_url_fetch);
        $output = curl_exec($ch);

        curl_close($ch);

        // Delete cookie
        unlink($filename_cookie);

        // Write file
        $fp = fopen($image_name, 'w');
        fwrite($fp, $output);
        fclose($fp);

        // Return filename
        _log('> Received '.strlen($output).' bytes');
        _log('> Saved to '.$z_images_path.$filename);

        return($filename);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Log information

    $logging = array();

    function _log($information)
    {
        global $logging;
        global $maskDateTime;
        global $showLog;
        global $cCRLF;

        $logString = date($maskDateTime).' : '.$information;

        $logging[] = $logString;
        if ($showLog) { echo $logString.$cCRLF; }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Read configuration file

    function readConfig($fileName)
    {
        global $cCRLF;

        if (!file_exists($fileName))
        {
            echo 'Config file not found. ('.$fileName.')'.$cCRLF;
            die;
        }

        $content = file_get_contents($fileName);
        $data = json_decode($content,TRUE);

        if ($data==NULL)
        {
            echo 'Invalid JSON format in config file?! ('.$fileName.')'.$cCRLF;
            die;
        }

        return($data);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // API request ID counter - for best practice / debug purposes only

    $requestCounter = 0;

    function nextRequestID()
    {
        global $requestCounter;

        $requestCounter++;
        return($requestCounter);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Create easy to read duration

    function getNiceDuration($durationInSeconds)
    {
        $duration = '';

        $days = floor($durationInSeconds / 86400);
        $durationInSeconds -= $days * 86400;
        $hours = floor($durationInSeconds / 3600);
        $durationInSeconds -= $hours * 3600;
        $minutes = floor($durationInSeconds / 60);
        $seconds = $durationInSeconds - $minutes * 60;

        if ($days>0)
        {
            $duration .= $days . ' day';
            if ($days!=1) { $duration .= 's'; }
        }

        if ($hours>0)
        {
            $duration .= ' ' . $hours . ' hr';
            if ($hours!=1) { $duration .= 's'; }
        }

        if ($minutes>0)
        {
            $duration .= ' ' . $minutes . ' min';
            if ($minutes!=1) { $duration .= 's'; }
        }

        if ($seconds>=0) { $duration .= ' ' . $seconds . ' sec'; }
        if ($seconds!=1) { $duration .= 's'; }

        return $duration;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Initialize ///////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    // --- CONFIG DATA ---

    // [CONFIGURE] Change only when you want to place your config file somewhere else ...
    $config = readConfig(getcwd().'/config/config.json');

    _log('# Configuration taken from config.json'.$cCRLF.json_encode($config,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    // --- POST DATA ---

    // Read POST data
    $problemJSON = file_get_contents('php://input');
    $problemData = json_decode($problemJSON,TRUE);

    // --- CLI DATA ---

    // Facilitate CLI based testing
    if (isset($argc))
    {
        if (($argc>1) && ($argv[1]=='test'))
        {
            _log('# Invoked from CLI');

            // Assumes that config.json file has the correct information

            $problemData['itemId'] = $config['cli_itemId'];
            $problemData['triggerId'] = $config['cli_triggerId'];
            $problemData['eventId'] = $config['cli_eventId'];
            $problemData['eventValue'] = $config['cli_eventValue'];
            $problemData['recipient'] = $config['cli_recipient'];
            $problemData['baseURL'] = $config['cli_baseURL'];
            $problemData['duration'] = $config['cli_duration'];
            $problemData['subject'] = $config['cli_subject'];
            $problemData['period'] = $config['cli_period'];

            // Switch on CLI log display
            $showLog = TRUE;
        }
    }

    _log('# Data passed from Zabbix'.$cCRLF.json_encode($problemData,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    // --- CHECK AND SET P_ VARIABLES ---
    // FROM POST OR CLI DATA ---

    if (!isset($problemData['itemId'])) { echo "Missing ITEM ID?\n"; die; }
    $p_itemId = intval($problemData['itemId']);

    if (!isset($problemData['triggerId'])) { echo "Missing TRIGGER ID?\n"; die; }
    $p_triggerId = intval($problemData['triggerId']);

    if (!isset($problemData['eventId'])) { echo "Missing EVENT ID?\n"; die; }
    $p_eventId = intval($problemData['eventId']);

    if (!isset($problemData['recipient'])) { echo "Missing RECIPIENT?\n"; die; }
    $p_recipient = $problemData['recipient'];

    if (!isset($problemData['eventValue'])) { echo "Missing EVENTVALUE?\n"; die; }
    $p_eventValue = intval($problemData['eventValue']);

    if (!isset($problemData['duration'])) { echo "Missing DURATION?\n"; die; }
    $p_duration = intval($problemData['duration']);

    if (!isset($problemData['baseURL'])) { echo "Missing URL?\n"; die; }
    $p_URL = $problemData['baseURL'];

    $p_subject = '{{ EVENT_SEVERITY }}: {{ EVENT_NAME|raw }}';
    if (isset($problemData['subject'])) { $p_subject = $problemData['subject']; }

    $p_graphWidth = 450;
    if (isset($problemData['graphWidth'])) { $p_graphWidth = intval($problemData['graphWidth']); }

    $p_graphHeight = 120;
    if (isset($problemData['graphHeight'])) { $p_graphHeight = intval($problemData['graphHeight']); }

    $p_showLegend = 0;
    if (isset($problemData['showLegend'])) { $p_showLegend = intval($problemData['showLegend']); }

    $p_period = '48h';
    if (isset($problemData['period'])) { $p_period = $problemData['period']; }

    // FROM CONFIG DATA

    $p_smtp_server = 'localhost';
    if (isset($config['smtp_server'])) { $p_smtp_server = $config['smtp_server']; }

    $p_smtp_port = 25;
    if (isset($config['smtp_port'])) { $p_smtp_port = $config['smtp_port']; }

    $p_smtp_transport = 'none';
    if ((isset($config['smtp_transport'])) && ($config['smtp_transport']=='tls')) { $p_smtp_transport = 'tls'; }
    if ((isset($config['smtp_transport'])) && ($config['smtp_transport']=='ssl')) { $p_smtp_transport = 'ssl'; }

    $p_smtp_strict = 'yes';
    if ((isset($config['smtp_strict'])) && ($config['smtp_strict']=='no')) { $p_smtp_strict = 'no'; }

    $p_graph_match = 'any';
    if ((isset($config['graph_match'])) && ($config['graph_match']=='exact')) { $p_graph_match = 'exact'; }

    // --- GLOBAL CONFIGURATION ---

    // Script related settings
    $z_url = $config['script_baseurl'];             // Script URL location (for relative paths to images, templates, log, tmp)
    $z_url_image = $z_url.'images/';                // Images URL (included in plain message text)

    // Absolute path where to store the generated images - note: script does not take care of clearing out old images!
    $z_path = getcwd().'/';                         // Absolute base path on the filesystem for this url
    $z_images_path = $z_path.'/images/';
    $z_template_path = $z_path.'/templates/';
    $z_tmp_cookies = $z_path.'//tmp/';
    $z_log_path = $z_path.'/log/';

    // Zabbix user (requires Super Admin access rights to access image generator script)
    $z_user = $config['zabbix_user'];
    $z_pass = $config['zabbix_user_pwd'];

    // Zabbix API user (requires Super Admin access rights)
    // TODO: Check if information retreival can be done with less rigths
    $z_api_user = $config['zabbix_api_user'];
    $z_api_pass = $config['zabbix_api_pwd'];

    // Mail sender
    $mailFrom = array($config['mail_from']=>'Zabbix Mailgraph');

    // Derived variables - do not change!
    $z_server = $p_URL;                             // Zabbix server URL from config
    $z_url_api = $z_server  ."api_jsonrpc.php";     // Zabbix API URL

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Check accessibility of paths and template files //////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    if (!is_writable($z_images_path)) { echo 'Image path inaccessible?'.$cCRLF; die; }
    if (!is_writable($z_tmp_cookies)) { echo 'Cookies temporary path inaccessible?'.$cCRLF; die; }
    if (!is_writable($z_log_path)) { echo 'Log path inaccessible?'.$cCRLF; die; }
    if (!file_exists($z_template_path.'html.template')) { echo 'HTML template missing?'.$cCRLF; die; }
    if (!file_exists($z_template_path.'plain.template')) { echo 'PLAIN template missing?'.$cCRLF; die; }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Fetch information via API ////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    $mailData = array();

    $mailData['BASE_URL'] = $p_URL;
    $mailData['SUBJECT'] = $p_subject;

    // --- LOGIN ---

    _log('# LOGIN to Zabbix');

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'user.login',
                     'params'=>array('user'=>$z_api_user,
                                     'password'=>$z_api_pass),
                     'id'=>nextRequestID(),
                     'auth'=>null);

    $result = postJSON($z_url_api,$request);

    $token = '';
    if (isset($result['result'])) { $token = $result['result']; }

    if ($token=='') { echo 'Error logging in to Zabbix? ('.$z_url_api.')'; die; }

    _log('> Token = '.$token);

    // --- GET TRIGGER INFO ---

    _log('# Retrieve TRIGGER information');

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'trigger.get',
                     'params'=>array('triggerids'=>$p_triggerId,
                                     'output'=>'extend',
                                     'selectFunctions'=>'extend',
                                     'selectTags'=>'extend'),
                     'expandComment'=>1,
                     'expandDescription'=>1,
                     'expandExpression'=>1,
                     'auth'=>$token,
                     'id'=>nextRequestID());

    $thisTrigger = postJSON($z_url_api,$request);
    _log('> Trigger data'.$cCRLF.json_encode($thisTrigger,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    if (!isset($thisTrigger['result'][0])) { echo '! No response data received?'.$cCRLF; die; }

    $mailData['TRIGGER_ID'] = $thisTrigger['result'][0]['triggerid'];
    $mailData['TRIGGER_DESCRIPTION'] = $thisTrigger['result'][0]['description'];
    $mailData['TRIGGER_COMMENTS'] = $thisTrigger['result'][0]['comments'];

    // --- Custom settings?

    $forceGraph = 0;

    foreach($thisTrigger['result'][0]['tags'] as $aTag)
    {
        if ($aTag['tag']=='mailGraph.period')
        {
            $p_period = $aTag['value'];
            _log('+ Graph display period override = '.$p_period);
        }

        if ($aTag['tag']=='mailGraph.graph')
        {
            $forceGraph = intval($aTag['value']);
            _log('+ Graph ID to display = '.$forceGraph);
        }
    }

    // --- GET ITEM INFO ---

    _log('# Retrieve ITEM information');

    $itemId = $thisTrigger['result'][0]['functions'][0]['itemid'];

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'item.get',
                     'params'=>array('itemids'=>$itemId,
                     'output'=>'extend'),
                     'auth'=>$token,
                     'id'=>nextRequestID());

    $thisItem = postJSON($z_url_api,$request);
    _log('> Item data'.$cCRLF.json_encode($thisItem,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    if (!isset($thisItem['result'][0])) { echo '! No response data received?'.$cCRLF; die; }

    $mailData['ITEM_ID'] = $thisItem['result'][0]['itemid'];
    $mailData['ITEM_KEY'] = $thisItem['result'][0]['key_'];
    $mailData['ITEM_NAME'] = $thisItem['result'][0]['name'];
    $mailData['ITEM_DESCRIPTION'] = $thisItem['result'][0]['description'];
    $mailData['ITEM_LASTVALUE'] = $thisItem['result'][0]['lastvalue'];
    $mailData['ITEM_PREVIOUSVALUE'] = $thisItem['result'][0]['prevvalue'];

    // Catch elements that have a recordset definition returned as a value ...
    if (substr($mailData['ITEM_LASTVALUE'],0,5)=='<?xml') { $mailData['ITEM_LASTVALUE'] = '[record]'; }
    if (substr($mailData['ITEM_PREVIOUSVALUE'],0,5)=='<?xml') { $mailData['ITEM_PREVIOUSTVALUE'] = '[record]'; }

    // --- GET HOST INFO ---

    _log('# Retrieve HOST information');

    $hostId = $thisItem['result'][0]['hostid'];

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'host.get',
                     'params'=>array('hostids'=>$hostId,
                                     'output'=>'extend'),
                     'auth'=>$token,
                     'id'=>nextRequestID());

    $thisHost = postJSON($z_url_api,$request);
    _log('> Host data'.$cCRLF.json_encode($thisHost,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    if (!isset($thisHost['result'][0])) { echo '! No response data received?'.$cCRLF; die; }

    $mailData['HOST_ID'] = $thisHost['result'][0]['hostid'];
    $mailData['HOST_NAME'] = $thisHost['result'][0]['name'];
    $mailData['HOST_ERROR'] = $thisHost['result'][0]['error'];
    $mailData['HOST_DESCRIPTION'] = $thisHost['result'][0]['description'];

    _log('# Retreive HOST macro information');

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'usermacro.get',
                     'params'=>array('hostids'=>$hostId,
                                     'output'=>'extend'),
                     'auth'=>$token,
                     'id'=>nextRequestID());

    $thisMacros = postJSON($z_url_api,$request);
    _log('> Host macro data'.$cCRLF.json_encode($thisMacros,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    // --- GET GRAPHS ASSOCIATED WITH THIS HOST ---
    // Note: graph.get where itemids are specified does not seem to work!

    _log('# Retrieve associated graphs to this HOST');

    $keyName = $thisItem['result'][0]['key_'];
    $hostId = $thisItem['result'][0]['hostid'];

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'graph.get',
                     'params'=>array('hostids'=>$hostId,
                                     'expandName'=>1,
                                     'selectGraphItems'=>'extend',
                                     'output'=>'extend'),
                     'auth'=>$token,
                     'id'=>nextRequestID());

    $thisGraphs = postJSON($z_url_api,$request);
    _log('> Graphs data'.$cCRLF.json_encode($thisGraphs,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    if ($forceGraph>0)
    {
        // --- GET GRAPH ASSOCIATED WITH FORCEGRAPH ---

        $request = array('jsonrpc'=>'2.0',
                         'method'=>'graph.get',
                         'params'=>array('graphids'=>$forceGraph,
                                         'expandName'=>1,
                                         'output'=>'extend'),
                         'auth'=>$token,
                         'id'=>nextRequestID());

        $forceGraphInfo = postJSON($z_url_api,$request);
        _log('> Forced graph data'.$cCRLF.json_encode($forceGraphInfo,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));
    }

    if (!isset($forceGraphInfo['result'][0]))
    {
        _log('! No data received for graph #'.$forceGraph.'; discarding forced graph information');
        $forceGraph = 0;
    }

    // --- FIND MATCHING GRAPH ITEMS WITH OUR TRIGGER ITEMS ---

    _log('# Matching retreived graph information with our trigger items');

    // Look for graphs across all functions inside the item
    $itemIds = array();

    foreach($thisTrigger['result'][0]['functions'] as $aFunction)
    {
        $didFind = FALSE;

        foreach($itemIds as $anItem)
        {
            if ($anItem==$aFunction['itemid']) { $didFind = TRUE; break; }
        }

        if (!$didFind) { $itemIds[] = $aFunction['itemid']; }
    }

    $matchedGraphs = array();
    $otherGraphs = array();

    foreach($thisGraphs['result'] as $aGraph)
    {
        foreach($aGraph['gitems'] as $aGraphItem)
        {
            foreach($itemIds as $anItemId)
            {
                if ($aGraphItem['itemid']==$anItemId)
                {
                    if ($anItemId=$itemId)
                    {
                        _log('+ Graph #'.$aGraphItem['graphid'].' full match found (item #'.$aGraphItem['itemid'].')');
                        $matchedGraphs[] = $aGraph;
                    }
                    else
                    {
                        $otherGraphs[] = $aGraph;
                        _log('~ Graph #'.$aGraphItem['graphid'].' partial match found (item #'.$aGraphItem['itemid'].')');
                    }
                }
            }
        }
    }

    _log('> Graphs found (matching/partial) = '.sizeof($matchedGraphs).' / '.sizeof($otherGraphs));

    // --- READ EVENT INFORMATION ---

    _log('# Retreiving EVENT information');

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'event.get',
                     'params'=>array('eventids'=>$p_eventId,
                                     'output'=>'extend'),
                     'auth'=>$token,
                     'id'=>nextRequestID());

    $thisEvent = postJSON($z_url_api,$request);
    _log('> Event data'.$cCRLF.json_encode($thisEvent,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    if (!isset($thisEvent['result'][0])) { echo '! No response data received?'.$cCRLF; die; }

    $mailData['EVENT_ID'] = $thisEvent['result'][0]['eventid'];
    $mailData['EVENT_NAME'] = $thisEvent['result'][0]['name'];
    $mailData['EVENT_OPDATA'] = $thisEvent['result'][0]['opdata'];
    $mailData['EVENT_VALUE'] = $p_eventValue;

    switch($p_eventValue)
    {
        case 0: // Recovering
                $mailData['EVENT_SEVERITY'] = 'Resolved';
                break;

        case 1: // Triggered/Active
                $_severity = array('Not classified','Information','Warning','Average','High','Disaster');
                $mailData['EVENT_SEVERITY'] = $_severity[$thisEvent['result'][0]['severity']];
                break;
    }

    $_status = array('Recovered','Triggered/Active');
    $mailData['EVENT_STATUS'] = $_status[$p_eventValue];

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Fetch Graph //////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    $graphFile = '';
    $graphURL = '';

    // If we have any matching graph, make the embedding information available

    if ($forceGraph>0)
    {
        _log('# Adding FORCED graph #'.$forceGraph);
        $graphFile = GraphImageById($forceGraph,
                                    $p_graphWidth,$p_graphHeight,
                                    $forceGraphInfo['result'][0]['graphtype'],
                                    $p_showLegend,$p_period);

        _log('> Filename = '.$graphFile);

        $mailData['GRAPH_ID'] = $forceGraph;
        $mailData['GRAPH_NAME'] = $forceGraphInfo['result'][0]['name'];
        $mailData['GRAPH_URL'] = $z_url_image . $graphFile;
        $mailData['GRAPH_MATCH'] = 'Forced';
    }
    else
    {
        if ((sizeof($matchedGraphs)+sizeof($otherGraphs))>0)
        {
            // Exact match goes first

            if (sizeof($matchedGraphs)>0)
            {
                _log('# Adding MATCHED graph #'.$matchedGraphs[0]['graphid']);
                $graphFile = GraphImageById($matchedGraphs[0]['graphid'],
                                            $p_graphWidth,$p_graphHeight,
                                            $matchedGraphs[0]['graphtype'],
                                            $p_showLegend,$p_period);

                _log('> Filename = '.$graphFile);

                $mailData['GRAPH_ID'] = $matchedGraphs[0]['graphid'];
                $mailData['GRAPH_NAME'] = $matchedGraphs[0]['name'];
                $mailData['GRAPH_URL'] = $z_url_image . $graphFile;
                $mailData['GRAPH_MATCH'] = 'Exact';
            }
            else
            {
                if (($p_graph_match!='exact') && (sizeof($otherGraphs)>0))
                {
                    _log('# Adding OTHER graph #'.$otherGraphs[0]['graphid']);
                    $graphFile = GraphImageById($otherGraphs[0]['graphid'],
                                                $p_graphWidth,$p_graphHeight,
                                                $otherGraphs[0]['graphtype'],
                                                $p_showLegend);

                    _log('> Filename = '.$graphFile);

                    $mailData['GRAPH_ID'] = $otherGraphs[0]['graphid'];
                    $mailData['GRAPH_NAME'] = $otherGraphs[0]['name'];
                    $mailData['GRAPH_URL'] = $z_url_image . $graphFile;
                    $mailData['GRAPH_MATCH'] = 'Other';
                }
            }

            $mailData['GRAPH_ZABBIXLINK'] = $z_server.'/graphs.php?form=update&graphid='.$mailData['GRAPH_ID'];
        }
    }

    // Prepare HTML LOG content

    $mailData['LOG_HTML'] = implode('</br/>',$logging);
    $mailData['LOG_HTML'] = str_replace($cCRLF,'<br/>',$mailData['LOG_HTML']);
    $mailData['LOG_HTML'] = str_replace('<br/>','<br/>'.$cCRLF,$mailData['LOG_HTML']);

    $mailData['LOG_HTML'] = '<html lang="en"><head><meta http-equiv=Content-Type content="text/html; charset=UTF-8">'.$cCRLF.
                            '<body>'.$cCRLF.
                            $mailData['LOG_HTML'].$cCRLF.
                            '</body>'.$cCRLF.
                            '</html>';

    // Prepare PLAIN LOG content

    $mailData['LOG_PLAIN'] = implode(chr(10),$logging);

    // Prepare others

    $mailData['TRIGGER_URL'] = $z_server.'/triggers.php?form=update&triggerid='.$mailData['TRIGGER_ID'];
    $mailData['ITEM_URL'] = $z_server.'/items.php?form=update&hostid='.$mailData['HOST_ID'].'&itemid='.$mailData['ITEM_ID'];
    $mailData['HOST_URL'] = $z_server.'/zabbix/hosts.php?form=update&hostid='.$mailData['HOST_ID'];
    $mailData['EVENTDETAILS_URL'] = $z_server.'/tr_events.php?triggerid='.$mailData['TRIGGER_ID'].'&eventid='.$mailData['EVENT_ID'];

    $mailData['EVENT_DURATION'] = $p_duration;

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Compose & Send Message ///////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    _log('# Setting up mailer');

    if (($p_smtp_transport=='tls') || ($p_smtp_transport=='ssl'))
    {
        $transport = (new Swift_SmtpTransport($p_smtp_server, $p_smtp_port, $p_smtp_transport));

        if ($p_smtp_strict=='no')
        {
            if ($transport instanceof \Swift_Transport_EsmtpTransport)
            {
                $transport->setStreamOptions([
                    'ssl' => ['allow_self_signed' => true,
                              'verify_peer' => false,
                              'verify_peer_name' => false]
                    ]);
            }
        }
        else
        {
            if ($transport instanceof \Swift_Transport_EsmtpTransport)
            {
                $transport->setStreamOptions([
                    'ssl' => ['allow_self_signed' => false,
                              'verify_peer' => true,
                              'verify_peer_name' => true]
                ]);
            }
        }
    }
    else
    {
        $transport = (new Swift_SmtpTransport($p_smtp_server, $p_smtp_port));
    }

    $mailer = new Swift_Mailer($transport);

    $message = (new Swift_Message());

    // Fetch mailer ID from this message (no Swift function available for it ...)
    // --- "Message-ID: <...id...@swift.generated>"

    $content = $message->toString();
    $lines = explode(chr(10),$content);
    $firstLine = $lines[0];
    $idParts = explode(' ',$firstLine);
    $messageId = substr($idParts[1],1,-2);

    _log('# Message ID = '.$messageId);

    // Build parts for HTML and PLAIN

    _log('# Processing templates');
    _log('+ '.$z_template_path.'html.template');
    _log('+ '.$z_template_path.'plain.template');

    $loader = new \Twig\Loader\ArrayLoader([
        'html' => file_get_contents($z_template_path.'html.template'),
        'plain' => file_get_contents($z_template_path.'plain.template'),
        'subject' => $mailData['SUBJECT'],
    ]);

    $twig = new \Twig\Environment($loader);

    if ($graphFile!='')
    {
        // Embed the image
        $mailData['GRAPH_CID'] = $message->embed(Swift_Image::fromPath($z_images_path.$graphFile));
        _log('> Embedded graph image '.$z_images_path.$graphFile);
    }

    $bodyHTML = $twig->render('html', $mailData);
    $bodyPlain = $twig->render('plain', $mailData);
    $mailSubject = $twig->render('subject', $mailData);

    // Prepare message

    $message->setSubject($mailSubject)
            ->setFrom($mailFrom)
            ->setTo($p_recipient)
            ->setBody($bodyHTML, 'text/html')
            ->addPart($bodyPlain, 'text/plain');

    if ($cDebugMail)
    {
        _log('# Attaching logs to mail message');

        $attachLogHTML = new Swift_Attachment($mailData['LOG_HTML'], 'log.html', 'text/html');
        $message->attach($attachLogHTML);

        $attachLogPlain = new Swift_Attachment($mailData['LOG_PLAIN'], 'log.txt', '/text/plain');
        $message->attach($attachLogPlain);
    }

    // Send message

    $result = $mailer->send($message);

    // Return TAG information

    $response = array('messageId.mailGraph'=>$messageId);
    echo json_encode($response).$cCRLF;

    // Store log?

    if ($cDebug)
    {
        unset($mailData['LOG_HTML']);
        unset($mailData['LOG_PLAIN']);

        $content = implode(chr(10),$logging).$cCRLF.$cCRLF.'=== MAILDATA ==='.$cCRLF.$cCRLF.json_encode($mailData,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);
        $content = str_replace(chr(13),'',$content);

        $logName = 'log.'.$p_eventId.'.'.date('YmdHis').'.dump';

        file_put_contents($z_log_path.$logName,$content);
	_log('= Log stored to '.$z_log_path.$logName);
    }
?>
