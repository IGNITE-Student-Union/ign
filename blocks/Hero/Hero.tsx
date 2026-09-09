import { __ } from "@wordpress/i18n";
import { useState } from "@wordpress/element";
import { useBlockProps, useInnerBlocksProps, InspectorControls } from "@wordpress/block-editor";
import {
	PanelBody,
	ToggleControl,
	TextControl,
	Button,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption
} from "@wordpress/components";
import {
	shouldDisplay,
	cn,
	fetchMedia,
	renameBlock,
	uniqueBlocks,
	addButtonToBlock,
	isTemplatePreview,
	isTemplateEdit
} from "@taktdev/utilities";

import { ImageDropUploader, MediaUploadPanel, FilteredServerSideRender } from "@taktdev/components";
import { ThemeHeading } from "parts/ThemeHeading";

addButtonToBlock("takt/hero", __("Hero Settings", "takt"));

type ImageAttribute = {
	id: number | null;
	focalPoint?: { x: number; y: number };
};

type ResponsiveImageAttribute = {
	desktop?: ImageAttribute;
	tablet?: ImageAttribute | null;
	mobile?: ImageAttribute | null;
};

type Tier = "desktop" | "tablet" | "mobile";

const DEFAULT_IMAGE: ImageAttribute = { id: null, focalPoint: { x: 0.5, y: 0.5 } };

// Legacy shape was a flat `{ id, focalPoint }`; treat it as desktop-only.
function normalizeImage(image: ResponsiveImageAttribute | ImageAttribute | undefined) {
	const isLegacy = !!image && "id" in image && !("desktop" in image);
	if (isLegacy) {
		return {
			desktop: image as ImageAttribute,
			tablet: null as ImageAttribute | null,
			mobile: null as ImageAttribute | null
		};
	}
	const responsive = (image || {}) as ResponsiveImageAttribute;
	return {
		desktop: responsive.desktop || DEFAULT_IMAGE,
		tablet: responsive.tablet || null,
		mobile: responsive.mobile || null
	};
}

// Mobile inherits tablet, tablet inherits desktop, when not overridden.
function resolveResponsiveImage(image: ResponsiveImageAttribute | ImageAttribute | undefined) {
	const { desktop, tablet, mobile } = normalizeImage(image);
	const resolvedTablet = tablet || desktop;
	const resolvedMobile = mobile || resolvedTablet;
	return { desktop, tablet: resolvedTablet, mobile: resolvedMobile };
}

type HeroAttributes = {
	anchor?: string;
	blockVariation: "primary" | "secondary";
	eyebrow?: string;
	heading?: string;
	description?: string;
	buttons?: Array<{
		title?: string;
		url?: string;
		postId?: number;
		opensInNewTab?: boolean;
		label?: string;
	}>;
	image: ResponsiveImageAttribute;
	enableQuickNav: boolean;
	showBreadcrumbs: boolean;
	showAppBadges: boolean;
	appStoreUrl: string;
	googlePlayUrl: string;
};

type EditProps = {
	attributes: HeroAttributes;
	setAttributes: (attrs: Partial<HeroAttributes>) => void;
	clientId: string;
};

export default function Edit({ attributes, setAttributes, clientId }: EditProps) {
	const { displayContent, hasInnerBlocks } = shouldDisplay();
	const hasHeader = isTemplatePreview() || isTemplateEdit();
	const isPrimary = attributes.blockVariation === "primary";
	const isSecondary = attributes.blockVariation === "secondary";

	const [activeTier, setActiveTier] = useState<Tier>("desktop");
	const normalizedImage = normalizeImage(attributes.image);
	const resolvedImage = resolveResponsiveImage(attributes.image);
	const activeImage = resolvedImage[activeTier];
	const isOverridden = activeTier !== "desktop" && !!normalizedImage[activeTier];

	const mediaImage = fetchMedia(activeImage?.id || null);
	const hasImage = !!mediaImage?.source_url;

	const setTierImage = (image: ImageAttribute) => {
		setAttributes({
			image: {
				desktop: normalizedImage.desktop,
				tablet: normalizedImage.tablet,
				mobile: normalizedImage.mobile,
				[activeTier]: image
			}
		});
	};

	const resetTierToInherited = () => {
		setAttributes({
			image: {
				desktop: normalizedImage.desktop,
				tablet: activeTier === "tablet" ? null : normalizedImage.tablet,
				mobile: activeTier === "mobile" ? null : normalizedImage.mobile
			}
		});
	};

	// Limit to one QuickNavigation block
	uniqueBlocks("takt/quick-navigation", clientId);

	// InnerBlocks setup for QuickNavigation
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: cn({
				"w-full": true,
				hidden: !attributes.enableQuickNav
			})
		},
		{
			allowedBlocks: ["takt/quick-navigation"],
			template: [["takt/quick-navigation"]],
			renderAppender: false
		}
	);

	return (
		<>
			<InspectorControls>
				{isPrimary && (
					<PanelBody title={__("Settings", "takt")} initialOpen={true}>
						<ToggleControl
							label={__("Show Quick Navigation", "takt")}
							checked={attributes.enableQuickNav}
							onChange={(value) => setAttributes({ enableQuickNav: value })}
							__nextHasNoMarginBottom
						/>
					</PanelBody>
				)}
				{isSecondary && (
					<PanelBody title={__("Settings", "takt")} initialOpen={true}>
						<ToggleControl
							label={__("Show Breadcrumbs", "takt")}
							help={
								attributes.showBreadcrumbs
									? __("Eyebrow is disabled when breadcrumbs are shown.", "takt")
									: ""
							}
							checked={attributes.showBreadcrumbs}
							onChange={(value) => setAttributes({ showBreadcrumbs: value })}
							__nextHasNoMarginBottom
						/>
						<ToggleControl
							label={__("Show App Store & Google Play Badges", "takt")}
							help={__("Links to the MyIGNITE app listings.", "takt")}
							checked={attributes.showAppBadges}
							onChange={(value) => setAttributes({ showAppBadges: value })}
							__nextHasNoMarginBottom
						/>
						{attributes.showAppBadges && (
							<>
								<TextControl
									label={__("App Store URL", "takt")}
									value={attributes.appStoreUrl}
									onChange={(value) => setAttributes({ appStoreUrl: value })}
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
								<TextControl
									label={__("Google Play URL", "takt")}
									value={attributes.googlePlayUrl}
									onChange={(value) => setAttributes({ googlePlayUrl: value })}
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							</>
						)}
					</PanelBody>
				)}

				<PanelBody title={__("Media", "takt")} initialOpen={true}>
					<ToggleGroupControl
						label={__("Editing", "takt")}
						value={activeTier}
						onChange={(value) => setActiveTier(value as Tier)}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="desktop" label={__("Desktop", "takt")} />
						<ToggleGroupControlOption value="tablet" label={__("Tablet", "takt")} />
						<ToggleGroupControlOption value="mobile" label={__("Mobile", "takt")} />
					</ToggleGroupControl>
					{isOverridden && (
						<Button variant="link" onClick={resetTierToInherited}>
							{activeTier === "tablet"
								? __("Reset to Desktop image", "takt")
								: __("Reset to Tablet image", "takt")}
						</Button>
					)}
					<MediaUploadPanel
						media={activeImage}
						label={__("Background Image", "takt")}
						onSelect={(image) => setTierImage(image)}
					/>
				</PanelBody>
			</InspectorControls>

			<section
				{...useBlockProps({
					className: cn({
						"not-discourse": true,
						"hero grid overflow-visible": true,
						"-mt-(--header-height)": hasHeader,
						"hero--primary": isPrimary,
						"hero--secondary": isSecondary
					})
				})}
			>
				{/* Layer 1: Background image — col-1/row-1, stretches to fill grid */}
				<div className="col-start-1 row-start-1 overflow-hidden bg-accent h-[calc(var(--header-height)+300px)] md:h-[calc(var(--header-height)+450px)] mask-b-from-65% mask-b-to-90%">
					{(displayContent || hasImage) && (
						<ImageDropUploader
							image={activeImage}
							onSelect={(image) => setTierImage(image)}
							className="w-full! h-full!"
							imageClassName="w-full! h-full! object-cover"
							imageStyle={{ pointerEvents: "none", cursor: "default" }}
							placeholderClassName="w-full h-full"
						/>
					)}
				</div>

				{/* Layer 2: Gradient — col-1/row-1, margin-top aligns with content text position */}
				<div className="col-start-1 row-start-1 self-start relative -z-1 mt-[calc(var(--header-height)+180px)] md:mt-[calc(var(--header-height)+300px)]">
					<div className="top-gradient" />
				</div>

				{/* Layer 3: Content — col-1/row-1, padding-top for minimum top spacing */}
				<div className="col-start-1 row-start-1 self-end relative z-10 pt-[calc(var(--header-height)+180px)] md:pt-[calc(var(--header-height)+300px)] pb-8 sm:pb-16">
					<div className="container">
						{isSecondary && attributes.showBreadcrumbs && (
							<FilteredServerSideRender
								block="takt/hero"
								attributes={attributes}
								querySelector=".breadcrumbs"
								className="breadcrumbs mb-4 uppercase text-sm font-medium leading-[1.1]"
								preventInteraction
							/>
						)}
						<ThemeHeading
							className={cn({
								"max-w-[912px]": isPrimary,
								"max-w-[700px]": isSecondary,
								"mx-auto": isPrimary,
								"mr-auto": isSecondary
							})}
							alignment={isPrimary ? "center" : "left"}
							enableEyebrow={!attributes.showBreadcrumbs}
							eyebrow={attributes.eyebrow}
							heading={attributes.heading}
							headingTag="h1"
							headingSize={0}
							headingClassName={cn({
								"text-[2.5rem] md:text-[length:var(--text-header-0)]": isPrimary
							})}
							description={attributes.description}
							buttons={isSecondary ? attributes.buttons : []}
							enableButtons={isSecondary}
							onChange={(value: Partial<HeroAttributes>) => {
								renameBlock(value.heading, attributes.heading, clientId);
								setAttributes(value);
							}}
						/>

						{isSecondary && attributes.showAppBadges && (
							<div
								className="flex flex-wrap items-center gap-4 mt-6"
								data-animate="fade-up"
								data-animate-delay="1200"
							>
								<a href={attributes.appStoreUrl} target="_blank" rel="noopener noreferrer">
									<img
										src="https://ignitestudentunion.ca/wp-content/uploads/2026/06/Download_on_the_App_Store_Badge_US-UK_RGB_wht_092917.svg"
										alt={__("Download on the App Store", "takt")}
										className="h-12 w-auto"
									/>
								</a>
								<a href={attributes.googlePlayUrl} target="_blank" rel="noopener noreferrer">
									<img
										src="https://ignitestudentunion.ca/wp-content/uploads/2026/06/GetItOnGooglePlay_Badge_Web_color_English.svg"
										alt={__("Get it on Google Play", "takt")}
										className="h-12 w-auto"
									/>
								</a>
							</div>
						)}

						{/* Quick Navigation (Primary only) */}
						{isPrimary && (
							<div
								className={cn({
									"mt-8 sm:mt-12 relative": true,
									"max-w-[912px] mx-auto": true,
									hidden: !displayContent && !hasInnerBlocks && !attributes.enableQuickNav
								})}
							>
								<div {...innerBlocksProps} />
							</div>
						)}
					</div>
				</div>
			</section>
		</>
	);
}
