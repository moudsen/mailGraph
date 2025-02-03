// mailGraph v2.20

// Function to test string
function isJSON(str) {
	try {
		JSON.stringify(JSON.parse(str));
		return true;
	} catch (e) {
		return false;
	}
}

try {
    // Pickup parameters
    params = JSON.parse(value),
        req = new HttpRequest(),
        fields = {},
        resp = '',
        result = { tags: {} };

    // Set HTTP proxy if required
    if (typeof params.HTTPProxy === 'string' && params.HTTPProxy.trim() !== '') {
        req.setProxy(params.HTTPProxy);
        fields.HTTPProxy = params.HTTPProxy;
    }

    // Declare output type
    req.addHeader('Content-Type: application/json');

    // Pick up fields relevant for mailGraph API level call while parsing/casting fields that should be integer
    fields.itemId = params.itemId * 1;
    fields.eventId = params.eventId * 1;
    fields.recipient = params.recipient;
    fields.baseURL = params.baseURL;
    fields.duration = params.duration * 1;

    if (fields.recipient.charAt(0) == '{') {
      throw '[MailGraph Webhook] Please define recipient for the test message!';
    }

    // Optional fields
    if (typeof params.graphWidth === 'string') { fields.graphWidth = params.graphWidth; }
    if (typeof params.graphHeight === 'string') { fields.graphHeight = params.graphHeight; }
    if (typeof params.subject === 'string') { fields.subject = params.subject; }
    if (typeof params.showLegend === 'string') { fields.showLegend = params.showLegend; }
    if (typeof params.periods === 'string') { fields.periods = params.periods; }
    if (typeof params.periods_headers === 'string') { fields.periods_headers = params.periods_headers; }
    if (typeof params.debug === 'string') { fields.debug = params.debug; }

    // Add generic fields
    Object.keys(params).forEach(function(key) {
        if (key.substring(0, 4) == 'info') {
            fields[key] = params[key];
        }
    });

    // Post information to the processing script
    Zabbix.Log(4, '[MailGraph Webhook] Sending request: ' + params.URL + '?' + JSON.stringify(fields));
    var resp = req.post(params.URL,JSON.stringify(fields));
    Zabbix.Log(4, '[Mailgraph Webhook] Received response:' + resp);

    // The response can be
    // - did not receive status 200 as result (contains HTTP server response)
    // - null (no response received at all)
    // - empty string (likely no e-mail sent due to recipient issue)
    // - not json (debugging message for troubleshooting or configuration hints)
    // - json (contains the mail message ID sent)

    if (req.getStatus() != 200) {
       throw '[MailGraph Webhook] Processing of mailGraph.php failed: ' + resp;
    }
    if (resp==null) {
      throw '[MailGraph Webhook] No response received from mailGraph.php? This should not occur (check URL and your webserver!)';
    }

    if (resp=='') {
      throw '[MailGraph Webhook] No data received from mailGraph - please check recipient address or mailGraph log and retry.';
    }

    // Check if JSON was returned
    if (!isJSON(resp)) {
      throw '[MailGraph Webhook] An error has occurred during processing: ' + resp;
    }

    // We expect the message id back from the processing script response in JSON format
    msg = JSON.parse(resp);

    result.tags.__message_id = msg.messageId;
    Zabbix.Log(4, '[MailGraph Webhook] Message sent with identification "' + msg.messageId + '"');

    // Pass the result back to Zabbix
    return JSON.stringify(result);
}
catch (error)
{
    // In case something else went wrong in the processing, pass the error back to Zabbix
    Zabbix.Log(127, 'MailGraph notification failed: '+error);
    throw 'MailGraph notification failed : '+error;
}
