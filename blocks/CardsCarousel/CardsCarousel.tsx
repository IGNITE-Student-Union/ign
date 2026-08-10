import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	InspectorControls,
	BlockControls,
	useInnerBlocksProps,
	store as blockEditorStore
} from "@wordpress/block-editor";
import { useSelect } from "@wordpress/data";
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	ToolbarButton,
	ToolbarGroup
} from "@wordpress/components";
import { shadow } from "@wordpress/icons";
import { useRefEffect } from "@wordpress/compose";
import { Swiper } from "swiper";
import { Navigation, Pagination, Autoplay, A11y } from "swiper/modules";
import { shouldDisplay, cn, renameBlock, addButtonToBlock } from "@taktdev/utilities";

import { Appender } from "@taktdev/components";
import { ThemeHeading } from "parts/ThemeHeading";
import { ReactComponent as ArrowLeft } from "./resources/ArrowLeft.svg";
import { ReactComponent as ArrowRight } from "./resources/ArrowRight.svg";

addButtonToBlock("takt/cards-carousel", __("Cards Carousel Settings", "takt"));

type CardsCarouselAttributes = {
	anchor?: string;
	darkMode: boolean;
	eyebrow?: string;
	heading?: string;
	description?: string;
	buttons?: Array<Record<string, unknown>>;
	showPagination: boolean;
	showNavigation: boolean;
	loopCarousel: boolean;
	autoplayCarousel: boolean;
	autoplayDelay: number;
	columns: number;
};

type EditProps = {
	attributes: CardsCarouselAttributes;
	setAttributes: (attrs: Partial<CardsCarouselAttributes>) => void;
	clientId: string;
};

function getCarouselConfig(opts: {
	element: HTMLElement;
	columns?: number;
	showNavigation?: boolean;
	showPagination?: boolean;
	loopCarousel?: boolean;
	autoplayCarousel?: boolean;
	autoplayDelay?: number;
	isEditing?: boolean;
}) {
	const parent = opts.element.closest(".swiper-parent");

	return {
		modules: [Navigation, Pagination, Autoplay, A11y],
		slidesPerView: 1,
		breakpoints: {
			570: {
				slidesPerView: Math.min(opts.columns ?? 3, 2)
			},
			900: {
				slidesPerView: opts.columns ?? 3
			}
		},
		breakpointsBase: "container" as const,
		spaceBetween: 24,
		simulateTouch: false,
		watchOverflow: false,
		autoHeight: false,
		navigation: {
			enabled: opts.showNavigation,
			prevEl: parent?.querySelector(".swiper-arrows .prev") as HTMLElement,
			nextEl: parent?.querySelector(".swiper-arrows .next") as HTMLElement
		},
		pagination: {
			enabled: opts.showPagination,
			el: parent?.querySelector(".swiper-pagination") as HTMLElement,
			type: "progressbar" as const
		},
		loop: opts.loopCarousel && !opts.isEditing,
		autoplay:
			opts.autoplayCarousel && !opts.isEditing ? { delay: (opts.autoplayDelay ?? 3) * 1000 } : false
	};
}

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

			const swiper = new Swiper(
				element,
				getCarouselConfig({
					element,
					columns: attributes.columns,
					showNavigation: attributes.showNavigation,
					showPagination: attributes.showPagination,
					loopCarousel: attributes.loopCarousel,
					autoplayCarousel: attributes.autoplayCarousel,
					autoplayDelay: attributes.autoplayDelay,
					isEditing
				})
			);

			return () => {
				swiper.destroy();
			};
		},
		[
			attributes.showNavigation,
			attributes.showPagination,
			attributes.loopCarousel,
			attributes.columns,
			isEditing
		]
	);

	const innerBlocksProps = useInnerBlocksProps(
		{ className: "swiper-wrapper" },
		{
			allowedBlocks: ["takt/cards-carousel-item"],
			template: [
				["takt/cards-carousel-item", {}],
				["takt/cards-carousel-item", {}],
				["takt/cards-carousel-item", {}]
			],
			renderAppender: false
		}
	);

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={shadow}
						label={__("Dark Mode", "takt")}
						onClick={() => setAttributes({ darkMode: !attributes.darkMode })}
						isPressed={attributes.darkMode}
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__("Carousel Settings", "takt")} initialOpen={true}>
					<RangeControl
						label={__("Columns", "takt")}
						help={__("Maximum number of visible columns on desktop.", "takt")}
						value={attributes.columns}
						onChange={(value) => setAttributes({ columns: value })}
						min={1}
						max={3}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
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
					className: cn({
						"not-discourse": true,
						"cards-carousel": true,
						"dark bg-transparent!": attributes.darkMode
					}),
					...(!attributes.heading && {
						"aria-label": __("Cards Carousel", "takt")
					})
				})}
			>
				<div
					className={cn({
						"container py-6 sm:py-16": true,
						"relative dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)":
							attributes.darkMode
					})}
				>
					{/* Header - two columns using ThemeHeading */}
					<ThemeHeading
						className="mb-16"
						eyebrow={attributes.eyebrow}
						heading={attributes.heading}
						headingSize={2}
						description={attributes.description}
						buttons={attributes.buttons}
						columns={2}
						onChange={(value: Partial<CardsCarouselAttributes>) => {
							renameBlock(value.heading, attributes.heading, clientId);
							setAttributes(value);
						}}
					/>

					{/* Carousel */}
					<div
						className="swiper-parent overflow-hidden grid grid-cols-1 grid-rows-1"
						aria-roledescription={__("carousel", "takt")}
					>
						{/* Pagination + Navigation row */}
						{(attributes.showPagination || attributes.showNavigation) && (
							<div className="flex items-center justify-end gap-4 mb-6">
								{/* Pagination progress bar with gradient */}
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
							className="swiper cards-carousel-swiper relative overflow-visible! w-full"
							data-navigation={attributes.showNavigation ? "1" : "0"}
							data-pagination={attributes.showPagination ? "1" : "0"}
							data-loop={attributes.loopCarousel ? "1" : "0"}
							data-autoplay={attributes.autoplayCarousel ? "1" : "0"}
							data-autoplay-delay={attributes.autoplayDelay}
							data-columns={attributes.columns}
						>
							<div {...innerBlocksProps} />
						</div>
					</div>

					{/* Appender */}
					{(displayContent || !hasInnerBlocks) && <Appender />}
				</div>
			</section>
		</>
	);
}
