/* Webosaurus Hot Take Box: animates the mascot. The take itself is already in the HTML. */
( function () {
	'use strict';

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function start( box ) {
		var urls = {};
		try { urls = JSON.parse( box.getAttribute( 'data-lottie' ) || '{}' ); } catch ( e ) { return; }
		var stage = box.querySelector( '.whtb__mascot' );
		var mood = box.getAttribute( 'data-mood' ) || 'smug';
		var anim = null;

		function play( m, loop, done ) {
			if ( ! window.lottie || ! urls[ m ] ) return;
			if ( anim ) anim.destroy();
			anim = window.lottie.loadAnimation( {
				container: stage,
				renderer: 'svg',
				loop: loop,
				autoplay: ! reduce,
				path: urls[ m ],
				rendererSettings: { preserveAspectRatio: 'xMidYMid meet' }
			} );
			if ( reduce ) {
				anim.addEventListener( 'DOMLoaded', function () { anim.goToAndStop( 0, true ); } );
			}
			if ( done ) anim.addEventListener( 'complete', done );
		}

		box.classList.add( 'whtb--in' );
		play( mood, true );

		/* Poke the dinosaur: it cackles once, then goes back to its mood. */
		if ( ! reduce ) {
			var poking = false;
			stage.addEventListener( 'click', function () {
				if ( poking ) return;
				poking = true;
				play( 'cackling', false, function () {
					poking = false;
					play( mood, true );
				} );
			} );
		}
	}

	function boot() {
		var boxes = document.querySelectorAll( '.whtb' );
		if ( ! boxes.length ) return;
		Array.prototype.forEach.call( boxes, function ( box ) {
			box.classList.remove( 'whtb--nojs' );

			/* Rotation: several approved takes, pick one per visit. */
			var takes = null;
			try { takes = JSON.parse( box.getAttribute( 'data-takes' ) || 'null' ); } catch ( e ) { takes = null; }
			if ( takes && takes.length > 1 ) {
				var pick = takes[ Math.floor( Math.random() * takes.length ) ];
				var p = box.querySelector( '.whtb__text p' );
				if ( p && pick && pick.text ) {
					p.textContent = pick.text;
					box.setAttribute( 'data-mood', pick.mood || 'smug' );
				}
			}
		} );

		if ( ! ( 'IntersectionObserver' in window ) ) {
			Array.prototype.forEach.call( boxes, start );
			return;
		}
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( e ) {
				if ( e.isIntersecting ) {
					io.unobserve( e.target );
					start( e.target );
				}
			} );
		}, { rootMargin: '0px 0px -10% 0px' } );
		Array.prototype.forEach.call( boxes, function ( b ) { io.observe( b ); } );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
	else boot();
}() );
