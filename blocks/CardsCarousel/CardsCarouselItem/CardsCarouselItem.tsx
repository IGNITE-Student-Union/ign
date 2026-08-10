import { __ } from "@wordpress/i18n";
import { useBlockProps, RichText, InspectorControls } from "@wordpress/block-editor";
import { PanelBody, ColorPalette } from "@wordpress/components";
import { ThemeColors, ColorValue, ColorClass } from "../../../parts/ThemeColors";
import { cn, shouldDisplay, renameBlock } from "@taktdev/utilities";
import { ThemeButton } from "parts/ThemeButton";

type ButtonLink = {
	title: string;
	url: string;
	postId?: number;
	opensInNewTab?: boolean;
	label?: string;
};

type CardsCarouselItemAttributes = {
	anchor?: string;
	title?: string;
	description?: string;
	button: ButtonLink;
	accentColor?: string;
};

type EditProps = {
	attributes: CardsCarouselItemAttributes;
	setAttributes: (attrs: Partial<CardsCarouselItemAttributes>) => void;
	clientId: string;
	context: {
		"takt/cards-carousel/columns"?: number;
	};
};

export default function Edit({ attributes, setAttributes, clientId, context }: EditProps) {
	const { displayContent } = shouldDisplay();
	// eslint-disable-next-line @typescript-eslint/no-unused-vars -- context available for future card sizing logic
	const _columns = context["takt/cards-carousel/columns"] ?? 3;

	return (
		<>
			<InspectorControls>
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
						"swiper-slide not-discourse": true,
						[attributes.accentColor]: !!attributes.accentColor
					})
				})}
			>
				<div className="light-surface w-full h-full bg-accent rounded-2xl p-6 md:p-8 flex flex-col">
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
						<div className="cards-carousel-item-button mt-auto">
							<ThemeButton
								link={attributes.button}
								onChange={(value) =>
									setAttributes({
										button: { ...attributes.button, ...value }
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
		</>
	);
}
