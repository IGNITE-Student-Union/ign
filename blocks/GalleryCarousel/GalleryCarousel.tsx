import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	InspectorControls,
	useInnerBlocksProps,
	store as blockEditorStore
} from "@wordpress/block-editor";
import { useSelect } from "@wordpress/data";
import { PanelBody, ToggleControl, RangeControl } from "@wordpress/components";
import { useRefEffect } from "@wordpress/compose";
import { Swiper } from "swiper";
import { Navigation, Pagination, Autoplay, A11y } from "swiper/modules";
import { shouldDisplay, renameBlock, cn, addButtonToBlock } from "@taktdev/utilities";

import { Appender } from "@taktdev/components";
import { ThemeHeading } from "parts/ThemeHeading";
import { ReactComponent as ArrowLeft } from "./resources/ArrowLeft.svg";
import { ReactComponent as ArrowRight } from "./resources/ArrowRight.svg";
import { ReactComponent as PauseIcon } from "./resources/Pause.svg";

addButtonToBlock("takt/gallery-carousel", __("Gallery Carousel Settings", "takt"));

type GalleryCarouselAttributes = {
	anchor?: string;
	eyebrow?: string;
	heading?: string;
	description?: string;
	buttons?: Array<{
		title?: string;
		url?: string;
		postId?: number;
		opensInNewTab?: boolean;
		label?: string;
		variation?: string;
	}>;
	showPagination: boolean;
	showNavigation: boolean;
	loopCarousel: boolean;
	autoplayCarousel: boolean;
	autoplayDelay: number;
};

type EditProps = {
	attributes: GalleryCarouselAttributes;
	setAttributes: (attrs: Partial<GalleryCarouselAttributes>) => void;
	clientId: string;
};

export default function Edit({ attributes, setAttributes, clientId }: EditProps) {
	const { displayContent, isSelected, hasInnerBlocks } = shouldDisplay();

	// Check if any child block is selected
	const isChildSelected = useSelect(
		(select) => select(blockEditorStore).hasSelectedInnerBlock(clientId, true),
		[clientId]
	);

	// Disable loop/autoplay when block OR children are focused for easier editing
	const isEditing = isSelected || isChildSelected;

	const swiperRef = useRefEffect(
		(element: HTMLElement) => {
			if (!element) {
				return;
			}

			const parent = element.closest(".swiper-parent");

			const swiper = new Swiper(element, {
				modules: [Navigation, Pagination, Autoplay, A11y],
				slidesPerView: 1,
				spaceBetween: 0,
				simulateTouch: false,
				watchOverflow: false,
				autoHeight: false,
				navigation: {
					enabled: attributes.showNavigation,
					prevEl: parent?.querySelector(".swiper-arrows .prev") as HTMLElement,
					nextEl: parent?.querySelector(".swiper-arrows .next") as HTMLElement
				},
				pagination: {
					enabled: attributes.showPagination,
					el: parent?.querySelector(".swiper-pagination") as HTMLElement,
					type: "progressbar" as const
				},
				loop: attributes.loopCarousel && !isEditing,
				autoplay:
					attributes.autoplayCarousel && !isEditing
						? { delay: (attributes.autoplayDelay ?? 3) * 1000 }
						: false
			});

			return () => {
				swiper.destroy();
			};
		},
		[attributes.showNavigation, attributes.showPagination, attributes.loopCarousel, isEditing]
	);

	const innerBlocksProps = useInnerBlocksProps(
		{ className: "swiper-wrapper" },
		{
			allowedBlocks: ["takt/gallery-carousel-item"],
			template: [
				["takt/gallery-carousel-item", {}],
				["takt/gallery-carousel-item", {}],
				["takt/gallery-carousel-item", {}]
			],
			renderAppender: false
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Carousel Settings", "takt")} initialOpen={true}>
					<ToggleControl
						label={__("Pagination", "takt")}
						checked={attributes.showPagination}
						onChange={(value) => setAttributes({ showPagination: value })}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={__("Navigation", "takt")}
						checked={attributes.showNavigation}
						onChange={(value) => setAttributes({ showNavigation: value })}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={__("Loop", "takt")}
						checked={attributes.loopCarousel}
						onChange={(value) => setAttributes({ loopCarousel: value })}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={__("Autoplay", "takt")}
						checked={attributes.autoplayCarousel}
						onChange={(value) => setAttributes({ autoplayCarousel: value })}
						__nextHasNoMarginBottom
					/>
					{attributes.autoplayCarousel && (
						<RangeControl
							label={__("Slide Duration", "takt")}
							help={__("Time in seconds.", "takt")}
							value={attributes.autoplayDelay}
							onChange={(value) => setAttributes({ autoplayDelay: value })}
							min={1}
							max={60}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					)}
				</PanelBody>
			</InspectorControls>

			<section
				{...useBlockProps({
					className: "gallery-carousel dark bg-transparent! not-discourse",
					...(!attributes.heading && {
						"aria-label": __("Gallery Carousel", "takt")
					})
				})}
			>
				<div className="container relative py-6 sm:py-16 dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)">
					{/* Noise texture overlay */}
					<div
						className="absolute inset-x-(--side-gutter) md:inset-x-(--bg-extend) inset-y-0 pointer-events-none rounded-3xl overflow-hidden"
						aria-hidden="true"
					>
						<svg className="hidden">
							<filter id={`gallery-carousel-noise-${clientId}`}>
								<feTurbulence
									type="fractalNoise"
									baseFrequency="0.65"
									numOctaves={3}
									stitchTiles="stitch"
								/>
							</filter>
						</svg>
						<div
							className="absolute inset-0 opacity-[0.08] mix-blend-color-dodge"
							style={{
								filter: `url(#gallery-carousel-noise-${clientId})`
							}}
						></div>
					</div>

					{/* Header - two columns using ThemeHeading */}
					<ThemeHeading
						eyebrow={attributes.eyebrow}
						heading={attributes.heading}
						headingSize={2}
						description={attributes.description}
						buttons={attributes.buttons}
						columns={2}
						enableButtons={true}
						enableEyebrow={true}
						enableDescription={true}
						enableHeading={true}
						className="mb-16"
						onChange={(value: Partial<GalleryCarouselAttributes>) => {
							renameBlock(value.heading, attributes.heading, clientId);
							setAttributes(value);
						}}
					/>

					{/* Carousel */}
					<div
						className={cn({
							"swiper-parent relative grid grid-cols-1 grid-rows-1": true,
							"overflow-hidden": !isSelected,
							"overflow-visible pb-6 -mb-6": isSelected
						})}
						role="region"
						aria-roledescription={__("carousel", "takt")}
						{...(attributes.heading
							? {
									"aria-labelledby": `${clientId}-heading`
								}
							: {
									"aria-label": __("Gallery Carousel", "takt")
								})}
					>
						{/* Pagination + Navigation row */}
						{(attributes.showPagination ||
							attributes.showNavigation ||
							attributes.autoplayCarousel) && (
							<div className="flex items-center justify-end gap-4 mb-6">
								{/* Autoplay toggle button */}
								{attributes.autoplayCarousel && (
									<button
										className="carousel-autoplay-toggle carousel-nav-btn"
										aria-label={__("Stop slide rotation", "takt")}
										data-label-pause={__("Stop slide rotation", "takt")}
										data-label-play={__("Start slide rotation", "takt")}
									>
										<PauseIcon />
									</button>
								)}

								{/* Pagination progress bar */}
								{attributes.showPagination && (
									<div
										className="swiper-pagination flex-1 relative! h-1 bg-charcoal/20 dark:bg-white/20 rounded-full overflow-hidden"
										aria-hidden="true"
									></div>
								)}

								{/* Navigation arrows */}
								{attributes.showNavigation && (
									<div
										className="swiper-arrows flex items-center gap-2 md:gap-4"
										role="group"
										aria-label={__("Carousel controls", "takt")}
									>
										<button
											className="carousel-nav-btn prev"
											aria-label={__("Previous Item", "takt")}
											title={__("Previous Item", "takt")}
										>
											<ArrowLeft />
										</button>
										<button
											className="carousel-nav-btn next"
											aria-label={__("Next Item", "takt")}
											title={__("Next Item", "takt")}
										>
											<ArrowRight />
										</button>
									</div>
								)}
							</div>
						)}

						<div
							ref={swiperRef}
							className="swiper gallery-carousel-swiper relative overflow-hidden default-mask w-full"
							data-navigation={attributes.showNavigation ? "1" : "0"}
							data-pagination={attributes.showPagination ? "1" : "0"}
							data-loop={attributes.loopCarousel ? "1" : "0"}
							data-autoplay={attributes.autoplayCarousel ? "1" : "0"}
							data-autoplay-delay={attributes.autoplayDelay}
						>
							<div {...innerBlocksProps} />
						</div>

						{/* Appender */}
						{(displayContent || !hasInnerBlocks) && (
							<Appender
								style={{
									position: "absolute",
									bottom: "0",
									right: "0px",
									zIndex: "1"
								}}
							/>
						)}
					</div>
				</div>
			</section>
		</>
	);
}
