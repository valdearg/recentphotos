<template>
	<div>
		<div v-if="loading && images.length === 0" class="loading-state">
			Loading images…
		</div>

		<div v-else-if="images.length === 0" class="empty-state">
			No media found in the current index.
		</div>

		<div v-else class="grid" :class="gridClass">
			<div v-for="(image, index) in images" :key="image.id" class="tile"
				:class="{ 'tile--select-mode': selectMode, 'tile--selected': isSelected(image) }"
				role="button" tabindex="0" :aria-pressed="selectMode ? (isSelected(image) ? 'true' : 'false') : null"
				@mouseenter="showHoverInfo(image, $event)" @mousemove="moveInfo($event)" @mouseleave="hideInfo"
				@focusin="showHoverInfo(image, $event)" @focusout="hideInfo"
				@click="onTileClick(image, index)" @keydown.enter.prevent="onTileKeydown(image, index)"
				@keydown.space.prevent="onTileKeydown(image, index)">
				<button v-if="selectMode" type="button" class="tile-select-control"
					:class="{ 'is-selected': isSelected(image) }"
					:title="isSelected(image) ? 'Deselect image' : 'Select image'"
					:aria-label="isSelected(image) ? 'Deselect image' : 'Select image'"
					:aria-pressed="isSelected(image) ? 'true' : 'false'" @click.stop="$emit('toggle-select', image)">
					<svg viewBox="0 0 24 24">
						<path d="M20 6 9 17l-5-5" />
					</svg>
				</button>

				<div v-if="!selectMode" class="tile-actions" @click.stop>
					<a class="tile-icon-link" :href="image.openUrl || image.downloadUrl" target="_blank" rel="noopener"
						title="Open in new tab" aria-label="Open in new tab">
						<svg viewBox="0 0 24 24">
							<path d="M14 3h7v7M21 3l-9 9M5 5h6v2H7v10h10v-4h2v6H5z" />
						</svg>
					</a>
				</div>

				<div v-if="image.mediaType === 'video'" class="tile-badge">▶</div>
				<div v-else-if="image.mediaType === 'gif'" class="tile-badge">GIF</div>

				<div class="tile-media" :class="[tileMediaClass(image), { 'tile-media--loaded': isImageLoaded(image) }]"
					:style="tileMediaStyle(image)">
					<img class="tile-image" :src="image.previewUrl" :alt="image.name" :class="{ 'is-loaded': isImageLoaded(image) }"
						@load="markImageLoaded(image, $event)" @error="markImageLoaded(image)">
				</div>

				<div v-if="!hideThumbnailInfo" class="meta">
					<div class="name">{{ image.name }}</div>
					<div class="sub">
						{{ formatDate(image.dateTaken || image.created) }}
					</div>
					<div v-if="showTags && visibleTags(image).length" class="tile-tags" @click.stop>
						<a v-for="tag in visibleTags(image)" :key="tag.id" class="tile-tag" :href="tagUrl(tag)"
							:style="tagStyle(tag)" target="_blank" rel="noopener" title="Open tagged files">
							{{ tag.name }}
						</a>
						<span v-if="remainingTagsCount(image) > 0" class="tile-tag tile-tag-more">
							+{{ remainingTagsCount(image) }}
						</span>
					</div>
				</div>
			</div>
		</div>

		<div v-if="hoverImage" class="tile-info-popover" :class="{ 'above': hoverInfoAbove }" :style="hoverInfoStyle"
			aria-hidden="true">
			<div><strong>Name:</strong> {{ hoverImage.name }}</div>
			<div><strong>Type:</strong> {{ hoverImage.mediaType }}</div>
			<div><strong>Date taken:</strong> {{ formatDate(hoverImage.dateTaken) }}</div>
			<div><strong>Created:</strong> {{ formatDate(hoverImage.created) }}</div>
			<div><strong>Modified:</strong> {{ formatDate(hoverImage.modified) }}</div>
			<div><strong>Size:</strong> {{ formatBytes(hoverImage.size) }}</div>
			<div><strong>MIME:</strong> {{ hoverImage.mime }}</div>
			<div v-if="hoverImage.fileTags && hoverImage.fileTags.length" class="tile-info-tags">
				<strong>File tags:</strong>
				<span class="tag-list">
					<span v-for="tag in hoverImage.fileTags" :key="tag.id" class="folder-tag" :style="tagStyle(tag)">
						{{ tag.name }}
					</span>
				</span>
			</div>
			<div v-if="hoverImage.folderTags && hoverImage.folderTags.length" class="tile-info-tags">
				<strong>Folder tags:</strong>
				<span class="tag-list">
					<span v-for="tag in hoverImage.folderTags" :key="tag.id" class="folder-tag" :style="tagStyle(tag)">
						{{ tag.name }}
					</span>
				</span>
			</div>
			<div><strong>Path:</strong> {{ relativePath(hoverImage) }}</div>
		</div>

		<div v-if="loading && images.length > 0" class="loading-more">
			<span class="loading-spinner" aria-hidden="true"></span>
			<span>Fetching more photos</span>
		</div>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'ImageGrid',
	props: {
		images: { type: Array, required: true },
		loading: { type: Boolean, default: false },
		showInfo: { type: Boolean, default: true },
		showTags: { type: Boolean, default: false },
		thumbnailMode: { type: String, default: 'square' },
		hideThumbnailInfo: { type: Boolean, default: false },
		selectMode: { type: Boolean, default: false },
		selectedIds: { type: Object, default: () => ({}) },
	},
	data() {
		return {
			hoverImage: null,
			hoverInfoLeft: 16,
			hoverInfoTop: 16,
			hoverInfoAbove: false,
			hoverInfoMaxHeight: 320,
			loadedImageIds: {},
			imageRatios: {},
		}
	},
	computed: {
		hoverInfoStyle() {
			return {
				left: `${this.hoverInfoLeft}px`,
				top: `${this.hoverInfoTop}px`,
				maxHeight: `${this.hoverInfoMaxHeight}px`,
			}
		},
		gridClass() {
			return {
				'grid--fit': this.thumbnailMode === 'fit',
			}
		},
	},
	watch: {
		showInfo(value) {
			if (!value) {
				this.hideInfo()
			}
		},
		selectMode(value) {
			if (value) {
				this.hideInfo()
			}
		},
	},
	methods: {
		showHoverInfo(image, e) {
			if (!this.showInfo || this.selectMode) return

			this.hoverImage = image
			this.moveInfo(e)
		},
		moveInfo(e) {
			if (!this.showInfo || this.selectMode || !this.hoverImage) return

			const source = e?.currentTarget
			const rect = source?.getBoundingClientRect ? source.getBoundingClientRect() : null
			const clientX = e?.clientX || (rect ? rect.right : 16)
			const clientY = e?.clientY || (rect ? rect.top : 16)
			const popoverWidth = Math.min(380, Math.max(280, window.innerWidth - 32))
			const margin = 16

			this.hoverInfoAbove = clientY > window.innerHeight * 0.58
			this.hoverInfoLeft = Math.max(margin, Math.min(clientX + 16, window.innerWidth - popoverWidth - margin))
			this.hoverInfoTop = this.hoverInfoAbove
				? Math.max(margin, clientY - 16)
				: Math.min(window.innerHeight - margin, clientY + 16)
			this.hoverInfoMaxHeight = Math.max(
				160,
				this.hoverInfoAbove ? clientY - (margin * 2) : window.innerHeight - clientY - (margin * 2),
			)
		},
		hideInfo() {
			this.hoverImage = null
		},
		isSelected(image) {
			return !!this.selectedIds[String(image?.id || '')]
		},
		onTileClick(image, index) {
			if (this.selectMode) {
				this.$emit('toggle-select', image)
				return
			}

			this.$emit('open', { image, index })
		},
		onTileKeydown(image, index) {
			this.onTileClick(image, index)
		},
		isImageLoaded(image) {
			return !!this.loadedImageIds[this.imageLoadKey(image)]
		},
		markImageLoaded(image, e = null) {
			const img = e?.target
			if (img?.naturalWidth > 0 && img?.naturalHeight > 0) {
				this.$set(this.imageRatios, this.imageLoadKey(image), `${img.naturalWidth} / ${img.naturalHeight}`)
			}
			this.$set(this.loadedImageIds, this.imageLoadKey(image), true)
		},
		imageLoadKey(image) {
			return `${image?.id || ''}:${image?.previewUrl || ''}`
		},
		tileMediaClass(image) {
			if (this.thumbnailMode !== 'fit') {
				return {}
			}

			return {
				[`tile-media--${this.estimatedShape(image)}`]: true,
			}
		},
		tileMediaStyle(image) {
			if (this.thumbnailMode !== 'fit') {
				return {}
			}

			const ratio = this.imageRatios[this.imageLoadKey(image)]
			return ratio ? { '--tile-ratio': ratio } : {}
		},
		estimatedShape(image) {
			if (image?.width > 0 && image?.height > 0) {
				const ratio = image.width / image.height
				if (ratio >= 1.55) return 'wide'
				if (ratio <= 0.68) return 'tall'
				if (ratio <= 0.88) return 'portrait'
				return 'landscape'
			}

			const key = `${image?.id || ''}${image?.name || ''}`
			let hash = 0
			for (let i = 0; i < key.length; i++) {
				hash = ((hash << 5) - hash) + key.charCodeAt(i)
				hash |= 0
			}

			const shapes = ['landscape', 'portrait', 'wide', 'landscape', 'tall']
			return shapes[Math.abs(hash) % shapes.length]
		},
		formatDate(ts) {
			return ts ? new Date(ts * 1000).toLocaleString() : 'Unknown'
		},
		formatBytes(b) {
			if (!b && b !== 0) return 'Unknown'
			const u = ['B', 'KB', 'MB', 'GB', 'TB']
			let i = 0
			let value = b
			while (value >= 1024 && i < u.length - 1) {
				value /= 1024
				i++
			}
			return `${value.toFixed(value >= 10 || i === 0 ? 0 : 1)} ${u[i]}`
		},
		relativePath(image) {
			if (!image || !image.path) {
				return image?.name || ''
			}

			return image.path
				.replace(/^\/[^/]+\/files\//, '')
				.replace(/^\/+/, '')
		},
		tagStyle(tag) {
			if (!tag || !tag.color) return {}

			const color = String(tag.color).trim()
			if (!/^#?[0-9a-f]{6}$/i.test(color)) return {}

			const normalized = color.startsWith('#') ? color : `#${color}`
			return {
				borderColor: normalized,
				backgroundColor: `${normalized}26`,
			}
		},
		tagUrl(tag) {
			const tagId = encodeURIComponent(tag?.id || '')
			return `${generateUrl('/apps/files/tags')}?dir=${encodeURIComponent(`/${tagId}`)}`
		},
		visibleTags(image) {
			return this.uniqueTags(image).slice(0, 3)
		},
		remainingTagsCount(image) {
			return Math.max(0, this.uniqueTags(image).length - 3)
		},
		uniqueTags(image) {
			const tags = [...(image?.fileTags || []), ...(image?.folderTags || [])]
			const seen = new Set()
			const unique = []

			for (const tag of tags) {
				const id = String(tag?.id || '')
				if (!id || seen.has(id)) {
					continue
				}

				seen.add(id)
				unique.push(tag)
			}

			return unique
		},
	},
}
</script>

<style scoped>
.grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
	gap: 14px;
	width: 100%;
	overflow: visible;
}

.grid--fit {
	display: block;
	column-count: auto;
	column-width: 260px;
	column-gap: 14px;
}

.tile {
	position: relative;
	display: block;
	color: inherit;
	border-radius: 10px;
	overflow: hidden;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	min-width: 0;
	cursor: pointer;
}

.grid--fit .tile {
	display: inline-block;
	width: 100%;
	margin: 0 0 14px;
	break-inside: avoid;
	page-break-inside: avoid;
}

.tile:hover,
.tile:focus-within {
	z-index: 20;
}

.tile:focus-visible {
	outline: 2px solid var(--color-primary-element, currentColor);
	outline-offset: 2px;
}

.tile--select-mode {
	cursor: default;
}

.tile--selected {
	border-color: var(--color-primary-element, currentColor);
	box-shadow: inset 0 0 0 2px var(--color-primary-element, currentColor);
}

.tile--selected .tile-media::after {
	content: "";
	position: absolute;
	inset: 0;
	background: color-mix(in srgb, var(--color-primary-element, #0082c9) 18%, transparent);
	pointer-events: none;
}

.tile-actions {
	position: absolute;
	top: 10px;
	right: 10px;
	z-index: 2;
}

.tile-select-control {
	position: absolute;
	top: 10px;
	right: 10px;
	z-index: 3;
	appearance: none;
	-webkit-appearance: none;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 38px;
	height: 38px;
	padding: 0;
	margin: 0;
	border: 1px solid rgba(255, 255, 255, 0.32);
	border-radius: 8px;
	background: rgba(0, 0, 0, 0.62);
	color: white;
	cursor: pointer;
	box-sizing: border-box;
}

.tile-select-control:hover,
.tile-select-control.is-selected {
	background: var(--color-primary-element, #0082c9);
	border-color: rgba(255, 255, 255, 0.5);
}

.tile-select-control svg {
	width: 19px;
	height: 19px;
	fill: none;
	stroke: currentColor;
	stroke-width: 2.4;
	opacity: 0;
}

.tile-select-control.is-selected svg {
	opacity: 1;
}

.tile-icon-link {
	appearance: none;
	-webkit-appearance: none;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 38px;
	height: 38px;
	padding: 0;
	margin: 0;
	border: 1px solid rgba(255, 255, 255, 0.25);
	background: rgba(0, 0, 0, 0.65);
	color: white;
	border-radius: 8px;
	text-decoration: none;
	box-sizing: border-box;
}

.tile-icon-link:hover {
	background: rgba(0, 0, 0, 0.8);
}

.tile-icon-link svg {
	width: 18px;
	height: 18px;
	stroke: white;
	stroke-width: 2;
	fill: none;
}

.tile-badge {
	position: absolute;
	left: 10px;
	top: 10px;
	z-index: 2;
	padding: 6px 10px;
	border-radius: 8px;
	background: rgba(0, 0, 0, 0.65);
	color: white;
	font-size: 12px;
	font-weight: 700;
}

.tile-media {
	position: relative;
	width: 100%;
	aspect-ratio: 1 / 1;
	overflow: hidden;
	border-radius: 10px 10px 0 0;
	background:
		linear-gradient(110deg, transparent 0%, rgba(128, 128, 128, 0.10) 44%, transparent 58%),
		var(--color-background-dark, rgba(0, 0, 0, 0.04));
	background-size: 220% 100%;
	animation: recentphotos-placeholder-sheen 1.4s ease-in-out infinite;
}

.tile-image {
	position: absolute;
	inset: 0;
	display: block;
	width: 100%;
	height: 100%;
	object-fit: cover;
	opacity: 0;
	transition: opacity 0.18s ease;
}

.tile-image.is-loaded {
	opacity: 1;
}

.tile-media--loaded {
	animation: none;
}

.grid--fit .tile-media {
	aspect-ratio: var(--tile-ratio, 4 / 3);
}

.grid--fit .tile-media--wide {
	aspect-ratio: var(--tile-ratio, 16 / 9);
}

.grid--fit .tile-media--landscape {
	aspect-ratio: var(--tile-ratio, 4 / 3);
}

.grid--fit .tile-media--portrait {
	aspect-ratio: var(--tile-ratio, 4 / 5);
}

.grid--fit .tile-media--tall {
	aspect-ratio: var(--tile-ratio, 3 / 4);
}

.grid--fit .tile-image {
	object-fit: contain;
}

.tile-info-popover {
	position: fixed;
	z-index: 10000;
	display: grid;
	grid-template-columns: 1fr;
	gap: 3px;
	width: min(380px, calc(100vw - 32px));
	overflow: auto;
	padding: 12px;
	border: 1px solid rgba(255, 255, 255, 0.14);
	border-radius: 8px;
	background: rgba(10, 10, 10, 0.84);
	color: white;
	box-shadow: 0 12px 28px rgba(0, 0, 0, 0.3);
	backdrop-filter: blur(6px);
	line-height: 1.35;
	word-break: break-word;
	pointer-events: none;
	transform: translateY(0);
	transition: transform 0.12s ease;
}

.tile-info-popover.above {
	transform: translateY(-100%);
}

.tile-info-popover>div {
	display: grid;
	grid-template-columns: max-content minmax(0, 1fr);
	column-gap: 7px;
	align-items: baseline;
	min-width: 0;
	font-size: 12px;
}

.tile-info-popover strong {
	white-space: nowrap;
	color: rgba(255, 255, 255, 0.86);
}

.tile-info-tags {
	align-items: flex-start;
}

.tag-list {
	display: inline-flex;
	flex-wrap: wrap;
	gap: 5px;
	min-width: 0;
}

.folder-tag {
	display: inline-flex;
	align-items: center;
	max-width: 100%;
	padding: 2px 7px;
	border: 1px solid rgba(255, 255, 255, 0.18);
	border-radius: 999px;
	background: rgba(255, 255, 255, 0.08);
	color: rgba(255, 255, 255, 0.92);
	font-size: 11px;
	font-weight: 500;
	line-height: 1.4;
	overflow-wrap: anywhere;
}

.meta {
	padding: 8px 10px 10px;
}

.name {
	font-size: 13px;
	font-weight: 600;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.sub {
	font-size: 12px;
	opacity: 0.7;
	margin-top: 4px;
}

.tile-tags {
	display: flex;
	flex-wrap: wrap;
	gap: 5px;
	max-height: 43px;
	margin-top: 8px;
	overflow: hidden;
}

.tile-tag {
	display: inline-flex;
	align-items: center;
	max-width: 100%;
	padding: 2px 7px;
	border: 1px solid var(--color-border, rgba(128, 128, 128, 0.28));
	border-radius: 999px;
	background: color-mix(in srgb, var(--color-main-background) 84%, currentColor 16%);
	color: var(--color-main-text);
	font-size: 11px;
	font-weight: 500;
	line-height: 1.35;
	text-decoration: none;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.tile-tag:hover,
.tile-tag:focus-visible {
	border-color: var(--color-primary-element, currentColor);
	color: var(--color-primary-element, currentColor);
	text-decoration: none;
}

.tile-tag-more {
	color: var(--color-text-maxcontrast, currentColor);
}

.loading-state,
.empty-state,
.loading-more {
	padding: 20px 0;
}

.loading-more {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 10px;
	color: var(--color-text-maxcontrast, currentColor);
	font-size: 13px;
	font-weight: 500;
}

.loading-spinner {
	width: 16px;
	height: 16px;
	border: 2px solid var(--color-border, rgba(128, 128, 128, 0.35));
	border-top-color: var(--color-primary-element, currentColor);
	border-radius: 999px;
	animation: recentphotos-loading-spin 0.8s linear infinite;
}

@keyframes recentphotos-loading-spin {
	from {
		transform: rotate(0deg);
	}

	to {
		transform: rotate(360deg);
	}
}

@keyframes recentphotos-placeholder-sheen {
	0% {
		background-position: 120% 0;
	}

	100% {
		background-position: -120% 0;
	}
}

@media (min-width: 1200px) {
	.grid--fit {
		column-width: 300px;
	}
}

@media (max-width: 700px) {
	.grid--fit {
		column-width: 220px;
	}
}
</style>
