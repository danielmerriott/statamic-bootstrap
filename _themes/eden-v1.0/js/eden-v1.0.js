// Add affix to navbar
// Set the height of a wrap div to replace missing space in DOM
// http://stackoverflow.com/questions/12070970/how-to-use-the-new-affix-plugin-in-twitters-bootstrap-2-1-0/13151016#13151016
$(function() {
  $('#nav-affix').affix({
      offset: { top: $('#nav-affix').offset().top }
  })
  $('#nav-wrap').height($("#nav-affix").height());
});

// Add affix to sidebar (if it exists)
// Scroll when close to the navbar (within 20 px) ... should be 70px, but lets calculate it anyway
// Set a bottom 20px above the page footer (css will style this as 'postiion: absolute'
$(function() {
  if($('#sidebar-affix').length) {
    $('#sidebar-affix').affix({
        offset: { top: $('#sidebar-affix').offset().top - $("#nav-affix").height() - 20,
               bottom: $("#pagefoot").outerHeight(!0) + 20 }
    })
  }
});

// Form validation - require 'pattern' to be set on the input property
// http://www.designchemical.com/blog/index.php/jquery/form-validation-using-jquery-and-regular-expressions/
// disable buttons immediately, re-enable via validation
$(function() {
  $('input[pattern]').parents('form').children('button').addClass('disabled');
  $('input[pattern]').parents('form').attr('onsubmit','return false;');
});
// validate on focus or key press
$('input[pattern]').bind('keyup', forminputvalidate);
$('input[pattern]').bind('focusin', forminputvalidate);
function forminputvalidate() {
  var f = $(this).val();
  var p = $(this).attr('pattern');
  var r = new RegExp(p);
  if(!r.test(f)) {
    $(this).parents('.form-group').addClass('has-error');
    $(this).parents('form').children('button').addClass('disabled');
    $(this).parents('form').attr('onsubmit','return false;');
  } else {
    $(this).parents('.form-group').removeClass('has-error');
    $(this).parents('form').children('button').removeClass('disabled');
    $(this).parents('form').removeAttr('onsubmit');
  }
};
// clear 'has-error' class when focus is lost
$('input[pattern]').bind('focusout', function(c) {
  $(this).parents('.form-group').removeClass('has-error');
});

// Convert images with a title to figures with floating text
// Only in the main body of a single article though!
$(function() {
  $('.article-single .content-main img[title]').each(function() {
    $(this).wrap('<figure></figure>');
    $(this).after('<figcaption>' + $(this).attr('title') + '</figcaption>');
    $(this).removeAttr('title');
    $(this).addClass('figure');
  })
  $('.article-single .content-main img:not(.figure), .article-single .content-main figure:not(img)').each(function(index) {
    $(this).addClass(index % 2 ? 'img-right' : 'img-left');
  })
});

// Fade in/out alert-fixed-top using bootstrap's fade and fade.in classes
// Stays on screen for at least 10 seconds and at least 3 seconds after mouseover
$(document).ready(function() {
  showAlertFixedTop()
});

function showAlertFixedTop() {
  var timeFadeIn = 500;
  var timeFadeOut = 10000;
  var timeFadeOutHover = 3000;
  var alertTimer;
  if ($('.alert-fixed-top.fade').length) {
    setTimeout("$('.alert-fixed-top.fade').addClass('in')", timeFadeIn);
    setTimeout(function() { alertFadeOut(false) }, (timeFadeIn + timeFadeOut - timeFadeOutHover) );
  }
  function alertFadeOut(minTime) {
    if ($('.alert-fixed-top.fade').length) {
      if (minTime) {
        //check for :hover in case mouseover happens before the second timer
        if (!$('.alert-fixed-top.fade').is(':hover')) {
          $('.alert-fixed-top.fade').alert('close');
        }
      } else {
        $('.alert-fixed-top.fade').mouseout(function () {
          alertTimer = setTimeout(function() { alertFadeOut(true) }, timeFadeOutHover);
        });
        $('.alert-fixed-top.fade').mouseover(function () {
          clearTimeout(alertTimer);
        });
        alertTimer = setTimeout(function() { alertFadeOut(true) }, timeFadeOutHover);
      }
    }
  }
}


// Cookie notification
$(document).ready(function() {
  var permitcookiePartialName = '_modalcookies';
  var permitcookieName = '_permitcookies';
  var permitcookieExpiryDays = 7;
  var permitcookieDataString = vernamcypher($(location).attr('hostname'), 
                               hexit('t=' + (new Date).valueOf() + ';' +
                                     'e=' + (new Date).setDate((new Date).getDate()+permitcookieExpiryDays).valueOf() + ';' +
                                     'l=' + $(location).attr('href')) );
  var crumbs = new Array();
  var crumbdata = $.cookie(permitcookieName);
  if (!crumbdata) {
    $('body').append(
      '<div class="alert alert-info alert-dismissable alert-fixed-top fade" id="' + $.now() + permitcookieName + '">' +
      '  <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
      '  <span class="fa fa-fw fa-lg fa-info-circle"></span>&nbsp;' +
      '  This website makes use of cookies to enhance your browsing experience and provide additional functionality.' +
      '  Please see the <a href="/privacy" class="alert-link">privacy policy</a> for more details.' +
      '</div>'
      );
    showAlertFixedTop()
    $.cookie(permitcookieName, permitcookieDataString, { expires: permitcookieExpiryDays, path: '/' });
  } else {
    crumbdata = unhexit(vernamcypher($(location).attr('hostname'),crumbdata));
    crumbdata = crumbdata.replace(/[\s]*\;[\s]*/g,';');
    crumbdata = crumbdata.replace(/[\s]*\=[\s]*/g,'=');
    var splitcookie = crumbdata.split(';');
    for (var i=0; i<splitcookie.length; i++) {
      crumbs[ splitcookie[i].split('=')[0] ] = splitcookie[i].split('=')[1]
    }
    var d = new Date(Number(crumbs['t']));
    var e = new Date(Number(crumbs['e']));
    console.log('found cookie!\n' + 
                'timestamp: ' + d + '\n' +
                'expires: ' + e + '\n' +
                'location: ' + crumbs['l']);
  }
  
  function vernamcypher(key, value) {
    var newkey = '';
    var result = '';
    for(var i=0; i<key.length; i++) {
      newkey+=key.charCodeAt(i)
    }
    for(var i=0; i<value.length; i++) {
      result+=String.fromCharCode(newkey[i % newkey.length]^value.charCodeAt(i));
    }
    return result;
  }
  function hexit(input) {
    var output = '';
    for (var i = 0; i < input.length; i++) {
      output += (input.charCodeAt(i) + 0x100).toString(16).slice(1);
    }
    return output;
  }
  function unhexit(input) {
    var output = '';
    for (var i = 0; i < input.length; i += 2) {
      output += String.fromCharCode('0x'+input[i]+input[i+1]);
    }
    return output;
  }

});

