<?php
/**
 * Header block frontend template.
 *
 * Available variables (auto-extracted from $attributes):
 * @var int|null $logoId              Logo image ID
 * @var array    $sepLink             External link (title, url, opensInNewTab)
 * @var bool     $showSearch           Whether to display the search form
 * @var string   $searchPlaceholder   Search input placeholder text
 * @var string   $mobileCtaText       Mobile CTA text
 * @var array    $mobileCtaLink       Mobile CTA link (title, url, variation)
 * @var bool     $fixedOnScrollDesktop      Fixed on scroll (desktop)
 * @var bool     $hideOnScrollDownDesktop   Hide when scrolling down (desktop)
 * @var bool     $fixedOnScrollMobile       Fixed on scroll (mobile)
 * @var bool     $hideOnScrollDownMobile    Hide when scrolling down (mobile)
 * @var string   $anchor              Block anchor ID
 *
 * Also available:
 * @var string   $children  Inner blocks content
 * @var WP_Block $block     Block instance
 */

// Set defaults for attributes without block.json defaults
$logoId = $logoId ?? null;
$fixedOnScrollDesktop    = ! empty( $fixedOnScrollDesktop );
$hideOnScrollDownDesktop = ! empty( $hideOnScrollDownDesktop );
$fixedOnScrollMobile     = ! empty( $fixedOnScrollMobile );
$hideOnScrollDownMobile  = ! empty( $hideOnScrollDownMobile );
$anchor                  = $anchor ?? 'header-main';
$searchPlaceholder       = ! empty( $searchPlaceholder ) ? $searchPlaceholder : __( 'SEARCH', 'takt' );

// Derived variables
$hasSepLink       = ! empty( $sepLink['url'] ) && ! empty( $sepLink['title'] );
$showSearch       = ! empty( $showSearch );
$hasMobileCta     = ! empty( $mobileCtaText ) || ( ! empty( $mobileCtaLink['url'] ) && ! empty( $mobileCtaLink['title'] ) );
$hasMobileCtaLink = ! empty( $mobileCtaLink['url'] ) && ! empty( $mobileCtaLink['title'] );
$hasChildren      = ! empty( $children );
?>

<div <?php theme_block_props( 'header' ); ?>>
	<div class="container relative flex flex-col md:gap-1 lg:gap-2">
		<?php // SEP Mobile Button (above nav bar) ?>
		<?php if ( $hasSepLink ) : ?>
			<a
				href="<?php echo esc_url( $sepLink['url'] ); ?>"
				<?php if ( ! empty( $sepLink['opensInNewTab'] ) ) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
				class="md:hidden relative z-100 flex items-center justify-center gap-3 text-white py-4 mt-[calc(var(--side-gutter)/2)] no-underline! font-sans font-medium text-base leading-[1.16] transition-colors hover:text-neon-green focus-visible:text-neon-green focus-visible:outline-dotted focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2 dark-surface before:absolute before:bg-charcoal before:rounded-full before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 before:border before:border-charcoal md:before:-inset-x-(--bg-extend)"
			>
				<span><?php echo esc_html( $sepLink['title'] ); ?></span>
				<span class="w-3 h-3 shrink-0"><?php theme_block_asset( 'ExternalArrow.svg' ); ?></span>
			</a>
		<?php endif; ?>

		<?php // Nav Bar ?>
		<div class="<?php echo class_name( [
			'header-main absolute left-0 right-0 z-200 rounded-[25px] py-4 w-full mt-2 md:mt-4 lg:mt-8 mb-2 md:mb-4 lg:mb-8 [&.scrolling]:mt-0 [&.scrolling]:mb-0 [&.scrolling.scroll-animate]:transition-[translate,opacity] [&.scrolling.scroll-animate]:duration-300 dark-surface before:absolute before:bg-charcoal before:rounded-[25px] before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)' => true,
			'md:z-200 md:[&.scrolling]:fixed md:[&.scrolling]:top-(--fixed-offset) md:[&.scrolling]:inset-x-0 md:[&.scrolling]:mx-auto md:[&.scrolling]:w-(--max-container)' => $fixedOnScrollDesktop && ! $hideOnScrollDownDesktop,
			'md:z-200 md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:fixed md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:top-(--fixed-offset) md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:inset-x-0 md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:mx-auto md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:w-(--max-container)' => $fixedOnScrollDesktop && $hideOnScrollDownDesktop,
			'md:[&.scrolling.scrolling-down:not(.scrolling-up)]:translate-y-[calc(-100%-var(--fixed-offset))]' => $hideOnScrollDownDesktop,
			'max-md:z-200 max-md:[&.scrolling]:fixed max-md:[&.scrolling]:top-(--fixed-offset) max-md:[&.scrolling]:inset-x-0 max-md:[&.scrolling]:mx-auto max-md:[&.scrolling]:w-(--max-container)' => $fixedOnScrollMobile && ! $hideOnScrollDownMobile,
			'max-md:z-200 max-md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:fixed max-md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:top-(--fixed-offset) max-md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:inset-x-0 max-md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:mx-auto max-md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:w-(--max-container)' => $fixedOnScrollMobile && $hideOnScrollDownMobile,
			'max-md:[&.scrolling.scrolling-down:not(.scrolling-up)]:translate-y-[calc(-100%-var(--fixed-offset))]' => $hideOnScrollDownMobile,
		] ); ?>" data-fixed-scroll-desktop="<?php echo (int) $fixedOnScrollDesktop; ?>" data-fixed-scroll-mobile="<?php echo (int) $fixedOnScrollMobile; ?>" data-hide-scroll-down-desktop="<?php echo (int) $hideOnScrollDownDesktop; ?>" data-hide-scroll-down-mobile="<?php echo (int) $hideOnScrollDownMobile; ?>">
			<?php // Nav bar inner ?>
			<div class="flex items-stretch justify-between max-md:flex-wrap">
				<?php // Logo ?>
				<?php if ( ! empty( $logoId ) ) : ?>
					<a
						href="<?php echo esc_url( get_site_url() ); ?>"
						title="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
						aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> - <?php esc_attr_e( 'Home', 'takt' ); ?>"
						<?php if ( is_front_page() ) : ?>aria-current="page"<?php endif; ?>
						class="flex items-center self-stretch text-white w-auto *:w-auto *:max-w-full *:h-full *:object-contain shrink-0 max-md:*:max-h-[26px] md:*:max-h-[36px] lg:*:max-h-[58px] focus-visible:outline-dotted focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2"
					>
						<?php theme_output_svg_or_img( $logoId ); ?>
					</a>
				<?php endif; ?>

				<?php // Right column: utility row (desktop only) + nav (desktop and mobile) ?>
				<div class="flex flex-col md:gap-3 lg:gap-6 items-end shrink min-w-0 max-md:w-full max-md:order-last">
					<?php // Utility Row (desktop only) — JS toggles aria-hidden on mobile via [data-utility-row] ?>
					<div data-utility-row class="hidden md:flex gap-4 items-center justify-end">
						<?php // Search Box ?>
						<?php if ( $showSearch ) : ?>
							<form
								action="/"
								method="get"
								role="search"
								class="relative w-[180px] lg:w-[250px]"
							>
								<div class="flex items-center gap-2 border-b border-white pb-1">
									<button
										type="submit"
										class="bg-transparent border-0 p-0 cursor-pointer shrink-0 text-white transition-colors hover:text-neon-green focus-visible:text-neon-green focus-visible:outline-dotted focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2"
										aria-label="<?php esc_attr_e( 'Submit search', 'takt' ); ?>"
									>
										<span class="w-[19.2px] h-[19.2px] block"><?php theme_block_asset( 'SearchIcon.svg' ); ?></span>
									</button>
									<input
										type="search"
										name="s"
										aria-label="<?php esc_attr_e( 'Search', 'takt' ); ?>"
										placeholder="<?php echo esc_attr( $searchPlaceholder ); ?>"
										class="bg-transparent! border-0! rounded-none! outline-none font-sans font-medium text-sm leading-[1.16] text-white! normal-case placeholder:uppercase placeholder:text-white/60! placeholder:opacity-100! w-full min-h-0! p-0!"
									/>
								</div>
							</form>
						<?php endif; ?>

						<?php // Vertical Separator ?>
						<?php if ( $hasSepLink ) : ?>
							<span class="w-px h-5 bg-white shrink-0"></span>
						<?php endif; ?>

						<?php // Call to Action Link (Desktop) ?>
						<?php if ( $hasSepLink ) : ?>
							<a
								href="<?php echo esc_url( $sepLink['url'] ); ?>"
								<?php if ( ! empty( $sepLink['opensInNewTab'] ) ) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
								class="flex items-center gap-2 no-underline! font-sans font-medium text-sm lg:text-base leading-[1.16] text-white transition-colors hover:text-neon-green focus-visible:text-neon-green focus-visible:outline-dotted focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2"
							>
								<span><?php echo esc_html( $sepLink['title'] ); ?></span>
								<span class="w-3 h-3 shrink-0"><?php theme_block_asset( 'ExternalArrow.svg' ); ?></span>
							</a>
						<?php endif; ?>
					</div>

					<?php // Collapsable container — holds nav, mobile search form, mobile CTA.
					// The mobile search form and CTA are SIBLINGS of <nav> rather than children
					// so the navigation landmark contains only links/menu items (audit check #31). ?>
					<?php if ( $hasChildren ) : ?>
						<div
							id="<?php echo esc_attr( $anchor ); ?>-site-header-collapsable"
							class="header-collapsable flex flex-col gap-8 w-full max-md:transition-[max-height,opacity,padding] max-md:duration-500 max-md:ease-in-out max-md:max-h-0 max-md:opacity-0 max-md:pointer-events-none max-md:overflow-hidden menu-open:max-h-(--menu-max-height) menu-open:opacity-100 menu-open:pointer-events-auto menu-open:overflow-y-auto menu-open:pt-8 menu-open:pb-8 md:items-end"
							tabindex="-1"
						>
							<?php // Mobile Divider ?>
							<hr class="md:hidden border-t border-white/20 w-full my-0" />

							<?php // Main Navigation (links only — keeps the nav landmark clean) ?>
							<nav aria-label="<?php esc_attr_e( 'Main Navigation', 'takt' ); ?>" class="contents md:flex md:justify-end md:w-full">
								<ul class="flex md:flex-row md:gap-4 lg:gap-6 xl:gap-8 md:items-start md:justify-end md:flex-wrap max-md:flex-col max-md:gap-8 max-md:w-full list-none p-0 m-0">
									<?php echo $children; ?>
								</ul>
							</nav>

							<?php // Mobile Search (sibling of <nav>) ?>
							<?php if ( $showSearch ) : ?>
								<form
									action="/"
									method="get"
									role="search"
									class="md:hidden relative w-full"
								>
									<div class="flex items-center gap-2 border-b border-white pb-1">
										<button
											type="submit"
											class="bg-transparent border-0 p-0 cursor-pointer shrink-0 text-white transition-colors hover:text-neon-green focus-visible:text-neon-green focus-visible:outline-dotted focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2"
											aria-label="<?php esc_attr_e( 'Submit search', 'takt' ); ?>"
										>
											<span class="w-[19.2px] h-[19.2px] block"><?php theme_block_asset( 'SearchIcon.svg' ); ?></span>
										</button>
										<input
											type="search"
											name="s"
											aria-label="<?php esc_attr_e( 'Search', 'takt' ); ?>"
											placeholder="<?php echo esc_attr( $searchPlaceholder ); ?>"
											class="appearance-none bg-transparent! border-0! rounded-none! outline-none font-sans font-medium text-sm leading-[1.16] text-white! normal-case placeholder:uppercase placeholder:text-white/60! placeholder:opacity-100! w-full min-h-0! p-0! [-webkit-text-fill-color:currentColor] [&::-webkit-search-cancel-button]:appearance-none"
										/>
									</div>
								</form>
							<?php endif; ?>

							<?php // Mobile Footer Section (sibling of <nav>) ?>
							<?php if ( $hasMobileCta ) : ?>
								<div class="dark bg-transparent md:hidden flex flex-col gap-4 items-start">
									<?php if ( ! empty( $mobileCtaText ) ) : ?>
										<p class="font-sans font-medium text-base leading-[1.5] text-white"><?php echo wp_kses_post( $mobileCtaText ); ?></p>
									<?php endif; ?>
									<?php if ( $hasMobileCtaLink ) : ?>
										<a
											href="<?php echo esc_url( $mobileCtaLink['url'] ); ?>"
											class="btn-primary"
										>
											<?php echo esc_html( $mobileCtaLink['title'] ); ?>
										</a>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php // Mobile Toggle ?>
				<?php if ( $hasChildren ) : ?>
					<button
						type="button"
						class="md:hidden cursor-pointer w-7 ml-auto flex items-center justify-end transition-colors text-white focus-visible:outline-dotted focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2"
						aria-controls="<?php echo esc_attr( $anchor ); ?>-site-header-collapsable"
						aria-expanded="false"
						aria-label="<?php esc_attr_e( 'Toggle Main Menu', 'takt' ); ?>"
					>
						<span class="block menu-open:hidden"><?php theme_block_asset( 'MainMenuOpen.svg' ); ?></span>
						<span class="hidden menu-open:block"><?php theme_block_asset( 'MainMenuClose.svg' ); ?></span>
					</button>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
