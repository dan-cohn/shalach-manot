// -------------------------------------------------------
// ezAjax
//
// A simple library for making asynchronous server
// requests and receiving the reply in JSON format.
//
// Currently all requests are sequenced so the previous
// one must complete before the next one is initiated.
// Requests are queued and may be retried if they time
// out.
//
// Copyright (c) 2011 Daniel M. Cohn
//
// Created: 22-Jan-2011
// Updated: 27-Feb-2015
//
// ** Important **
// Requires json2.js
// -------------------------------------------------------

function EzAjaxObject(baseUrl, initialTimeLimit, maxRetries) {
  var requests=[];  // queue
  var currIndex = 0;  // pointer to request being processed (or 0 if none)
  var lastIndex = 0;  // pointer to last occupied position in queue
  var reqTimer = null;
  var xmlhttp, retriesLeft;
  var timeLimit = initialTimeLimit;  // current timer value

  function Request(action, parms, callback) {
    var sid = Math.random();
    var cb = ((callback !== undefined) && callback)?callback:null;
    this.getUrl = function() {
      var url = baseUrl+"?action="+action;
      if (parms) {
        for (parm in parms) {
          if (parms.hasOwnProperty(parm)) url+="&"+parm+"="+encodeURIComponent(parms[parm]);
        }
      }
      url+="&sid="+sid;
      return url;
    }

    this.reply = function(response) {
      if (cb) cb(response);
    }
  }

  function getXmlHttpObject() {
    if (window.XMLHttpRequest) {
      // code for IE7+, Firefox, Chrome, Opera, Safari
      return new XMLHttpRequest();
    }
    if (window.ActiveXObject) {
      // code for IE6, IE5
      return new ActiveXObject("Microsoft.XMLHTTP");
    }
    return null;
  }

  function stateChange() {
    var result, resultText;

    if (!xmlhttp || !reqTimer) return;  // request timed out before receiving a response

    if (xmlhttp.readyState === 4) {  // request completed & response ready

      if (reqTimer) {
        window.clearTimeout(reqTimer);
        reqTimer = null;
      }

      if (xmlhttp.status == 200 || xmlhttp.status == 304) {  // "OK" or "Not Modified"
        resultText = xmlhttp.responseText;

        try {
          result=JSON.parse(resultText);
        }
        catch (e) {}

        if (!result) {
          if (resultText.length > 0) alert("Error: Server returned invalid data: " + resultText.substr(0,120));
          result = {"rc":false};
        }

        if (!result.hasOwnProperty("rc")) result.rc = true;

      } else {  // not OK
        if (xmlhttp.status > 0) alert("Error: Could not complete request. Error code "+xmlhttp.status+".");
        else alert("Request failed - communication error. Check your network connection.");
        result = {"rc":false};
      }

      try {
        requests[currIndex].reply(result);
      }
      catch (e) {
        alert ("Unexpected error: "+e);
      }

      delete xmlhttp;
      xmlhttp = null;
      delete requests[currIndex];

      popRequest();  // send next request if one is pending
    }
  }

  function timeoutHandler() {
    reqTimer = null;

    if (xmlhttp) {  // sanity check
      xmlhttp.abort();
      delete xmlhttp;
      xmlhttp = null;

      if (requests[currIndex]) {
        if (--retriesLeft > 0) {
          timeLimit += initialTimeLimit;  // linear backoff
          sendRequest();  // repeat current request & set new timer
        } else {
          alert("Error: Server not responding. Check your Internet connection.");

          requests[currIndex].reply({"rc":false});  // invoke callback
          delete requests[currIndex];

          popRequest();  // try next request if one is queued up
        }
      } else {
        // sometimes request is gone - not sure why (race condition?); skip current
        popRequest();
      }
    }
  }

  function sendRequest() {
    xmlhttp = getXmlHttpObject();

    if (xmlhttp) {
      reqTimer = window.setTimeout(timeoutHandler, timeLimit);
      xmlhttp.onreadystatechange = stateChange;
      xmlhttp.open("GET", requests[currIndex].getUrl(), true);
      xmlhttp.send(null);
    } else {
      alert("Error: Could not complete request!");  // DMC: not ideal
    }
  }

  function popRequest() {
    // is a request pending?
    if ((currIndex < lastIndex) && requests[currIndex+1]) {
      ++currIndex;
      timeLimit = initialTimeLimit;
      sendRequest();
      retriesLeft = maxRetries;
    } else {
      currIndex = 0;
    }
  }

  // API for initiating a request
  this.initiate = function(action, parms, callback) {
    requests[++lastIndex] = new Request(action, parms, callback);
    if (!currIndex) {
      currIndex = lastIndex - 1;
      popRequest();
    }
  }

  // API for checking to see if a request is still in progress
  this.isBusy = function() {
    return (currIndex > 0);
  }
}

var ezAjax = new EzAjaxObject("", 15000, 2);
