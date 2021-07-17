<?php
    $cCRLF = chr(13).chr(10);

    if ($argc<3)
    {
        echo 'Usage: config.php <config file> <command>'.$cCRLF;
        echo 'CREATE                         create a new config file'.$cCRLF;
        echo "ADD '<key>' '<value>'          add a new keypair to the config file".$cCRLF;
        echo "REPLACE '<key>' '<newvalue>'   change value for a specific key".$cCRLF;
        echo "REMOVE '<key>'                 remove the specific key".$cCRLF;
        echo "LIST                           list configured keys/values".$cCRLF;
        die;
    }

    function readConfig()
    {
        global $argv;
        global $cCRLF;
        global $data;

        if (!file_exists($argv[1]))
        {
            echo 'Config file not found. Create a new one with CREATE command or specify correct path?'.$cCRLF;
            die;
        }

        $content = file_get_contents($argv[1]);
        $data = json_decode($content,TRUE);

        if (($data==NULL) && (sizeof($content)>2))
        {
            echo 'Invalid JSON format in config file?!'.$cCRLF;
            die;
        }
    }

    function writeConfig()
    {
        global $argv;
        global $data;

        $content = json_encode($data,JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);
        file_put_contents($argv[1],$content);
    }

    // config.php 1=file 2=command 3=key 4=value

    switch(strtolower($argv[2]))
    {
        //////////////////////////////////////////////////////////////////////////////////////////////////////
        case 'create':
            if (file_exists($argv[1]))
            {
                echo 'Config file already exists.'.$cCRLF;
                die;
            }

            $contents = '{}';
            file_put_contents($argv[1],$contents);
            echo 'New config file created'.$cCRLF;
            break;

        //////////////////////////////////////////////////////////////////////////////////////////////////////
        case 'add':
            readConfig();

            if (isset($data[$argv[3]]))
            {
                echo 'Key already exists. Use REPLACE to modify value or REMOVE key first.'.$cCRLF;
                die;
            }

            if (!isset($argv[4]))
            {
                echo 'No value specified?'.$cCRLF;
                die;
            }

            $data[$argv[3]] = $argv[4];

            writeConfig();
            break;

        //////////////////////////////////////////////////////////////////////////////////////////////////////
        case 'replace':
            readConfig();

            if (!isset($data[$argv[3]]))
            {
                echo 'Key does not exist. Use ADD to add a new key and value.'.$cCRLF;
                die;
            }

            if (!isset($argv[4]))
            {
                echo 'No value specified?'.$cCRLF;
                die;
            }

            $data[$argv[3]] = $argv[4];

            writeConfig();
            break;

        //////////////////////////////////////////////////////////////////////////////////////////////////////
        case 'remove':
            readConfig();

            if (!isset($data[$argv[3]]))
            {
                echo 'Key does not exist.'.$cCRLF;
                die;
            }

            if (isset($argv[4]))
            {
                echo 'No value expected?'.$cCRLF;
                die;
            }

            unset($data[$argv[3]]);

            writeConfig();
            break;

        //////////////////////////////////////////////////////////////////////////////////////////////////////
        case 'list':
            readConfig();

            foreach($data as $aKey=>$aValue)
            {
                echo '  "'.$aKey.'" => "'.$aValue.'"'.$cCRLF;
            }

            break;

        //////////////////////////////////////////////////////////////////////////////////////////////////////
        default:
            echo 'Unknown command?'.$cCRLF;
    }
?>
