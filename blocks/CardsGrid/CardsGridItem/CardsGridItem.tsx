import { __ } from "@wordpress/i18n";
import { useBlockProps, RichText, InspectorControls } from "@wordpress/block-editor";
import { PanelBody, ColorPalette } from "@wordpress/components";
import { ThemeColors, ColorValue, ColorClass } from "../../../parts/ThemeColors";
import { cn, shouldDisplay, renameBlock, fetchMedia } from "@taktdev/utilities";
import { ImageDropUploader, MediaUploadPanel } from "@taktdev/components";
import { ThemeButton } from "parts/ThemeButton";

type ImageAttribute = {
	id: number | null;
	focalPoint?: { x: number; y: number };
};

type ButtonLink = {
	title: string;
	url: string;
	postId?: number;
	opensInNewTab?: boolean;
	label?: string;
};

type CardsGridItemAttributes = {
	anchor?: string;
	image: ImageAttribute;
	title?: string;
	description?: string;
	button: ButtonLink;
	accentColor?: string;
};

type EditProps = {
	attributes: CardsGridItemAttributes;
	setAttributes: (attrs: Partial<CardsGridItemAttributes>) => void;
	clientId: string;
	context: {
		"takt/cards-grid/columns"?: number;
	};
};

export default function Edit({ attributes, setAttributes, clientId, context }: EditProps) {
	const { displayContent } = shouldDisplay();
	// eslint-disable-next-line @typescript-eslint/no-unused-vars -- context available for future card sizing logic
	const _columns = context["takt/cards-grid/columns"] ?? 3;

	const mediaImage = fetchMedia(attributes.image?.id || null);
	const hasImage = !!mediaImage?.source_url;

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Card Image", "takt")} initialOpen={true}>
					<MediaUploadPanel
						media={attributes.image}
						label={__("Image", "takt")}
						onSelect={(image) => setAttributes({ image })}
					/>
				</PanelBody>
				<PanelBody title={__("Settings", "takt")}>
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

			<div
				{...useBlockProps({
					className: cn({
						"h-full not-discourse": true,
						[attributes.accentColor]: !!attributes.accentColor
					})
				})}
			>
				<div className="h-full flex flex-col">
					{/* Image wrapper - colored background with padding around image */}
					{(displayContent || hasImage) && (
						<div className="bg-accent rounded-[25px] p-4">
							<div className="aspect-[4/3] w-full max-h-[350px] rounded-xl overflow-hidden">
								<ImageDropUploader
									image={attributes.image}
									onSelect={(image) => setAttributes({ image })}
									className="w-full! h-full! object-cover"
									imageClassName="w-full! h-full! object-cover"
									placeholderClassName="w-full h-full"
								/>
							</div>
						</div>
					)}

					{/* Content wrapper - colored background with padding */}
					<div className="light-surface bg-accent rounded-[25px] p-6 flex flex-col flex-1">
						{/* Title */}
						{(displayContent || attributes.title) && (
							<RichText
								tagName="h3"
								className="text-header-5 text-charcoal mb-4"
								placeholder={__("Card Title", "takt")}
								value={attributes.title || ""}
								allowedFormats={[]}
								onChange={(value) => {
									renameBlock(value, attributes.title, clientId);
									setAttributes({ title: value });
								}}
							/>
						)}

						{/* Description */}
						{(displayContent || attributes.description) && (
							<RichText
								tagName="p"
								className="text-charcoal text-body mb-6 flex-1"
								placeholder={__("Card description…", "takt")}
								value={attributes.description || ""}
								allowedFormats={["core/bold", "core/italic"]}
								onChange={(value) => setAttributes({ description: value })}
							/>
						)}

						{/* Button with arrow */}
						{(displayContent || attributes.button?.title) && (
							<div className="cards-grid-item-button mt-auto">
								<ThemeButton
									link={attributes.button}
									onChange={(value) =>
										setAttributes({
											button: {
												...attributes.button,
												...value
											}
										})
									}
									placeholder={__("Learn More", "takt")}
									variation="tertiary"
									allowVariationChange={false}
									className="text-charcoal! text-left! group inline-flex items-center gap-2"
								/>
							</div>
						)}
					</div>
				</div>
			</div>
		</>
	);
}
