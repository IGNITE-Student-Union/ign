import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	InspectorControls,
	RichText,
	useInnerBlocksProps
} from "@wordpress/block-editor";
import { PanelBody, RangeControl } from "@wordpress/components";

import { ImageDropUploader } from "@taktdev/components";

type AddressItem = {
	name: string;
	address: string;
};

type FooterInfoAttributes = {
	anchor?: string;
	logoId?: number;
	addressLabel: string;
	addresses: AddressItem[];
	hoursLabel: string;
	hoursContent: string;
	contactLabel: string;
	contactContent: string;
	socialLabel: string;
};

type EditProps = {
	attributes: FooterInfoAttributes;
	setAttributes: (attrs: Partial<FooterInfoAttributes>) => void;
	clientId: string;
};

const TEMPLATE: [string, Record<string, unknown>][] = [["takt/footer-info-social", {}]];

export default function Edit({ attributes, setAttributes }: EditProps) {
	const innerBlocksProps = useInnerBlocksProps(
		{ className: "relative" },
		{
			template: TEMPLATE,
			allowedBlocks: ["takt/footer-info-social"],
			renderAppender: false
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Addresses", "takt")} initialOpen={true}>
					<RangeControl
						label={__("Number of Addresses", "takt")}
						value={attributes.addresses.length}
						onChange={(value) => {
							const count = value ?? 1;
							const current = [...attributes.addresses];
							if (count > current.length) {
								for (let i = current.length; i < count; i++) {
									current.push({
										name: "",
										address: ""
									});
								}
							} else {
								current.length = count;
							}
							setAttributes({ addresses: current });
						}}
						min={1}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<section
				{...useBlockProps({
					className:
						"dark-surface relative py-8 text-white not-discourse before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)"
				})}
			>
				{/* Logo area */}
				<div className="mb-8">
					<ImageDropUploader
						image={attributes.logoId}
						onSelect={(image) => setAttributes({ logoId: image.id ?? undefined })}
						className="max-h-[58px] max-w-[230px] w-auto h-auto"
						imageClassName="max-h-[58px] max-w-[230px] w-auto h-auto object-contain"
						placeholderClassName="h-[58px] w-[230px]"
						openOnImageClick={false}
						inlineSVG={true}
					/>
				</div>

				{/* Info row */}
				<div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-x-4 sm:gap-x-8 gap-y-8">
					{/* Address section */}
					<div className="flex flex-col gap-3 col-span-2 sm:col-span-3 lg:col-span-3">
						<RichText
							tagName="h3"
							className="font-heading text-base leading-[1.1] tracking-[0.02em]"
							placeholder={__("Address", "takt")}
							value={attributes.addressLabel}
							allowedFormats={[]}
							onChange={(value) => setAttributes({ addressLabel: value })}
						/>
						<div className="flex flex-col sm:flex-row sm:flex-wrap gap-8">
							{attributes.addresses.map((addr, index) => (
								<div
									key={index}
									className="text-xs font-medium leading-normal font-sans sm:flex-1 sm:min-w-48"
								>
									<RichText
										tagName="p"
										className="font-bold"
										placeholder={__("Campus Name", "takt")}
										value={addr.name}
										allowedFormats={[]}
										onChange={(value) => {
											const updated = [...attributes.addresses];
											updated[index] = {
												...updated[index],
												name: value
											};
											setAttributes({
												addresses: updated
											});
										}}
									/>
									<RichText
										tagName="p"
										placeholder={__("Address line…", "takt")}
										value={addr.address}
										allowedFormats={[]}
										onChange={(value) => {
											const updated = [...attributes.addresses];
											updated[index] = {
												...updated[index],
												address: value
											};
											setAttributes({
												addresses: updated
											});
										}}
									/>
								</div>
							))}
						</div>
					</div>

					{/* Hours */}
					<div className="flex flex-col gap-3 break-words">
						<RichText
							tagName="h3"
							className="font-heading text-base leading-[1.1] tracking-[0.02em]"
							placeholder={__("Hours", "takt")}
							value={attributes.hoursLabel}
							allowedFormats={[]}
							onChange={(value) => setAttributes({ hoursLabel: value })}
						/>
						<RichText
							tagName="div"
							className="text-xs font-medium leading-normal font-sans"
							placeholder={__("Hours of operation…", "takt")}
							value={attributes.hoursContent}
							allowedFormats={["core/bold", "core/italic"]}
							onChange={(value) => setAttributes({ hoursContent: value })}
						/>
					</div>

					{/* Contact */}
					<div className="flex flex-col gap-3 break-words">
						<RichText
							tagName="h3"
							className="font-heading text-base leading-[1.1] tracking-[0.02em]"
							placeholder={__("Contact", "takt")}
							value={attributes.contactLabel}
							allowedFormats={[]}
							onChange={(value) =>
								setAttributes({
									contactLabel: value
								})
							}
						/>
						<RichText
							tagName="div"
							className="text-xs font-medium leading-normal font-sans"
							placeholder={__("Contact info…", "takt")}
							value={attributes.contactContent}
							allowedFormats={["core/bold", "core/italic", "core/link"]}
							onChange={(value) =>
								setAttributes({
									contactContent: value
								})
							}
						/>
					</div>

					{/* Follow Us + Social Icons */}
					<div className="flex flex-col gap-3">
						<RichText
							tagName="h3"
							className="font-heading text-base leading-[1.1] tracking-[0.02em]"
							placeholder={__("Follow Us", "takt")}
							value={attributes.socialLabel}
							allowedFormats={[]}
							onChange={(value) => setAttributes({ socialLabel: value })}
						/>
						<div {...innerBlocksProps} />
					</div>
				</div>
			</section>
		</>
	);
}
