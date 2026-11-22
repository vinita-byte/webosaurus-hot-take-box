/* Webosaurus Hot Take Box: the approval desk (editor panel and Hot Take Desk rows). */
( function () {
	'use strict';

	var cfg = window.WHTB || {};
	var apiFetch = window.wp && window.wp.apiFetch;
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function el( tag, attrs, kids ) {
		var n = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			if ( k === 'text' ) n.textContent = attrs[ k ];
			else if ( k === 'on' ) Object.keys( attrs.on ).forEach( function ( e ) { n.addEventListener( e, attrs.on[ e ] ); } );
			else if ( attrs[ k ] !== false && attrs[ k ] != null ) n.setAttribute( k, attrs[ k ] === true ? '' : attrs[ k ] );
		} );
		( kids || [] ).forEach( function ( c ) { if ( c ) n.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c ); } );
		return n;
	}

	/* A small Lottie player that can switch moods. */
	function Mascot( node, mood ) {
		this.node = node;
		this.mood = null;
		this.anim = null;
		this.set( mood || 'smug' );
	}
	Mascot.prototype.set = function ( mood ) {
		if ( ! window.lottie || mood === this.mood ) return;
		this.mood = mood;
		if ( this.anim ) this.anim.destroy();
		this.anim = window.lottie.loadAnimation( {
			container: this.node,
			renderer: 'svg',
			loop: true,
			autoplay: ! reduce,
			path: cfg.lottie + 'websaurus-' + mood + '.json',
			rendererSettings: { preserveAspectRatio: 'xMidYMid meet' }
		} );
	};

	/* Mount Lotties only when they scroll into view; the desk can list many posts. */
	var io = 'IntersectionObserver' in window ? new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( e ) {
			if ( e.isIntersecting ) {
				io.unobserve( e.target );
				e.target.__mascot = new Mascot( e.target, e.target.getAttribute( 'data-whtb-lottie' ) );
			}
		} );
	}, { rootMargin: '200px' } ) : null;
	function mountMascot( node, mood ) {
		node.setAttribute( 'data-whtb-lottie', mood );
		if ( io ) io.observe( node );
		else node.__mascot = new Mascot( node, mood );
	}
	function setMood( node, mood ) {
		if ( node.__mascot ) node.__mascot.set( mood );
		else node.setAttribute( 'data-whtb-lottie', mood );
	}

	var LABELS = {
		empty: 'No take yet',
		pending: 'Waiting for approval',
		live: 'Live',
		pulled: 'Pulled'
	};

	/* Unsaved editor text, so drafts riff on what the author sees right now. */
	function editorText() {
		try {
			var ed = window.wp && window.wp.data && window.wp.data.select( 'core/editor' );
			if ( ed && ed.getEditedPostContent ) {
				return { title: ed.getEditedPostAttribute( 'title' ) || '', content: ed.getEditedPostContent() || '' };
			}
		} catch ( e ) { /* not the block editor */ }
		var title = document.getElementById( 'title' );
		var content = '';
		if ( window.tinymce && window.tinymce.get( 'content' ) && ! window.tinymce.get( 'content' ).isHidden() ) {
			content = window.tinymce.get( 'content' ).getContent();
		} else if ( document.getElementById( 'content' ) ) {
			content = document.getElementById( 'content' ).value;
		}
		return { title: title ? title.value : '', content: content };
	}

	function Desk( root ) {
		this.root = root;
		this.compact = root.classList.contains( 'whtb-desk--row' );
		this.state = JSON.parse( root.getAttribute( 'data-state' ) || '{}' );
		this.busy = false;
		this.error = '';
		this.writing = false;
		root.__desk = this;
		this.render();
	}

	Desk.prototype.call = function ( action, data ) {
		var self = this;
		if ( self.busy ) return Promise.resolve();
		self.busy = action;
		self.error = '';
		/* Do not re-render while waiting: it would wipe edits the user can still see. */
		self.root.classList.add( 'is-busy' );
		self.root.querySelectorAll( 'button, textarea, select' ).forEach( function ( n ) { n.disabled = true; } );
		if ( action === 'draft' ) {
			var btn = self.root.querySelector( '[data-whtb-draft]' );
			if ( btn ) btn.textContent = 'Cooking up puns…';
			var m = self.root.querySelector( '.whtb-desk__mascot' );
			if ( m ) setMood( m, 'thinking' );
		}
		return apiFetch( {
			path: '/whtb/v1/posts/' + self.state.id + ( action === 'get' ? '' : '/' + action ),
			method: action === 'get' ? 'GET' : 'POST',
			data: data
		} ).then( function ( state ) {
			self.state = state;
			self.writing = false;
		} ).catch( function ( err ) {
			self.error = ( err && err.message ) || 'Something went wrong.';
		} ).then( function () {
			self.busy = false;
			self.render();
		} );
	};

	Desk.prototype.draft = function () {
		return this.call( 'draft', this.compact ? {} : editorText() );
	};

	Desk.prototype.render = function () {
		var self = this;
		var s = self.state;
		var root = self.root;
		var keepMascot = root.querySelector( '.whtb-desk__mascot' );
		var heroMood = s.takes && s.takes.length ? s.takes[ 0 ].mood : ( s.drafts && s.drafts.length ? s.drafts[ 0 ].mood : 'thinking' );
		if ( self.busy === 'draft' ) heroMood = 'thinking';

		var mascot = keepMascot || el( 'div', { 'class': 'whtb-desk__mascot', 'aria-hidden': 'true' } );
		if ( ! keepMascot ) mountMascot( mascot, heroMood );
		else setMood( mascot, heroMood );

		var head = el( 'div', { 'class': 'whtb-desk__head' }, [
			mascot,
			el( 'div', { 'class': 'whtb-desk__titles' }, [
				self.compact ? el( 'h2', { 'class': 'whtb-desk__title' }, [
					el( 'a', { href: s.edit_link, text: s.title || '(no title)' } )
				] ) : null,
				el( 'p', { 'class': 'whtb-desk__meta' }, [
					el( 'span', { 'class': 'whtb-pill whtb-pill--' + s.status, text: LABELS[ s.status ] || s.status } ),
					self.compact && s.post_status !== 'publish' ? el( 'span', { 'class': 'whtb-desk__poststatus', text: s.post_status } ) : null,
					s.status === 'live' && s.approved_by ? el( 'span', { text: 'Approved by ' + s.approved_by } ) : null,
					self.compact && s.view_link && s.status === 'live' ? el( 'a', { href: s.view_link, target: '_blank', rel: 'noopener', text: 'See it live' } ) : null
				] )
			] )
		] );

		var body = el( 'div', { 'class': 'whtb-desk__body' } );

		if ( s.stale && s.drafts && s.drafts.length ) {
			body.appendChild( el( 'p', { 'class': 'whtb-note whtb-note--warn', text: 'The post changed after these drafts were written. Draft a fresh batch if the angle moved.' } ) );
		}

		/* Live takes. */
		if ( s.takes && s.takes.length ) {
			var live = el( 'div', { 'class': 'whtb-live' }, [ el( 'h3', { text: s.takes.length > 1 ? 'Live now (rotating)' : 'Live now' } ) ] );
			s.takes.forEach( function ( t, i ) {
				live.appendChild( el( 'div', { 'class': 'whtb-take whtb-take--live' }, [
					el( 'p', { 'class': 'whtb-take__text', text: t.text } ),
					el( 'span', { 'class': 'whtb-chip', text: t.mood } ),
					s.can_approve ? el( 'button', {
						type: 'button',
						'class': 'button-link whtb-danger',
						disabled: !! self.busy,
						text: 'Pull',
						on: { click: function () { self.call( 'pull', s.takes.length > 1 ? { index: i } : {} ); } }
					} ) : null
				] ) );
			} );
			body.appendChild( live );
		}

		/* Drafts waiting on a human. */
		var drafts = ( s.drafts || [] ).slice();
		if ( self.writing ) drafts.unshift( { text: '', mood: 'smug', own: true } );
		if ( drafts.length ) {
			var tray = el( 'div', { 'class': 'whtb-tray' }, [
				el( 'h3', { text: self.writing && drafts.length === 1 ? 'Your take' : 'Pick one' } ),
				s.note ? el( 'p', { 'class': 'whtb-note', text: s.note } ) : null
			] );
			drafts.forEach( function ( d, i ) {
				var realIndex = self.writing ? i - 1 : i;
				var area = el( 'textarea', { rows: 2, 'class': 'whtb-take__input', 'aria-label': 'Take text', placeholder: 'Type a one line hot take' } );
				area.value = d.text;
				var sel = el( 'select', { 'class': 'whtb-take__mood', 'aria-label': 'Mood' } );
				Object.keys( cfg.moods || {} ).forEach( function ( m ) {
					var o = el( 'option', { value: m, text: cfg.moods[ m ] } );
					if ( m === d.mood ) o.selected = true;
					sel.appendChild( o );
				} );
				var preview = function () { setMood( mascot, sel.value ); };
				sel.addEventListener( 'change', preview );
				var take = function () { return { text: area.value, mood: sel.value }; };
				var actions = el( 'div', { 'class': 'whtb-take__actions' }, [
					s.can_approve ? el( 'button', {
						type: 'button',
						'class': 'button button-primary',
						disabled: !! self.busy,
						text: s.takes && s.takes.length && ! cfg.rotate ? 'Approve and replace live' : 'Approve',
						on: { click: function () { self.call( 'approve', { takes: [ take() ], mode: 'replace' } ); } }
					} ) : el( 'span', { 'class': 'whtb-note', text: 'An editor will approve this.' } ),
					s.can_approve && cfg.rotate && s.takes && s.takes.length ? el( 'button', {
						type: 'button',
						'class': 'button',
						disabled: !! self.busy,
						text: 'Add to rotation',
						on: { click: function () { self.call( 'approve', { takes: [ take() ], mode: 'add' } ); } }
					} ) : null,
					el( 'button', {
						type: 'button',
						'class': 'button-link whtb-danger',
						disabled: !! self.busy,
						text: d.own ? 'Cancel' : 'Toss',
						on: { click: function () {
							if ( d.own ) { self.writing = false; self.render(); } else { self.call( 'toss', { index: realIndex } ); }
						} }
					} )
				] );
				var card = el( 'div', { 'class': 'whtb-take', on: { mouseenter: preview, focusin: preview } }, [
					area,
					el( 'div', { 'class': 'whtb-take__row' }, [ sel, actions ] )
				] );
				tray.appendChild( card );
				if ( d.own ) setTimeout( function () { area.focus(); }, 0 );
			} );
			body.appendChild( tray );
		} else if ( ! s.takes || ! s.takes.length ) {
			body.appendChild( el( 'p', { 'class': 'whtb-note', text: self.compact
				? 'No drafts yet.'
				: 'When you publish, ' + ( cfg.mascot || 'the mascot' ) + ' drafts a few takes on the whole article and parks them here for approval. Nothing goes live until someone approves one.' } ) );
		}

		if ( self.error ) {
			body.appendChild( el( 'p', { 'class': 'whtb-note whtb-note--error', role: 'alert', text: self.error } ) );
		} else if ( s.error && ! self.busy && ( ! s.drafts || ! s.drafts.length ) ) {
			body.appendChild( el( 'p', { 'class': 'whtb-note whtb-note--error', text: 'Last draft failed: ' + s.error } ) );
		}

		var foot = el( 'div', { 'class': 'whtb-desk__foot' }, [
			el( 'button', {
				type: 'button',
				'class': 'button' + ( s.drafts && s.drafts.length ? '' : ' button-primary' ),
				disabled: !! self.busy,
				'data-whtb-draft': true,
				text: self.busy === 'draft' ? 'Cooking up puns…' : ( s.drafts && s.drafts.length ? 'Draft a fresh batch' : 'Draft hot takes' ),
				on: { click: function () { self.draft(); } }
			} ),
			! self.writing ? el( 'button', {
				type: 'button',
				'class': 'button-link',
				disabled: !! self.busy,
				text: 'Write my own',
				on: { click: function () { self.writing = true; self.render(); } }
			} ) : null,
			s.engine ? el( 'span', { 'class': 'whtb-engine', text: 'Drafted by ' + ( s.engine === 'offline' ? 'the offline pun engine' : s.engine ) } ) : null
		] );

		root.textContent = '';
		root.classList.toggle( 'is-busy', !! self.busy );
		root.appendChild( head );
		root.appendChild( body );
		root.appendChild( foot );
	};

	function boot() {
		if ( ! apiFetch ) return;
		document.querySelectorAll( '.whtb-desk' ).forEach( function ( n ) { new Desk( n ); } );
		document.querySelectorAll( '[data-whtb-lottie]:not(.whtb-desk__mascot)' ).forEach( function ( n ) {
			mountMascot( n, n.getAttribute( 'data-whtb-lottie' ) );
		} );

		/* Bulk drafting on the desk, one post at a time so nothing times out. */
		var bulk = document.querySelector( '[data-whtb-bulk-draft]' );
		if ( bulk ) {
			bulk.addEventListener( 'click', function () {
				var desks = Array.prototype.map.call( document.querySelectorAll( '.whtb-desk' ), function ( n ) { return n.__desk; } )
					.filter( function ( d ) { return d && ( ! d.state.drafts || ! d.state.drafts.length ); } );
				var status = document.querySelector( '.whtb-bulk__status' );
				var done = 0;
				bulk.disabled = true;
				desks.reduce( function ( p, d ) {
					return p.then( function () {
						status.textContent = 'Drafting ' + ( done + 1 ) + ' of ' + desks.length + '…';
						return d.draft().then( function () { done++; } );
					} );
				}, Promise.resolve() ).then( function () {
					status.textContent = 'Done. ' + done + ' posts drafted. They are now on the waiting tab.';
					bulk.disabled = false;
				} );
			} );
		}
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
	else boot();
}() );
