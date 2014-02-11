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

