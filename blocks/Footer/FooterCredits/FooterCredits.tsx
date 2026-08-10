import { __ } from "@wordpress/i18n";
import { useBlockProps, useInnerBlocksProps, RichText } from "@wordpress/block-editor";
import { Appender } from "@taktdev/components";

type FooterCreditsAttributes = {
	anchor?: string;
	copyrightText: string;
};

type EditProps = {
	attributes: FooterCreditsAttributes;
	setAttributes: (attrs: Partial<FooterCreditsAttributes>) => void;
	clientId: string;
};

export default function Edit({ attributes, setAttributes }: EditProps) {
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: "flex flex-wrap gap-6 justify-center md:justify-end list-none"
		},
		{
			allowedBlocks: ["takt/footer-credits-item"],
			template: [
				["takt/footer-credits-item", {}],
				["takt/footer-credits-item", {}],
				["takt/footer-credits-item", {}]
			],
			renderAppender: false,
			orientation: "horizontal"
		}
	);

	const currentYear = new Date().getFullYear();

	return (
		<div
			{...useBlockProps({
				className: "dark-surface bg-charcoal rounded-3xl py-4 text-white not-discourse"
			})}
		>
			<div className="flex flex-col md:flex-row items-center gap-4 md:gap-6">
				{/* Legal links */}
				<nav className="md:order-3" aria-label={__("Legal Links", "takt")}>
					<div className="relative">
						<Appender />
						<ul {...innerBlocksProps} />
					</div>
				</nav>

				{/* Copyright */}
				<div className="text-xs font-medium leading-normal font-sans text-center md:text-left md:order-1">
					<span>&copy; {currentYear} </span>
					<RichText
						tagName="span"
						placeholder={__("Company Name. All rights reserved.", "takt")}
						value={attributes.copyrightText}
						allowedFormats={[]}
						onChange={(value) => setAttributes({ copyrightText: value })}
					/>
				</div>

			</div>
		</div>
	);
}
