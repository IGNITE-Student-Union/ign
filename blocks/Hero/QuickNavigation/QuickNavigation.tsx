import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	InspectorControls,
	RichText,
	__experimentalLinkControl as LinkControl
} from "@wordpress/block-editor";
import {
	PanelBody,
	Button,
	Popover,
	Icon,
	SelectControl,
	TextControl
} from "@wordpress/components";
import { useState, useRef } from "@wordpress/element";
import { pencil, trash, dragHandle } from "@wordpress/icons";
import { cn } from "@taktdev/utilities";
import { ReactComponent as IconChevron } from "./resources/IconChevron.svg";

type LinkItem = {
	id: string;
	label: string;
	url: string;
};

type QuickNavigationAttributes = {
	title: string;
	links: LinkItem[];
	buttonText: string;
	dropdownBehavior: "first" | "placeholder" | "random";
	placeholder: string;
};

type EditProps = {
	attributes: QuickNavigationAttributes;
	setAttributes: (attrs: Partial<QuickNavigationAttributes>) => void;
};

export default function Edit({ attributes, setAttributes }: EditProps) {
	const { title, links, buttonText, dropdownBehavior, placeholder } = attributes;

	// Determine which option to show in the preview
	const getPreviewOption = () => {
		if (dropdownBehavior === "placeholder") {
			return placeholder || "";
		}
		if (links.length > 0) {
			return links[0].label;
		}
		return "";
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Navigation Links", "takt")} initialOpen={true}>
					<LinkEditor links={links} onChange={(newLinks) => setAttributes({ links: newLinks })} />
				</PanelBody>
				<PanelBody title={__("Dropdown Settings", "takt")} initialOpen={false}>
					<SelectControl
						label={__("Default Selection", "takt")}
						value={dropdownBehavior}
						options={[
							{
								label: __("Show first item", "takt"),
								value: "first"
							},
							{
								label: __("Show placeholder", "takt"),
								value: "placeholder"
							},
							{
								label: __("Random item on page load", "takt"),
								value: "random"
							}
						]}
						onChange={(value) =>
							setAttributes({
								dropdownBehavior: value as "first" | "placeholder" | "random"
							})
						}
						__nextHasNoMarginBottom
					/>
					{dropdownBehavior === "placeholder" && (
						<TextControl
							label={__("Placeholder Text", "takt")}
							value={placeholder}
							onChange={(value) => setAttributes({ placeholder: value })}
							help={__("Leave empty to show a blank dropdown", "takt")}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					)}
				</PanelBody>
			</InspectorControls>

			<div
				{...useBlockProps({
					className:
						"quick-navigation dark-surface dark bg-charcoal rounded-[32px] p-8 flex flex-col sm:flex-row sm:items-center gap-x-12 gap-y-6 relative overflow-hidden not-discourse"
				})}
			>
				{/* Title */}
				<RichText
					tagName="span"
					className="font-heading text-[32px] sm:text-4xl text-white shrink-0 relative z-1 text-center sm:text-left"
					placeholder={__("Title…", "takt")}
					value={title}
					onChange={(value) => setAttributes({ title: value })}
					allowedFormats={[]}
				/>

				{/* Dropdown preview */}
				<div className="flex-1 relative z-1">
					<select
						className="w-full bg-black border-t-0! border-x-0! border-b border-white rounded-none! text-white py-2 pl-0! pr-8 min-h-0! focus:outline-none focus:border-white! text-xl"
						disabled
						value={dropdownBehavior === "placeholder" ? "__placeholder__" : links[0]?.url || ""}
					>
						{dropdownBehavior === "placeholder" && (
							<option value="__placeholder__">{getPreviewOption()}</option>
						)}
						{links.map((link) => (
							<option key={link.id} value={link.url}>
								{link.label}
							</option>
						))}
					</select>
					<span className="absolute right-0 top-1/2 -translate-y-1/2 pointer-events-none text-white">
						<IconChevron />
					</span>
				</div>

				{/* Button */}
				<RichText
					tagName="button"
					className="btn-secondary whitespace-nowrap relative z-1"
					placeholder={__("Button text…", "takt")}
					value={buttonText}
					onChange={(value) => setAttributes({ buttonText: value })}
					allowedFormats={[]}
				/>
			</div>
		</>
	);
}

/**
 * Generate a unique ID for new links
 */
function generateId(): string {
	return Math.random().toString(36).substring(2, 9);
}

/**
 * Link editor component with native HTML5 drag-drop reordering
 */
function LinkEditor({
	links,
	onChange
}: {
	links: LinkItem[];
	onChange: (links: LinkItem[]) => void;
}) {
	const [isAdding, setIsAdding] = useState(false);
	const [editingIndex, setEditingIndex] = useState<number | null>(null);
	const [draggedIndex, setDraggedIndex] = useState<number | null>(null);
	const [dragOverIndex, setDragOverIndex] = useState<number | null>(null);
	const dragNodeRef = useRef<HTMLDivElement | null>(null);
	const addButtonRef = useRef<HTMLButtonElement | null>(null);
	const itemRefs = useRef<Map<number, HTMLDivElement>>(new Map());

	const handleDragStart = (e: React.DragEvent<HTMLDivElement>, index: number) => {
		setDraggedIndex(index);
		dragNodeRef.current = e.currentTarget;
		e.dataTransfer.effectAllowed = "move";
		e.dataTransfer.setData("text/plain", String(index));
		setTimeout(() => {
			if (dragNodeRef.current) {
				dragNodeRef.current.style.opacity = "0.5";
			}
		}, 0);
	};

	const handleDragEnd = () => {
		if (dragNodeRef.current) {
			dragNodeRef.current.style.opacity = "1";
		}
		setDraggedIndex(null);
		setDragOverIndex(null);
		dragNodeRef.current = null;
	};

	const handleDragOver = (e: React.DragEvent<HTMLDivElement>, index: number) => {
		e.preventDefault();
		e.dataTransfer.dropEffect = "move";
		if (draggedIndex !== null && draggedIndex !== index) {
			setDragOverIndex(index);
		}
	};

	const handleDragLeave = () => {
		setDragOverIndex(null);
	};

	const handleDrop = (e: React.DragEvent<HTMLDivElement>, dropIndex: number) => {
		e.preventDefault();
		if (draggedIndex !== null && draggedIndex !== dropIndex) {
			const newLinks = [...links];
			const [draggedItem] = newLinks.splice(draggedIndex, 1);
			newLinks.splice(dropIndex, 0, draggedItem);
			onChange(newLinks);
		}
		handleDragEnd();
	};

	const handleAddLink = (value: { url?: string; title?: string }) => {
		if (value.url) {
			const newLink: LinkItem = {
				id: generateId(),
				label: value.title || value.url,
				url: value.url
			};
			onChange([...links, newLink]);
			setIsAdding(false);
		}
	};

	const handleEditLink = (index: number, value: { url?: string; title?: string }) => {
		if (value.url) {
			const updated = [...links];
			updated[index] = {
				...updated[index],
				label: value.title || value.url,
				url: value.url
			};
			onChange(updated);
			setEditingIndex(null);
		}
	};

	const handleRemoveLink = (index: number) => {
		const updated = links.filter((_, i) => i !== index);
		onChange(updated);
	};

	const editingLink = editingIndex !== null ? links[editingIndex] : null;
	const editingAnchor = editingIndex !== null ? itemRefs.current.get(editingIndex) : null;

	return (
		<div className="link-editor">
			{links.length > 0 && (
				<div
					className="link-list"
					style={{
						display: "flex",
						flexDirection: "column",
						gap: "8px"
					}}
				>
					{links.map((link, index) => (
						<div
							key={link.id}
							ref={(el) => {
								if (el) {
									itemRefs.current.set(index, el);
								} else {
									itemRefs.current.delete(index);
								}
							}}
							draggable
							onDragStart={(e) => handleDragStart(e, index)}
							onDragEnd={handleDragEnd}
							onDragOver={(e) => handleDragOver(e, index)}
							onDragLeave={handleDragLeave}
							onDrop={(e) => handleDrop(e, index)}
							className={cn({
								"components-panel__row": true
							})}
							style={{
								display: "flex",
								alignItems: "center",
								gap: "8px",
								padding: "12px",
								background: "#fff",
								border: "1px solid #e0e0e0",
								borderRadius: "4px",
								cursor: "grab",
								boxShadow: "0 1px 2px rgba(0,0,0,0.05)",
								borderColor:
									dragOverIndex === index && draggedIndex !== index ? "#007cba" : "#e0e0e0",
								borderTopWidth:
									dragOverIndex === index && draggedIndex !== null && draggedIndex > index
										? "3px"
										: "1px",
								borderBottomWidth:
									dragOverIndex === index && draggedIndex !== null && draggedIndex < index
										? "3px"
										: "1px"
							}}
						>
							{/* Drag handle */}
							<span
								style={{
									color: "#949494",
									display: "flex",
									alignItems: "center"
								}}
								aria-label={__("Drag to reorder", "takt")}
							>
								<Icon icon={dragHandle} size={18} />
							</span>

							{/* Link info */}
							<div style={{ flex: 1, minWidth: 0 }}>
								<div
									style={{
										fontWeight: 500,
										fontSize: "13px",
										overflow: "hidden",
										textOverflow: "ellipsis",
										whiteSpace: "nowrap"
									}}
								>
									{link.label || __("(no title)", "takt")}
								</div>
								<div
									style={{
										fontSize: "11px",
										color: "#757575",
										overflow: "hidden",
										textOverflow: "ellipsis",
										whiteSpace: "nowrap"
									}}
								>
									{link.url}
								</div>
							</div>

							{/* Actions */}
							<Button
								icon={<Icon icon={pencil} size={20} />}
								label={__("Edit link", "takt")}
								onClick={() => setEditingIndex(index)}
								style={{ minWidth: "auto", padding: "4px" }}
							/>
							<Button
								icon={<Icon icon={trash} size={20} />}
								label={__("Remove link", "takt")}
								isDestructive
								onClick={() => handleRemoveLink(index)}
								style={{ minWidth: "auto", padding: "4px" }}
							/>
						</div>
					))}
				</div>
			)}

			{links.length === 0 && (
				<p
					style={{
						color: "#757575",
						fontSize: "13px",
						marginBottom: "12px"
					}}
				>
					{__("No links added yet.", "takt")}
				</p>
			)}

			<Button
				ref={addButtonRef}
				variant="primary"
				onClick={() => setIsAdding(true)}
				style={{
					width: "100%",
					justifyContent: "center",
					marginTop: links.length > 0 ? "12px" : "0"
				}}
			>
				{__("Add Link", "takt")}
			</Button>

			{/* Add link popover */}
			{isAdding && addButtonRef.current && (
				<Popover
					anchor={addButtonRef.current}
					placement="left-start"
					offset={16}
					onClose={() => setIsAdding(false)}
				>
					<div style={{ padding: "8px", minWidth: "300px" }}>
						<LinkControl
							searchInputPlaceholder={__("Search or enter URL", "takt")}
							hasTextControl
							value={{}}
							onChange={handleAddLink}
							settings={[]}
							showInitialSuggestions
						/>
					</div>
				</Popover>
			)}

			{/* Edit link popover */}
			{editingIndex !== null && editingLink && editingAnchor && (
				<Popover
					anchor={editingAnchor}
					placement="left-start"
					offset={16}
					onClose={() => setEditingIndex(null)}
				>
					<div style={{ padding: "8px", minWidth: "300px" }}>
						<LinkControl
							searchInputPlaceholder={__("Search or enter URL", "takt")}
							hasTextControl
							forceIsEditingLink
							value={{
								url: editingLink.url,
								title: editingLink.label
							}}
							onChange={(value: { url?: string; title?: string }) =>
								handleEditLink(editingIndex, value)
							}
							settings={[]}
							showInitialSuggestions
						/>
					</div>
				</Popover>
			)}
		</div>
	);
}
