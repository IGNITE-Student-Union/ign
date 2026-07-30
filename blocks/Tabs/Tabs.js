/**
 * Tabs - Frontend interactivity
 *
 * Architecture: Each tab's content lives in a <details> element wrapped in a container div.
 * - Desktop: A separate tablist controls which <details> is open. The wrapper div stretches
 *   full-height and receives the background color. The <details> element inside uses CSS
 *   position:sticky. Summary is visible but inert. JavaScript sets bg-accent-lighter on the
 *   active wrapper and removes it from inactive wrappers.
 * - Mobile: Native <details>/<summary> accordion behavior. Tablist is hidden. Wrapper is transparent.
 * - Resize: Transition between modes, ensuring at least one is open on desktop.
 */
document.addEventListener( 'DOMContentLoaded', () => {
  const MD_BREAKPOINT = '(min-width: 60rem)';
  const isDesktop = () => window.matchMedia( MD_BREAKPOINT ).matches;

  document.querySelectorAll( '.tabs' ).forEach( ( container ) => {
    const tablist = container.querySelector( '.tabs-tablist' );
    const panelsContainer = container.querySelector( '.tabs-panels' );
    if ( ! tablist || ! panelsContainer ) return;

    const tabButtons = Array.from( tablist.querySelectorAll( '.tabs-button' ) );
    const wrappers = Array.from( panelsContainer.querySelectorAll( ':scope > .tabs-item-wrapper' ) );
    const detailsItems = wrappers.map( ( w ) => w.querySelector( '.tabs-item' ) );

    if ( ! tabButtons.length || ! wrappers.length || ! detailsItems.length ) return;

    const baseId = container.id || 'tabs-' + Math.random().toString( 36 ).substring( 2, 9 );
    const tabsLayout = container.querySelector( '.tabs-layout' );
    let selectedTabIndex = 0;
    let currentMode = isDesktop() ? 'desktop' : 'mobile';

    // ---- Scroll Helper ----

    function getFullFixedHeight() {
      // Read the individual CSS custom properties set by Header.js.
      // These are always pixel values regardless of header visibility.
      const root = document.documentElement.style;
      const adminBar = parseFloat( root.getPropertyValue( '--wp-admin--admin-bar--height' ) ) || 0;
      const marginTop = parseFloat( root.getPropertyValue( '--header-margin-top' ) ) || 0;
      const headerMain = parseFloat( root.getPropertyValue( '--header-main-height' ) ) || 0;
      return adminBar + marginTop + headerMain;
    }

    function scrollToContentIfNeeded() {
      if ( ! tabsLayout ) return;

      requestAnimationFrame( () => {
        const fixedHeight = getFullFixedHeight();
        const layoutRect = tabsLayout.getBoundingClientRect();

        // Scroll if the tabs layout top is above or behind the fixed header
        if ( layoutRect.top < fixedHeight ) {
          const top = layoutRect.top + window.scrollY - fixedHeight;
          const html = document.documentElement;
          html.classList.add( 'suppress-header-scroll' );
          const release = () => {
            html.classList.remove( 'suppress-header-scroll' );
            window.removeEventListener( 'scrollend', release );
            clearTimeout( fallback );
          };
          const fallback = setTimeout( release, 1000 );
          window.addEventListener( 'scrollend', release, { once: true } );
          window.scrollTo( { top, behavior: 'smooth' } );
        }
      } );
    }

    // ---- ARIA Setup ----

    function setupAria() {
      tabButtons.forEach( ( btn, i ) => {
        const tabId = baseId + '-tab-' + i;
        const panelId = baseId + '-panel-' + i;

        btn.setAttribute( 'id', tabId );
        btn.setAttribute( 'role', 'tab' );
        btn.setAttribute( 'aria-controls', panelId );

        // Set panel ARIA on the content div inside each details
        const content = detailsItems[ i ]?.querySelector( '.tabs-item-content' );
        if ( content ) {
          content.setAttribute( 'id', panelId );
          content.setAttribute( 'role', 'tabpanel' );
          content.setAttribute( 'tabindex', '0' );
          content.setAttribute( 'aria-labelledby', tabId );
        }
      } );
    }

    // ---- Desktop Tab Selection ----

    const validColors = [ 'neon-green', 'blue', 'green', 'yellow', 'orange', 'purple' ];

    function selectTab( index, focus ) {
      selectedTabIndex = index;

      tabButtons.forEach( ( btn, i ) => {
        const isActive = i === index;
        btn.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
        btn.setAttribute( 'tabindex', isActive ? '0' : '-1' );
        btn.classList.toggle( 'selected', isActive );
        btn.classList.toggle( 'bg-accent', isActive );
        btn.classList.toggle( 'hover:!bg-accent', isActive );

        // Animate description reveal on active tab
        const desc = btn.querySelector( '.tabs-button-description' );
        if ( desc ) {
          if ( isActive ) {
            desc.classList.remove( 'grid-rows-[0fr]', 'opacity-0', 'mt-0' );
            desc.classList.add( 'grid-rows-[1fr]', 'opacity-100', 'mt-6' );
            desc.removeAttribute( 'aria-hidden' );
          } else {
            desc.classList.remove( 'grid-rows-[1fr]', 'opacity-100', 'mt-6' );
            desc.classList.add( 'grid-rows-[0fr]', 'opacity-0', 'mt-0' );
            desc.setAttribute( 'aria-hidden', 'true' );
          }
        }
      } );

      // Open/close details elements and set wrapper backgrounds
      detailsItems.forEach( ( details, i ) => {
        if ( i === index ) {
          details.setAttribute( 'open', '' );
        } else {
          details.removeAttribute( 'open' );
        }
      } );

      // Show active wrapper, hide others, and set background/color on active wrapper
      wrappers.forEach( ( wrapper, i ) => {
        // Clean up color classes from all wrappers
        validColors.forEach( ( c ) => wrapper.classList.remove( c ) );

        if ( i === index ) {
          // Active wrapper: show it and apply background + per-tab color
          wrapper.classList.remove( 'md:hidden' );
          wrapper.classList.add( 'md:flex', 'bg-accent-lighter' );

          // Apply per-tab color class if specified
          const tabColor = wrapper.dataset.tabColor || '';
          if ( tabColor && validColors.includes( tabColor ) ) {
            wrapper.classList.add( tabColor );
          }
        } else {
          // Inactive wrapper: hide it and remove background
          wrapper.classList.add( 'md:hidden' );
          wrapper.classList.remove( 'md:flex', 'bg-accent-lighter' );
        }
      } );

      if ( focus && tabButtons[ index ] ) {
        tabButtons[ index ].focus();
      }
    }

    // ---- Desktop Click Handlers ----

    tabButtons.forEach( ( btn, i ) => {
      btn.addEventListener( 'click', () => {
        if ( ! isDesktop() ) return;
        selectTab( i );
        scrollToContentIfNeeded();
      } );
    } );

    // ---- Desktop Keyboard Navigation (vertical tablist) ----

    tabButtons.forEach( ( btn, i ) => {
      btn.addEventListener( 'keydown', ( e ) => {
        if ( ! isDesktop() ) return;

        let targetIndex = null;

        switch ( e.key ) {
          case 'ArrowDown':
            e.preventDefault();
            targetIndex = ( i + 1 ) % tabButtons.length;
            break;
          case 'ArrowUp':
            e.preventDefault();
            targetIndex = ( i - 1 + tabButtons.length ) % tabButtons.length;
            break;
          case 'Home':
            e.preventDefault();
            targetIndex = 0;
            break;
          case 'End':
            e.preventDefault();
            targetIndex = tabButtons.length - 1;
            break;
          case 'Enter':
          case ' ':
            e.preventDefault();
            selectTab( i, true );
            return;
        }

        if ( targetIndex !== null ) {
          tabButtons[ targetIndex ].focus();
          selectTab( targetIndex );
        }
      } );
    } );

    // ---- Prevent summary click toggling on desktop + mobile scroll ----

    detailsItems.forEach( ( details ) => {
      const summary = details.querySelector( 'summary' );
      if ( ! summary ) return;

      summary.addEventListener( 'click', ( e ) => {
        if ( isDesktop() ) {
          e.preventDefault();
        }
      } );

    } );

    // ---- Desktop ARIA on content panels ----

    function setDesktopMode() {
      currentMode = 'desktop';

      // Re-enable the ARIA tabs pattern. The SSR markup ships without these roles
      // so an audit running on mobile (or before JS execution) doesn't see a tab
      // interface that isn't visually present (audit checks #51/#53/#55).
      tablist.setAttribute( 'role', 'tablist' );
      tablist.setAttribute( 'aria-orientation', 'vertical' );
      const tablistLabel = tablist.getAttribute( 'data-aria-label' );
      if ( tablistLabel ) {
        tablist.setAttribute( 'aria-label', tablistLabel );
      }
      tabButtons.forEach( ( btn ) => btn.setAttribute( 'role', 'tab' ) );
      detailsItems.forEach( ( details, i ) => {
        const content = details.querySelector( '.tabs-item-content' );
        if ( content ) {
          content.setAttribute( 'role', 'tabpanel' );
          content.setAttribute( 'tabindex', '0' );
          content.setAttribute( 'aria-labelledby', tabButtons[ i ].id );
        }
      } );

      // Ensure at least one tab is open
      const anyOpen = detailsItems.some( ( d ) => d.hasAttribute( 'open' ) );
      if ( ! anyOpen ) {
        selectedTabIndex = 0;
      }

      selectTab( selectedTabIndex );
    }

    function setMobileMode() {
      currentMode = 'mobile';

      // Show all wrappers and remove desktop background/color classes
      wrappers.forEach( ( w ) => {
        w.classList.remove( 'md:hidden', 'md:flex', 'bg-accent-lighter' );
        validColors.forEach( ( c ) => w.classList.remove( c ) );
      } );

      // The tablist is display:none on mobile and the UI degrades to a
      // native <details>/<summary> accordion. Strip the tab/tablist/
      // tabpanel roles so the markup reflects the actual interaction
      // pattern instead of advertising a tab interface that isn't there.
      tablist.removeAttribute( 'role' );
      tablist.removeAttribute( 'aria-orientation' );
      tablist.removeAttribute( 'aria-label' );
      tabButtons.forEach( ( btn ) => btn.removeAttribute( 'role' ) );
      detailsItems.forEach( ( details ) => {
        const content = details.querySelector( '.tabs-item-content' );
        if ( content ) {
          content.removeAttribute( 'role' );
          content.removeAttribute( 'aria-labelledby' );
          content.removeAttribute( 'tabindex' );
        }
      } );

      // On mobile, close all details and let user open them manually
      detailsItems.forEach( ( details ) => {
        details.removeAttribute( 'open' );
      } );
    }

    // ---- Responsive Mode Switching ----

    function handleResize() {
      const newMode = isDesktop() ? 'desktop' : 'mobile';
      if ( newMode !== currentMode ) {
        if ( newMode === 'desktop' ) {
          setDesktopMode();
        } else {
          setMobileMode();
        }
      }
    }

    window.addEventListener( 'resize', handleResize );

    // ---- Initialization ----

    setupAria();

    if ( isDesktop() ) {
      setDesktopMode();
    } else {
      setMobileMode();
    }
  } );
} );
