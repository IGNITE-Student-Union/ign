import { __ } from "@wordpress/i18n";
import { useRef, useState, useEffect } from "@wordpress/element";
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	BlockControls
} from "@wordpress/block-editor";
import {
	PanelBody,
	BaseControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalInputControl as InputControl
} from "@wordpress/components";
import { flipHorizontal, flipVertical, shadow } from "@wordpress/icons";
import {
	shouldDisplay,
	cn,
	fetchMedia,
	renameBlock,
	extractVideoId,
	addButtonToBlock
} from "@taktdev/utilities";

import { ImageDropUploader, MediaUploadPanel } from "@taktdev/components";
import { ThemeHeading } from "parts/ThemeHeading";
import { ReactComponent as IconPlay } from "./resources/IconPlay.svg";

addButtonToBlock("takt/content-with-media", __("Content With Media Settings", "takt"));

type ImageAttribute = {
	id: number | null;
	focalPoint?: { x: number; y: number };
};

type ContentWithMediaAttributes = {
	anchor?: string;
	isReversed: boolean;
	reverseRows: boolean;
	darkMode: boolean;
	eyebrow?: string;
	heading?: string;
	headingSize: "small" | "regular";
	mediaType: "image" | "video";
	imageLayout: "single" | "gallery";
	showAllImagesOnMobile: boolean;
	images: ImageAttribute[];
	videoSource: "file" | "youtube" | "vimeo";
	videoFile?: number;
	videoId?: string;
	posterImage: ImageAttribute;
};

type EditProps = {
	attributes: ContentWithMediaAttributes;
	setAttributes: (attrs: Partial<ContentWithMediaAttributes>) => void;
	clientId: string;
};

const ALLOWED_BLOCKS = [
	"core/paragraph",
	"core/list",
	"core/quote",
	"core/separator",
	"takt/button-row"
];

const TEMPLATE: [string, Record<string, unknown>][] = [
	["core/paragraph", { placeholder: __("Add your content here…", "takt") }],
	[
		"takt/button-row",
		{},
		[["takt/button", { button: { url: "", variation: "primary", title: "" } }]]
	]
];

export default function Edit({ attributes, setAttributes, clientId }: EditProps) {
	const { displayContent } = shouldDisplay();

	// InnerBlocks props
	const innerBlocksProps = useInnerBlocksProps(
		{ className: "discourse content-with-media-inner" },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false
		}
	);

	// Fetch all images
	const mediaImages = attributes.images.map((img) => fetchMedia(img?.id || null));
	const posterImage = fetchMedia(attributes.posterImage?.id || null);
	const videoFile = fetchMedia(attributes.videoFile || null);

	// Video playback state
	const [mediaRenderId, setMediaRenderId] = useState(0);
	const [isPlaying, setIsPlaying] = useState(false);
	const videoRef = useRef<HTMLDivElement>(null);

	// Reset playback state when media changes
	useEffect(() => {
		setMediaRenderId(mediaRenderId + 1);
		setIsPlaying(false);
	}, [
		attributes.mediaType,
		attributes.videoId,
		attributes.videoSource,
		attributes.posterImage?.id
	]);

	// Check if we have any media
	const hasImageMedia = mediaImages.some((img) => img?.source_url);
	const hasVideo =
		(attributes.videoSource === "file" && videoFile?.source_url) ||
		(["youtube", "vimeo"].includes(attributes.videoSource) && !!attributes.videoId);
	const hasMedia =
		attributes.mediaType === "image" ? hasImageMedia : hasVideo || posterImage?.source_url;

	const updateImage = (index: number, image: ImageAttribute) => {
		const newImages = [...attributes.images];
		newImages[index] = image;
		setAttributes({ images: newImages });
	};

	const playVideo = () => {
		if (!videoRef.current) {
			return;
		}

		const videoContainer = videoRef.current;

		if (attributes.videoSource === "file" && videoContainer.querySelector("video")) {
			const video = videoContainer.querySelector("video");
			if (video) {
				video.play();
				setIsPlaying(true);
			}
		} else if (attributes.videoSource === "youtube") {
			const wrapper = videoContainer.querySelector(".embed-video-container");
			if (wrapper) {
				wrapper.innerHTML = `<iframe src="https://www.youtube.com/embed/${attributes.videoId}?autoplay=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" loading="lazy" title="YouTube video player"></iframe>`;
				setIsPlaying(true);
			}
		} else if (attributes.videoSource === "vimeo") {
			const wrapper = videoContainer.querySelector(".embed-video-container");
			if (wrapper) {
				wrapper.innerHTML = `<iframe src="https://player.vimeo.com/video/${attributes.videoId}?autoplay=1&byline=0&portrait=0&badge=0&dnt=true&vimeo_logo=false&title=false" allow="autoplay; fullscreen; picture-in-picture" loading="lazy" title="Vimeo video player"></iframe>`;
				setIsPlaying(true);
			}
		}
	};

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
						onClick={() => setAttributes({ darkMode: !attributes.darkMode })}
						isPressed={attributes.darkMode}
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__("Settings", "takt")} initialOpen={true}>
					<ToggleGroupControl
						label={__("Media Type", "takt")}
						value={attributes.mediaType}
						onChange={(value) =>
							setAttributes({
								mediaType: value as "image" | "video"
							})
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="image" label={__("Images", "takt")} />
						<ToggleGroupControlOption value="video" label={__("Video", "takt")} />
					</ToggleGroupControl>

					{attributes.mediaType === "image" && (
						<ToggleGroupControl
							label={__("Image Layout", "takt")}
							value={attributes.imageLayout}
							onChange={(value) =>
								setAttributes({
									imageLayout: value as "single" | "gallery"
								})
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						>
							<ToggleGroupControlOption value="single" label={__("Image", "takt")} />
							<ToggleGroupControlOption value="gallery" label={__("Gallery", "takt")} />
						</ToggleGroupControl>
					)}

					{attributes.mediaType === "image" && attributes.imageLayout === "gallery" && (
						<ToggleControl
							label={__("Show All Images on Mobile", "takt")}
							help={
								attributes.showAllImagesOnMobile
									? __("All three images are shown on mobile and desktop.", "takt")
									: __("Only the main image is shown on mobile; all three remain on desktop.", "takt")
							}
							checked={attributes.showAllImagesOnMobile}
							onChange={(value) => setAttributes({ showAllImagesOnMobile: value })}
							__nextHasNoMarginBottom
						/>
					)}

					{attributes.mediaType === "video" && (
						<ToggleGroupControl
							label={__("Video Source", "takt")}
							value={attributes.videoSource}
							onChange={(value) =>
								setAttributes({
									videoSource: value as "file" | "youtube" | "vimeo"
								})
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						>
							<ToggleGroupControlOption value="file" label={__("File", "takt")} />
							<ToggleGroupControlOption value="youtube" label={__("YouTube", "takt")} />
							<ToggleGroupControlOption value="vimeo" label={__("Vimeo", "takt")} />
						</ToggleGroupControl>
					)}

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
						<ToggleGroupControlOption value="regular" label={__("Regular", "takt")} />
						<ToggleGroupControlOption value="small" label={__("Small", "takt")} />
					</ToggleGroupControl>
				</PanelBody>

				<PanelBody title={__("Media", "takt")} initialOpen={true}>
					{attributes.mediaType === "image" && (
						<>
							<MediaUploadPanel
								media={attributes.images[0]}
								label={
									attributes.imageLayout === "single"
										? __("Image", "takt")
										: __("Main Image", "takt")
								}
								onSelect={(image) => updateImage(0, image)}
							/>
							{attributes.imageLayout === "gallery" && (
								<>
									<MediaUploadPanel
										media={attributes.images[1]}
										label={__("Secondary Image 1", "takt")}
										onSelect={(image) => updateImage(1, image)}
									/>
									<MediaUploadPanel
										media={attributes.images[2]}
										label={__("Secondary Image 2", "takt")}
										onSelect={(image) => updateImage(2, image)}
									/>
								</>
							)}
						</>
					)}

					{attributes.mediaType === "video" && (
						<>
							{attributes.videoSource === "file" && (
								<MediaUploadPanel
									media={attributes.videoFile}
									label={__("Video File", "takt")}
									allowedTypes={["video"]}
									onSelect={(media) => setAttributes({ videoFile: media.id })}
									enableFocalPoint={false}
								/>
							)}

							{["vimeo", "youtube"].includes(attributes.videoSource) && (
								<BaseControl
									id="video-url-control"
									label={__("Video URL or ID", "takt")}
									__nextHasNoMarginBottom
								>
									<InputControl
										__next40pxDefaultSize
										label={__("Video URL or ID", "takt")}
										hideLabelFromVision
										value={attributes.videoId}
										autoComplete="off"
										spellCheck={false}
										type="text"
										placeholder={attributes.videoSource === "youtube" ? "dQw4w9WgXcQ" : "123456789"}
										onChange={(newValue) => {
											const videoId = extractVideoId(newValue || "");
											setAttributes({ videoId });
										}}
									/>
								</BaseControl>
							)}

							<MediaUploadPanel
								media={attributes.posterImage}
								label={__("Poster Image", "takt")}
								onSelect={(image) => setAttributes({ posterImage: image })}
							/>
						</>
					)}
				</PanelBody>
			</InspectorControls>

			<section
				{...useBlockProps({
					className: cn({
						"not-discourse": true,
						"content-with-media": true,
						"py-6 sm:py-16": !attributes.darkMode,
						"dark bg-transparent!": attributes.darkMode
					})
				})}
			>
				<div
					className={cn({
						"container grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-10 sm:gap-y-16 items-stretch": true,
						"max-md:gap-y-0!": attributes.darkMode
					})}
				>
					<div
						className={cn({
							"flex flex-col justify-center gap-8": true,
							"relative py-6 md:py-8 dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)":
								attributes.darkMode,
							"md:col-start-1": attributes.isReversed,
							"md:col-start-2": !attributes.isReversed
						})}
					>
						<ThemeHeading
							eyebrow={attributes.eyebrow}
							heading={attributes.heading}
							headingSize={attributes.headingSize === "small" ? 3 : 2}
							enableDescription={false}
							enableButtons={false}
							onChange={(value: Partial<ContentWithMediaAttributes>) => {
								renameBlock(value.heading, attributes.heading, clientId);
								setAttributes(value);
							}}
						/>
						<div {...innerBlocksProps} />
					</div>

					{(displayContent || hasMedia) && (
						<div
							className={cn({
								"-order-1": !attributes.reverseRows,
								"relative py-6 md:py-8 dark-surface before:absolute before:bg-charcoal before:rounded-3xl before:-z-1 before:-inset-x-[calc(var(--side-gutter)/2)] before:inset-y-0 md:before:-inset-x-(--bg-extend)":
									attributes.darkMode,
								"md:order-none md:row-start-1": true,
								"md:col-start-1": !attributes.isReversed,
								"md:col-start-2": attributes.isReversed
							})}
						>
							<div
								className={cn({
									"md:sticky md:top-[calc(var(--fixed-elements-height,0px)+1rem)]": true,
									"md:min-h-[450px] md:overflow-hidden md:rounded-3xl":
										!(attributes.mediaType === "image" && attributes.imageLayout === "gallery")
								})}
							>
								{/* Image */}
								{attributes.mediaType === "image" && attributes.imageLayout === "single" && (
									<div
										className={cn({
											"w-full aspect-[1.08] md:aspect-[624/677] overflow-hidden rounded-3xl": true,
											"default-mask": !attributes.darkMode
										})}
									>
										<ImageDropUploader
											image={attributes.images[0]}
											onSelect={(image) => updateImage(0, image)}
											className="w-full! h-full! object-cover"
											imageClassName="w-full! h-full! object-cover"
											placeholderClassName="w-full h-full"
										/>
									</div>
								)}

								{/* Image Gallery */}
								{attributes.mediaType === "image" && attributes.imageLayout === "gallery" && (
									<div className="flex flex-col gap-4">
										{/* Main large image */}
										<div className="w-full aspect-[588/458] overflow-hidden rounded-xl md:rounded-3xl">
											<ImageDropUploader
												image={attributes.images[0]}
												onSelect={(image) => updateImage(0, image)}
												className="w-full! h-full! object-cover"
												imageClassName="w-full! h-full! object-cover"
												placeholderClassName="w-full h-full"
											/>
										</div>

										{/* Secondary images row - left large, right always small */}
										<div
											className={cn({
												"flex gap-4 items-start": true,
												"max-md:hidden": !attributes.showAllImagesOnMobile
											})}
										>
											<div className="flex-1 aspect-[366/203] overflow-hidden rounded-xl md:rounded-3xl">
												<ImageDropUploader
													image={attributes.images[1]}
													onSelect={(image) => updateImage(1, image)}
													className="w-full! h-full! object-cover"
													imageClassName="w-full! h-full! object-cover"
													placeholderClassName="w-full h-full"
												/>
											</div>
											<div className="w-[35%] flex-none aspect-[206/123] overflow-hidden rounded-xl md:rounded-3xl">
												<ImageDropUploader
													image={attributes.images[2]}
													onSelect={(image) => updateImage(2, image)}
													className="w-full! h-full! object-cover"
													imageClassName="w-full! h-full! object-cover"
													placeholderClassName="w-full h-full"
												/>
											</div>
										</div>
									</div>
								)}

								{/* Video */}
								{attributes.mediaType === "video" && (
									<div
										ref={videoRef}
										key={mediaRenderId}
										className="content-with-media-video-container relative w-full aspect-[1.08] grid grid-cols-1 bg-black overflow-hidden rounded-3xl"
									>
										{/* Poster Image + Play Button (hidden when playing) */}
										{(!!attributes.posterImage?.id || !hasVideo) && (
											<button
												onClick={playVideo}
												type="button"
												aria-label={__("Play video", "takt")}
												className={cn({
													"w-full h-full col-1 row-1 cursor-pointer z-2 content-with-media-play-button group relative": true,
													hidden: isPlaying
												})}
											>
												<ImageDropUploader
													image={attributes.posterImage}
													onSelect={(image) =>
														setAttributes({
															posterImage: image
														})
													}
													className="absolute! inset-0 w-full! h-full! object-cover pointer-events-none!"
													imageClassName="absolute! inset-0 w-full! h-full! object-cover pointer-events-none!"
													placeholderClassName="absolute inset-0 w-full h-full *:max-h-full *:max-w-full"
												/>
												{hasVideo && (
													<span
														className="absolute inset-0 flex items-center justify-center z-2"
														aria-hidden="true"
													>
														<span className="play-icon">
															<IconPlay />
														</span>
													</span>
												)}
											</button>
										)}

										{/* Actual Video (file upload) */}
										{!!videoFile && attributes.videoSource === "file" && (
											<video
												className="w-full! h-full! object-cover col-1 row-1"
												playsInline
												controls
												preload="none"
											>
												<source src={videoFile.source_url} />
											</video>
										)}

										{/* Embedded Video (YouTube/Vimeo) */}
										{!!attributes.videoId &&
											["vimeo", "youtube"].includes(attributes.videoSource) && (
												<div
													className="w-full h-full embed-video-container *:w-full *:h-full col-1 row-1 *:bg-black"
													data-source={attributes.videoSource}
													data-video-id={attributes.videoId}
												>
													{/* Iframe inserted here when play button clicked */}
												</div>
											)}
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
