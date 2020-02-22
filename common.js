// --------------------------------------
// Common Javascript functions
// --------------------------------------

// Error handler
window.onerror = function(message, url, lineNumber) {  
  var logText="line "+lineNumber+": "+message;
  ezAjax.initiate("error",{"message":logText}, error_callback);
  return true; // prevents browser error messages  
};

function error_callback(result) {
  // no action required
}

function addCommas(nStr)
{
  nStr += '';
  var x = nStr.split('.');
  var x1 = x[0];
  var x2 = x.length > 1 ? '.' + x[1] : '';
  var rgx = /(\d+)(\d{3})/;
  while (rgx.test(x1)) {
    x1 = x1.replace(rgx, '$1' + ',' + '$2');
  }
  return x1 + x2;
}

Number.prototype.dollars=function() {
  return "$"+this.toFixed(2).replace(/\.00$/,'');
}

Number.prototype.dollars2=function() {
  return "$"+addCommas(this.toFixed(2));
}

function gebi(e) {
  return document.getElementById(e);
}

function htmlspecialchars(str) {
  var newStr=str.slice(0),charmap={'&':'&amp;', '<':'&lt;', '>':'&gt;'};
  return newStr.replace(/[&<>]/g, function (c) {return charmap[c];});
}

function trim_contents(e) {
  e.value=e.value.replace(/^\s+/,'').replace(/\s+$/,'').replace(/\s\s+/g,' ');
}

function capitalize(e) {
  if (e.value) e.value=e.value.toUpperCase();
}

function and_to_amp(e) {
  e.value=e.value.replace(/ and /gi,' & ').replace(/\& family/gi,'and Family');
}

function clean_phone_num(e) {
  trim_contents(e);
  e.value=e.value.replace(/\(?(\d{3})\)\s?-?/,'$1-');
  e.value=e.value.replace(/(\d{3,7})\s?\.?\s?(\d{4})/,'$1-$2').replace(/(\d{3})\s?\.?\s?(\d{3})([^0-9])/,'$1-$2$3');
}

function convert_to_dollars(e,blankIfZero) {
  var num=parseFloat(e.value);
  if (num) {
    e.value=num.toFixed(2);
  } else {
    e.value=(blankIfZero)?"":"0.00";
  }
}

function convert_to_positive_dollars(e,blankIfZero) {
  var num=parseFloat(e.value);
  if (num) {
    if (num>0) {
      e.value=num.toFixed(2);
    } else {
      e.value=(-num).toFixed(2);
    }
  } else {
    e.value=(blankIfZero)?"":"0.00";
  }
}

function ignoreEnter(e) {
  var keynum=0;
  if (window.event) keynum=e.keyCode;
  else if (e.which) keynum=e.which;
  if (keynum===13) {
    if (e.stopPropagation) e.stopPropagation();
    e.cancelBubble=true;  // for IE8 and below
    e.returnValue=false;  // for IE8 and below
    return false;
  } else {
    return true;
  }
}

function ignore(e) {
  if (e.stopPropagation) e.stopPropagation();
  e.cancelBubble=true;  // for IE8 and below
  e.returnValue=false;  // for IE and below
  return false;
}

function noBS(e) {
  var keynum=0;
  if (window.event) keynum=e.keyCode;
  else if (e.which) keynum=e.which;
  if (keynum===220) {  // backslash
    e.cancelBubble=true;  // for IE
    e.returnValue=false;  // for IE
    return false;
  } else return true;
}

function numbersOnly(e) {
  var keynum=0;
  if (window.event) keynum=e.keyCode;
  else if (e.which) keynum=e.which;
  // accept only digits, numeric keypad digits, backspace, tab, and left/right arrows
  if ((keynum >= 48 && keynum <= 57) || (keynum >= 96 && keynum <= 105) || (keynum===8) || (keynum===37) || (keynum===39) || (keynum===9)) {
    if (!e.shiftKey || (keynum===9)) return true;  // allow for shift-tab but no other shift+key
  }
  e.cancelBubble=true;  // for IE
  e.returnValue=false;  // for IE
  return false;
}

function keydown(e,f) {
  var keynum=0;

  if (window.event) keynum=e.keyCode;
  else if (e.which) keynum=e.which;

  if (keynum===13) {  // Enter
    f();
    if (e.stopPropagation) e.stopPropagation();
    e.cancelBubble=true;  // for IE8 and below
    e.returnValue=false;  // for IE8 and below
    return false;
  }

  return true;
}

function inputF(e) {  // onfocus event handler
  e.className=e.className+" textfocus";
}

function inputB(e) {  // onblur event handler
  e.className=e.className.replace(/ textfocus/g,"");  // using global replace because it sometimes gets added twice
}
