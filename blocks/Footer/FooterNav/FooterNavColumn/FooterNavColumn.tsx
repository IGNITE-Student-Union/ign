import { __ } from "@wordpress/i18n";
import { useBlockProps, useInnerBlocksProps, RichText } from "@wordpress/block-editor";
import { renameBlock } from "@taktdev/utilities";
import { Appender } from "@taktdev/components";

type FooterNavColumnAttributes = {
	anchor?: string;
	heading: string;
};

type EditProps = {
	attributes: FooterNavColumnAttributes;
	setAttributes: (attrs: Partial<FooterNavColumnAttributes>) => void;
	clientId: string;
};

export default function Edit({ attributes, setAttributes, clientId }: EditProps) {
	const headingId = `footer-nav-col-${clientId}`;

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: "flex flex-col gap-2 list-none",
			"aria-labelledby": headingId
		},
		{
			allowedBlocks: ["takt/footer-nav-column-item"],
			template: [["takt/footer-nav-column-item", {}]],
			renderAppender: false
		}
	);

	return (
		<div
			{...useBlockProps({
				className: "flex flex-col gap-4 min-w-0 break-words not-discourse"
			})}
		>
			<RichText
				tagName="h3"
				id={headingId}
				className="font-heading text-base leading-[1.1] tracking-[0.02em]"
				placeholder={__("Column Heading", "takt")}
				value={attributes.heading}
				allowedFormats={[]}
				onChange={(value) => {
					renameBlock(value, attributes.heading, clientId);
					setAttributes({ heading: value });
				}}
			/>

			<div className="relative">
				<Appender />
				<ul {...innerBlocksProps} />
			</div>
		</div>
	);
}
