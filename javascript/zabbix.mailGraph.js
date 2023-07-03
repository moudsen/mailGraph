// mailGraph v2.13
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

    // Must have fields
    fields.itemId = params.itemId * 1;
    fields.eventId = params.eventId * 1;
    fields.recipient = params.recipient;
    fields.baseURL = params.baseURL;
    fields.duration = params.duration * 1;

    if (isNaN(fields.eventId)) {
      throw '[MailGraph Webhook] Invalid event ID? Integer required (use actual event ID from Zabbix!)';
    }

    if (isNaN(fields.duration)) {
      throw '[MailGraph Webhook] Invalid duration? Integer required (set to zero if unknown)!';
    }

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

    // If blank the email address is likely incorrect
    if (resp==null) {
      throw '[MailGraph Webhook] No data received from mailGraph! Likely the email processing failed? Check mailGraph logging';
    }

    // If there was an error, report it and stop
    if (req.getStatus() != 200) { throw JSON.parse(resp).errors[0]; }

    // If no datas returned, report it and stop
    if (resp.charAt(0) == "!") {
      throw '[MailGraph Webhook] No data received from mailGraph! Likely the event ID provided does not exist? Check mailGraph logging';
    }

    // We expect the message id back from the processing script
    msg = JSON.parse(resp);

    result.tags.__message_id = msg.messageId;
    Zabbix.Log(4, '[MailGraph Webhook] Message sent with identification "' + msg.messageId + '"');

    // Pass the result back to Zabbix
    return JSON.stringify(result);
}
catch (error)
{
    // In case something went wrong in the processing, pass the error back to Zabbix
    Zabbix.Log(127, 'MailGraph notification failed: '+error);
    throw 'MailGraph notification failed : '+error;
}
