import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls, BlockControls } from "@wordpress/block-editor";
import {
	PanelBody,
	ToolbarButton,
	ToolbarGroup,
	ColorPalette,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption
} from "@wordpress/components";

import { image as imageIcon } from "@wordpress/icons";
import { shouldDisplay, cn, fetchMedia, renameBlock } from "@taktdev/utilities";
import { ImageDropUploader, MediaUploadPanel } from "@taktdev/components";
import { ThemeHeading } from "parts/ThemeHeading";
import { ThemeColors, ColorValue, ColorClass } from "../../parts/ThemeColors";

type ImageAttribute = {
	id: number | null;
	focalPoint?: { x: number; y: number };
};

type CallToActionAttributes = {
	anchor?: string;
	backgroundType: "color" | "image";
	image: ImageAttribute;
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
	accentColor?: string;
};

type EditProps = {
	attributes: CallToActionAttributes;
	setAttributes: (attrs: Partial<CallToActionAttributes>) => void;
	clientId: string;
};

export default function Edit({ attributes, setAttributes, clientId }: EditProps) {
	const { displayContent } = shouldDisplay();
	const mediaImage = fetchMedia(attributes.image?.id || null);
	const hasImage = !!mediaImage?.source_url;
	const isDark = attributes.backgroundType === "image" && hasImage;

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={imageIcon}
						label={__("Use Image Background", "takt")}
						onClick={() =>
							setAttributes({
								backgroundType: attributes.backgroundType === "image" ? "color" : "image"
							})
						}
						isPressed={attributes.backgroundType === "image"}
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__("Background", "takt")} initialOpen={true}>
					<ToggleGroupControl
						label={__("Background Type", "takt")}
						value={attributes.backgroundType}
						onChange={(value) =>
							setAttributes({
								backgroundType: value as "color" | "image"
							})
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="color" label={__("Color", "takt")} />
						<ToggleGroupControlOption value="image" label={__("Image", "takt")} />
					</ToggleGroupControl>

					{attributes.backgroundType === "image" && (
						<MediaUploadPanel
							media={attributes.image}
							label={__("Background Image", "takt")}
							onSelect={(image) => setAttributes({ image })}
						/>
					)}
				</PanelBody>
				<PanelBody title={__("Accent Color", "takt")} initialOpen={true}>
					<ColorPalette
						colors={ThemeColors().colorOptions}
						value={attributes.accentColor ? ColorValue(attributes.accentColor) : undefined}
						onChange={(color) => {
							setAttributes({ accentColor: color ? ColorClass(color) : "" });
						}}
						disableCustomColors={true}
						clearable={true}
					/>
				</PanelBody>
			</InspectorControls>

			<section
				{...useBlockProps({
					className: cn({
						"call-to-action py-16 not-discourse": true,
						[attributes.accentColor || ""]: !!attributes.accentColor
					})
				})}
			>
				<div
					className={cn({
						"call-to-action-inner container relative py-16": true,
						"before:absolute before:bg-accent before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)":
							!isDark,
						"dark-surface dark before:absolute before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend) before:bg-[linear-gradient(90deg,rgba(31,31,29,0.5)_17.715%,rgba(31,31,29,0)_85.991%),linear-gradient(90deg,rgba(31,31,29,0.2)_0%,rgba(31,31,29,0.2)_100%)] before:z-1":
							isDark
					})}
				>
					{isDark && (displayContent || hasImage) && (
						<div className="absolute inset-y-0 -inset-x-[calc(var(--side-gutter)/2)] md:-inset-x-(--bg-extend) overflow-hidden rounded-3xl">
							<ImageDropUploader
								image={attributes.image}
								onSelect={(image) => setAttributes({ image })}
								className="w-full! h-full!"
								imageClassName="w-full! h-full! object-cover!"
								placeholderClassName="w-full h-full"
							/>
						</div>
					)}

					<div className="relative z-2 max-w-2xl">
						<ThemeHeading
							eyebrow={attributes.eyebrow}
							heading={attributes.heading}
							headingSize={2}
							description={attributes.description}
							buttons={attributes.buttons}
							buttonsClassName="max-sm:flex-col max-sm:items-start"
							onChange={(value: Partial<CallToActionAttributes>) => {
								renameBlock(value.heading, attributes.heading, clientId);
								setAttributes(value);
							}}
						/>
					</div>
				</div>
			</section>
		</>
	);
}
