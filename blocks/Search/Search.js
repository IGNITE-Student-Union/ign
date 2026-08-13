document.addEventListener( 'DOMContentLoaded', function () {
	var sectionSelector = '.search';
	var formSelector = '.search-filters form';
	var selectSelector = '.search-filter-select select';
	var activeFiltersSelector = '.search-active-filters';
	var resultsSelector = '.search-results';
	var loadingSelector = '.search-loading';

	var sections = document.querySelectorAll( sectionSelector );
	if ( ! sections.length ) return;

	sections.forEach( function ( section ) {
		var form = section.querySelector( formSelector );
		var results = section.querySelector( resultsSelector );
		var loading = section.querySelector( loadingSelector );

		if ( ! results ) return;

		var paginationMode = section.dataset.paginationMode || 'pagination';
		var perPageVar = 'show_per_page';

		// ── Form Serialization ──
		function serializeForm( f ) {
			var data = new FormData( f );
			var params = new URLSearchParams();
			for ( var pair of data.entries() ) {
				if ( pair[ 1 ] !== null && pair[ 1 ] !== undefined && pair[ 1 ] !== '' ) {
					params.append( pair[ 0 ], pair[ 1 ] );
				}
			}
			return params.toString();
		}

		// ── State Management ──
		function updateState( push ) {
			if ( ! form ) return;
			// Reset page to 1
			var pagedInput = form.querySelector( 'input[name="paged"]' );
			if ( pagedInput ) pagedInput.value = '1';

			var query = serializeForm( form ).replace( /%2C/gi, ',' );
			var url = window.location.pathname + ( query ? '?' + query : '' );
			if ( push ) {
				history.pushState( { query: query }, '', url );
			}
			buildButtons();
			loadResults( url, false );
		}

		// ── AJAX Loading ──
		function loadResults( url, append ) {
			if ( ! append ) {
				results.classList.add( 'hidden' );
				results.setAttribute( 'aria-busy', 'true' );
				if ( loading ) loading.classList.remove( 'hidden' );
			}

			fetch( url )
				.then( function ( res ) {
					return res.text().then( function ( html ) {
						return { html: html, url: res.url };
					} );
				} )
				.then( function ( data ) {
					history.replaceState( {}, '', data.url );

					var temp = document.createElement( 'div' );
					temp.innerHTML = data.html;

					// Scope queries to the matching search block in the response
					var responseSection = section.id
						? temp.querySelector( '#' + section.id )
						: temp.querySelector( '.search' );
					if ( ! responseSection ) responseSection = temp;

					var newResults = responseSection.querySelector( resultsSelector );

					if ( newResults ) {
						if ( append ) {
							// Load More: append new cards to existing grid
							var existingGrid = results.querySelector( '.grid' );
							var newGrid = newResults.querySelector( '.grid' );
							var firstNewCard = newGrid ? newGrid.firstElementChild : null;
							if ( existingGrid && newGrid ) {
								var cards = newGrid.children;
								while ( cards.length > 0 ) {
									existingGrid.appendChild( cards[ 0 ] );
								}
							}
							// Update load more button. Update the existing node in place
							// instead of replaceWith()-ing it: replaceWith() destroys the
							// element the user just activated via keyboard, which drops
							// focus to <body> once the new content is inserted.
							var newLoadMore = responseSection.querySelector( '.search-load-more' );
							var oldLoadMore = section.querySelector( '.search-load-more' );
							var loadMoreButton = oldLoadMore ? oldLoadMore.querySelector( 'button' ) : null;
							if ( oldLoadMore && newLoadMore && loadMoreButton ) {
								var newButton = newLoadMore.querySelector( 'button' );
								if ( newButton ) {
									loadMoreButton.disabled = false;
									loadMoreButton.removeAttribute( 'aria-disabled' );
									loadMoreButton.removeAttribute( 'aria-busy' );
									loadMoreButton.textContent = newButton.textContent;
									loadMoreButton.dataset.nextPage = newButton.dataset.nextPage;
									loadMoreButton.dataset.maxPages = newButton.dataset.maxPages;
									delete loadMoreButton.dataset.originalText;
								}
							} else if ( oldLoadMore && ! newLoadMore ) {
								oldLoadMore.remove();
							}

							// Move focus to the heading of the first newly-loaded result so
							// screen readers announce "moved to: [new result title]" and the
							// user continues reading from where new content begins. Fall
							// back to the (now-preserved) Load More button if no heading
							// is found in the new markup.
							var newHeading = firstNewCard ? firstNewCard.querySelector( 'h2, h3' ) : null;
							if ( newHeading ) {
								if ( ! newHeading.hasAttribute( 'tabindex' ) ) {
									newHeading.setAttribute( 'tabindex', '-1' );
								}
								newHeading.focus( { preventScroll: true } );
							} else if ( loadMoreButton ) {
								loadMoreButton.focus( { preventScroll: true } );
							}
						} else {
							results.innerHTML = newResults.innerHTML;
						}
					}

					// Rebuild active filter pills from current form state
					buildButtons();

					if ( ! append ) {
						section.scrollIntoView( { behavior: 'smooth', block: 'start' } );
						// Move focus to results container for screen reader awareness
						results.focus();
					}
				} )
				.finally( function () {
					results.classList.remove( 'hidden' );
					results.setAttribute( 'aria-busy', 'false' );
					if ( loading ) loading.classList.add( 'hidden' );
				} );
		}

		// ── Filter Select Handlers ──
		if ( form ) {
			form.querySelectorAll( selectSelector ).forEach( function ( select ) {
				select.addEventListener( 'change', function () {
					var input = select.parentElement.querySelector( 'input' );
					if ( ! input ) return;
					var values = input.value.split( ',' ).filter( function ( v ) { return v; } );
					values.push( select.value );
					input.value = values.join( ',' );
					select.value = '';
					updateState( true );
					select.blur();
				} );
			} );

			// Per-page change
			var perPageSelect = form.querySelector( 'select[data-type="per_page"]' );
			if ( perPageSelect ) {
				perPageSelect.addEventListener( 'change', function () {
					updateState( true );
				} );
			}

			// Form Submit
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				updateState( true );
			} );
		}

		// ── Remove Filter / Clear All ──
		function removeFilterFromButton( button ) {
			var field = button.dataset.field;
			var value = button.dataset.value;
			if ( ! form ) return;
			var input = form.querySelector( 'input[data-type="' + field + '"]' );
			if ( ! input ) return;
			var values = input.value.split( ',' ).filter( function ( v ) { return v && v !== value; } );
			input.value = values.join( ',' );
			button.remove();
		}

		// ── Keyboard Event Handlers for Filter Pills ──
		function handleFilterPillKeyboard( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				e.target.click();
			}
		}

		var activeFilters = section.querySelector( activeFiltersSelector );
		if ( activeFilters ) {
			activeFilters.addEventListener( 'click', function ( e ) {
				var button = e.target.closest( 'button' );
				if ( ! button ) return;
				e.preventDefault();

				if ( button.dataset.clearAll ) {
					section.querySelectorAll( activeFiltersSelector + ' button[data-field]' ).forEach( removeFilterFromButton );
				} else if ( button.dataset.field ) {
					removeFilterFromButton( button );
				}
				updateState( true );
			} );
			// Add keyboard support for filter pills
			activeFilters.addEventListener( 'keydown', handleFilterPillKeyboard );
		}

		// ── Pagination Link Interception ──
		section.addEventListener( 'click', function ( e ) {
			var target = e.target.closest( '.pagination a' );
			if ( target ) {
				e.preventDefault();
				history.pushState( {}, '', target.getAttribute( 'href' ) );
				loadResults( target.getAttribute( 'href' ), false );
			}
		} );

		// ── Load More Button ──
		if ( paginationMode === 'load-more' ) {
			section.addEventListener( 'click', function ( e ) {
				var button = e.target.closest( '.search-load-more button' );
				if ( ! button ) return;
				e.preventDefault();

				var nextPage = parseInt( button.dataset.nextPage, 10 );
				var url = new URL( window.location.href );
				url.searchParams.set( 'paged', nextPage );
				button.disabled = true;
				button.setAttribute( 'aria-disabled', 'true' );
				button.setAttribute( 'aria-busy', 'true' );
				var originalText = button.textContent;
				button.textContent = 'Loading...';

				// Store original text to restore if needed
				button.dataset.originalText = originalText;

				loadResults( url.toString(), true );
			} );
		}

		// ── Build Active Filter Buttons ──
		function buildButtons() {
			if ( ! form ) return;
			var selects = form.querySelectorAll( selectSelector );
			var activeFiltersEl = section.querySelector( activeFiltersSelector );
			if ( ! activeFiltersEl ) return;

			var clearAllBtn = activeFiltersEl.querySelector( 'button[data-clear-all]' );

			activeFiltersEl.querySelectorAll( 'button[data-field]' ).forEach( function ( btn ) { btn.remove(); } );

			selects.forEach( function ( select ) {
				select.querySelectorAll( 'option' ).forEach( function ( o ) { o.disabled = false; } );
				select.querySelectorAll( 'option[value=""]' ).forEach( function ( o ) { o.disabled = true; } );

				var field = select.dataset.type;
				var input = select.parentElement.querySelector( 'input' );
				if ( ! input ) return;
				var values = input.value.split( ',' ).filter( function ( v ) { return v; } );

				values.forEach( function ( value ) {
					var option = select.querySelector( 'option[value="' + value + '"]' );
					if ( option ) option.disabled = true;

					var label = option ? option.innerText.trim() : value;
					clearAllBtn.insertAdjacentHTML(
						'beforebegin',
						'<button type="button" class="inline-flex items-center gap-1 border border-current rounded-full px-3 py-1 text-body-small uppercase font-medium tracking-wider cursor-pointer" data-field="' + field + '" data-value="' + value + '">' +
							'<span>' + label + '</span>' +
							'<div class="search-remove-filter"></div>' +
						'</button>'
					);
				} );

				select.disabled = Array.from( select.options ).every( function ( o ) { return o.disabled; } );
			} );
		}

		// ── Load Filters from URL (for popstate) ──
		function loadFiltersFromUrl( url ) {
			if ( ! form ) return;
			var urlObj = new URL( url, window.location.origin );
			var params = new URLSearchParams( urlObj.search );

			form.querySelectorAll( 'input[data-type]' ).forEach( function ( input ) {
				var type = input.dataset.type;
				input.value = params.get( type ) || '';
			} );

			// Update search input
			var searchInput = form.querySelector( 'input[data-type="s"]' );
			if ( searchInput ) searchInput.value = params.get( 's' ) || '';

			// Update per_page select
			var perPageSel = form.querySelector( 'select[data-type="per_page"]' );
			if ( perPageSel && params.get( perPageVar ) ) {
				perPageSel.value = params.get( perPageVar );
			}

			buildButtons();
		}

		// ── Browser Back/Forward ──
		window.addEventListener( 'popstate', function () {
			loadFiltersFromUrl( window.location.href );
			loadResults( window.location.href, false );
		} );
	} );
} );
