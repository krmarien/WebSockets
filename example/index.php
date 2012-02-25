<!doctype html>

<style>
	input { width: 300px; }
	#send { display: none; }
</style>

<div>
	<div>URL of your websocket server:</div>
	<div><input onchange="urlChanged()" id="url" type="text" name="url" value="ws://localhost:9988/" autofocus/></div>
	<div><button type="button" onclick="clickConnect()">Connect</button></div>
	<div id="status"></div>
</div>

<div id="send">
	<div>Send some text:</div>
	<div><input id="input" type="text" name="bla" value="" placeholder="enter something and press return to send"/></div>
	<div><button type="button" onclick="clickSend()">Send</button></div>
</div>

<script>

var s
	,urlEl = document.getElementById('url')
	,statusEl = document.getElementById('status')
	,sendEl = document.getElementById('send')
	,inputEl = document.getElementById('input')

function clickConnect()
{
	statusEl.innerText = 'connecting...';

	/* http://dev.w3.org/html5/websockets/ */ 

	s = new WebSocket(urlEl.value); 

	s.onopen = function() {
		statusEl.innerText = 'connected!';
		sendEl.style.display = 'block';
	}

	s.onclose = function() {
		statusEl.innerText = 'connection closed';
		sendEl.style.display = 'none';
	}

	s.onerror = function(e) {
		statusEl.innerText = 'error';
		console.log('error', e)
	}

	s.onmessage = function(e) {
		statusEl.innerText = 'got message: ' + e.data;
	}
}

function clickSend()
{
	s.send(inputEl.value);
	inputEl.value = '';
}

function urlChanged()
{
	if (!window.localStorage) {
		return;
	}

	localStorage.setItem('wsurl', urlEl.value);
}

urlEl.onkeypress = function(e) {
	if (e.keyCode == 13) {
		clickConnect();
	}
}

inputEl.onkeypress = function(e) {
	if (e.keyCode == 13) {
		clickSend();
	}
}

if (window.localStorage) {
	var wsurl = localStorage.getItem('wsurl');
	urlEl.value = wsurl;
}

</script>

