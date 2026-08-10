import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	InspectorControls,
	BlockControls,
	useInnerBlocksProps
} from "@wordpress/block-editor";
import { PanelBody, RangeControl, ToolbarButton, ToolbarGroup } from "@wordpress/components";
import { shadow } from "@wordpress/icons";
import { shouldDisplay, cn, renameBlock, addButtonToBlock } from "@taktdev/utilities";

import { Appender } from "@taktdev/components";
import { ThemeHeading } from "parts/ThemeHeading";

addButtonToBlock("takt/cards-grid", __("Cards Grid Settings", "takt"));

type CardsGridAttributes = {
	anchor?: string;
	darkMode: boolean;
	eyebrow?: string;
	heading?: string;
	description?: string;
	buttons?: Array<Record<string, unknown>>;
	columns: number;
};

type EditProps = {
	attributes: CardsGridAttributes;
	setAttributes: (attrs: Partial<CardsGridAttributes>) => void;
	clientId: string;
};

export default function Edit({ attributes, setAttributes, clientId }: EditProps) {
	const { displayContent, hasInnerBlocks } = shouldDisplay();

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: cn({
				"grid gap-6": true,
				"grid-cols-1": attributes.columns === 1,
				"grid-cols-1 md:grid-cols-2": attributes.columns === 2,
				"grid-cols-1 md:grid-cols-2 lg:grid-cols-3": attributes.columns === 3
			})
		},
		{
			allowedBlocks: ["takt/cards-grid-item"],
			template: [
				["takt/cards-grid-item", {}],
				["takt/cards-grid-item", {}],
				["takt/cards-grid-item", {}]
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
				<PanelBody title={__("Grid Settings", "takt")} initialOpen={true}>
					<RangeControl
						label={__("Columns", "takt")}
						help={__("Number of columns on desktop. Mobile is always 1, tablet max 2.", "takt")}
						value={attributes.columns}
						onChange={(value) => setAttributes({ columns: value })}
						min={1}
						max={3}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</PanelBody>
			</InspectorControls>

			<section
				{...useBlockProps({
					className: cn({
						"not-discourse": true,
						"cards-grid": true,
						"py-6 sm:py-16": !attributes.darkMode,
						"dark bg-transparent!": attributes.darkMode
					})
				})}
			>
				<div
					className={cn({
						container: true,
						"relative py-6 sm:py-16 dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)":
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
						onChange={(value: Partial<CardsGridAttributes>) => {
							renameBlock(value.heading, attributes.heading, clientId);
							setAttributes(value);
						}}
					/>

					{/* Grid + Appender */}
					{(hasInnerBlocks || displayContent) && (
						<div className="relative">
							<div {...innerBlocksProps} />
							<Appender />
						</div>
					)}
				</div>
			</section>
		</>
	);
}
