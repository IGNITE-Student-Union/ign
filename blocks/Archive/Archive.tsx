import { __ } from "@wordpress/i18n";
import { useEffect, useRef } from "@wordpress/element";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	FormTokenField,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption
} from "@wordpress/components";
import { useSelect } from "@wordpress/data";
import { store as coreStore } from "@wordpress/core-data";
import { renameBlock, cn } from "@taktdev/utilities";
import { FilteredServerSideRender } from "@taktdev/components";
import { ThemeHeading } from "parts/ThemeHeading";

type ArchiveAttributes = {
	anchor?: string;
	blockVariation: string;
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
	displayFilters: boolean;
	displaySort: boolean;
	displaySearch: boolean;
	displayPerPage: boolean;
	postsPerPage: number;
	maxColumns: number;
	paginationMode: string;
	presetFilters: Record<string, string[]>;
	showTags: boolean;
};

type EditProps = {
	attributes: ArchiveAttributes;
	setAttributes: (attrs: Partial<ArchiveAttributes>) => void;
	clientId: string;
};

// Taxonomy configuration mirroring PHP post_type_config
const VARIATION_TAXONOMIES: Record<string, Record<string, string>> = {
	post: { category: "topic" },
	team_member: { department: "department" },
	resource: { audience: "audience", resource_type: "resource-type" },
	policy: { policy_topic: "policy-topic" }
};

export default function Edit({ attributes, setAttributes, clientId }: EditProps) {
	// Compute column-aware base for postsPerPage
	const base = attributes.maxColumns === 3 ? 9 : 10;
	const step = 1;
	const min = 3;
	const max = base * 5;

	// Reset presetFilters and enforce variation defaults when blockVariation changes
	const prevVariation = useRef(attributes.blockVariation);
	useEffect(() => {
		if (prevVariation.current !== attributes.blockVariation) {
			prevVariation.current = attributes.blockVariation;
			if (attributes.blockVariation === "post") {
				setAttributes({ presetFilters: {}, maxColumns: 4, postsPerPage: 10 });
			} else {
				setAttributes({ presetFilters: {}, maxColumns: 3, postsPerPage: 9 });
			}
		}
	}, [attributes.blockVariation]);

	// Taxonomies for the current variation
	const variationTaxonomies = VARIATION_TAXONOMIES[attributes.blockVariation] ?? {};
	const taxonomyNames = Object.keys(variationTaxonomies);

	type TaxTerm = { slug: string; name: string };

	// Load terms for each taxonomy in the current variation
	const taxonomyTerms = useSelect(
		(select) => {
			const result: Record<string, TaxTerm[]> = {};
			taxonomyNames.forEach((taxName) => {
				const terms = select(coreStore).getEntityRecords("taxonomy", taxName, {
					per_page: -1,
					hide_empty: true
				});
				result[taxName] = (terms as TaxTerm[] | null) ?? [];
			});
			return result;
		},
		[attributes.blockVariation]
	);

	// Helper: get selected token labels for a taxonomy
	function getTokensForTaxonomy(taxName: string): string[] {
		const slug = variationTaxonomies[taxName];
		const selectedSlugs = attributes.presetFilters[slug] ?? [];
		const terms = taxonomyTerms[taxName] ?? [];
		return selectedSlugs.map((s) => {
			const term = terms.find((t) => t.slug === s);
			return term ? term.name : s;
		});
	}

	// Helper: handle FormTokenField change
	function handlePresetTokenChange(taxName: string, tokens: (string | { value: string })[]) {
		const slug = variationTaxonomies[taxName];
		const terms = taxonomyTerms[taxName] ?? [];
		const slugs = tokens.map((token) => {
			const label = typeof token === "string" ? token : token.value;
			const term = terms.find((t) => t.name === label);
			return term ? term.slug : label;
		});
		setAttributes({
			presetFilters: {
				...attributes.presetFilters,
				[slug]: slugs
			}
		});
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Archive Settings", "takt")} initialOpen={true}>
					<ToggleControl
						label={__("Display filters", "takt")}
						checked={attributes.displayFilters}
						onChange={(value) => setAttributes({ displayFilters: value })}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={__("Display sort", "takt")}
						checked={attributes.displaySort}
						onChange={(value) => setAttributes({ displaySort: value })}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={__("Display search", "takt")}
						checked={attributes.displaySearch}
						onChange={(value) => setAttributes({ displaySearch: value })}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={__("Display per page selector", "takt")}
						checked={attributes.displayPerPage}
						onChange={(value) => setAttributes({ displayPerPage: value })}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={__("Show Tags", "takt")}
						help={__("Display taxonomy tags on cards.", "takt")}
						checked={attributes.showTags}
						onChange={(value) => setAttributes({ showTags: value })}
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={__("Posts per page", "takt")}
						value={attributes.postsPerPage}
						onChange={(value) => setAttributes({ postsPerPage: value })}
						min={min}
						max={max}
						step={step}
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={__("Max columns", "takt")}
						value={attributes.maxColumns}
						onChange={(value) => {
							const newBase = value === 3 ? 9 : 10;
							// Reset postsPerPage to new base if current value doesn't divide evenly
							const newPostsPerPage =
								attributes.postsPerPage % newBase === 0 ? attributes.postsPerPage : newBase;
							setAttributes({
								maxColumns: value,
								postsPerPage: newPostsPerPage
							});
						}}
						min={1}
						max={4}
						__nextHasNoMarginBottom
					/>
					<ToggleGroupControl
						label={__("Pagination mode", "takt")}
						value={attributes.paginationMode}
						onChange={(value) => setAttributes({ paginationMode: value })}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="load-more" label={__("Load More", "takt")} />
						<ToggleGroupControlOption value="pagination" label={__("Pagination", "takt")} />
					</ToggleGroupControl>
				</PanelBody>

				{taxonomyNames.length > 0 && (
					<PanelBody title={__("Preset Filters", "takt")} initialOpen={false}>
						{taxonomyNames.map((taxName) => {
							const slug = variationTaxonomies[taxName];
							const terms = taxonomyTerms[taxName] ?? [];
							const suggestions = terms.map((t) => t.name);
							return (
								<FormTokenField
									key={taxName}
									label={slug}
									value={getTokensForTaxonomy(taxName)}
									suggestions={suggestions}
									onChange={(tokens) => handlePresetTokenChange(taxName, tokens)}
									__experimentalExpandOnFocus
									__experimentalShowHowTo={false}
									__nextHasNoMarginBottom
								/>
							);
						})}
					</PanelBody>
				)}
			</InspectorControls>

			<section
				{...useBlockProps({
					className: cn({
						"not-discourse archive dark bg-transparent!": true
					}),
					"data-pagination-mode": attributes.paginationMode,
					"data-post-type": attributes.blockVariation
				})}
			>
				<div
					className={cn({
						"container grid grid-cols-1 gap-16": true,
						"relative py-6 sm:py-16 dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)": true
					})}
				>
					<ThemeHeading
						eyebrow={attributes.eyebrow}
						heading={attributes.heading}
						headingSize={2}
						description={attributes.description}
						buttons={attributes.buttons}
						columns={2}
						enableButtons={true}
						onChange={(value) => {
							renameBlock(value.heading, attributes.heading, clientId);
							setAttributes(value);
						}}
					/>

					<FilteredServerSideRender
						querySelector=".archive-inner"
						className="archive-inner grid grid-cols-1 gap-gap-4"
					/>
				</div>
			</section>
		</>
	);
}
