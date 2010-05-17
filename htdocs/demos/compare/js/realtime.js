if (!window.console) {
	window.console = {log: jQuery.noop, error: jQuery.noop};
}
if (!window.WebSocket) {
	window.WebSocket = undefined;
}

var _rt;

var realtimeComm = function(handlerUrl) {
	_rt = this;

	_rt.socketConn = undefined;
	_rt.urlSocket  = 'ws://' + handlerUrl;
	_rt.urlHttp    = parent.location.protocol + '//' + handlerUrl;
	_rt.cursor     = get_cookie('waterspout_cursor') || '';
	_rt.cookie     = get_cookie('waterspout_cookie') || '';
	_rt.callbacks  = {};

	if (typeof window.WebSocket != 'undefined') {
		_rt.__listenWebSocket('/core/handshake');
	}

	// Cleanup open websocket connections when the document is unloaded.
	jQuery(window).unload(function() {
		_rt.cleanup();
	});
};

realtimeComm.prototype.addListener = function(uri, callback) {
	_rt.__setCallback(uri, callback);

	// Determine if the broswer supports websockets.
	if (typeof window.WebSocket != 'undefined') {
		// Tell the server the listen at this URI.
		_rt.send(uri, {});
	} else {
		// Fail back to long polling.
		_rt.__listenPolling(uri);
	}
};

realtimeComm.prototype.send = function(uri, data, callback) {
	_rt.__setCallback(uri, callback);

	// Inject the URI into the submission.
	data.__URI__ = uri;

	if (typeof _rt.socketConn != 'undefined') {
		_rt.socketConn.send(jQuery.toJSON(data));
	} else {
		jQuery.ajax({
			'url': _rt.urlHttp + uri,
			'type': 'POST',
			'dataType': 'jsonp',
			'jsonpCallback': '_rt.JSONPsend',
			'data': data,
			'error': _rt.__handleError
		});
	}
};

realtimeComm.prototype.JSONPsend = function(response) {
	_rt.__onmessage(response, false);
};

realtimeComm.prototype.JSONPlisten = function(response) {
	_rt.__onmessage(response, true);
};

realtimeComm.prototype.__setCallback = function(uri, callback) {
	if (typeof callback === 'function') {
		_rt.callbacks[uri] = callback;
	}
};

realtimeComm.prototype.__handleError = function(xhr, status, error) {
	console.log('textStatus:', status, 'errorThrown', error);
};

realtimeComm.prototype.__listenPolling = function(uri) {
	jQuery.ajax({
		'url': _rt.urlHttp + uri,
		'type': 'POST',
		'dataType': 'jsonp',
		'jsonpCallback': '_rt.JSONPlisten',
		'data': {'waterspout_cursor': _rt.cursor, 'waterspout_cookie': _rt.cookie},
		'error': _rt.__handleError
	});
};

realtimeComm.prototype.__listenWebSocket = function(uri) {
	if (typeof window.WebSocket != 'undefined') {
		_rt.socketConn = new WebSocket(_rt.urlSocket + uri);
		_rt.socketConn.onmessage = function(response) {_rt.__onmessage(response, true);};
		_rt.socketConn.onclose = _rt.__listenWebSocket;
	}
};

realtimeComm.prototype.__onmessage = function(response, pollingRequest) {
	if (response) {
		var data;
		if (typeof _rt.socketConn != 'undefined' && response.data) {
			data = jQuery.evalJSON(response.data);
		} else if (response.responseText) {
			data = jQuery.evalJSON(response.responseText);
		} else {
			data = response;
		}

		if (data) {
			if (data.cursor) {
				_rt.cursor = data.cursor;
				set_cookie('waterspout_cursor', _rt.cursor, 0, '/', window.location.host.substring(window.location.host.indexOf('.'), window.location.host.length), false);
			}

			var callbackFunc = _rt.callbacks[data.__URI__];
			if (typeof callbackFunc != 'undefined') {
				callbackFunc(data);

				if (pollingRequest === true && typeof _rt.socketConn == 'undefined') {
					_rt.__listenPolling(data.__URI__);
				}
			}
		}
	}
};

realtimeComm.prototype.cleanup = function() {
	if (typeof _rt.socketConn != 'undefined') {
		// Don't try to reopen the socket connection when cleaning up.
		_rt.socketConn.onclose = function () {};
		_rt.socketConn.close();
	}
};

function get_cookie(check_name) {
	// First we'll split this cookie up into name/value pairs.
	// Note: document.cookie only returns name=value, not the other components.
	var a_all_cookies = document.cookie.split(';');
	var a_temp_cookie = '';
	var cookie_name   = '';
	var cookie_value  = null;

	for (var i = 0, size = a_all_cookies.length; i < size; i++) {
		// Now we'll split apart each name=value pair.
		a_temp_cookie = a_all_cookies[i].split('=');

		// And trim left/right whitespace while we're at it.
		cookie_name = a_temp_cookie[0].replace(/^\s+|\s+$/g, '');

		// If the extracted name matches passed check_name.
		if (cookie_name == check_name) {
			// We need to handle case where cookie has no value but exists (no = sign, that is):
			if (a_temp_cookie.length > 1) {
				cookie_value = decodeURIComponent(a_temp_cookie[1].replace(/^\s+|\s+$/g, ''));
			}

			break;
		}
	}

	return cookie_value;
}

function set_cookie(name, value, expires, path, domain, secure) {
	// Set time, it's in milliseconds.
	var today = new Date();
	today.setTime(today.getTime());

	// If the expires variable is set, make the correct
	// expires time, the current script below will set
	// it for x number of days, to make it for hours,
	// delete * 24, for minutes, delete * 60 * 24.
	if (expires) {
		expires = expires * 1000 * 60 * 60 * 24;
	}
	var expires_date = new Date(today.getTime() + (expires));

	document.cookie = name + '=' + encodeURIComponent(value) +
		((expires) ? ';expires=' + expires_date.toGMTString() : '') +
		((path) ? ';path=' + path : '') +
		((domain) ? ';domain=' + domain : '') +
		((secure) ? ';secure' : '');
}
