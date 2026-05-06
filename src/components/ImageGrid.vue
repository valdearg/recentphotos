<template>
	<div>
		<div v-if="loading && images.length === 0" class="loading-state">
			Loading images…
		</div>

		<div v-else-if="images.length === 0" class="empty-state">
			No media found in the current index.
		</div>

		<div v-else class="grid">
			<div v-for="(image, index) in images" :key="image.id" class="tile"
				@mouseenter="showHoverInfo(image, $event)" @mousemove="moveInfo($event)" @mouseleave="hideInfo"
				@focusin="showHoverInfo(image, $event)" @focusout="hideInfo"
				@click="$emit('open', { image, index })">
				<div class="tile-actions" @click.stop>
					<a class="tile-icon-link" :href="image.openUrl || image.downloadUrl" target="_blank" rel="noopener"
						title="Open in new tab" aria-label="Open in new tab">
						<svg viewBox="0 0 24 24">
							<path d="M14 3h7v7M21 3l-9 9M5 5h6v2H7v10h10v-4h2v6H5z" />
						</svg>
					</a>
				</div>

				<div v-if="image.mediaType === 'video'" class="tile-badge">▶</div>
				<div v-else-if="image.mediaType === 'gif'" class="tile-badge">GIF</div>

				<img :src="image.previewUrl" :alt="image.name">

				<div class="meta">
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
	},
	data() {
		return {
			hoverImage: null,
			hoverInfoLeft: 16,
			hoverInfoTop: 16,
			hoverInfoAbove: false,
			hoverInfoMaxHeight: 320,
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
	},
	watch: {
		showInfo(value) {
			if (!value) {
				this.hideInfo()
			}
		},
	},
	methods: {
		showHoverInfo(image, e) {
			if (!this.showInfo) return

			this.hoverImage = image
			this.moveInfo(e)
		},
		moveInfo(e) {
			if (!this.showInfo || !this.hoverImage) return

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

.tile:hover,
.tile:focus-within {
	z-index: 20;
}

.tile-actions {
	position: absolute;
	top: 10px;
	right: 10px;
	z-index: 2;
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

.tile img {
	width: 100%;
	aspect-ratio: 1 / 1;
	object-fit: cover;
	display: block;
	border-radius: 10px 10px 0 0;
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
</style>
