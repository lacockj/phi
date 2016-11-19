;(function(){

/**
 * @namespace Phi
 * @requires jQuery
 */
window.phi = window.phi || {};

phi.debug = false;

phi.doc = {
  defaultTarget: $('#content-main'),
  transition: function( html, tgtSelector ){
    if ( phi.debug ) console.log( "transition", tgtSelector );
    var tgt = ( typeof tgtSelector === 'string' && tgtSelector.length && tgtSelector !== "#" ) ? $(tgtSelector) : phi.doc.defaultTarget;
    tgt.fadeOut(250);
    window.setTimeout(function(){
      tgt.html( html ).fadeIn();
    }, 250);
  },
  load: function( pathname, tgtSelector ){
    if ( phi.debug ) console.log( "load", pathname );
    // Load next page content
    $.get( pathname )
    .done(function( html, status, xhr ){
      phi.doc.transition( html, tgtSelector );
      $('a').off('click', phi.doc.anchorClickHandler);
      $('a').on('click', phi.doc.anchorClickHandler);
    })
    .fail(function(xhr){
      if ( phi.debug ) console.log( xhr );
      phi.doc.transition( xhr.responseText );
    });
  },
  reload: function( e ){
    if ( phi.debug ) console.log(e);
    phi.doc.load( window.location.pathname );
  },
  anchorClickHandler: function( e ){
    var target, targetSelector;
    if ( phi.debug ) console.log( "anchorClickHandler", e );
    if ( phi.debug ) console.log( window.location );    

    // If linking to same origin...
    if ( e.target.href.indexOf( window.location.origin ) === 0 ) {
      // and data-target is set...
      target = e.target.attributes.getNamedItem('data-target');
      if ( target === null ) return;
      targetSelector = target.value;
      if ( targetSelector.length && targetSelector.charAt(0) === "#" ) {
        e.preventDefault();
        if ( e.target.pathname !== window.location.pathname ) {
          // Add next page to history
          window.history.pushState( null, window.document.title, window.location.origin + e.target.pathname );
          phi.doc.load( e.target.pathname, targetSelector );
        }
      }
    }
  }
};

$(document).ready(function(){
  $('a').on('click', phi.doc.anchorClickHandler);
  window.onpopstate = phi.doc.reload;
  if ( phi.debug ) console.log("ready");
});

/*
phi.bg = {
  piOverThirty: Math.PI / 30,
  cX: 0,
  cY: 0,
  pX: 0,
  pY: 0,
  floating: $('.floating-bg')
}
*/

/*
window.setInterval(function(){
  var d = new Date();
  var a = ( d.getSeconds() + ( d.getMilliseconds() / 1000 ) ) * phi.bg.piOverThirty;
  phi.bg.cX = Math.round( Math.cos( a ) * 50 + 50 );
  //phi.bg.cY = Math.round( Math.sin( a ) * 50 + 50 );
  phi.bg.cY = 0;
  var x = phi.bg.cX + phi.bg.pX;
  var y = phi.bg.cY + phi.bg.pY;
  phi.bg.floating.each(function(index, item){
    var $item = $(item);
    var factor = parseFloat( $item.data('float-factor') );
    factor = isNaN(factor) ? -1 : -1*factor;
    $item.css('background-position', (x*factor) + "px " + (y*factor) + "px");
  });
}, 10);
*/

/*
// Watch mouse movement; adjust floating background images. //
$('body').on('mousemove', function(e){
  phi.bg.pX = Math.round( e.pageX / window.innerWidth * 100 );
  phi.bg.pY = Math.round( e.pageY / window.innerHeight * 100 );
  var x = phi.bg.cX + phi.bg.pX;
  var y = phi.bg.cY + phi.bg.pY;
  $('.floating-bg').each(function(index, item){
    var $item = $(item);
    var factor = parseFloat( $item.data('float-factor') );
    factor = isNaN(factor) ? -1 : -1*factor;
    $item.css('background-position', (x*factor) + "px " + (y*factor) + "px");
  });
});
*/

// Testing

function testLoginStatus () {
  $.get('/api/login')
  .done(function( response ){
    console.log("get login done");
    console.log( response );
    testLogout();
  })
  .fail(function( xhr ){
    console.log("get login fail");
    console.log( xhr.responseText );
    testLogin();
  });
}

function testLogin () {
  $.post('/api/login', {
    'user': "bob",
    'pass': "pass"
  })
  .done(function( response ){
    console.log("post login done");
    console.log( response );
  })
  .fail(function( xhr ){
    console.log("post login fail");
    console.log( xhr.responseText );
  })
  .then(function(){
    phi.test.runNext();
  });
}

function testLogout () {
  $.get('/api/logout')
  .done(function( response ){
    console.log("get logout done");
    console.log( response );
  })
  .fail(function( xhr ){
    console.log("get logout fail");
    console.log( xhr.responseText );
  })
  .then(function(){
    phi.test.runNext();
  });
}

phi.test = {
  sequence: [
    //testLoginStatus
  ],
  runNext: function () {
    if ( this.sequence.length && typeof this.sequence[0] === 'function' ) {
      this.sequence.shift().call();
    } else {
      console.log("Done.");
    }
  }
};

//phi.test.runNext();


if ( false ) {

  /*
  $.get('/api/hashword/pass')
  .done(function(){
    console.log("get hash done");
  })
  .fail(function(){
    console.log("get hash fail");
  });
  */

  $.get('/test/chucknorris')
  .done(function(){
    console.log("get test done");
  })
  .fail(function(){
    console.log("get test fail");
  });

  $.post('/test/chucknorris')
  .done(function(){
    console.log("post test done");
  })
  .fail(function(){
    console.log("post test fail");
  });

  $.ajax('/test/chucknorris', {'method': 'OPTIONS'})
  .done(function(){
    console.log("options test done");
  })
  .fail(function(){
    console.log("options test fail");
  });

}

})();