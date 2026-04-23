<template>
	<div>
		<div v-if="loading && images.length === 0" class="loading-state">
			Loading images…
		</div>

		<div v-else-if="images.length === 0" class="empty-state">
			No media found in the current index.
		</div>

		<div v-else class="grid">
			<div v-for="(image, index) in images" :key="image.id" class="tile" :title="image.name"
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
				</div>
			</div>
		</div>

		<div v-if="loading && images.length > 0" class="loading-more">
			Loading more…
		</div>
	</div>
</template>

<script>
export default {
	name: 'ImageGrid',
	props: {
		images: { type: Array, required: true },
		loading: { type: Boolean, default: false },
	},
	methods: {
		formatDate(ts) {
			return ts ? new Date(ts * 1000).toLocaleString() : 'Unknown'
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

.loading-state,
.empty-state,
.loading-more {
	padding: 20px 0;
}
</style>