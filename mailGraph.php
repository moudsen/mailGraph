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
    // Release 1 tested with Zabbix 5.4 and 6.0 LTS (history available on GitHub)
    // ------------------------------------------------------------------------------------------------------
    // Release 2 tested with Zabbix 5.4, 6.0 LTS and 6.4 - tested with latest Composer libraries
    // ------------------------------------------------------------------------------------------------------
    // 2.00 2021/12/16 - Mark Oudsen - itemId not always provisioned by Zabbix
    //                                 Several fixes on warning - several small bug fixes
    // 2.01 2021/12/16 - Mark Oudsen - Screens are no longer available - reverting to using Dashboards now
    // 2.02 2022/01/30 - Mark Oudsen - Added cleanup routine for old logs and images
    // 2.10 2023/06/30 - Mark Oudsen - Refactored deprecated code - now compatible with Zabbix 6.0 LTS, 6.4
    // 2.11 2023/07/01 - Mark Oudsen - Refactored Zabbix javascript - now capturing obvious errors
    //                                 Added ability to locate latest problems for testing purposes
    // 2.12 2023/07/02 - Mark Oudsen - Replaced SwiftMailer with PHPMailer (based on AutoTLS)
    // 2.13 2023/07/03 - Mark Oudsen - Bugfixes speciifally on links into Zabbix (missing context or info)
    // 2.14 2023/07/10 - Mark Oudsen - Adding ability to set 'From'  and 'ReplyTo' addresses in configuration
    //                                 Adding ACK_URL for utilization in the template to point to Ack page
    //                                 Small refactor on itemId variable processing (no longer mandatory)
    //                                 Additional logic added to random eventId to explain in case of issues
    //                                 Fixed missing flag for fetching web url related items
    // 2.15 2023/08/16 - Mark Oudsen - Bugfix for Zabbix 5.4 where empty or zeroed variables are no longer
    //                                 passed by Zabbix (hence resulting in weird errors)
    // 2.16 2023/08/16 - Mark Oudsen - Adding ability to use ACKNOWLEDGE messages in the mail message
    // 2.17 2024/12/30 - Mark Oudsen - Fixed #47 mailData initializaton (wrong location) - BernardLinz
    //                                 Fixed #46 invalid GraphId on trigger tag - BernardLinz
    //                                 Fixed #44 config.json.template wrong var name - WMP
    //                                 Fixed #45 handling of international characters - Dima-online
    //                                 Tested with latest PHPMailer (6.9.3) and TWIG (3.11.3), PHP 7.4
    //                                 Tested with PHP 8.3, TWIG (3.18.0)
    // 2.18 2025/01/10 - Mark Oudsen - SCREEN tag information is only processed for Zabbix versions <= 5
    //      2025/01/14 - Mark Oudsen - Fixed #51 SMTPS (implicit) or STARTTLS (explicit)
    // 2.20 2025/01/25 - Mark Oudsen - Fixed #49 to support Zabbix API bearer token (Zabbix 7.x+)
    //                                 Added detection of php curl module (must have)
    //                                 Fixed bug on array size determination
    //                                 CURLOPT_BINARYTRANSFER deprecated (removed)
    // ------------------------------------------------------------------------------------------------------
    // Release 3 placeholder for Zabbix 7.0 LTS and 7.2+
    // ------------------------------------------------------------------------------------------------------
    // 2.20                            Tested in Zabbix 7.0.7 LTS and Zabbix 7.2.2
    // 2.21 2025/02/20 - Mark Oudsen - Added #57 enhancement for manipulation of data value truncing
    // 2.22 2025/03/19 - Mark Oudsen - Fixed #60 incorrect JSON request (boolean as text instead of bool)
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
    // Notes
    // -----
    // 1) mailGraph is following the environmental requirements from Zabbix, supporting PHP 7 and 8 as per
    //    - https://www.zabbix.com/documentation/6.0/en/manual/installation/requirements
    //    - https://www.zabbix.com/documentation/6.4/en/manual/installation/requirements
    //    - https://www.zabbix.com/documentation/7.0/en/manual/installation/requirements
    //    - https://www.zabbix.com/documentation/7.2/en/manual/installation/requirements
    //
    // 2) TWIG 3.18.0 is available on PHP 8 only
    //    - Seemless in-place upgrade when using composer update after upgrading  PHP 7.x to PHP 8.x
    //
    // 3) Full testing of composer libraries updates/versions is limited to every 6 to 12 months
    //    - In case you encounter an issue, please raise an issue on GitHub
    //      https://github.com/moudsen/mailGraph/issues
    //    - Refer to the wiki for exact versions tested
    //
    // 4) PHP related notes
    //    - PHP 5.4 - Limited to mailGraph v1.xx only - unsupported (end of life) - Tested - No more updates
    //    - PHP 7.x - Limited to mailGraph v2.xx only - unsupported (end of life) - Tested - Freezing
    //    - PHP 8.x - Supported - Tested
    //
    // 5) Zabbix related
    //    - As from Zabbix 7.2 onwards the only authentication method accepted is API bearer token
    //
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    //
    // Roadmap
    // -------
    // - Automatic setup and configuration of mailGraph
    // - Automatic code updates (CLI triggered)
    // - Add DASHBOARD processing facility (SCREEN was abandoned after Zabbix 5.4) [idea only]
    // - Extract Graph API functionality to seperate code unit/object
    //
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    //
    // MAIN SEQUENCE
    // -------------
    // 1) Fetch trigger, item, host, graph, event information via Zabbix API via CURL
    // 2) Fetch Graph(s) associated to the item/trigger (if any) via Zabbix URL via CURL
    // 3) Build and send mail message from template using PHPmailer//TWIG
    //
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    // CONSTANTS
    $cVersion = 'v2.22';
    $cCRLF = chr(10).chr(13);
    $maskDateTime = 'Y-m-d H:i:s';
    $maxGraphs = 8;

    // DEBUG SETTINGS
    // -- Should be FALSE for production level use

    $cDebug = TRUE;           // Extended debug logging mode, switch to FALSE for production environment
    $cDebugMail = FALSE;      // If TRUE, includes log in the mail message (html and plain text attachments)
    $showLog = FALSE;         // Display the log - !!! only use in combination with CLI mode !!!

    // Limit server level output errors

    error_reporting(E_ERROR | E_PARSE);

    // INCLUDE REQUIRED LIBRARIES (Composer)
    // (configure at same location as the script is running or load in your own central library)
    // -- phpmailer/phpmailer           https://github.com/PHPMailer/PHPMailer
    // -- twig/twig                     https://twig.symfony.com/doc/3.x/templates.html

    // Change only required if you decide to use a local/central library, otherwise leave as is
    include(getcwd().'/vendor/autoload.php');

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Fetch the response of the given URL
    // --- Redirects will be honored
    // --- Enforces use of IPv4
    // --- Caller must verify if the return string is JSON or ERROR

    function postJSON($url,$data)
    {
        global $cCRLF;
        global $cVersion;
        global $cDebug;
        global $HTTPProxy;
        global $z_api_token;

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

        // Set headers
        $headers = ['Content-Type:application/json'];

        // Bypass token authentication method if we are using API token method
        if ($z_api_token != '') {
            if ($data['method']!='apiinfo.version') {
                $headers[] = 'Authorization: Bearer '.$z_api_token;

                if ($cDebug) { _log('> Adding API bearer token'); }
            }

            if (isset($data['auth'])) {
                unset($data['auth']);

                if ($cDebug) { _log('> Cleared AUTH information'); }
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set POST
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Set URL and output-to-variable option
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        // Set Agent name
        curl_setopt($ch, CURLOPT_USERAGENT, 'Zabbix-mailGraph - '.$cVersion);

        // Execute Curl
        $data = curl_exec($ch);

        // Check if we have valid data
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
        global $z_api_token;
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $z_login_data  = array('name' => $z_user, 'password' => $z_pass, 'enter' => "Sign in");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $z_login_data);

        curl_setopt($ch, CURLOPT_COOKIEJAR, $filename_cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $filename_cookie);

        // Login to Zabbix
        $login = curl_exec($ch);

        if ($login!='')
        {
            //TODO: Pick up the specific error from CURL?
            echo 'Error logging in to Zabbix!'.$cCRLF;
            return('');
        }

        // Get the graph
        curl_setopt($ch, CURLOPT_URL, $z_url_fetch);
        $output = curl_exec($ch);

        curl_close($ch);

        // Delete cookie (if exists)
        if (file_exists($filename_cookie))
        {
            unlink($filename_cookie);
            _log('- Removed cookie '.$filename_cookie);
        }

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
    // Check the array if it contains information that should not be logged

    function maskKey(&$theValue, $theKey)
    {
        switch($theKey)
        {
            case 'zabbix_user':
            case 'zabbix_user_pwd':
            case 'zabbix_api_user':
            case 'zabbix_api_pwd':
            case 'zabbix_api_token':
            case 'username':
            case 'password':
                $theValue = '<masked>';
                break;
            }
        }

    function maskOutputContent($info)
    {
        array_walk_recursive($info,'maskKey');
        return($info);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Cleanup 'path': remove files with 'ext' older than 'daysOld'

    function cleanupDir($path, $ext, $daysOld)
    {
        _log('- Scanning "'.$path.'"');

        $files = glob($path.'/*'.$ext);
        $now = time();

        $filesRemoved = 0;
        $filesKept = 0;

        foreach ($files as $file)
        {
            if (is_file($file))
            {
                if ($now - filemtime($file) >= (60 * 60 * 24 * $daysOld))
                {
                    _log('> Removing "'.$file.'"');
                    unlink($file);
                    $filesRemoved++;
                }
                else
                {
                    $filesKept++;
                }
            }
        }

        _log(': Done. Cleaned up '.$filesRemoved.' file(s), kept '.$filesKept.' file(s)');
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Zabbix translator functions

    function zabbixTStoString($linuxTime)
    {
        return date("Y-m-d H:i:s", $linuxTime);
    }

    function zabbixActionToString($actionMask)
    {
        $values = [];

        if ($actionMask & 1) { $values[] = "Close problem"; }
        if ($actionMask & 2) { $values[] = "Acknowledge event"; }
        if ($actionMask & 4) { $values[] = "Add message"; }
        if ($actionMask & 8) { $values[] = "Change severity"; }
        if ($actionMask & 16) { $values[] = "Unacknowledge event"; }
        if ($actionMask & 32) { $values[] = "Suppress event"; }
        if ($actionMask & 64) { $values[] = "Unsuppress event"; }
        if ($actionMask & 128) { $values[] = "Change event rank to cause"; }
        if ($actionMask & 256) { $values[] = "Change event rank to sympton"; }

        return implode(", ", $values);
    }

    function zabbixSeverityToString($severity)
    {
        switch ($severity) {
            case 0: return('Not classified');
            case 1: return('Information');
            case 2: return('Warning');
            case 3: return('Average');
            case 4: return('High');
            case 5: return('Disaster');
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Initialize ///////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    // --- CHECK CURL
    if (!extension_loaded('curl')) {
        _log('! mailGraph requires the php-curl module to function properly. Please install this module and retry.');
        die;
    }

    // --- CONFIG DATA ---

    // [CONFIGURE] Change only when you want to place your config file somewhere else ...
    $config = readConfig(getcwd().'/config/config.json');

    _log('# Configuration taken from config.json'.$cCRLF.
         json_encode(maskOutputContent($config),JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    // --- MAIL DATA ---

    $mailData = array();

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

            // Assumes that config.json file has the correct information for MANDATORY information

            // DEFAULTS
            $problemData['eventId'] = 0;
            $problemData['duration'] = 0;

            // MANDATORY
            $problemData['recipient'] = $config['cli_recipient'];
            $problemData['baseURL'] = $config['cli_baseURL'];

            // OPTIONAL
            if (isset($config['cli_eventId'])) { $problemData['eventId'] = $config['cli_eventId']; }
            if (isset($config['cli_duration'])) { $problemData['duration'] = $config['cli_duration']; }
            if (isset($config['cli_subject'])) { $problemData['subject'] = $config['cli_subject']; }
            if (isset($config['cli_period'])) { $problemData['period'] = $config['cli_period']; }
            if (isset($config['cli_period_header'])) { $problemData['period_header'] = $config['cli_period_header']; }
            if (isset($config['cli_periods'])) { $problemData['periods'] = $config['cli_periods']; }
            if (isset($config['cli_periods_headers'])) { $problemData['periods_headers'] = $config['cli_periods_headers']; }
            if (isset($config['cli_debug'])) { $problemData['debug'] = $config['cli_debug']; }
            if (isset($config['cli_proxy'])) { $problemData['HTTPProxy'] = $config['cli_proxy']; }

            // BACKWARDS COMPATIBILITY - obsolete from Zabbix 6.2 onwards
            $problemData['itemId'] = 0;
            if (isset($config['cli_itemId'])) { $problemData['itemId'] = $config['cli_itemId']; }
        }

        if (($argc>1) && ($argv[1]=='cleanup'))
        {
            // Switch on CLI log display
            $showLog = TRUE;

            // Check for configuration of retention period for images and logs
            _log('<<< mailGraph '.$cVersion.' >>>');
            _log('# Invoked from CLI');

            // Set defaults
            $retImages = 30;
            $retLogs = 14;

            // Check if configured settings
            if (isset($config['retention_images'])) { $retImages = intval($config['retention_images']); }
            if (isset($config['retention_logs'])) { $retLogs = intval($config['retention_logs']); }

            _log('Cleaning up IMAGES ('.$retImages.' days) and LOGS ('.$retLogs.' days)');

            cleanupDir(getcwd().'/images', '.png', $retImages);
            cleanupDir(getcwd().'/log', '.dump', $retLogs);

            exit(0);
        }
    }

    _log('# Data passed to MailGraph main routine and used for processing'.
         $cCRLF.json_encode($problemData,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

    // --- CHECK AND SET P_ VARIABLES ---

    // FROM POST OR CLI DATA

    if (!isset($problemData['eventId'])) { echo "Missing EVENT ID?\n"; die; }
    $p_eventId = intval($problemData['eventId']);

    if (!isset($problemData['recipient'])) { echo "Missing RECIPIENT?\n"; die; }
    $p_recipient = $problemData['recipient'];

    $p_duration = 0;
    if (isset($problemData['duration'])) { $p_duration = intval($problemData['duration']); }

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

    $p_smtp_strict = 'yes';
    if ((isset($config['smtp_strict'])) && ($config['smtp_strict']=='no')) { $p_smtp_strict = 'no'; }

    $p_smtp_security = 'none';
    if ((isset($config['smtp_security'])) && ($config['smtp_security']=='smtps')) { $p_smtp_security = 'smtps'; }
    if ((isset($config['smtp_security'])) && ($config['smtp_security']=='starttls')) { $p_smtp_security = 'starttls'; }

    $p_smtp_username = '';
    if (isset($config['smtp_username'])) { $p_smtp_username = $config['smtp_username']; }

    $p_smtp_password = '';
    if (isset($config['smtp_password'])) { $p_smtp_password = $config['smtp_password']; }

    $p_smtp_from_address = '';
    if (isset($config['smtp_from_address'])) { $p_smtp_from_address = $config['smtp_from_address']; }

    $p_smtp_from_name = 'mailGraph';
    if (isset($config['smtp_from_name'])) { $p_smtp_from_name = $config['smtp_from_name']; }

    $p_smtp_reply_address = '';
    if (isset($config['smtp_reply_address'])) { $p_smtp_reply_address = $config['smtp_reply_address']; }

    $p_smtp_reply_name = 'mailGraph feedback';
    if (isset($config['smtp_reply_name'])) { $p_smtp_reply_name = $config['smtp_reply_name']; }

    // >>> Backwards compatibility but smtp_from_address is leading (<v2.14)
    $mailFrom = '';
    if (isset($config['mail_from'])) { $mailFrom = $config['mail_from']; }

    if (($p_smtp_from_address=='') && ($mailFrom!=''))
    {
        $p_smtp_from_address = $mailFrom;
    }

    $p_graph_match = 'any';
    if ((isset($config['graph_match'])) && ($config['graph_match']=='exact')) { $p_graph_match = 'exact'; }

    $p_item_value_truncate = 0;
    if (isset($config['item_value_truncate'])) { $p_item_value_truncate = intval($config['item_value_truncate']); }

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

    // If tmp, log, or images does not exist, create them
    if (!is_dir($z_tmp_cookies))
    {
        mkdir($z_tmp_cookies);
        _log('+ created TMP directory "'.$z_tmp_cookies.'"');
    }

    if (!is_dir($z_log_path))
    {
        mkdir($z_log_path);
        _log('+ created LOG directory "'.$z_log_path.'"');
    }

    if (!is_dir($z_images_path))
    {
        mkdir($z_images_path);
        _log('+ created IMAGES directory "'.$z_images_path.'"');
    }

    // Zabbix token - if a token is defined this will be the selected login method automatically (username/password neglected)
    $z_api_token = '';

    if (isset($config['zabbix_api_token'])) {
      $z_api_token = $config['zabbix_api_token'];
    }

    // Zabbix user (requires Super Admin access rights to access image generator script)
    $z_user = $config['zabbix_user'];
    $z_pass = $config['zabbix_user_pwd'];

    // Zabbix API user (requires Super Admin access rights)
    // --- Copy from Zabbix user and override when defined in configuration
    // TODO: Check if information retreival can be done with less rigths
    $z_api_user = $z_user;
    $z_api_pass = $z_pass;

    if (isset($config['zabbix_api_user']))
    {
        $z_api_user = $config['zabbix_api_user'];
    }

    if (isset($config['zabbix_api_pwd']))
    {
        $z_api_pass = $config['zabbix_api_pwd'];
    }

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

    $mailData['BASE_URL'] = $p_URL;
    $mailData['SUBJECT'] = $p_subject;

    // -------------
    // --- LOGIN ---
    // -------------

    // We only use the USER.LOGIN method if not using the API bearer token method
    if ($z_api_token=='') {
        _log('# LOGIN to Zabbix');

        $request = array('jsonrpc'=>'2.0',
                         'method'=>'user.login',
                         'params'=>array('username'=>$z_api_user,
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
    } else {
        if ($cDebug) {
            _log('# Using API bearer token authentication to access Zabbix');
        }

        $token = '';
    }

    // -----------------------
    // --- LOG API VERSION ---
    // -----------------------

    _log('# Record Zabbix API version');

    $request = array('jsonrpc'=>'2.0',
                     'method'=>'apiinfo.version',
                     'params'=>[],
                     'id'=>nextRequestID());

    $result = postJSON($z_url_api, $request);

    $apiVersion = $result['result'];
    $apiVersionMajor = substr($apiVersion,0,01);

    _log('> API version '.$apiVersion);

    // -----------------------------------
    // --- IF NO EVENT ID FETCH LATEST ---
    // -----------------------------------

    if ($p_eventId=="0")
    {
        _log('# No event ID given, picking up random event from Zabbix');

        $request = array('jsonrpc'=>'2.0',
                         'method'=>'problem.get',
                         'params'=>array('output'=>'extend',
                                         'recent'=>TRUE,
                                         'limit'=>1),
                         'auth'=>$token,
                         'id'=>nextRequestID());

        if ($z_api_token=='') {
            $request['auth'] = $token;
        }

        $thisProblems = postJSON($z_url_api, $request);
        _log('> Problem data (recent)'.$cCRLF.json_encode($thisProblems,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

        if (!isset($thisProblems['result'][0]))
        {
            _log('- No response data received. Retrying with less recent problems ... ');

            $request = array('jsonrpc'=>'2.0',
                             'method'=>'problem.get',
                             'params'=>array('output'=>'extend',
                                             'recent'=>FALSE,
                                             'limit'=>1),
                             'auth'=>$token,
                             'id'=>nextRequestID());

            $thisProblems = postJSON($z_url_api, $request);
            _log('> Problem data (not recent)'.$cCRLF.json_encode($thisProblems,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));

            if (!isset($thisProblems['result'][0]))
            {
                _log('! Cannot continue: mailGraph is unable to pick a random event via the Zabbix API. It is highly likely that no active problems exist? Please retry or determine and set an event ID manually and retry.');
                die;
            }
        }

        $p_eventId = $thisProblems['result'][0]['eventid'];
        _log('> Picked up random last event #'.$p_eventId);
    }

    // ------------------------------
    // --- READ EVENT INFORMATION ---
    // ------------------------------

    _log('# Retreiving EVENT information');

    if ($apiVersionMajor<"7") {
        $request = array('jsonrpc'=>'2.0',
                         'method'=>'event.get',
                         'params'=>array('eventids'=>$p_eventId,
                                         'output'=>'extend',
                                         'selectRelatedObject'=>'extend',
                                         'selectSuppressionData'=>'extend',
                                         'select_acknowledges'=>'extend'),
                         'auth'=>$token,
                         'id'=>nextRequestID());
    } else {
        $request = array('jsonrpc'=>'2.0',
                         'method'=>'event.get',
                         'params'=>array('eventids'=>$p_eventId,
                                         'output'=>'extend',
                                         'selectRelatedObject'=>'extend',
                                         'selectSuppressionData'=>'extend',
                                         'selectAcknowledges'=>'extend'),
                         'auth'=>$token,
                         'id'=>nextRequestID());
    }

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

    // --- Collect and attach acknowledge messages for this event
    if (count($thisEvent['result'][0]['acknowledges'])>0) {
        foreach($thisEvent['result'][0]['acknowledges'] as $aCount=>$anAck) {
            $mailData['ACKNOWLEDGES'][$aCount] = $anAck;
            $mailData['ACKNOWLEDGES'][$aCount]['_clock'] = zabbixTStoString($anAck['clock']);
            $mailData['ACKNOWLEDGES'][$aCount]['_actions'] = zabbixActionToString($anAck['action']);
            $mailData['ACKNOWLEDGES'][$aCount]['_old_severity'] = zabbixSeverityToString($anAck['old_severity']);
            $mailData['ACKNOWLEDGES'][$aCount]['_new_severity'] = zabbixSeverityToString($anAck['new_severity']);
        }
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
                                     'selectTags'=>'extend',
                                     'expandComment'=>1,
                                     'expandDescription'=>1,
                                     'expandExpression'=>1),
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
		if ($apiVersionMajor<="5") {
                    $triggerScreen = intval($aTag['value']);
                    _log('+ Trigger screen = '.$triggerScreen);
                } else {
                    _log('- Trigger screen value ignored');
                }
                break;

            case 'mailGraph.screenPeriod':
		if ($apiVersionMajor<="5") {
                    $triggerScreenPeriod = $aTag['value'];
                    _log('+ Trigger screen period = '.$triggerScreenPeriod);
                }
                break;

            case 'mailGraph.screenPeriodHeader':
                if ($apiVersionMajor<="5") {
                    $triggerScreenPeriodHeader = $aTag['value'];
                    _log('+ Trigger screen header = '.$triggerScreenPeriodHeader);
                }
                break;
            case 'mailGraph.valueTruncate':
                $p_item_value_truncate = intval($aTag['value']);
                _log('+ Data value truncing = '.$p_item_value_truncate);
                break;
        }
    }

    // If no specific itemId is requested take the first item found on the items list from the host
    if (!isset($p_itemId))
    {
        foreach($thisTrigger['result'][0]['functions'] as $aFunction)
        {
            $p_itemId = $aFunction['itemid'];
            _log('- Item ID taken from trigger (first) function = '.$p_itemId);
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
                         'webitems'=>'true',
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

    // Handling long data elements
    if ($p_item_value_truncate>0) {
        if (strlen($mailData['ITEM_LASTVALUE'])>$p_item_value_truncate) { $mailData['ITEM_LASTVALUE'] = substr($mailData['ITEM_LASTVALUE'],0,$p_item_value_truncate).' ...'; }
        if (strlen($mailData['ITEM_PREVIOUSVALUE'])>$p_item_value_truncate) { $mailData['ITEM_PREVIOUSVALUE'] = substr($mailData['ITEM_PREVIOUSVALUE'],0,$p_item_value_truncate).' ...'; }
    }

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
    if (isset($thisHost['result'][0]['error'])) { $mailData['HOST_ERROR'] = $thisHost['result'][0]['error']; }
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

        if (isset($screenGrahps['result'][0]['screenitems'])) {
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
        } else {
            _log('> No screen items associated to this screen?');
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

    $hostGraphs = array();

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

    // Strip off any excessive elements from the end (protection of graph generation overload on system)

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
            $theGraph = $forceGraphInfo['result'][0];
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
            $mailData['GRAPHS_I'][$aKey]['CID'] = 'images/'.$graphFile;
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
            $mailData['GRAPHS_'.$varName][$aKey]['CID'] = 'images/'.$graphFile;
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

    $mailData['LOG_HTML'] = '<html lang="en"><head><meta http-equiv=Content-Type content="text/html; charset=UTF-8"></head>'.$cCRLF.
                            '<style type="text/css">'.$cCRLF.
                            'body { font-family: courier, courier new, serif; font-size: 12px; }'.$cCRLF.
                            '</style>'.$cCRLF.
                            '<body>'.$cCRLF.
                            $mailData['LOG_HTML'].$cCRLF.
                            '</body>'.$cCRLF.
                            '</html>';

    // Prepare PLAIN LOG content

    $mailData['LOG_PLAIN'] = implode(chr(10),$logging);

    // Prepare others

    $mailData['TRIGGER_URL'] = $z_server.'triggers.php?form=update&triggerid='.$mailData['TRIGGER_ID'].'&context=host';
    $mailData['ITEM_URL'] = $z_server.'items.php?form=update&hostid='.$mailData['HOST_ID'].'&itemid='.$mailData['ITEM_ID'].'&context=host';
    $mailData['HOST_URL'] = $z_server.'hosts.php?form=update&hostid='.$mailData['HOST_ID'];
    $mailData['ACK_URL'] = $z_server.'zabbix.php?action=popup&popup_action=acknowledge.edit&eventids[]='.$mailData['EVENT_ID'];
    $mailData['EVENTDETAILS_URL'] = $z_server.'tr_events.php?triggerid='.$mailData['TRIGGER_ID'].'&eventid='.$mailData['EVENT_ID'];

    $mailData['EVENT_DURATION'] = $p_duration;
    $mailData['HOST_PROBLEMS_URL'] = $z_server.'zabbix.php?show=1&name=&inventory%5B0%5D%5Bfield%5D=type&inventory%5B0%5D%5Bvalue%5D=&evaltype=0&tags%5B0%5D%5Btag%5D=&tags%5B0%5D%5Boperator%5D=0&tags%5B0%5D%5Bvalue%5D=&show_tags=3&tag_name_format=0&tag_priority=&show_opdata=0&show_timeline=1&filter_name=&filter_show_counter=0&filter_custom_time=0&sort=clock&sortorder=DESC&age_state=0&show_suppressed=0&unacknowledged=0&compact_view=0&details=0&highlight_row=0&action=problem.view&hostids%5B%5D='.$mailData['HOST_ID'];

    // Handling long data elements
    if ($p_item_value_truncate>0) {
        if (strlen($mailData['ITEM_LASTVALUE'])>$p_item_value_truncate) { $mailData['ITEM_LASTVALUE'] = substr($mailData['ITEM_LASTVALUE'],0,$p_item_value_truncate).' ...'; }
        if (strlen($mailData['ITEM_PREVIOUSVALUE'])>$p_item_value_truncate) { $mailData['ITEM_PREVIOUSVALUE'] = substr($mailData['ITEM_PREVIOUSVALUE'],0,$p_item_value_truncate).' ...'; }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Compose & Send Message ///////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    _log('# Configuring Mailer');

    $mail = new PHPMailer(true);

    try
    {
        // If debugging is required change to '1'
        $mail->SMTPDebug = 0;

        // Initialize for international characters
        $mail->CharSet = "UTF-8";
        $mail->Encoding = "base64";

	// --- Inialize SMTP parameters
        $mail->isSMTP();
        $mail->Host = $p_smtp_server;
        $mail->Port = $p_smtp_port;

        // --- Initialize transport security
        switch($p_smtp_security) {
            case 'smtps':
                _log('> Using SMTPS transport encryption method');
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                break;
            case 'starttls':
                _log('> Using STARTTLS transport encryption method');
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                break;
            default:
                _log('> Plain transport (no encryption)');
        }

        // --- Disable strict certificate checking?
        if ($p_smtp_strict=='no')
        {
            _log('> No strict TLS checking');
            $mail->SMTPOptions = [
                'ssl' => [ 'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true ]
            ];
        }

        // --- Authentication required?
        if ($p_smtp_username!="")
        {
            $mail->SMTPAuth = true;
            $mail->Username = $p_smtp_username;
            $mail->Password = $p_smtp_password;
        }

        // --- Define from
        $mail->Sender = $p_smtp_from_address;
        $mail->SetFrom($p_smtp_from_address, $p_smtp_from_name, FALSE);

        // --- Define reply-to
        if ($p_smtp_reply_address!='')
        {
            $mail->clearReplyTos();
            $mail->addReplyTo($p_smtp_reply_address, $p_smtp_reply_name);
        }

        // --- Add recipient
        $mail->addAddress($p_recipient);

        // --- Prepare embedding of the graphs by attaching and generating "cid" (content-id) information
        function embedGraphs($graphs,$varName,$type)
        {
            global $mail;
            global $mailData;

            foreach($graphs as $aKey=>$anItem)
            {
                $mail->AddEmbeddedImage($mailData['GRAPHS_'.$varName][$aKey]['PATH'],
                                        $mailData['GRAPHS_'.$varName][$aKey]['CID']);

                // Add content-id marker to the identifier for ease of use in Twig
                $mailData['GRAPHS_'.$varName][$aKey]['CID'] = 'cid:'.$mailData['GRAPHS_'.$varName][$aKey]['CID'];

                _log('> Embedded graph image ('.$type.') '.$mailData['GRAPHS_'.$varName][$aKey]['URL']);
            }
        }

        embedGraphs($graphFiles,'I','ITEM');
        embedGraphs($triggerGraphs,'T','TRIGGER');
        embedGraphs($hostGraphs,'H','HOST');

        // --- Render the content with TWIG for HTML, Plain text and the Subject of the message
        $loader = new \Twig\Loader\ArrayLoader([
            'html' => file_get_contents($z_template_path.'html.template'),
            'plain' => file_get_contents($z_template_path.'plain.template'),
            'subject' => $mailData['SUBJECT'],
        ]);

        $twig = new \Twig\Environment($loader);

        $bodyHTML = $twig->render('html', $mailData);
        $bodyPlain = $twig->render('plain', $mailData);
        $mailSubject = $twig->render('subject', $mailData);

        // --- Attach debug log processing?
        if (($cDebugMail) || (isset($problemData['debug'])))
        {
            _log('# Attaching logs to mail message');

            $mail->addStringAttachment($mailData['LOG_HTML'],'log.html');
            $mail->addStringAttachment($mailData['LOG_PLAIN'],'log.txt');
        }

        // ---Fill body and subject and mark as HTML while also supplying plain text option alternative
        // Note: Not using PHPMailer option for automatic text/plain generation (by design)
        $mail->Body = $bodyHTML;
        $mail->isHTML(true);
        $mail->AltBody = $bodyPlain;
        $mail->Subject = $mailSubject;

        // --- Send the message
        if (!$mail->send())
        {
            _log("! Failed to send mail message");
            echo "! Failed to send mail message. Likely an issue with the recipient email address?".$cCRLF;
            echo "+ Mailer error: ".$mail->ErrorInfo.$cCRLF;
        }

        // --- Obtain message ID
        $messageId = $mail->getlastMessageID();
        _log('# Message ID = '.$messageId);

        // --- Return Event TAG information for Zabbix to store with Zabbix event
        $response = array('messageId'=>$messageId);
        echo json_encode($response).$cCRLF;
    } catch (phpmailerException $e)
    {
       echo "! Failed to send message".$cCRLF;
       echo "! phpMailer error message: ".$e->getMessage().$cCRLF;
       _log("! phpMailer failed: ".$e->getMessage());
    } catch (Exception $e)
    {
       echo "! Failed to send message".$cCRLF;
       echo "! Error message: ".$e->getMessage().$cCRLF;
       echo "! Check your mail server and/or transport settings!".$cCRLF;
       _log("! Failed: ".$e->getMessage());
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Wrap up //////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////

    // Store log?

    if (($cDebug) || (isset($problemData['debug'])))
    {
        // Prevent duplicate dump of log information
        unset($mailData['LOG_HTML']);
        unset($mailData['LOG_PLAIN']);

        // Attach the collected information
        $content = implode(chr(10),$logging).$cCRLF.$cCRLF.'=== VALUES AVAILABLE FOR TWIG TEMPLATE ==='.$cCRLF.$cCRLF.json_encode($mailData,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);
        $content = str_replace(chr(13),'',$content);

        // Save to unique log file
        $logName = 'log.'.$p_eventId.'.'.date('YmdHis').'.dump';
        file_put_contents($z_log_path.$logName,$content);
        _log('= Log stored to '.$z_log_path.$logName);
    }
?>
