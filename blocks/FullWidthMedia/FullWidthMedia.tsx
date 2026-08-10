import { __ } from "@wordpress/i18n";
import { useRef, useState, useEffect } from "@wordpress/element";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
	PanelBody,
	BaseControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalInputControl as InputControl
} from "@wordpress/components";
import { shouldDisplay, cn, fetchMedia, renameBlock, extractVideoId } from "@taktdev/utilities";
import { ImageDropUploader, MediaUploadPanel } from "@taktdev/components";
import { ThemeHeading } from "parts/ThemeHeading";
import { ReactComponent as IconPlay } from "./resources/IconPlay.svg";

type ImageAttribute = {
	id: number | null;
	focalPoint?: { x: number; y: number };
};

type FullWidthMediaAttributes = {
	anchor?: string;
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
	mediaType: "image" | "video";
	image: ImageAttribute;
	videoSource: "file" | "youtube" | "vimeo";
	videoFile?: number;
	videoId?: string;
	posterImage: ImageAttribute;
};

type EditProps = {
	attributes: FullWidthMediaAttributes;
	setAttributes: (attrs: Partial<FullWidthMediaAttributes>) => void;
	clientId: string;
};

export default function Edit({ attributes, setAttributes, clientId }: EditProps) {
	const { displayContent } = shouldDisplay();

	const mediaImage = fetchMedia(attributes.image?.id || null);
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
	const hasImageMedia = mediaImage?.source_url;
	const hasVideo =
		(attributes.videoSource === "file" && videoFile?.source_url) ||
		(["youtube", "vimeo"].includes(attributes.videoSource) && !!attributes.videoId);
	const hasMedia =
		attributes.mediaType === "image" ? hasImageMedia : hasVideo || posterImage?.source_url;

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
				wrapper.innerHTML = `<iframe src="https://www.youtube.com/embed/${
					attributes.videoId
				}?autoplay=1" title="${__(
					"Video player",
					"takt"
				)}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" loading="lazy"></iframe>`;
				setIsPlaying(true);
			}
		} else if (attributes.videoSource === "vimeo") {
			const wrapper = videoContainer.querySelector(".embed-video-container");
			if (wrapper) {
				wrapper.innerHTML = `<iframe src="https://player.vimeo.com/video/${
					attributes.videoId
				}?autoplay=1&byline=0&portrait=0&badge=0&dnt=true&vimeo_logo=false&title=false" title="${__(
					"Video player",
					"takt"
				)}" allow="autoplay; fullscreen; picture-in-picture" loading="lazy"></iframe>`;
				setIsPlaying(true);
			}
		}
	};

	return (
		<>
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
						<ToggleGroupControlOption value="image" label={__("Image", "takt")} />
						<ToggleGroupControlOption value="video" label={__("Video", "takt")} />
					</ToggleGroupControl>

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
				</PanelBody>

				<PanelBody title={__("Media", "takt")} initialOpen={true}>
					{attributes.mediaType === "image" && (
						<MediaUploadPanel
							media={attributes.image}
							label={__("Image", "takt")}
							onSelect={(image) => setAttributes({ image })}
						/>
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
									id="full-width-media-video-url"
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
					className: "full-width-media py-6 sm:py-16 not-discourse"
				})}
			>
				<div className="container flex flex-col gap-16">
					{/* Content Area */}
					<ThemeHeading
						eyebrow={attributes.eyebrow}
						heading={attributes.heading}
						headingSize={2}
						description={attributes.description}
						buttons={attributes.buttons}
						columns={2}
						onChange={(value: Partial<FullWidthMediaAttributes>) => {
							renameBlock(value.heading, attributes.heading, clientId);
							setAttributes(value);
						}}
					/>

					{/* Media Area */}
					{(displayContent || hasMedia) && (
						<div className="w-full">
							{/* Image */}
							{attributes.mediaType === "image" && (
								<div className="dark-surface relative before:absolute before:-inset-[18px] sm:before:-inset-(--bg-extend) before:bg-charcoal before:rounded-3xl before:pointer-events-none">
									<div className="w-full h-[294px] sm:h-[600px] default-mask overflow-hidden relative z-1">
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

							{/* Video */}
							{attributes.mediaType === "video" && (
								<div className="dark-surface relative before:absolute before:-inset-[18px] sm:before:-inset-(--bg-extend) before:bg-charcoal before:rounded-3xl before:pointer-events-none">
									<div
										ref={videoRef}
										key={mediaRenderId}
										className="full-width-media-video-container relative w-full aspect-video grid grid-cols-1 default-mask overflow-hidden z-1"
									>
										{/* Poster Image + Play Button (hidden when playing) */}
										{(!!attributes.posterImage?.id || !hasVideo) && (
											<button
												onClick={playVideo}
												type="button"
												aria-label={__("Play video", "takt")}
												className={cn({
													"w-full h-full min-h-0 col-1 row-1 cursor-pointer z-2 full-width-media-play-button group relative": true,
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
												className="w-full! h-full! min-h-0! object-cover col-1 row-1"
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
													className="w-full h-full min-h-0 embed-video-container *:w-full *:h-full col-1 row-1 *:bg-black"
													data-source={attributes.videoSource}
													data-video-id={attributes.videoId}
												>
													{/* Iframe inserted here when play button clicked */}
												</div>
											)}
									</div>
								</div>
							)}
						</div>
					)}
				</div>
			</section>
		</>
	);
}
