if (!window.console) console = {log: function() {}, error: function() {}};

RealTimeMessage = function(handlerUrl, listener) {
	var self = this;

	self.socketConn = undefined;
	self.urlSocket  = 'ws://' + handlerUrl;
	self.urlPolling = parent.location.protocol + '//' + handlerUrl;
	self.cursor     = get_cookie('waterspout_cursor');
	self.listener   = listener;
	self.cookie     = get_cookie('waterspout_cookie');

	if (window.WebSocket) {
		try {
			self.__listenWebSocket();
		} catch (e) {
			if (self.listener) {
				self.__listenPolling();
			}
		}
	} else {
		if (self.listener) {
			self.__listenPolling();
		}
	}

	Event.observe(window, 'unload', self.cleanup);
};

RealTimeMessage.prototype.send = function(data) {
	if (typeof this.socketConn != 'undefined') {
		this.socketConn.send($H(data).toJSON());
	} else {
		new Ajax.Request(this.urlPolling, {
			method: 'post',
			requestHeaders: {'WaterSpout-Request': true},
			parameters: data,
			onSuccess: (this.__onmessage).bind(this),
			onFailure: this.handleFailure,
			onException: this.handleException
		});
	}
};

RealTimeMessage.prototype.handleFailure = function(t) {
	console.log('onFailure: ', t.statusText);
};

RealTimeMessage.prototype.handleException = function(req, e) {
	console.log('onException: ', e.message);
};

RealTimeMessage.prototype.__listenPolling = function() {
	new Ajax.Request(this.urlPolling, {
		method: 'post',
		requestHeaders: {'WaterSpout-Request': true},
		parameters: {'waterspout_cursor': this.cursor, 'waterspout_cookie': this.cookie},
		onSuccess: (this.__onmessage).bind(this),
		onFailure: this.handleFailure,
		onException: this.handleException
	});
};

RealTimeMessage.prototype.__listenWebSocket = function() {
	this.socketConn = new WebSocket(this.urlSocket);
	this.socketConn.onmessage = (this.__onmessage).bind(this);
	this.socketConn.onclose = (this.__listenWebSocket).bind(this);
};

RealTimeMessage.prototype.__onmessage = function(evt) {
	if (typeof this.onmessage != 'undefined') {
		if (typeof this.socketConn != 'undefined') {
			var data = evt.data.evalJSON();
		} else {
			var data = evt.responseJSON;
		}

		this.onmessage(data);

		if (data.cursor) {
			this.cursor = data.cursor;
			set_cookie('waterspout_cursor', this.cursor, 0, '/', window.location.host.substring(window.location.host.indexOf('.'), window.location.host.length), false);
		}

		if (typeof this.socketConn == 'undefined') {
			if (this.listener) {
				this.__listenPolling();
			}
		}
	}
};

RealTimeMessage.prototype.cleanup = function() {
	if (typeof this.socketConn != 'undefined') {
		// Don't try to reopen the socket connection when cleaning up.
		this.socketConn.onclose = function () {};
		this.socketConn.close();
	}
};

function get_cookie(check_name) {
	// first we'll split this cookie up into name/value pairs
	// note: document.cookie only returns name=value, not the other components
	var a_all_cookies  = document.cookie.split(';');
	var a_temp_cookie  = '';
	var cookie_name    = '';
	var cookie_value   = '';
	var b_cookie_found = false; // set boolean t/f default f

	for (i = 0; i < a_all_cookies.length; i++)
	{
		// now we'll split apart each name=value pair
		a_temp_cookie = a_all_cookies[i].split('=');


		// and trim left/right whitespace while we're at it
		cookie_name = a_temp_cookie[0].replace(/^\s+|\s+$/g, '');

		// if the extracted name matches passed check_name
		if (cookie_name == check_name)
		{
			b_cookie_found = true;
			// we need to handle case where cookie has no value but exists (no = sign, that is):
			if (a_temp_cookie.length > 1)
			{
				cookie_value = unescape(a_temp_cookie[1].replace(/^\s+|\s+$/g, ''));
			}
			// note that in cases where cookie is initialized but no value, null is returned
			return cookie_value;
			break;
		}
		a_temp_cookie = null;
		cookie_name = '';
	}
	if (!b_cookie_found)
	{
		return null;
	}
}

function set_cookie(name, value, expires, path, domain, secure) {
	// set time, it's in milliseconds
	var today = new Date();
	today.setTime(today.getTime());

	// if the expires variable is set, make the correct
	// expires time, the current script below will set
	// it for x number of days, to make it for hours,
	// delete * 24, for minutes, delete * 60 * 24
	if (expires)
	{
		expires = expires * 1000 * 60 * 60 * 24;
	}
	var expires_date = new Date(today.getTime() + (expires));

	document.cookie = name + '=' + escape(value) +
		((expires) ? ';expires=' + expires_date.toGMTString() : '') +
		((path) ? ';path=' + path : '') +
		((domain) ? ';domain=' + domain : '') +
		((secure) ? ';secure' : '');
}
