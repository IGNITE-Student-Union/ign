( () => {
	const focusableSelector =
		'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])';
	const submenuItemSelector =
		'.wp-block-takt-header-sub-item a, .wp-block-takt-header-sub-item button';
	const mediaQuery = window.matchMedia( '(min-width: 60rem)' );
	const isDesktop = () => mediaQuery.matches;

	document.querySelectorAll( '.header-main' ).forEach( ( header ) => {
		const fixedScrollDesktop = !! parseInt(
			header.dataset.fixedScrollDesktop,
			10
		);
		const fixedScrollMobile = !! parseInt(
			header.dataset.fixedScrollMobile,
			10
		);
		const hideOnScrollDownDesktop = !! parseInt(
			header.dataset.hideScrollDownDesktop,
			10
		);
		const hideOnScrollDownMobile = !! parseInt(
			header.dataset.hideScrollDownMobile,
			10
		);
		const menuToggle = header.querySelector(
			'[aria-label="Toggle Main Menu"]'
		);
		const collapsable = header.querySelector( '.header-collapsable' );
		const utilityRow = header.querySelector( '[data-utility-row]' );

		// Mirror visual hidden-state to assistive-tech via aria-hidden so screen
		// readers don't reach the off-screen mobile menu content or the
		// CSS-hidden desktop utility row while on mobile.
		const updateAriaHidden = () => {
			const onDesktop = isDesktop();
			const menuIsOpen = header.classList.contains( 'menu-open' );
			if ( collapsable ) {
				if ( ! onDesktop && ! menuIsOpen ) {
					collapsable.setAttribute( 'aria-hidden', 'true' );
				} else {
					collapsable.removeAttribute( 'aria-hidden' );
				}
			}
			if ( utilityRow ) {
				if ( ! onDesktop ) {
					utilityRow.setAttribute( 'aria-hidden', 'true' );
				} else {
					utilityRow.removeAttribute( 'aria-hidden' );
				}
			}
		};

		// Compute available height for the open mobile menu and set --menu-max-height on the header.
		// Formula: viewportHeight - rect.top - headerPadding - logoRowHeight - bottomSpacing
		//   rect.top      = header's distance from viewport top (accounts for admin bar, fixed offset, SEP CTA)
		//   headerPadding = py-4 top + bottom = 32px
		//   logoRowHeight = height of the logo/hamburger row (content-dependent)
		//   bottomSpacing = --side-gutter, so the rounded bar ends with the same spacing as the horizontal gutter
		// Clamped to a minimum of 200px.
		const updateMenuMaxHeight = () => {
			const rect = header.getBoundingClientRect();
			const logoRow = header.querySelector( ':scope > .flex' );
			const logoRowHeight = logoRow ? logoRow.offsetHeight : 0;
			// py-4 on header-main = 16px top + 16px bottom = 32px total
			const headerPadding = 32;
			// Bottom spacing: match the side gutter for visual consistency.
			// --side-gutter is expressed in rem (e.g. "1rem"), so convert to px by
			// multiplying by the root font-size. Falls back to 16px if unresolvable.
			const sideGutterRaw = getComputedStyle( document.documentElement ).getPropertyValue( '--side-gutter' ).trim();
			const rootFontSize = parseFloat( getComputedStyle( document.documentElement ).fontSize ) || 16;
			const bottomSpacing = sideGutterRaw.endsWith( 'rem' )
				? parseFloat( sideGutterRaw ) * rootFontSize
				: parseFloat( sideGutterRaw ) || 16;
			const available = window.innerHeight - rect.top - headerPadding - logoRowHeight - bottomSpacing;
			header.style.setProperty(
				'--menu-max-height',
				Math.max( 200, Math.floor( available ) ) + 'px'
			);
		};

		// The header's natural margin-top before any scrolling state is applied.
		// Used by calculateFixedOffset so the fixed position matches normal-flow position.
		// Recalculated on breakpoint change (mt-2 on mobile, mt-8 on desktop).
		let naturalMarginTop = parseInt( window.getComputedStyle( header ).marginTop, 10 ) || 0;

		let lastScrollY = window.scrollY;
		let scrollingUpActive = false;
		let scrollingDownActive = false;

		// Header's absolute position in the document (top edge, in px from page top).
		// Captured when the header is in normal flow; used by calculateStartHeight.
		let headerDocumentTop = header.getBoundingClientRect().top + window.scrollY;

		// Start height: the scrollY at which the header should enter fixed mode.
		// Calculated so the header's visual position matches --fixed-offset at transition,
		// eliminating any jump.
		const calculateStartHeight = () => {
			const fixedOffset = parseFloat(
				header.style.getPropertyValue( '--fixed-offset' ) || '0'
			);
			const startHeight = Math.max(
				0,
				Math.floor( headerDocumentTop - fixedOffset )
			);
			header.style.setProperty(
				'--start-height',
				startHeight + 'px'
			);
			return startHeight;
		};

		// Fixed offset calculation
		const addFixedOffsetsRecursively = ( element ) => {
			let offset = 0;
			const style = window.getComputedStyle( element );
			if ( style.position === 'fixed' ) {
				const rect = element.getBoundingClientRect();
				if ( rect.height > 0 && style.display !== 'none' ) {
					const visible = Math.max(
						0,
						Math.min( rect.bottom, window.innerHeight ) -
							Math.max( rect.top, 0 )
					);
					offset += visible;
					const adminBar =
						document.querySelector( '#wpadminbar' );
					if ( adminBar ) {
						const adminBarHeight = adminBar.offsetHeight;
						if ( offset === adminBarHeight ) {
							offset -= adminBarHeight;
						}
					}
				}
				return offset;
			}
			Array.from( element.children ).forEach( ( child ) => {
				offset += addFixedOffsetsRecursively( child );
			} );
			return offset;
		};

		const calculateFixedOffset = () => {
			let offset = 0;
			let node = header.previousElementSibling;
			while ( node ) {
				offset += addFixedOffsetsRecursively( node );
				node = node.previousElementSibling;
			}
			const adminBar = document.querySelector( '#wpadminbar' );
			if ( adminBar ) {
				offset += adminBar.offsetHeight;
			}
			const finalOffset = Math.floor( offset ) + naturalMarginTop;
			header.style.setProperty(
				'--fixed-offset',
				finalOffset + 'px'
			);
		};

		// Scroll direction detection
		const updateScrollStatus = () => {
			// Skip scroll-direction handling while a programmatic scroll is in flight
			// (e.g. Tabs scrolling its layout into view). Prevents the header from
			// appearing unintentionally on upward programmatic scrolls.
			if ( document.documentElement.classList.contains( 'suppress-header-scroll' ) ) {
				lastScrollY = window.scrollY;
				return;
			}

			const threshold = 10;
			const scrollY = window.scrollY;
			const startHeight = parseFloat(
				header.style.getPropertyValue( '--start-height' ) || '0'
			);

			// When mobile menu is open on mobile, keep header fixed regardless of scroll direction
			const menuIsOpen = header.classList.contains( 'menu-open' );

			// Skip menu max-height recalculation on scroll — it's set once
			// when the menu opens and on resize. Recalculating on scroll
			// causes jitter from mobile browser chrome resizing.

			if ( scrollY >= startHeight ) {
				// Enter fixed mode
				if ( ! header.classList.contains( 'scrolling' ) ) {
					header.classList.remove( 'scroll-animate' );
					header.style.top = '';
					// Always enter scroll mode as visible (scrolling-up).
					// The header is still on screen at this point, so it
					// should stay visible. The first animated hide will
					// happen when the scroll-down threshold is met.
					header.classList.add( 'scrolling', 'scrolling-up' );
					scrollingUpActive = true;
					lastScrollY = scrollY;
					document.documentElement.style.setProperty(
						'--current-header-main-height',
						( isDesktop() && fixedScrollDesktop ) ||
							( ! isDesktop() && fixedScrollMobile )
							? 'var(--header-main-height)'
							: '0px'
					);
					header.offsetHeight;
					header.classList.add( 'scroll-animate' );
				} else {
					header.classList.add( 'scrolling' );
				}
				if ( scrollY < lastScrollY ) {
					if (
						! scrollingUpActive &&
						lastScrollY - scrollY > threshold
					) {
						header.classList.add( 'scrolling-up' );
						header.classList.remove( 'scrolling-down' );
						document.documentElement.style.setProperty(
							'--current-header-main-height',
							( isDesktop() && fixedScrollDesktop ) ||
								( ! isDesktop() && fixedScrollMobile )
								? 'var(--header-main-height)'
								: '0px'
						);
						scrollingUpActive = true;
						scrollingDownActive = false;
					}
					if ( scrollingUpActive ) lastScrollY = scrollY;
				} else if ( scrollY > lastScrollY ) {
					if (
						! scrollingDownActive &&
						scrollY - lastScrollY > threshold
					) {
						// Skip scroll-down hiding when mobile menu is open
						if ( ! menuIsOpen || isDesktop() ) {
							header.classList.add( 'scrolling-down' );
							header.classList.remove( 'scrolling-up' );
							document.documentElement.style.setProperty(
								'--current-header-main-height',
								( isDesktop() && hideOnScrollDownDesktop ) ||
									( ! isDesktop() &&
										hideOnScrollDownMobile )
									? '0px'
									: 'var(--header-main-height)'
							);
							scrollingDownActive = true;
							scrollingUpActive = false;
						}
					}
					if ( scrollingDownActive ) lastScrollY = scrollY;
				}
			} else {
				header.classList.remove(
					'scrolling',
					'scrolling-up',
					'scrolling-down',
					'scroll-animate'
				);
				// Restore inline top for SEP link offset in normal flow
				updateHeaderTop();
				scrollingUpActive = false;
				scrollingDownActive = false;
				lastScrollY = scrollY;
				document.documentElement.style.setProperty(
					'--current-header-main-height',
					'var(--header-main-height)'
				);
			}
		};

		// Position header-main below any in-flow siblings (e.g. SEP link on mobile).
		// header-main is absolute so it ignores sibling flow; we set top explicitly.
		const updateHeaderTop = () => {
			let offset = 0;
			let sibling = header.previousElementSibling;
			while ( sibling ) {
				const style = window.getComputedStyle( sibling );
				if ( style.display !== 'none' && style.position !== 'absolute' && style.position !== 'fixed' ) {
					offset += sibling.offsetHeight + ( parseInt( style.marginTop, 10 ) || 0 ) + ( parseInt( style.marginBottom, 10 ) || 0 );
				}
				sibling = sibling.previousElementSibling;
			}
			header.style.top = offset > 0 ? offset + 'px' : '';
		};

		// Height tracking
		const headerBlock = header.closest( '.header' );
		const updateHeaderHeight = () => {
			header.classList.add( 'transition-none!', 'resizing' );
			const height = header.offsetHeight;
			document.documentElement.style.setProperty(
				'--header-main-height',
				height + 'px'
			);
			// Ensure admin bar height is always set from the DOM element
			const adminBarEl = document.querySelector( '#wpadminbar' );
			document.documentElement.style.setProperty(
				'--wp-admin--admin-bar--height',
				adminBarEl ? adminBarEl.offsetHeight + 'px' : '0px'
			);
			// Set header margin-top so --fixed-elements-height includes the gap
			document.documentElement.style.setProperty(
				'--header-margin-top',
				naturalMarginTop + 'px'
			);
			// header-main is position:absolute so parent collapses to 0.
			// Compute total header height from the element itself:
			// offsetTop (distance from container top) + height + bottom margin.
			// Also set min-height on the wrapper so it reserves space in flow.
			if ( headerBlock ) {
				const marginBottom = parseInt( window.getComputedStyle( header ).marginBottom, 10 ) || 0;
				const totalHeight = header.offsetTop + height + marginBottom;
				document.documentElement.style.setProperty(
					'--header-height',
					totalHeight + 'px'
				);
				headerBlock.style.minHeight = totalHeight + 'px';
			}
			header.classList.remove( 'transition-none!', 'resizing' );
		};

		// Focus trapping
		const trapFocus = ( e ) => {
			const focusable = header.querySelectorAll( focusableSelector );
			if ( ! focusable.length ) return;

			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];

			if ( e.key === 'Tab' ) {
				if ( e.shiftKey ) {
					if ( document.activeElement === first ) {
						last.focus();
						e.preventDefault();
					}
				} else {
					if ( document.activeElement === last ) {
						first.focus();
						e.preventDefault();
					}
				}
			} else if ( e.key === 'Escape' ) {
				closeMenu();
				if ( menuToggle ) menuToggle.focus();
			}
		};

		// Menu open/close
		const openMenu = () => {
			// On mobile: force fixed mode BEFORE adding menu-open so the
			// menu transition target (--menu-max-height) is calculated at
			// the correct fixed position.
			if ( ! isDesktop() && fixedScrollMobile ) {
				if ( ! header.classList.contains( 'scrolling' ) ) {
					header.classList.remove( 'scroll-animate' );
					header.style.top = '';
					header.classList.add( 'scrolling', 'scrolling-up' );
					header.classList.remove( 'scrolling-down' );
					header.offsetHeight; // force reflow
					header.classList.add( 'scroll-animate' );
				} else {
					header.classList.add( 'scrolling', 'scrolling-up' );
					header.classList.remove( 'scrolling-down' );
				}
			}

			// Compute max-height at the final fixed position, before the
			// menu-open class triggers the CSS transition from max-h-0.
			updateMenuMaxHeight();

			// Now trigger menu open (starts CSS transition)
			header.classList.add( 'menu-open' );
			if ( menuToggle ) {
				menuToggle.setAttribute( 'aria-expanded', 'true' );
			}
			if ( collapsable ) {
				collapsable
					.querySelectorAll( focusableSelector )
					.forEach( ( el ) => {
						el.removeAttribute( 'inert' );
					} );
			}
			updateAriaHidden();
			header.addEventListener( 'keydown', trapFocus );
		};

		const closeMenu = () => {
			header.classList.remove( 'menu-open' );
			if ( menuToggle && ! isDesktop() ) {
				menuToggle.setAttribute( 'aria-expanded', 'false' );
			}
			if ( collapsable ) {
				collapsable
					.querySelectorAll( focusableSelector )
					.forEach( ( el ) => {
						if ( ! isDesktop() ) {
							el.setAttribute( 'inert', '' );
						} else {
							el.removeAttribute( 'inert' );
						}
					} );
			}
			updateAriaHidden();
			header.removeEventListener( 'keydown', trapFocus );

			// On mobile: if we forced scrolling for the menu overlay and the
			// user hasn't actually scrolled past the header, return to normal flow.
			if ( ! isDesktop() && fixedScrollMobile ) {
				const startHeight = calculateStartHeight();
				if ( window.scrollY <= startHeight ) {
					header.classList.remove( 'scrolling', 'scrolling-up', 'scrolling-down', 'scroll-animate' );
					updateHeaderTop();
					calculateFixedOffset();
				}
			}
		};

		// Menu toggle click
		if ( menuToggle ) {
			menuToggle.addEventListener( 'click', () => {
				if ( header.classList.contains( 'menu-open' ) ) {
					closeMenu();
				} else {
					openMenu();
				}
			} );
		}

		// Initialize (fixedOffset must be calculated before startHeight).
		// Defer to next frame to ensure CSS custom properties (--spacing etc.)
		// are resolved — avoids race condition where margin computes as 0.
		const initPositioning = () => {
			updateHeaderTop();
			naturalMarginTop = parseInt( window.getComputedStyle( header ).marginTop, 10 ) || 0;
			calculateFixedOffset();
			headerDocumentTop = header.getBoundingClientRect().top + window.scrollY;
			calculateStartHeight();
			updateHeaderHeight();
			// Set initial --current-header-main-height so --fixed-elements-height
			// reflects the header before any scroll events fire.
			document.documentElement.style.setProperty(
				'--current-header-main-height',
				'var(--header-main-height)'
			);
		};
		initPositioning();
		requestAnimationFrame( initPositioning );

		// Set initial inert state for mobile menu items
		if ( collapsable && ! isDesktop() ) {
			collapsable
				.querySelectorAll( focusableSelector )
				.forEach( ( el ) => {
					el.setAttribute( 'inert', '' );
				} );
		}
		updateAriaHidden();

		// Scroll event
		let scrollRAF = null;
		window.addEventListener(
			'scroll',
			() => {
				if ( scrollRAF ) return;
				scrollRAF = requestAnimationFrame( () => {
					updateScrollStatus();
					scrollRAF = null;
				} );
			},
			{ passive: true }
		);

		// Resize event — recalculate positioning and update scroll state
		window.addEventListener( 'resize', () => {
			const wasScrolling = header.classList.contains( 'scrolling' );
			// Temporarily exit scrolling to measure natural position
			if ( wasScrolling ) {
				header.classList.remove( 'scrolling', 'scrolling-up', 'scrolling-down', 'scroll-animate' );
				header.offsetHeight; // force reflow
			}
			updateHeaderTop();
			calculateFixedOffset();
			headerDocumentTop = header.getBoundingClientRect().top + window.scrollY;
			calculateStartHeight();
			updateHeaderHeight();
			// Reset scroll direction state so updateScrollStatus re-evaluates cleanly
			scrollingUpActive = false;
			scrollingDownActive = false;
			lastScrollY = window.scrollY;
			updateScrollStatus();
			if ( header.classList.contains( 'menu-open' ) ) {
				updateMenuMaxHeight();
			}
		} );

		// Responsive breakpoint change
		mediaQuery.addEventListener( 'change', () => {
			closeMenu();
			// Temporarily remove scrolling to recapture natural margin-top
			header.classList.remove( 'scrolling', 'scrolling-up', 'scrolling-down', 'scroll-animate' );
			scrollingUpActive = false;
			scrollingDownActive = false;
			lastScrollY = window.scrollY;
			header.offsetHeight; // force reflow
			updateHeaderTop();
			naturalMarginTop = parseInt( window.getComputedStyle( header ).marginTop, 10 ) || 0;
			calculateFixedOffset();
			headerDocumentTop = header.getBoundingClientRect().top + window.scrollY;
			calculateStartHeight();
			updateHeaderHeight();
			// Re-enter fixed mode if already scrolled past start height
			updateScrollStatus();
		} );

		// Submenu handling (single button per item, works for both desktop and mobile)
		const handleSubmenus = () => {
			// All top-level interactive items: dropdown buttons AND plain link anchors
			const allTopLevelItems = Array.from(
				header.querySelectorAll(
					'.header-main-item > button.header-main-item-button, .header-main-item > a'
				)
			);

			// Only buttons that open submenus (used for open/close logic).
			// .header-main-item-button is rendered only when an item has
			// children, so the class alone identifies submenu triggers.
			const subMenuButtons = Array.from(
				header.querySelectorAll( '.header-main-item-button' )
			);

			// Helper: close a single submenu
			const closeSubmenu = ( btn ) => {
				btn.setAttribute( 'aria-expanded', 'false' );
				const sub = btn
					.closest( '.header-main-item' )
					?.querySelector( '.header-main-item-submenu' );
				if ( sub ) {
					sub.classList.add( 'hidden' );
					sub.classList.remove( 'block' );
					sub.setAttribute( 'aria-hidden', 'true' );
				}
			};

			// Helper: open a single submenu
			const openSubmenu = ( btn ) => {
				btn.setAttribute( 'aria-expanded', 'true' );
				const sub = btn
					.closest( '.header-main-item' )
					?.querySelector( '.header-main-item-submenu' );
				if ( sub ) {
					sub.classList.remove( 'hidden' );
					sub.classList.add( 'block' );
					sub.removeAttribute( 'aria-hidden' );
				}
			};

			// Helper: close all submenus
			const closeAllSubmenus = () => {
				subMenuButtons.forEach( closeSubmenu );
			};

			// Helper: move focus to an adjacent top-level item
			const focusTopLevelItem = ( currentItem, delta ) => {
				const currentIndex = allTopLevelItems.indexOf( currentItem );
				if ( currentIndex === -1 ) return;
				const nextIndex = ( currentIndex + delta + allTopLevelItems.length ) % allTopLevelItems.length;
				allTopLevelItems[ nextIndex ]?.focus();
			};

			subMenuButtons.forEach( ( button ) => {
				const submenu = button
					.closest( '.header-main-item' )
					?.querySelector( '.header-main-item-submenu' );
				if ( ! submenu ) return;

				// Submenu focusable items — <a>/<button> inside header-sub-item <li>s.
				// (<li> elements without tabindex cannot receive programmatic .focus().)
				const items = Array.from( submenu.querySelectorAll( submenuItemSelector ) );

				// Keyboard handler for submenu navigation (attached once during init)
				// Re-queries items on each keydown to avoid stale refs after DOM changes
				const handleSubmenuKeydown = ( e ) => {
					const isOpen = button.getAttribute( 'aria-expanded' ) === 'true';
					if ( ! isOpen ) return;

					// Re-query focusable items to avoid stale refs
					const currentItems = Array.from(
						submenu.querySelectorAll( submenuItemSelector )
					);

					switch ( e.key ) {
						case 'Home':
							e.preventDefault();
							currentItems[ 0 ]?.focus();
							break;
						case 'End':
							e.preventDefault();
							currentItems[ currentItems.length - 1 ]?.focus();
							break;
						case 'ArrowLeft':
							e.preventDefault();
							closeSubmenu( button );
							focusTopLevelItem( button, -1 );
							break;
						case 'ArrowRight':
							e.preventDefault();
							closeSubmenu( button );
							focusTopLevelItem( button, 1 );
							break;
						case 'Escape':
							closeSubmenu( button );
							button.focus();
							break;
					}
				};

				// Attach keyboard handler to submenu (always active)
				submenu.addEventListener( 'keydown', handleSubmenuKeydown );

				// Keyboard support on the top-level button
				button.addEventListener( 'keydown', ( e ) => {
					const isOpen =
						button.getAttribute( 'aria-expanded' ) === 'true';

					switch ( e.key ) {
						case 'ArrowDown':
							e.preventDefault();
							if ( ! isOpen ) {
								// On desktop: close others first, then open and focus first item
								if ( isDesktop() ) closeAllSubmenus();
								openSubmenu( button );
								// Defer focus until after display:none is removed
								requestAnimationFrame( () => {
									const freshItems = Array.from(
										submenu.querySelectorAll( submenuItemSelector )
									);
									freshItems[ 0 ]?.focus();
								} );
							} else {
								items[ 0 ]?.focus();
							}
							break;
						case 'ArrowUp':
							e.preventDefault();
							if ( ! isOpen ) {
								if ( isDesktop() ) closeAllSubmenus();
								openSubmenu( button );
								// Defer focus until after display:none is removed
								requestAnimationFrame( () => {
									const freshItems = Array.from(
										submenu.querySelectorAll( submenuItemSelector )
									);
									freshItems[ freshItems.length - 1 ]?.focus();
								} );
							} else {
								items[ items.length - 1 ]?.focus();
							}
							break;
						case 'ArrowLeft':
							e.preventDefault();
							if ( isOpen ) closeSubmenu( button );
							focusTopLevelItem( button, -1 );
							break;
						case 'ArrowRight':
							e.preventDefault();
							if ( isOpen ) closeSubmenu( button );
							focusTopLevelItem( button, 1 );
							break;
						case 'Home':
							e.preventDefault();
							allTopLevelItems[ 0 ]?.focus();
							break;
						case 'End':
							e.preventDefault();
							allTopLevelItems[ allTopLevelItems.length - 1 ]?.focus();
							break;
						case 'Escape':
							if ( isOpen ) {
								closeSubmenu( button );
								button.focus();
							}
							break;
					}
				} );

				button.addEventListener( 'click', () => {
					const isOpen =
						button.getAttribute( 'aria-expanded' ) === 'true';

					// On desktop, close all other submenus (only one open at a time)
					if ( isDesktop() ) {
						subMenuButtons.forEach( ( otherBtn ) => {
							if ( otherBtn !== button ) {
								closeSubmenu( otherBtn );
							}
						} );
					}

					if ( isOpen ) {
						closeSubmenu( button );
					} else {
						openSubmenu( button );
					}
				} );
			} );

			// Add Left/Right/Home/End keyboard navigation to plain link items
			allTopLevelItems.forEach( ( item ) => {
				// Skip items that are submenu buttons (already handled above)
				if ( item.classList.contains( 'header-main-item-button' ) ) return;

				item.addEventListener( 'keydown', ( e ) => {
					switch ( e.key ) {
						case 'ArrowLeft':
							e.preventDefault();
							focusTopLevelItem( item, -1 );
							break;
						case 'ArrowRight':
							e.preventDefault();
							focusTopLevelItem( item, 1 );
							break;
						case 'Home':
							e.preventDefault();
							allTopLevelItems[ 0 ]?.focus();
							break;
						case 'End':
							e.preventDefault();
							allTopLevelItems[ allTopLevelItems.length - 1 ]?.focus();
							break;
					}
				} );
			} );

			// Close all submenus when the user starts scrolling
			window.addEventListener( 'scroll', () => {
				closeAllSubmenus();
			}, { passive: true } );

			// Close submenus when clicking outside the dropdown panel or its trigger button
			document.addEventListener( 'click', ( e ) => {
				subMenuButtons.forEach( ( btn ) => {
					const sub = btn
						.closest( '.header-main-item' )
						?.querySelector( '.header-main-item-submenu' );
					const isOpen = btn.getAttribute( 'aria-expanded' ) === 'true';
					if ( ! isOpen ) return;

					// Keep open if click is inside the submenu panel or on/inside the trigger button
					const clickedInsideSubmenu = sub && sub.contains( e.target );
					const clickedTrigger = btn === e.target || btn.contains( e.target );
					if ( ! clickedInsideSubmenu && ! clickedTrigger ) {
						closeSubmenu( btn );
					}
				} );
			} );
		};

		handleSubmenus();
	} );
} )();
