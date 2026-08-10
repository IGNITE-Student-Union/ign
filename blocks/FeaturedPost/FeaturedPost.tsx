import { __ } from "@wordpress/i18n";
import { useEffect, useRef } from "@wordpress/element";
import { useBlockProps, InspectorControls, BlockControls } from "@wordpress/block-editor";
import {
	PanelBody,
	ToolbarButton,
	ToolbarGroup,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption
} from "@wordpress/components";
import { flipHorizontal, flipVertical, shadow } from "@wordpress/icons";
import { shouldDisplay, cn, fetchMedia, renameBlock } from "@taktdev/utilities";
import { PostSelectorSortable, AdvancedRichText } from "@taktdev/components";
import { useSelect } from "@wordpress/data";
import { store as coreStore } from "@wordpress/core-data";

type FeaturedPostAttributes = {
	anchor?: string;
	selectedPost?: number;
	isReversed: boolean;
	reverseRows: boolean;
	darkMode: boolean;
	buttonLabel: string;
	headingSize: "small" | "regular";
	eyebrow?: string;
};

type EditProps = {
	attributes: FeaturedPostAttributes;
	setAttributes: (attrs: Partial<FeaturedPostAttributes>) => void;
	clientId: string;
};

export default function Edit({ attributes, setAttributes, clientId }: EditProps) {
	const { displayContent } = shouldDisplay();

	// Fetch selected post data with embedded media and terms
	const post = useSelect(
		(select) => {
			if (!attributes.selectedPost) {
				return null;
			}
			// @ts-ignore
			return select(coreStore).getEntityRecord("postType", "post", attributes.selectedPost, {
				_embed: true
			});
		},
		[attributes.selectedPost]
	);

	// Extract post data
	const postTitle = post?.title?.rendered || "";
	const postExcerpt = post?.excerpt?.rendered
		? post.excerpt.rendered.replace(/<[^>]+>/g, "").trim()
		: "";
	const featuredMediaId = post?._embedded?.["wp:featuredmedia"]?.[0]?.id || null;
	const firstCategory = post?._embedded?.["wp:term"]?.[0]?.[0] || null;

	// Fetch category accent color
	const categoryAccentColor = useSelect(
		(select) => {
			if (!firstCategory?.id) {
				return "";
			}
			// @ts-ignore
			const term = select(coreStore).getEntityRecord("taxonomy", "category", firstCategory.id);
			return term?.meta?.accent_color || "";
		},
		[firstCategory?.id]
	);

	// Fetch the featured image
	const featuredImage = fetchMedia(featuredMediaId);

	// Rename block based on post title
	const headingTag = attributes.headingSize === "small" ? "h3" : "h2";
	const prevTitleRef = useRef(postTitle);
	useEffect(() => {
		if (postTitle !== prevTitleRef.current) {
			renameBlock(postTitle, prevTitleRef.current, clientId);
			prevTitleRef.current = postTitle;
		}
	}, [postTitle, clientId]);

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={flipHorizontal}
						label={__("Reverse Columns", "takt")}
						onClick={() =>
							setAttributes({
								isReversed: !attributes.isReversed
							})
						}
						isPressed={attributes.isReversed}
					/>
					<ToolbarButton
						icon={flipVertical}
						label={__("Reverse Rows (Mobile)", "takt")}
						onClick={() =>
							setAttributes({
								reverseRows: !attributes.reverseRows
							})
						}
						isPressed={attributes.reverseRows}
					/>
					<ToolbarButton
						icon={shadow}
						label={__("Dark Mode", "takt")}
						onClick={() =>
							setAttributes({
								darkMode: !attributes.darkMode
							})
						}
						isPressed={attributes.darkMode}
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__("Post Selection", "takt")} initialOpen={true}>
					<PostSelectorSortable
						value={attributes.selectedPost || 0}
						onChange={(value) => {
							const postId = typeof value === "number" ? value : 0;
							setAttributes({
								selectedPost: postId || undefined
							});
						}}
						postTypes={["post"]}
						limit={1}
						title={__("Select Post", "takt")}
					/>
				</PanelBody>

				<PanelBody title={__("Settings", "takt")} initialOpen={true}>
					<ToggleGroupControl
						label={__("Heading Size", "takt")}
						value={attributes.headingSize}
						onChange={(value) =>
							setAttributes({
								headingSize: value as "small" | "regular"
							})
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="regular" label={__("Regular (H2)", "takt")} />
						<ToggleGroupControlOption value="small" label={__("Small (H3)", "takt")} />
					</ToggleGroupControl>
				</PanelBody>
			</InspectorControls>

			<section
				{...useBlockProps({
					className: cn({
						"not-discourse": true,
						"featured-post": true,
						"py-6 sm:py-16": !attributes.darkMode,
						"dark bg-transparent!": attributes.darkMode
					})
				})}
			>
				<div
					className={cn({
						"container grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-10 sm:gap-y-16 items-stretch": true,
						"relative py-6 sm:py-16 gap-y-8! dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)":
							attributes.darkMode
					})}
				>
					{/* Content Column */}
					<div
						className={cn({
							"flex items-center": true,
							"md:col-start-1": attributes.isReversed,
							"md:col-start-2": !attributes.isReversed
						})}
					>
						<div className="grid grid-cols-1 h-fit">
							{/* Topic Tag Row */}
							{(displayContent || firstCategory || attributes.eyebrow) && (
								<div className="flex gap-4 items-center not-last:mb-6">
									{(displayContent || firstCategory) && (
										<span
											className={cn({
												"inline-flex items-center justify-center px-2 py-1 rounded-full bg-accent-lighter border border-accent text-sm font-medium uppercase leading-[1.1] whitespace-nowrap shrink-0 text-charcoal": true,
												[categoryAccentColor]: !!categoryAccentColor
											})}
										>
											{firstCategory?.name || __("Topic Tag", "takt")}
										</span>
									)}
									{(displayContent || attributes.eyebrow) && (
										<AdvancedRichText
											className="uppercase text-sm font-medium leading-[1.1]"
											tagName="span"
											value={attributes.eyebrow || ""}
											allowedFormats={[]}
											placeholder={__("Eyebrow text…", "takt")}
											onChange={(value: string) =>
												setAttributes({
													eyebrow: value
												})
											}
										/>
									)}
								</div>
							)}

							{/* Heading (Post Title - read only) */}
							{(displayContent || postTitle) && (
								<div
									className={cn({
										"not-last:mb-8 not-last:md:mb-12": true,
										"text-header-2": attributes.headingSize === "regular",
										"text-header-3": attributes.headingSize === "small"
									})}
								>
									{React.createElement(
										headingTag,
										{},
										postTitle || __("Select a post to feature", "takt")
									)}
								</div>
							)}

							{/* Description (Post Excerpt - read only) */}
							{(displayContent || postExcerpt) && (
								<div className="group not-last:mb-8 not-last:md:mb-12">
									<p>{postExcerpt || __("Post excerpt will appear here…", "takt")}</p>
								</div>
							)}

							{/* Button */}
							{(displayContent || attributes.buttonLabel) && (
								<div className="inline-flex flex-wrap gap-4 items-center">
									<span className="btn-primary">
										<AdvancedRichText
											tagName="span"
											value={attributes.buttonLabel}
											allowedFormats={[]}
											placeholder={__("Learn More", "takt")}
											onChange={(value: string) =>
												setAttributes({
													buttonLabel: value
												})
											}
										/>
									</span>
								</div>
							)}
						</div>
					</div>

					{/* Media Column */}
					{(displayContent || featuredImage?.source_url) && (
						<div
							className={cn({
								"-order-1": !attributes.reverseRows,
								"md:order-none md:row-start-1": true,
								"md:col-start-1": !attributes.isReversed,
								"md:col-start-2": attributes.isReversed
							})}
						>
							<div className="w-full aspect-[4/3] overflow-hidden default-mask">
								{featuredImage?.source_url ? (
									<img
										src={featuredImage.source_url}
										alt={featuredImage.alt_text || ""}
										className="w-full h-full object-cover"
									/>
								) : (
									<div className="w-full h-full bg-gray-200 flex items-center justify-center text-gray-400">
										{__("Featured Image", "takt")}
									</div>
								)}
							</div>
						</div>
					)}
				</div>
			</section>
		</>
	);
}
