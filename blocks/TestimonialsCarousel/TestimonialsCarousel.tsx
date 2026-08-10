import { __ } from "@wordpress/i18n";
import { useState, useEffect } from "@wordpress/element";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	BaseControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption
} from "@wordpress/components";
import { Swiper } from "swiper";
import { Navigation, Pagination, Autoplay, A11y } from "swiper/modules";
import { shouldDisplay, renameBlock } from "@taktdev/utilities";
import {
	FilteredServerSideRender,
	PostSelectorSortable,
	TaxonomyMultiSelect
} from "@taktdev/components";
import { ThemeHeading } from "parts/ThemeHeading";

type TestimonialsCarouselAttributes = {
	anchor?: string;
	eyebrow: string;
	heading: string;
	description: string;
	buttons: Array<{
		title?: string;
		url?: string;
		postId?: number;
		opensInNewTab?: boolean;
		label?: string;
	}>;
	postsSource: string;
	selectedPosts: number[];
	postsLimit: number;
	hideIfEmpty: boolean;
	selectedPrograms: number[];
	showPagination: boolean;
	showNavigation: boolean;
	loopCarousel: boolean;
	autoplayCarousel: boolean;
	autoplayDelay: number;
	colorSource: string;
};

type EditProps = {
	attributes: TestimonialsCarouselAttributes;
	setAttributes: (attrs: Partial<TestimonialsCarouselAttributes>) => void;
	clientId: string;
};

export default function Edit({ attributes, setAttributes, clientId }: EditProps) {
	const { isSelected } = shouldDisplay();
	const [renderedNode, setRenderedNode] = useState<HTMLDivElement | null>(null);

	// Initialize Swiper on the SSR-rendered content
	useEffect(() => {
		const element = renderedNode?.querySelector(".swiper") as HTMLDivElement;
		if (!element) {
			return;
		}

		const parent = element.closest(".swiper-parent");

		const swiper = new Swiper(element, {
			modules: [Navigation, Pagination, Autoplay, A11y],
			slidesPerView: 1,
			breakpoints: {
				570: {
					slidesPerView: 2
				},
				900: {
					slidesPerView: 3
				}
			},
			breakpointsBase: "container",
			spaceBetween: 24,
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
				type: "progressbar"
			},
			loop: attributes.loopCarousel && !isSelected,
			autoplay:
				attributes.autoplayCarousel && !isSelected
					? { delay: attributes.autoplayDelay * 1000 }
					: false,
			a11y: {
				enabled: false
			}
		});

		return () => {
			swiper.destroy();
		};
	}, [
		attributes.showNavigation,
		attributes.showPagination,
		attributes.loopCarousel,
		attributes.autoplayCarousel,
		attributes.autoplayDelay,
		isSelected,
		renderedNode
	]);

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Content", "takt")} initialOpen={true}>
					<ToggleGroupControl
						label={__("Selection Mode", "takt")}
						value={attributes.postsSource}
						onChange={(value) => setAttributes({ postsSource: value as string })}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="automatic" label={__("Automatic", "takt")} />
						<ToggleGroupControlOption value="manual" label={__("Manual", "takt")} />
					</ToggleGroupControl>

					{attributes.postsSource === "manual" && (
						<BaseControl
							label={__("Selected Testimonials", "takt")}
							id="testimonials-selector"
							__nextHasNoMarginBottom
						>
							<PostSelectorSortable
								value={attributes.selectedPosts}
								onChange={(value) =>
									setAttributes({
										selectedPosts: Array.isArray(value) ? value : [].concat(value || [])
									})
								}
								postTypes={["testimonial"]}
							/>
						</BaseControl>
					)}

					{attributes.postsSource === "automatic" && (
						<>
							<TaxonomyMultiSelect
								title={__("Program", "takt")}
								value={attributes.selectedPrograms}
								taxonomy="program"
								onChange={(value) =>
									setAttributes({
										selectedPrograms: value
									})
								}
							/>

							<RangeControl
								label={__("Number of items to display", "takt")}
								value={attributes.postsLimit}
								onChange={(value) => setAttributes({ postsLimit: value })}
								min={1}
								max={50}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>

							<ToggleControl
								label={__("Hide if empty", "takt")}
								checked={attributes.hideIfEmpty}
								onChange={(value) => setAttributes({ hideIfEmpty: value })}
								__nextHasNoMarginBottom
							/>
						</>
					)}
				</PanelBody>

				<PanelBody title={__("Carousel Settings", "takt")} initialOpen={true}>
					<ToggleGroupControl
						label={__("Accent Color", "takt")}
						value={attributes.colorSource}
						onChange={(value) => setAttributes({ colorSource: value as string })}
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="page" label={__("Page", "takt")} />
						<ToggleGroupControlOption value="testimonial" label={__("Testimonial", "takt")} />
					</ToggleGroupControl>
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
					className: "testimonials-carousel dark bg-transparent! not-discourse",
					...(!attributes.heading && {
						"aria-label": __("Testimonials Carousel", "takt")
					})
				})}
			>
				<div className="container relative py-6 sm:py-16 dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)">
					{/* Header - two columns using ThemeHeading */}
					<ThemeHeading
						className="mb-16"
						eyebrow={attributes.eyebrow}
						heading={attributes.heading}
						headingSize={2}
						description={attributes.description}
						buttons={attributes.buttons}
						columns={2}
						enableButtons={true}
						onChange={(value: Partial<TestimonialsCarouselAttributes>) => {
							renameBlock(value.heading, attributes.heading, clientId);
							setAttributes(value);
						}}
					/>

					{/* Carousel - SSR rendered */}
					<FilteredServerSideRender
						querySelector=".swiper-parent"
						className="swiper-parent overflow-hidden relative"
						ref={setRenderedNode}
					/>
				</div>
			</section>
		</>
	);
}
