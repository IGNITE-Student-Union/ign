import { __ } from "@wordpress/i18n";
import { useState, useEffect, useRef } from "@wordpress/element";
import { useSelect } from "@wordpress/data";
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	BlockControls,
	MediaUpload,
	// @ts-ignore
	MediaReplaceFlow,
	store as blockEditorStore
} from "@wordpress/block-editor";
import {
	PanelBody,
	BaseControl,
	ToggleControl,
	ToolbarGroup,
	ToolbarButton,
	Button
} from "@wordpress/components";
import { image as imageIcon, trash } from "@wordpress/icons";
import { shouldDisplay, cn, fetchMedia, addButtonToBlock } from "@taktdev/utilities";
import {
	Appender,
	ImageDropUploader,
	ImageOrInlineSvg,
	AdvancedRichText,
	Link
} from "@taktdev/components";

addButtonToBlock("takt/header", __("Header Settings", "takt"));

import { ReactComponent as SearchIcon } from "./resources/SearchIcon.svg";
import { ReactComponent as ExternalArrow } from "./resources/ExternalArrow.svg";
import { ReactComponent as MainMenuOpen } from "./resources/MainMenuOpen.svg";
import { ReactComponent as MainMenuClose } from "./resources/MainMenuClose.svg";

type SepLink = {
	title: string;
	url: string;
	opensInNewTab: boolean;
};

type MobileCtaLink = {
	title: string;
	url: string;
	variation: string;
};

type HeaderAttributes = {
	anchor?: string;
	logoId?: number;
	sepLink: SepLink;
	showSearch: boolean;
	searchPlaceholder: string;
	mobileCtaText: string;
	mobileCtaLink: MobileCtaLink;
	fixedOnScrollDesktop: boolean;
	hideOnScrollDownDesktop: boolean;
	fixedOnScrollMobile: boolean;
	hideOnScrollDownMobile: boolean;
};

type EditProps = {
	attributes: HeaderAttributes;
	setAttributes: (attrs: Partial<HeaderAttributes>) => void;
	clientId: string;
};

export default function Edit({ attributes, setAttributes, clientId }: EditProps) {
	const { displayContent } = shouldDisplay();
	const [isMenuOpen, setIsMenuOpen] = useState(false);
	const [submenuSpacerHeight, setSubmenuSpacerHeight] = useState(0);
	const headerRef = useRef<HTMLDivElement>(null);
	const logo = fetchMedia(attributes.logoId || null);

	// Listen for submenu height changes from HeaderItem children.
	// Track active clientId to ignore stale close events when switching submenus.
	const activeSubmenuRef = useRef<string | null>(null);
	useEffect(() => {
		const doc = headerRef.current?.ownerDocument;
		if (!doc) return;
		const handler = (e: Event) => {
			const { clientId: itemId, height } = (e as CustomEvent).detail;
			if (height > 0) {
				activeSubmenuRef.current = itemId;
				setSubmenuSpacerHeight(height);
			} else if (itemId === activeSubmenuRef.current) {
				activeSubmenuRef.current = null;
				setSubmenuSpacerHeight(0);
			}
		};
		doc.addEventListener("header-submenu-change", handler);
		return () => doc.removeEventListener("header-submenu-change", handler);
	}, []);

	const hasInnerBlocks = useSelect(
		(select) => {
			const blocks = (select(blockEditorStore) as any).getBlocks(clientId);
			return blocks.length > 0;
		},
		[clientId]
	);

	const hasSepLink = !!attributes.sepLink?.url && !!attributes.sepLink?.title;
	const hasMobileCtaText = !!attributes.mobileCtaText;
	const hasMobileCtaLink = !!attributes.mobileCtaLink?.url && !!attributes.mobileCtaLink?.title;
	const hasMobileFooter = hasMobileCtaText || hasMobileCtaLink;

	const anchor = attributes.anchor || "header-main";

	const innerBlocksProps = useInnerBlocksProps(
		{
			className:
				"flex md:flex-row md:gap-4 lg:gap-6 xl:gap-8 md:items-start md:justify-end md:flex-wrap max-md:flex-col max-md:gap-8 max-md:w-full list-none p-0 m-0"
		},
		{
			allowedBlocks: ["takt/header-item"],
			template: [["takt/header-item", {}]],
			renderAppender: false,
			orientation: "horizontal" as const
		}
	);

	useEffect(() => {
		const iframe = document.querySelector(
			".block-editor-iframe__container iframe"
		) as HTMLIFrameElement | null;
		if (!iframe) {
			return;
		}

		const onLoad = () => {
			const iframeDoc = iframe.contentDocument;
			const iframeWin = iframe.contentWindow;
			if (!iframeDoc || !iframeWin) {
				return;
			}

			const header = iframeDoc.querySelector(".header-main") as HTMLElement | null;
			if (!header) {
				return;
			}

			const headerBlock = header.closest(".header") as HTMLElement | null;

			// Measure header and set CSS variables so PostHero negative margin works.
			// Re-runs on iframe resize to handle viewport preview changes.
			const updateHeaderHeight = () => {
				const wasScrolling = header.classList.contains("scrolling");
				if (wasScrolling) {
					header.style.transition = "none";
					header.classList.remove("scrolling", "scrolling-up", "scrolling-down");
					header.offsetHeight;
				}

				const mt = parseInt(iframeWin.getComputedStyle(header).marginTop, 10) || 0;
				const mb = parseInt(iframeWin.getComputedStyle(header).marginBottom, 10) || 0;
				const h = header.offsetHeight;
				const totalHeight = header.offsetTop + h + mb;

				iframeDoc.documentElement.style.setProperty("--header-height", totalHeight + "px");
				iframeDoc.documentElement.style.setProperty("--header-main-height", h + "px");
				header.style.setProperty("--fixed-offset", mt + "px");

				if (headerBlock) {
					headerBlock.style.minHeight = totalHeight + "px";
				}

				if (wasScrolling) {
					header.classList.add("scrolling");
					header.offsetHeight;
					header.style.transition = "";
				}
			};

			updateHeaderHeight();

			// Re-measure when header dimensions change (fonts loading, blocks rendering, etc.)
			const headerObserver = new ResizeObserver(() => updateHeaderHeight());
			headerObserver.observe(header);
			if (headerBlock) {
				headerObserver.observe(headerBlock);
			}

			// Dynamic start-height: recalculated each frame based on sibling heights.
			// Adapts to viewport changes (e.g. SEP link visible on mobile, hidden on desktop).
			const calculateStartHeight = () => {
				let startHeight = 0;
				let node = header.previousElementSibling;
				while (node) {
					startHeight += (node as HTMLElement).offsetHeight || 0;
					node = node.previousElementSibling;
				}
				startHeight -= parseInt(iframeWin!.getComputedStyle(header).marginTop, 10) || 0;
				return Math.max(0, startHeight);
			};

			let lastScrollY = 0;
			let scrollingUpActive = false;
			let scrollingDownActive = false;

			const updateScrollStatus = () => {
				const threshold = 10;
				const scrollY = iframeWin!.scrollY;
				const startHeight = calculateStartHeight();

				if (scrollY > startHeight) {
					if (!header.classList.contains("scrolling")) {
						header.style.transition = "none";
						header.classList.add("scrolling");
						header.offsetHeight;
						header.style.transition = "";
					}
					if (scrollY < lastScrollY) {
						if (!scrollingUpActive && lastScrollY - scrollY > threshold) {
							header.classList.add("scrolling-up");
							header.classList.remove("scrolling-down");
							scrollingUpActive = true;
							scrollingDownActive = false;
						}
						if (scrollingUpActive) {
							lastScrollY = scrollY;
						}
					} else if (scrollY > lastScrollY) {
						if (!scrollingDownActive && scrollY - lastScrollY > threshold) {
							header.classList.add("scrolling-down");
							header.classList.remove("scrolling-up");
							scrollingDownActive = true;
							scrollingUpActive = false;
						}
						if (scrollingDownActive) {
							lastScrollY = scrollY;
						}
					}
				} else {
					header.classList.remove("scrolling", "scrolling-up", "scrolling-down");
					scrollingUpActive = false;
					scrollingDownActive = false;
					lastScrollY = scrollY;
				}
			};

			let scrollRAF: number | null = null;
			const onScroll = () => {
				if (scrollRAF) {
					return;
				}
				scrollRAF = requestAnimationFrame(() => {
					updateScrollStatus();
					scrollRAF = null;
				});
			};

			const onResize = () => {
				scrollingUpActive = false;
				scrollingDownActive = false;
				lastScrollY = iframeWin!.scrollY;
				updateHeaderHeight();
				updateScrollStatus();
			};

			iframeWin!.addEventListener("scroll", onScroll, {
				passive: true
			});
			iframeWin!.addEventListener("resize", onResize);
			return () => {
				iframeWin!.removeEventListener("scroll", onScroll);
				iframeWin!.removeEventListener("resize", onResize);
				headerObserver.disconnect();
			};
		};

		if (iframe.contentDocument?.readyState === "complete") {
			return onLoad();
		}
		iframe.addEventListener("load", onLoad);
		return () => iframe.removeEventListener("load", onLoad);
	}, [
		attributes.fixedOnScrollDesktop,
		attributes.hideOnScrollDownDesktop,
		attributes.fixedOnScrollMobile,
		attributes.hideOnScrollDownMobile
	]);

	return (
		<>
			<BlockControls>
				{!logo && (
					<ToolbarGroup>
						<MediaUpload
							onSelect={(image: { id: number }) => setAttributes({ logoId: image.id })}
							allowedTypes={["image"]}
							render={({ open }) => (
								<ToolbarButton icon={imageIcon} label={__("Add Logo", "takt")} onClick={open} />
							)}
						/>
					</ToolbarGroup>
				)}
				{!!logo && (
					<ToolbarGroup>
						<MediaReplaceFlow
							name={__("Replace Logo", "takt")}
							mediaId={attributes.logoId}
							mediaURL={logo.source_url}
							allowedTypes={["image"]}
							accept="image/*"
							onSelect={(image: { id: number }) => setAttributes({ logoId: image.id })}
						/>
						<ToolbarButton
							icon={trash}
							label={__("Remove Logo", "takt")}
							onClick={() => setAttributes({ logoId: undefined })}
						/>
					</ToolbarGroup>
				)}
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__("Logo Settings", "takt")} initialOpen={true}>
					<BaseControl id="header-logo-control" label={__("Logo", "takt")} __nextHasNoMarginBottom>
						<MediaUpload
							onSelect={(image: { id: number }) => setAttributes({ logoId: image.id })}
							allowedTypes={["image"]}
							value={attributes.logoId}
							render={({ open }) => (
								<div>
									{logo ? (
										<div>
											<ImageOrInlineSvg
												attachment={logo}
												style={{
													maxWidth: "100%"
												}}
											/>
											<Button
												variant="secondary"
												onClick={() =>
													setAttributes({
														logoId: undefined
													})
												}
												style={{
													marginTop: "10px",
													width: "100%"
												}}
												className="justify-center"
											>
												{__("Remove Logo", "takt")}
											</Button>
										</div>
									) : (
										<Button
											variant="primary"
											onClick={open}
											style={{ width: "100%" }}
											className="justify-center"
										>
											{__("Select Logo", "takt")}
										</Button>
									)}
								</div>
							)}
						/>
					</BaseControl>
				</PanelBody>

				<PanelBody title={__("Behaviour", "takt")} initialOpen={true}>
					<ToggleControl
						label={__("Show search", "takt")}
						checked={attributes.showSearch}
						onChange={(value) => setAttributes({ showSearch: value })}
						__nextHasNoMarginBottom
					/>
					<BaseControl
						id="header-behaviour-desktop"
						label={__("Desktop", "takt")}
						__nextHasNoMarginBottom
					>
						<ToggleControl
							label={__("Fixed on top of the screen", "takt")}
							checked={attributes.fixedOnScrollDesktop}
							onChange={(value) =>
								setAttributes({
									fixedOnScrollDesktop: value,
									hideOnScrollDownDesktop: !value ? false : attributes.hideOnScrollDownDesktop
								})
							}
							__nextHasNoMarginBottom
						/>
						{attributes.fixedOnScrollDesktop && (
							<ToggleControl
								label={__("Hide when scrolling down", "takt")}
								checked={attributes.hideOnScrollDownDesktop}
								onChange={(value) =>
									setAttributes({
										hideOnScrollDownDesktop: value
									})
								}
								__nextHasNoMarginBottom
							/>
						)}
					</BaseControl>
					<BaseControl
						id="header-behaviour-mobile"
						label={__("Mobile", "takt")}
						__nextHasNoMarginBottom
					>
						<ToggleControl
							label={__("Fixed on top of the screen", "takt")}
							checked={attributes.fixedOnScrollMobile}
							onChange={(value) =>
								setAttributes({
									fixedOnScrollMobile: value,
									hideOnScrollDownMobile: !value ? false : attributes.hideOnScrollDownMobile
								})
							}
							__nextHasNoMarginBottom
						/>
						{attributes.fixedOnScrollMobile && (
							<ToggleControl
								label={__("Hide when scrolling down", "takt")}
								checked={attributes.hideOnScrollDownMobile}
								onChange={(value) =>
									setAttributes({
										hideOnScrollDownMobile: value
									})
								}
								__nextHasNoMarginBottom
							/>
						)}
					</BaseControl>
				</PanelBody>
			</InspectorControls>

			<div
				{...useBlockProps({
					className: "header not-discourse"
				})}
			>
				<div className="container relative flex flex-col md:gap-1 lg:gap-2">
					{/* SEP Mobile Button (above nav bar) */}
					{(displayContent || hasSepLink) && (
						<div
							className={cn({
								"md:hidden relative z-100 flex items-center justify-center gap-3 text-white py-4 mt-[calc(var(--side-gutter)/2)] no-underline! font-sans font-medium text-base leading-[1.16] transition-colors hover:text-neon-green focus-visible:text-neon-green focus-visible:outline-dotted focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2 dark-surface before:absolute before:bg-charcoal before:rounded-full before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 before:border before:border-charcoal md:before:-inset-x-(--bg-extend)": true,
								"opacity-50": !hasSepLink
							})}
						>
							<Link
								link={attributes.sepLink}
								onChange={(value) =>
									setAttributes({
										sepLink: { ...attributes.sepLink, ...value }
									})
								}
								placeholder={__("Call to Action", "takt")}
								validateLink={true}
								tagName="span"
								className="font-sans font-medium text-base leading-[1.16] no-underline! text-inherit"
							/>
							<ExternalArrow className="w-3 h-3 shrink-0" />
						</div>
					)}

					{/* Nav Bar */}
					<div
						ref={headerRef}
						className={cn({
							"header-main relative z-200 rounded-[25px] py-4 w-full mt-2 md:mt-4 lg:mt-8 mb-2 md:mb-4 lg:mb-8 [&.scrolling]:mt-0 [&.scrolling]:mb-0 [&.scrolling.scroll-animate]:transition-[translate,opacity] [&.scrolling.scroll-animate]:duration-300 dark-surface before:absolute before:bg-charcoal before:rounded-[25px] before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)": true,
							"md:z-200 md:[&.scrolling]:fixed! md:[&.scrolling]:top-(--fixed-offset) md:[&.scrolling]:inset-x-0 md:[&.scrolling]:mx-auto md:[&.scrolling]:w-(--max-container)":
								!!attributes.fixedOnScrollDesktop && !attributes.hideOnScrollDownDesktop,
							"md:z-200 md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:fixed! md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:top-(--fixed-offset) md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:inset-x-0 md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:mx-auto md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:w-(--max-container)":
								!!attributes.fixedOnScrollDesktop && !!attributes.hideOnScrollDownDesktop,
							"md:[&.scrolling.scrolling-down:not(.scrolling-up)]:translate-y-[calc(-100%-var(--fixed-offset))]":
								!!attributes.hideOnScrollDownDesktop,
							"max-md:z-200 max-md:[&.scrolling]:fixed! max-md:[&.scrolling]:top-(--fixed-offset) max-md:[&.scrolling]:inset-x-0 max-md:[&.scrolling]:mx-auto max-md:[&.scrolling]:w-(--max-container)":
								!!attributes.fixedOnScrollMobile && !attributes.hideOnScrollDownMobile,
							"max-md:z-200 max-md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:fixed! max-md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:top-(--fixed-offset) max-md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:inset-x-0 max-md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:mx-auto max-md:[&.scrolling:is(.scrolling-down,.scrolling-up)]:w-(--max-container)":
								!!attributes.fixedOnScrollMobile && !!attributes.hideOnScrollDownMobile,
							"max-md:[&.scrolling.scrolling-down:not(.scrolling-up)]:translate-y-[calc(-100%-var(--fixed-offset))]":
								!!attributes.hideOnScrollDownMobile,
							"menu-open": isMenuOpen
						})}
						data-fixed-scroll-desktop={attributes.fixedOnScrollDesktop ? "1" : "0"}
						data-fixed-scroll-mobile={attributes.fixedOnScrollMobile ? "1" : "0"}
						data-hide-scroll-down-desktop={attributes.hideOnScrollDownDesktop ? "1" : "0"}
						data-hide-scroll-down-mobile={attributes.hideOnScrollDownMobile ? "1" : "0"}
					>
						{/* Nav bar inner */}
						<div className="flex items-stretch justify-between max-md:flex-wrap">
							{/* Logo */}
							{!logo && (
								<ImageDropUploader
									image={attributes.logoId}
									onSelect={(image) =>
										setAttributes({
											logoId: image.id ?? undefined
										})
									}
									className="flex items-center self-stretch text-white w-auto shrink-0 max-md:max-h-[26px] md:max-h-[36px] lg:max-h-[58px] [&>*]:h-full [&>*]:w-auto [&_svg]:h-full [&_svg]:w-auto [&_img]:h-full [&_img]:w-auto [&_img]:object-contain focus-visible:outline-dotted focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2"
									imageClassName="h-full w-auto"
									placeholderClassName="h-full w-auto"
								/>
							)}
							{!!logo && (
								<a
									href="/"
									title={__("Home", "takt")}
									aria-label={__("Home", "takt")}
									onClick={(e) => e.preventDefault()}
									className="flex items-center self-stretch text-white w-auto shrink-0 max-md:max-h-[26px] md:max-h-[36px] lg:max-h-[58px] [&>*]:h-full [&>*]:w-auto [&_svg]:h-full [&_svg]:w-auto [&_img]:h-full [&_img]:w-auto [&_img]:object-contain focus-visible:outline-dotted focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2"
								>
									<ImageOrInlineSvg attachment={logo} />
								</a>
							)}

							{/* Right column: utility row (desktop only) + nav (desktop and mobile) */}
							<div className="flex flex-col md:gap-3 lg:gap-6 items-end shrink min-w-0 max-md:w-full max-md:order-last">
								{/* Utility Row (desktop only) */}
								<div className="hidden md:flex gap-4 items-center justify-end">
									{/* Search Box */}
									{attributes.showSearch && (
										<div className="relative w-[180px] lg:w-[250px]">
											<div className="flex items-center gap-2 border-b border-white pb-1">
												<span className="shrink-0 text-white">
													<SearchIcon className="w-[19.2px] h-[19.2px] text-white" />
												</span>
												<AdvancedRichText
													tagName="span"
													value={attributes.searchPlaceholder}
													onChange={(value) => setAttributes({ searchPlaceholder: value })}
													placeholder={__("SEARCH", "takt")}
													allowedFormats={[]}
													className="font-sans font-medium text-sm leading-[1.16] text-white/60 uppercase w-full"
												/>
											</div>
										</div>
									)}

									{/* Vertical Separator */}
									{(displayContent || hasSepLink) && (
										<span className="w-px h-5 bg-white shrink-0" />
									)}

									{/* SEP Link (Desktop) */}
									{(displayContent || hasSepLink) && (
										<div
											className={cn({
												"hidden md:flex items-center gap-2 no-underline! font-sans font-medium text-sm lg:text-base leading-[1.16] text-white transition-colors hover:text-neon-green focus-visible:text-neon-green focus-visible:outline-dotted focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2": true,
												"opacity-50": !hasSepLink
											})}
										>
											<Link
												link={attributes.sepLink}
												onChange={(value) =>
													setAttributes({
														sepLink: { ...attributes.sepLink, ...value }
													})
												}
												placeholder={__("Call to Action", "takt")}
												validateLink={true}
												tagName="span"
												className="font-sans font-medium text-base leading-[1.16] no-underline! text-inherit"
											/>
											<ExternalArrow className="w-3 h-3 shrink-0" />
										</div>
									)}
								</div>

								{/* Collapsable container — holds nav, mobile search, mobile CTA. */}
								{/* Search and CTA are siblings of <nav> so the navigation landmark */}
								{/* contains only links (audit check #31). */}
								<div
									id={`${anchor}-site-header-collapsable`}
									className={cn({
										"header-collapsable flex flex-col gap-8 w-full md:items-end": true,
										"max-md:transition-[max-height,opacity,padding] max-md:duration-500 max-md:ease-in-out max-md:max-h-(--menu-max-height) max-md:opacity-100 max-md:pointer-events-auto max-md:overflow-y-auto max-md:overflow-x-hidden max-md:pt-8 max-md:pb-8":
											isMenuOpen,
										"max-md:max-h-0 max-md:opacity-0 max-md:pointer-events-none max-md:overflow-hidden":
											!isMenuOpen
									})}
									tabIndex={-1}
								>
									{/* Mobile Divider */}
									<hr className="md:hidden border-t border-white/20 w-full my-0" />

									{/* Main Navigation (links only) */}
									<nav
										aria-label={__("Main Navigation", "takt")}
										className="contents md:flex md:justify-end md:w-full"
									>
										<div className="relative">
											<Appender />
											<ul {...innerBlocksProps} />
										</div>
									</nav>

									{/* Mobile Search */}
									{attributes.showSearch && (
										<div className="md:hidden relative w-full">
											<div className="flex items-center gap-2 border-b border-white pb-1">
												<span className="shrink-0 text-white">
													<SearchIcon className="w-[19.2px] h-[19.2px] text-white" />
												</span>
												<AdvancedRichText
													tagName="span"
													value={attributes.searchPlaceholder}
													onChange={(value) => setAttributes({ searchPlaceholder: value })}
													placeholder={__("SEARCH", "takt")}
													allowedFormats={[]}
													className="font-sans font-medium text-sm leading-[1.16] text-white/60 uppercase w-full"
												/>
											</div>
										</div>
									)}

									{/* Mobile Footer Section */}
									{(displayContent || hasMobileFooter) && (
										<div
											className={cn({
												"dark bg-transparent md:hidden flex flex-col gap-4 items-start": true,
												"opacity-50": !hasMobileFooter
											})}
										>
											{(displayContent || hasMobileCtaText) && (
												<AdvancedRichText
													className="font-sans font-medium text-base leading-[1.5] text-white"
													tagName="p"
													value={attributes.mobileCtaText}
													allowedFormats={[]}
													onChange={(value) =>
														setAttributes({
															mobileCtaText: value
														})
													}
													placeholder={__("Mobile CTA text…", "takt")}
												/>
											)}
											{(displayContent || hasMobileCtaLink) && (
												<Link
													link={attributes.mobileCtaLink}
													onChange={(value) =>
														setAttributes({
															mobileCtaLink: { ...attributes.mobileCtaLink, ...value }
														})
													}
													placeholder={__("Contact Us", "takt")}
													validateLink={true}
													className={cn({
														"btn-primary": true,
														"opacity-50": !hasMobileCtaLink
													})}
												/>
											)}
										</div>
									)}
								</div>
							</div>

							{/* Mobile Toggle */}
							{hasInnerBlocks && (
								<button
									type="button"
									className="md:hidden cursor-pointer w-7 ml-auto flex items-center justify-end transition-colors text-white focus-visible:outline-dotted focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-2"
									onClick={() => setIsMenuOpen(!isMenuOpen)}
									aria-controls={`${anchor}-site-header-collapsable`}
									aria-expanded={isMenuOpen}
									aria-label={__("Toggle Main Menu", "takt")}
								>
									<span
										className={cn({
											block: !isMenuOpen,
											hidden: isMenuOpen
										})}
									>
										<MainMenuOpen className="w-full text-white" />
									</span>
									<span
										className={cn({
											hidden: !isMenuOpen,
											block: isMenuOpen
										})}
									>
										<MainMenuClose className="w-full text-white" />
									</span>
								</button>
							)}
						</div>
					</div>
				</div>
				{/* Spacer: in-flow element outside header-main so the editor iframe grows to fit absolute submenus */}
				{submenuSpacerHeight > 0 && (
					<div
						aria-hidden="true"
						className="hidden md:block"
						style={{ height: submenuSpacerHeight }}
					/>
				)}
			</div>
		</>
	);
}
