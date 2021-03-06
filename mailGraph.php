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
    // 1.18 2021/03/04 - Mark Oudsen - Added ability to specify Tags per trigger
    //                                 Shorten long "lastvalue" or "prevvalue"
    // 1.19 2021/03/05 - Mark Oudsen - Added ability to pass Zabbix 'infoXXX' parameters for TWIG template
    // 1.20 2021/03/07 - Mark Oudsen - Production level version - leaving BETA from here on ...
    // 1.21 2021/03/09 - Mark Oudsen - Reverted graph.get code back to original code as it was not a bug but
    //                                 a wrongly typed requested (should be ARRAY, not comma separated)!
    // 1.22 2021/03/10 - Mark Oudsen - Added ability to embed multiple periods (1-4) of the same graph
    // 1.23 2021/03/12 - Mark Oudsen - Added graph support for 'Stacked', 'Pie' and 'Exploded'
    // 1.24 2021/03/12 - Mark Oudsen - Added support for HTTP proxy
    // 1.25 2021/03/16 - Mark Oudsen - Refactoring for optimized flow and relevant data retrieval
    // 1.26 2021/03/19 - Mark Oudsen - Bugfixes after refactor (wrong itemId and incorrect eventValue)
    //                                 Suppressing Zabbix username-password in log
    // 1.27 2021/03/19 - Mark Oudsen - Added ability to define "mailGraph.screen" tag to embed graphs from
    //                                 Added PHP informational and warnings to log for easier debug/spotting
    // 1.28 2021/03/24 - Mark Oudsen - Added ability to specify username/password for TLS/SSL
    // 1.29 2021/04/03 - Mark Oudsen - Bugfix due to stricter JSONRPC version check since Zabbix 5.0.10
    //      2021/07/05 - Mark Oudsen - Minor detail added: CLI debug mode now also outputs version
    //      2021/07/07 - Mark Oudsen - Fixed HTTPProxy typo in code (instead of HTTPPRoxy)
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
    // 2) Fetch Graph(s) associated to the item/trigger (if any) via Zabbix URL login via CURL
    // 3) Build and send mail message from template using Swift/TWIG
    //
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    // CONSTANTS

    $cVersion = 'v1.29';
    $cCRLF = chr(10).chr(13);
    $maskDateTime = 'Y-m-d H:i:s';
    $maxGraphs = 4;

    // DEBUG SETTINGS
    // -- Should be FALSE for production level use

    $cDebug = FALSE;          // Extended debug logging mode
    $cDebugMail = FALSE;      // If TRUE, includes log in the mail message (html and plain text attachments)
    $showLog = FALSE;         // Display the log - !!! only use in combination with CLI mode !!!

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
        global $HTTPProxy;

        // Initialize Curl instance
        _log('% postJSON: '.$url);
        if ($cDebug) { _log('> POST data: '.json_encode(maskOutputContent($data))); }

        $ch = curl_init();

        // Set options

        if ((isset($HTTPProxy)) && ($HTTPProxy!=''))
        {
            if ($cDebug) { _log('% Using proxy: '.$HTTPProxy); }
            curl_setopt($ch, CURLOPT_PROXY, $HTTPProxy);
        }

        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);

        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        curl_setopt($ch, CURLOPT_USERAGENT, 'Zabbix-mailGraph - '.$cVersion);

        // Execute Curl
        $data = curl_exec($ch);

        if ($data===FALSE)
        {
            _log('! Failed: '.curl_error($ch));

            $data = array();
            $data[] = 'An error occurred while retreiving the requested page.';
            $data[] .= 'Requested page = '.$url;
            $data[] .= 'Error = '.curl_error($ch);
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
        global $HTTPProxy;

        // Unique names
        $thisTime = time();

        // Relative web calls
        $z_url_index   = $z_server ."index.php";

        switch($graphType)
        {
           // 0: Normal
           // 1: Stacked
           case 0:
           case 1:
                $z_url_graph   = $z_server ."chart2.php";
                break;

           // 2: Pie
           // 3: Exploded
           case 2:
           case 3:
                $z_url_graph   = $z_server ."chart6.php";
                break;

           default:
                // Catch all ...
                _log('% Graph type #'.$graphType.' unknown; forcing "Normal"');
                $z_url_graph   = $z_server ."chart2.php";
        }

        $z_url_fetch   = $z_url_graph ."?graphid=" .$graphid ."&width=" .$width ."&height=" .$height .
                                       "&graphtype=".$graphType."&legend=".$showLegend."&profileIdx=web.graphs.filter".
                                       "&from=now-".$period."&to=now";

        // Prepare POST login
        $z_login_data  = array('name' => $z_user, 'password' => $z_pass, 'enter' => "Sign in");

        // Cookie and image names
        $filename_cookie = $z_tmp_cookies ."zabbix_cookie_" .$graphid . "." .$thisTime. ".txt";
        $filename = "zabbix_graph_" .$graphid . "." . $thisTime . "-" . $period . ".png";
        $image_name = $z_images_path . $filename;

        // Configure CURL
        _log('% GraphImageById: '.$z_url_fetch);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $z_url_index);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Zabbix-mailGraph - '.$cVersion);

        if ((isset($HTTPProxy)) && ($HTTPProxy!=''))
        {
            _log('% Using proxy: '.$HTTPProxy);
            curl_setopt($ch, CURLOPT_PROXY, $HTTPProxy);
        }

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
    // Catch PHP warnings/notices/errors

    function catchPHPerrors($errno, $errstr, $errfile, $errline)
    {
        // --- Just log ...

        _log('!! ('.$errno.') "'.$errstr.'" at line #'.$errline.' of "'.$errfile.'"');

        // --- We do not take care of any errors, etc.
        return FALSE;
    }

    set_error_handler("catchPHPerrors");

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
    // Check the array for information we do not want to share in any logging

    function maskOutputFields($info)
    {
        foreach($info as $aKey=>$aValue)
        {
            switch($aKey)
            {
                case 'zabbix_user':
                case 'zabbix_user_pwd':
                case 'zabbix_api_user':
                case 'zabbix_api_pwd':
                    $info[$aKey] = '<masked>';
                    break;
            }
        }

        return($info);
    }

    // Check the array if it contains information that should not be logged

    function maskOutputContent($info)
    {
        global $config;

        foreach($info as $infoKey=>$infoValue)
        {
            if (is_array($infoValue)) { $info[$infoKey] = maskOutputContent($infoValue); }

            foreach($config as $aKey=>$aValue)
            {
                switch($aKey)
                {
                    case 'zabbix_user':
                    case 'zabbix_user_pwd':
                    case 'zabbix_api_user':
                    case 'zabbix_api_pwd':
                        if ($aValue==$infoValue) { $info[$infoKey] = '<masked>'; };
                        break;
                }
            }
        }

        return($info);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Initialize ///////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    // --- CONFIG DATA ---

    // [CONFIGURE] Change only when you want to place your config file somewhere else ...
    $config = readConfig(getcwd().'/config/config.json');

    _log('# Configuration taken from config.json'.$cCRLF.
         json_encode(maskOutputFields($config),JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    // --- POST DATA ---

    // Read POST data
    $problemJSON = file_get_contents('php://input');
    $problemData = json_decode($problemJSON,TRUE);

    // --- CLI DATA ---

    $HTTPProxy = '';

    // Facilitate CLI based testing
    if (isset($argc))
    {
        if (($argc>1) && ($argv[1]=='test'))
        {
            // Switch on CLI log display
            $showLog = TRUE;

            _log('<<< mailGraph '.$cVersion.' >>>');
            _log('# Invoked from CLI');

            // Assumes that config.json file has the correct information

            // MANDATORY
            $problemData['itemId'] = $config['cli_itemId'];
            $problemData['eventId'] = $config['cli_eventId'];
            $problemData['recipient'] = $config['cli_recipient'];
            $problemData['baseURL'] = $config['cli_baseURL'];
            $problemData['duration'] = $config['cli_duration'];

            // OPTIONAL
            if (isset($config['cli_subject'])) { $problemData['subject'] = $config['cli_subject']; }
            if (isset($config['cli_period'])) { $problemData['period'] = $config['cli_period']; }
            if (isset($config['cli_period_header'])) { $problemData['period_header'] = $config['cli_period_header']; }
            if (isset($config['cli_periods'])) { $problemData['periods'] = $config['cli_periods']; }
            if (isset($config['cli_periods_headers'])) { $problemData['periods_headers'] = $config['cli_periods_headers']; }
            if (isset($config['cli_debug'])) { $problemData['debug'] = $config['cli_debug']; }
            if (isset($config['cli_proxy'])) { $problemData['HTTPProxy'] = $config['cli_proxy']; }
        }
    }

    _log('# Data passed to MailGraph main routine and used for processing'.
         $cCRLF.json_encode($problemData,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    // --- CHECK AND SET P_ VARIABLES ---
    // FROM POST OR CLI DATA

    if (!isset($problemData['itemId'])) { echo "Missing ITEM ID?\n"; die; }
    $p_itemId = intval($problemData['itemId']);

    if (!isset($problemData['eventId'])) { echo "Missing EVENT ID?\n"; die; }
    $p_eventId = intval($problemData['eventId']);

    if (!isset($problemData['recipient'])) { echo "Missing RECIPIENT?\n"; die; }
    $p_recipient = $problemData['recipient'];

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

    if (isset($problemData['HTTPProxy'])) { $HTTPProxy = $problemData['HTTPProxy']; }

    // DYNAMIC VARIABLES FROM ZABBIX

    foreach($problemData as $aKey=>$aValue)
    {
        if (substr($aKey,0,4)=='info') { $mailData[$aKey] = $aValue; }
    }

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

    $p_smtp_username = '';
    if (isset($config['smtp_username'])) { $p_smtp_username = $config['smtp_username']; }

    $p_smtp_password = '';
    if (isset($config['smtp_password'])) { $p_smtp_password = $config['smtp_password']; }

    $p_graph_match = 'any';
    if ((isset($config['graph_match'])) && ($config['graph_match']=='exact')) { $p_graph_match = 'exact'; }

    // --- GLOBAL CONFIGURATION ---

    // Script related settings
    $z_url = $config['script_baseurl'];             // Script URL location (for relative paths to images, templates, log, tmp)
    $z_url_image = $z_url.'images/';                // Images URL (included in plain message text)

    // Absolute path where to store the generated images - note: script does not take care of clearing out old images!
    $z_path = getcwd().'/';                         // Absolute base path on the filesystem for this url
    $z_images_path = $z_path.'images/';
    $z_template_path = $z_path.'templates/';
    $z_tmp_cookies = $z_path.'tmp/';
    $z_log_path = $z_path.'log/';

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

    // -------------
    // --- LOGIN ---
    // -------------

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

    if ($token=='')
    {
        echo 'Error logging in to Zabbix? ('.$z_url_api.'): '.$cCRLF;
        echo var_dump($request).$cCRLF;
        echo var_dump($result).$cCRLF;
        die;
    }

    _log('> Token = '.$token);

    // ------------------------------
    // --- READ EVENT INFORMATION ---
    // ------------------------------

    _log('# Retreiving EVENT information');

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'event.get',
                     'params'=>array('eventids'=>$p_eventId,
                                     'output'=>'extend',
                                     'selectRelatedObject'=>'extend',
                                     'selectSuppressionData'=>'extend'),
                     'auth'=>$token,
                     'id'=>nextRequestID());

    $thisEvent = postJSON($z_url_api,$request);
    _log('> Event data'.$cCRLF.json_encode($thisEvent,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    if (!isset($thisEvent['result'][0])) { echo '! No response data received?'.$cCRLF; die; }

    $mailData['EVENT_ID'] = $thisEvent['result'][0]['eventid'];
    $mailData['EVENT_NAME'] = $thisEvent['result'][0]['name'];
    $mailData['EVENT_OPDATA'] = $thisEvent['result'][0]['opdata'];
    $mailData['EVENT_SUPPRESSED'] = $thisEvent['result'][0]['suppressed'];
    $mailData['EVENT_VALUE'] = $thisEvent['result'][0]['relatedObject']['value'];

    switch($mailData['EVENT_VALUE'])
    {
        case 0: // Recovering
                $mailData['EVENT_SEVERITY'] = 'Resolved';
                $mailData['EVENT_STATUS'] = 'Recovered';
                break;

        case 1: // Triggered/Active
                $_severity = array('Not classified','Information','Warning','Average','High','Disaster');
                $mailData['EVENT_SEVERITY'] = $_severity[$thisEvent['result'][0]['severity']];
                $mailData['EVENT_STATUS'] = 'Triggered/Active';
                break;
    }

    $p_triggerId = $thisEvent['result'][0]['relatedObject']['triggerid'];

    // ------------------------
    // --- GET TRIGGER INFO ---
    // ------------------------

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
    $triggerScreen = 0;
    $triggerScreenPeriod = '';
    $triggerScreenPeriodHeader = '';

    foreach($thisTrigger['result'][0]['tags'] as $aTag)
    {
        switch ($aTag['tag'])
        {
            case 'mailGraph.period':
                $problemData['period'] = $aTag['value'];
                _log('+ Graph display period override = '.$problemData['period']);
                break;

            case 'mailGraph.period_header':
                $problemData['period_header'] = $aTag['value'];
                _log('+ Graph display period header override = '.$problemData['period_header']);
                break;

            case 'mailGraph.periods':
                $problemData['periods'] = $aTag['value'];
                _log('+ Graph display periods override = '.$problemData['periods']);
                break;

            case 'mailGraph.periods_headers':
                $problemData['periods_headers'] = $aTag['value'];
                _log('+ Graph display periods headers override = '.$problemData['periods_headers']);
                break;

            case 'mailGraph.graph':
                $forceGraph = intval($aTag['value']);
                _log('+ Graph ID to display = '.$forceGraph);
                break;

            case 'mailGraph.showLegend':
                $p_showLegend = intval($aTag['value']);
                _log('+ Graph display legend override = '.$p_showLegend);
                break;

            case 'mailGraph.graphWidth':
                $p_graphWidth = intval($aTag['value']);
                _log('+ Graph height override = '.$$p_graphWidth);
                break;

            case 'mailGraph.graphHeight':
                $p_graphHeight = intval($aTag['value']);
                _log('+ Graph height override = '.$$p_graphHeight);
                break;

            case 'mailGraph.debug':
                $problemData['debug'] = 1;
                _log('+ Mail debug log enabled');
                break;

            case 'mailGraph.screen':
                $triggerScreen = intval($aTag['value']);
                _log('+ Trigger screen = '.$triggerScreen);
                break;

            case 'mailGraph.screenPeriod':
                $triggerScreenPeriod = $aTag['value'];
                _log('+ Trigger screen period = '.$triggerScreenPeriod);
                break;

            case 'mailGraph.screenPeriodHeader':
                $triggerScreenPeriodHeader = $aTag['value'];
                _log('+ Trigger screen header = '.$triggerScreenPeriodHeader);
                break;
        }
    }

    // ---------------------
    // --- GET ITEM INFO ---
    // ---------------------

    _log('# Retrieve ITEM information');

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'item.get',
                     'params'=>array('itemids'=>$p_itemId,
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

    // Catch long elements
    if (strlen($mailData['ITEM_LASTVALUE'])>50) { $mailData['ITEM_LASTVALUE'] = substr($mailData['ITEM_LASTVALUE'],0,50).' ...'; }
    if (strlen($mailData['ITEM_PREVIOUSVALUE'])>50) { $mailData['ITEM_PREVIOUSVALUE'] = substr($mailData['ITEM_PREVIOUSVALUE'],0,50).' ...'; }

    // ---------------------
    // --- GET HOST INFO ---
    // ---------------------

    _log('# Retrieve HOST information');

    $hostId = $thisItem['result'][0]['hostid'];

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'host.get',
                     'params'=>array('hostids'=>$hostId,
                                     'output'=>'extend',
                                     'selectTags'=>'extend'),
                     'auth'=>$token,
                     'id'=>nextRequestID());

    $thisHost = postJSON($z_url_api,$request);
    _log('> Host data'.$cCRLF.json_encode($thisHost,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    if (!isset($thisHost['result'][0])) { echo '! No response data received?'.$cCRLF; die; }

    $mailData['HOST_ID'] = $thisHost['result'][0]['hostid'];
    $mailData['HOST_NAME'] = $thisHost['result'][0]['name'];
    $mailData['HOST_ERROR'] = $thisHost['result'][0]['error'];
    $mailData['HOST_DESCRIPTION'] = $thisHost['result'][0]['description'];

    // --- Custom settings?

    $hostScreen = 0;
    $hostScreenPeriod = '';
    $hostScreenPeriodHeader = '';

    foreach($thisHost['result'][0]['tags'] as $aTag)
    {
        switch ($aTag['tag'])
        {
            case 'mailGraph.screen':
                $hostScreen = intval($aTag['value']);
                _log('+ Host screen (from TAG) = '.$hostScreen);
                break;

            case 'mailGraph.screenPeriod':
                $hostScreenPeriod = $aTag['value'];
                _log('+ Host screen period (from TAG) = '.$hostScreenPeriod);
                break;

            case 'mailGraph.screenPeriodHeader':
                $hostScreenPeriodHeader = $aTag['value'];
                _log('+ Host screen period header (from TAG) = '.$hostScreenPeriodHeader);
                break;
        }
    }

    _log('# Retreive HOST macro information');

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'usermacro.get',
                     'params'=>array('hostids'=>$hostId,
                                     'output'=>'extend'),
                     'auth'=>$token,
                     'id'=>nextRequestID());

    $thisMacros = postJSON($z_url_api,$request);
    _log('> Host macro data'.$cCRLF.json_encode($thisMacros,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    foreach($thisMacros['result'] as $aMacro)
    {
        switch($aMacro['macro'])
        {
            case 'mailGraph.screen':
                $hostScreen = intval($aMacro['value']);
                _log('+ Host screen (from MACRO) = '.$hostScreen);
                break;

            case 'mailGraph.screenPeriod':
                $hostScreenPeriod = $aMacro['value'];
                _log('+ Host screen period (from MACRO) = '.$hostScreenPeriod);
                break;

            case 'mailGraph.screenPeriodHeader':
                $hostScreenPeriodHeader = $aMacro['value'];
                _log('+ Host screen header (from MACRO) = '.$hostScreenPeriodHeader);
                break;
        }
    }

    // ------------------------------------------------------------------
    // --- GET GRAPHS ASSOCIATED WITH THIS HOST AND THE TRIGGER ITEMS ---
    // ------------------------------------------------------------------

    _log('# Retrieve associated graphs to this HOST and the TRIGGER ITEMS');

    $searchItems = array();

    foreach($thisTrigger['result'][0]['functions'] as $aFunction)
    {
        $searchItems[] = $aFunction['itemid'];
    }

    $keyName = $thisItem['result'][0]['key_'];
    $hostId = $thisItem['result'][0]['hostid'];

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'graph.get',
                     'params'=>array('hostids'=>$hostId,
                                     'itemids'=>$searchItems,
                                     'expandName'=>1,
                                     'selectGraphItems'=>'extend',
                                     'output'=>'extend'),
                     'auth'=>$token,
                     'id'=>nextRequestID());

    $thisGraphs = postJSON($z_url_api,$request);
    _log('> Graphs data'.$cCRLF.json_encode($thisGraphs,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    if ($forceGraph>0)
    {
        // --------------------------------------------
        // --- GET GRAPH ASSOCIATED WITH FORCEGRAPH ---
        // --------------------------------------------

        _log('# Retrieving FORCED graph information');

        $request = array('jsonrpc'=>'2.0',
                         'method'=>'graph.get',
                         'params'=>array('graphids'=>$forceGraph,
                                         'expandName'=>1,
                                         'output'=>'extend'),
                         'auth'=>$token,
                         'id'=>nextRequestID());

        $forceGraphInfo = postJSON($z_url_api,$request);
        _log('> Forced graph data'.$cCRLF.
             json_encode($forceGraphInfo,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

        if (!isset($forceGraphInfo['result'][0]))
        {
            _log('! No data received for graph #'.$forceGraph.'; discarding forced graph information');
            $forceGraph = 0;
        }
    }

    // --------------------------------------------------------
    // --- FIND MATCHING GRAPH ITEMS WITH OUR TRIGGER ITEMS ---
    // --------------------------------------------------------

    _log('# Matching retreived graph information with our trigger items');

    // --- Look for graphs across all functions inside the item

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
                    if ($anItemId==$p_itemId)
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

    // ---------------------------------------------------------------------------
    // --- FIND MATCHING GRAPH ITEMS WITH TRIGGER AND/OR HOST SCREEN REFERENCE ---
    // ---------------------------------------------------------------------------

    function _sort($a,$b)
    {
        if ($a['screen']['y']>$b['screen']['y']) { return(1); }
        if ($a['screen']['y']<$b['screen']['y']) { return(-1); }
        if ($a['screen']['x']>$b['screen']['x']) { return(1); }
        if ($a['screen']['x']<$b['screen']['x']) { return(-1); }
        return(0);
    }

    function fetchGraphsFromScreen($screenId)
    {
        global $token;
        global $z_url_api;
        global $cCRLF;

        // --- Pick up the SCREEN ITEMS associated to the SCREEN

        $request = array('jsonrpc'=>'2.0',
                         'method'=>'screen.get',
                         'params'=>array('screenids'=>$screenId,
                                         'output'=>'extend',
                                         'selectScreenItems'=>'extend'),
                         'auth'=>$token,
                         'id'=>nextRequestID());

        $screenGraphs = postJSON($z_url_api,$request);
        _log('> Screen items data for screen #'.$screenId.$cCRLF.json_encode($screenGraphs,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

        // --- Filter on specific type(s) and enrich the graph data

        $result = array();

        foreach($screenGraphs['result'][0]['screenitems'] as $anItem)
        {
            switch($anItem['resourcetype'])
            {
                case 0: // Graph
                    $request = array('jsonrpc'=>'2.0',
                                     'method'=>'graph.get',
                                     'params'=>array('graphids'=>$anItem['resourceid'],
                                                     'expandName'=>1,
                                                     'output'=>'extend'),
                                     'auth'=>$token,
                                     'id'=>nextRequestID());

                    $screenGraph = postJSON($z_url_api,$request);
                    _log('+ Graph data for screen item #'.$anItem['screenitemid'].$cCRLF.
                         json_encode($screenGraph,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

                    $result[] = array('screen'=>$anItem,'name'=>$screenGraphs['result'][0]['name'],'graph'=>$screenGraph['result'][0]);
                    break;
            }
        }

        // --- Sort the result according to SCREEN x,y position

        usort($result,"_sort");

        // --- Done

        return($result);
    }

    $triggerGraphs = array();

    if ($triggerScreen>0)
    {
        _log('# Fetching graph information for TRIGGER for screen #'.$hostScreen);
        $triggerGraphs = fetchGraphsFromScreen($triggerScreen);
        _log('> Graphs found = '.sizeof($triggerGraphs));
    }


    if ($hostScreen>0)
    {
        _log('# Fetching graph information for HOST for screen #'.$hostScreen);
        $hostGraphs = fetchGraphsFromScreen($hostScreen);
        _log('> Graphs found = '.sizeof($hostGraphs));
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Fetch Graph(s) ///////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    // Determine number of periods for the ITEM graphs

    $p_periods = array();
    $p_periods_headers = array();

    if (isset($problemData['periods']))
    {
        // Multiple periods mode selected

        _log('# Multi period graph mode selected');

        $p_periods = explode(',',$problemData['periods']);

        // If invalid, replace with single graph item
        if (sizeof($p_periods)==0) { $p_periods[] = $p_period; }

        // --- Determine headers

        if (isset($problemData['periods_headers'])) { $p_periods_headers = explode(',',$problemData['periods_headers']); }

        // If no headers specified, simply copy the period information
        if (sizeof($p_periods_headers)==0) { $p_periods_headers = $p_periods; }
    }
    else
    {
        // Single period mode selected

        $p_periods[] = $p_period;

        if (isset($problemData['period_header']))
        {
            $p_periods_headers[] = $problemData['period_header'];
        }
        else
        {
            $p_periods_headers[] = $p_period;
        }
    }

    // Strip off any excessive elements from the end

    while (sizeof($p_periods)>$maxGraphs) { array_pop($p_periods); }
    while (sizeof($p_periods_headers)>$maxGraphs) { array_pop($p_periods_headers); }

    // Fetching of the ITEM graphs

    $graphFiles = array();
    $graphURL = '';

    // If we have any matching graph, make the embedding information available

    if ((sizeof($matchedGraphs) + sizeof($otherGraphs) + $forceGraph)>0)
    {
        if ($forceGraph>0)
        {
            $theGraph = $forceGraphInfo;
            $theType = 'Forced';
        }
        else
        {
            if (sizeof($matchedGraphs)>0)
            {
                $theGraph = $matchedGraphs[0];
                $theType = 'Matched';
            }
            else
            {
                if (sizeof($otherGraphs)>0)
                {
                    $theGraph = $otherGraphs[0];
                    $theType = 'Other';
                }
            }
        }

        $mailData['GRAPH_ID'] = $theGraph['graphid'];
        $mailData['GRAPH_NAME'] = $theGraph['name'];
        $mailData['GRAPH_MATCH'] = $theType;

        _log('# Adding '.strtoupper($theType).' graph #'.$mailData['GRAPH_ID']);

        foreach($p_periods as $aKey=>$aPeriod)
        {
            $graphFile = GraphImageById($mailData['GRAPH_ID'],
                                        $p_graphWidth,$p_graphHeight,
                                        $theGraph['graphtype'],
                                        $p_showLegend,$aPeriod);

            $graphFiles[] = $graphFile;

            $mailData['GRAPHS_I'][$aKey]['PATH'] = $z_images_path . $graphFile;
            $mailData['GRAPHS_I'][$aKey]['URL'] = $z_url_image . $graphFile;
            $mailData['GRAPHS_I'][$aKey]['HEADER'] = $p_periods_headers[$aKey];
        }

        $mailData['GRAPH_ZABBIXLINK'] = $z_server.'graphs.php?form=update&graphid='.$mailData['GRAPH_ID'];
    }

    // Fetch graphs associated to TRIGGER or HOST screen references obtained earlier

    function addGraphs($varName,$info,$period,$periodHeader)
    {
        global $p_graphWidth;
        global $p_graphHeight;
        global $p_showLegend;
        global $z_url_image;
        global $z_images_path;
        global $z_server;
        global $mailData;

        $files = array();

        foreach($info as $aKey=>$anItem)
        {
            $graphFile = GraphImageById($anItem['graph']['graphid'],
                                        $p_graphWidth,$p_graphHeight,
                                        $anItem['graph']['graphtype'],
                                        $p_showLegend,$period);

            $mailData['GRAPHS_'.$varName][$aKey]['URL'] = $z_url_image . $graphFile;
            $mailData['GRAPHS_'.$varName][$aKey]['PATH'] = $z_images_path . $graphFile;
        }

        $mailData['GRAPHS_'.$varName.'_LINK'] = $z_server.'screens.php?elementid='.$info[0]['screen']['screenid'];
        $mailData['GRAPHS_'.$varName.'_HEADER'] = $info[0]['name'];
        $mailData['GRAPHS_'.$varName.'_PERIODHEADER'] = $periodHeader;

    }

    if (sizeof($triggerGraphs)>0)
    {
        if ($triggerScreenPeriod=='')
        {
            $triggerScreenPeriod = $p_periods[0];
            $triggerScreenPeriodHeader = $p_periods_headers[0];
        }

        if ($triggerScreenPeriodHeader=='') { $triggerScreenPeriodHeader = $triggerScreenPeriod; }

        addGraphs('T',$triggerGraphs,$triggerScreenPeriod,$triggerScreenPeriodHeader);

        $mailData['TRIGGER_SCREEN'] = $triggerScreen;
    }

    if (sizeof($hostGraphs)>0)
    {
        if ($hostScreenPeriod=='')
        {
            $hostScreenPeriod = $p_periods[0];
            $hostScreenPeriodHeader = $p_periods_headers[0];
        }

        if ($hostScreenPeriodHeader=='') { $hostScreenPeriodHeader = $hostScreenPeriod; }

        addGraphs('H',$hostGraphs,$hostScreenPeriod,$hostScreenPeriodHeader);

        $mailData['HOST_SCREEN'] = $hostScreen;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Prepare HTML LOG content /////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

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

    $mailData['TRIGGER_URL'] = $z_server.'triggers.php?form=update&triggerid='.$mailData['TRIGGER_ID'];
    $mailData['ITEM_URL'] = $z_server.'items.php?form=update&hostid='.$mailData['HOST_ID'].'&itemid='.$mailData['ITEM_ID'];
    $mailData['HOST_URL'] = $z_server.'hosts.php?form=update&hostid='.$mailData['HOST_ID'];
    $mailData['EVENTDETAILS_URL'] = $z_server.'tr_events.php?triggerid='.$mailData['TRIGGER_ID'].'&eventid='.$mailData['EVENT_ID'];

    $mailData['EVENT_DURATION'] = $p_duration;

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Compose & Send Message ///////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    _log('# Setting up mailer');

    // Do we need TLS or SSL?

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

    // Username/password?

    if ($p_smtp_username!='') { $transport->setUsername($p_smtp_username); }
    if ($p_smtp_password!='') { $transport->setPassword($p_smtp_password); }

    // Start actual mail(er)

    $mailer = new Swift_Mailer($transport);

    $message = (new Swift_Message());

    // --- Fetch mailer ID from this message (no Swift function available for it ...)
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

    // --- Embed the images

    function embedGraphs($graphs,$varName,$type)
    {
        global $message;
        global $mailData;

        foreach($graphs as $aKey=>$anItem)
        {
            $mailData['GRAPHS_'.$varName][$aKey]['CID'] = $message->embed(Swift_Image::fromPath($mailData['GRAPHS_'.$varName][$aKey]['PATH']));
            _log('> Embedded graph image ('.$type.') '.$mailData['GRAPHS_'.$varName][$aKey]['PATH']);
        }
    }

    embedGraphs($graphFiles,'I','ITEM');
    embedGraphs($triggerGraphs,'T','TRIGGER');
    embedGraphs($hostGraphs,'H','HOST');

    // --- Render the content

    $bodyHTML = $twig->render('html', $mailData);
    $bodyPlain = $twig->render('plain', $mailData);
    $mailSubject = $twig->render('subject', $mailData);

    // Prepare message

    $message->setSubject($mailSubject)
            ->setFrom($mailFrom)
            ->setTo($p_recipient)
            ->setBody($bodyHTML, 'text/html')
            ->addPart($bodyPlain, 'text/plain');

    if (($cDebugMail) || (isset($problemData['debug'])))
    {
        _log('# Attaching logs to mail message');

        $attachLogHTML = new Swift_Attachment($mailData['LOG_HTML'], 'log.html', 'text/html');
        $message->attach($attachLogHTML);

        $attachLogPlain = new Swift_Attachment($mailData['LOG_PLAIN'], 'log.txt', '/text/plain');
        $message->attach($attachLogPlain);
    }

    // Send message

    $result = $mailer->send($message);

    // Return Event TAG information for Zabbix

    $response = array('messageId.mailGraph'=>$messageId);
    echo json_encode($response).$cCRLF;

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Wrap up //////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    // Store log?

    if (($cDebug) || (isset($problemData['debug'])))
    {
        unset($mailData['LOG_HTML']);
        unset($mailData['LOG_PLAIN']);

        $content = implode(chr(10),$logging).$cCRLF.$cCRLF.'=== VALUES AVAILABLE FOR TWIG TEMPLATE ==='.$cCRLF.$cCRLF.json_encode($mailData,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);
        $content = str_replace(chr(13),'',$content);

        $logName = 'log.'.$p_eventId.'.'.date('YmdHis').'.dump';

        file_put_contents($z_log_path.$logName,$content);
	_log('= Log stored to '.$z_log_path.$logName);
    }
?>
