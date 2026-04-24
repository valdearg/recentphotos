<template>
	<div v-if="mounted" class="viewer-overlay" @click="onOverlayClick" @mousemove="onActivity"
		@touchstart.passive="onActivity">
		<div ref="toolbar" class="viewer-toolbar" :class="{ 'viewer-ui-hidden': uiHidden }">
			<div class="viewer-title">{{ currentImage?.name || '' }}</div>

			<div class="viewer-actions">
				<button @click="zoomOut" title="Zoom out" :disabled="isVideo">
					<svg viewBox="0 0 24 24">
						<path d="M5 12h14" />
					</svg>
				</button>
				<button @click="zoomIn" title="Zoom in" :disabled="isVideo">
					<svg viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" />
					</svg>
				</button>
				<button @click="resetView" title="Reset" :disabled="isVideo">
					<svg viewBox="0 0 24 24">
						<path d="M12 5v4l3-3-3-3v4a7 7 0 1 0 7 7" />
					</svg>
				</button>
				<button @click="toggleInfo" title="Info">
					<svg viewBox="0 0 24 24">
						<circle cx="12" cy="12" r="10" />
						<path d="M12 16v-4M12 8h.01" />
					</svg>
				</button>
				<button @click="toggleFullscreen" title="Fullscreen">
					<svg viewBox="0 0 24 24">
						<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" />
					</svg>
				</button>
				<button @click="toggleSlideshow" title="Slideshow">
					<svg v-if="!slideshowRunning" viewBox="0 0 24 24">
						<path d="M8 5v14l11-7z" />
					</svg>
					<svg v-else viewBox="0 0 24 24">
						<path d="M6 5h4v14H6zm8 0h4v14h-4z" />
					</svg>
				</button>
				<button @click="openInNewTab" title="Open in new tab">
					<svg viewBox="0 0 24 24">
						<path d="M14 3h7v7M21 3l-9 9M5 5h6v2H7v10h10v-4h2v6H5z" />
					</svg>
				</button>
				<button @click="$emit('close')" title="Close">
					<svg viewBox="0 0 24 24">
						<path d="M6 6l12 12M6 18L18 6" />
					</svg>
				</button>
			</div>
		</div>

		<div ref="body" class="viewer-body" :class="{ 'viewer-ui-hidden': uiHidden }">
			<div class="viewer-side viewer-side-left" @click.stop="previousImage">
				<button type="button" class="nav-button" :disabled="!hasPrevious" title="Previous"
					@click.stop="previousImage">
					<svg viewBox="0 0 24 24">
						<path d="M15 18l-6-6 6-6" />
					</svg>
				</button>
			</div>

			<div ref="imageWrap" class="viewer-image-wrap" @wheel.prevent="onWheel" @mousedown="startDrag"
				@mousemove="onDrag" @mouseup="stopDrag" @mouseleave="stopDrag" @dblclick.stop="toggleDoubleClickZoom"
				@touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd">

				<video v-if="isVideo && currentImage" ref="videoEl" class="viewer-video" :src="currentImage.fullUrl"
					controls playsinline @click.stop @loadedmetadata="updateMediaResolution"></video>

				<img v-else-if="currentImage" ref="imageEl" class="viewer-image" :src="currentImage.fullUrl"
					:alt="currentImage.name" :style="imageStyle" draggable="false" @click.stop
					@load="updateMediaResolution">
			</div>

			<div class="viewer-side viewer-side-right" @click.stop="nextImage">
				<button type="button" class="nav-button" :disabled="!hasNext" title="Next" @click.stop="nextImage">
					<svg viewBox="0 0 24 24">
						<path d="M9 6l6 6-6 6" />
					</svg>
				</button>
			</div>
		</div>

		<div v-if="showInfo && currentImage" class="viewer-info" :class="{ 'viewer-ui-hidden': uiHidden }">
			<div><strong>Name:</strong> {{ currentImage.name }}</div>
			<div><strong>Type:</strong> {{ currentImage.mediaType }}</div>
			<div><strong>Date taken:</strong> {{ formatDate(currentImage.dateTaken) }}</div>
			<div><strong>Created:</strong> {{ formatDate(currentImage.created) }}</div>
			<div><strong>Modified:</strong> {{ formatDate(currentImage.modified) }}</div>
			<div><strong>Size:</strong> {{ formatBytes(currentImage.size) }}</div>
			<div><strong>MIME:</strong> {{ currentImage.mime }}</div>
			<div><strong>Path:</strong> {{ currentImage.path }}</div>
			<div><strong>Resolution:</strong> {{ mediaWidth && mediaHeight ? `${mediaWidth} × ${mediaHeight}` :
				'Unknown' }}</div>
			<div v-if="!isVideo"><strong>Zoom:</strong> {{ Math.round(scale * 100) }}%</div>
		</div>

		<div class="viewer-footer" :class="{ 'viewer-ui-hidden': uiHidden }">
			{{ currentIndex + 1 }} / {{ images.length }}
		</div>
	</div>
</template>

<script>
export default {
	name: 'ImageViewer',
	props: {
		images: { type: Array, required: true },
		startIndex: { type: Number, default: 0 },
	},
	data() {
		return {
			currentIndex: this.startIndex,
			mounted: false,
			scale: 1,
			x: 0,
			y: 0,
			dragging: false,
			startX: 0,
			startY: 0,
			showInfo: false,
			isFullscreen: false,
			slideshowRunning: false,
			timer: null,
			uiHidden: false,
			uiHideTimer: null,
			touchStartX: 0,
			touchStartY: 0,
			mediaWidth: null,
			mediaHeight: null,
		}
	},
	computed: {
		currentImage() { return this.images[this.currentIndex] || null },
		hasNext() { return this.currentIndex < this.images.length - 1 },
		hasPrevious() { return this.currentIndex > 0 },
		isVideo() { return this.currentImage?.mediaType === 'video' },
		imageStyle() {
			return {
				transform: `translate(${this.x}px, ${this.y}px) scale(${this.scale})`,
				cursor: this.scale > 1 ? (this.dragging ? 'grabbing' : 'grab') : 'default',
			}
		},
	},
	watch: {
		currentIndex(i) {
			this.resetView();
			this.mediaWidth = null
			this.mediaHeight = null
			const next = this.images[i + 1];
			if (next?.fullUrl) { const img = new Image(); img.src = next.fullUrl; }
			const prev = this.images[i - 1];
			if (prev?.fullUrl) { const img = new Image(); img.src = prev.fullUrl; }
			if (i >= this.images.length - 3) this.$emit('load-more');
			this.onActivity();
		},
		slideshowRunning(value) {
			if (value) this.startUiHideTimer();
			else this.showUi();
		},
	},
	mounted() {
		document.body.appendChild(this.$el);
		this.mounted = true;
		window.addEventListener('keydown', this.onKey);
		document.addEventListener('fullscreenchange', this.onFullscreenChange);
		this.onActivity();
	},
	beforeDestroy() {
		window.removeEventListener('keydown', this.onKey);
		document.removeEventListener('fullscreenchange', this.onFullscreenChange);
		this.stopSlideshow();
		this.clearUiHideTimer();
		if (this.$el && this.$el.parentNode) this.$el.parentNode.removeChild(this.$el);
	},
	methods: {
		onOverlayClick(e) {
			const t = e.target;
			if (this.$refs.toolbar?.contains(t)) return;
			if (t.closest('.nav-button')) return;
			if (t.closest('.viewer-info')) return;
			if (this.$refs.imageEl?.contains(t)) return;
			if (this.$refs.videoEl?.contains(t)) return;
			if (t.closest('.viewer-side')) return;
			this.$emit('close');
		},
		onKey(e) {
			this.onActivity();
			if (e.key === 'Escape') this.$emit('close');
			else if (e.key === 'ArrowRight') this.nextImage();
			else if (e.key === 'ArrowLeft') this.previousImage();
			else if (e.key === ' ') { e.preventDefault(); this.toggleSlideshow(); }
			else if (e.key.toLowerCase() === 'f') this.toggleFullscreen();
			else if (e.key.toLowerCase() === 'i') this.toggleInfo();
			else if (!this.isVideo && e.key === '+') this.zoomIn();
			else if (!this.isVideo && e.key === '-') this.zoomOut();
			else if (!this.isVideo && e.key === '0') this.resetView();
		},
		onFullscreenChange() { this.isFullscreen = !!document.fullscreenElement; },
		async toggleFullscreen() {
			try {
				if (!document.fullscreenElement) await this.$el.requestFullscreen();
				else await document.exitFullscreen();
			} catch (e) { }
		},
		openInNewTab() {
			if (!this.currentImage) return;
			const url = this.currentImage.openUrl || this.currentImage.downloadUrl;
			if (url) window.open(url, '_blank', 'noopener');
		},
		toggleInfo() { this.showInfo = !this.showInfo; this.onActivity(); },
		zoomIn() { if (this.isVideo) return; this.scale = Math.min(this.scale + 0.2, 5); this.onActivity(); },
		zoomOut() { if (this.isVideo) return; this.scale = Math.max(this.scale - 0.2, 1); if (this.scale === 1) { this.x = 0; this.y = 0; } this.onActivity(); },
		toggleDoubleClickZoom() { if (this.isVideo) return; if (this.scale === 1) this.scale = 2; else this.resetView(); this.onActivity(); },
		resetView() { this.scale = 1; this.x = 0; this.y = 0; this.dragging = false; this.onActivity(); },
		onWheel(e) { if (this.isVideo) return; e.deltaY < 0 ? this.zoomIn() : this.zoomOut(); },
		startDrag(e) { if (this.isVideo || this.scale <= 1) return; this.dragging = true; this.startX = e.clientX - this.x; this.startY = e.clientY - this.y; this.onActivity(); },
		onDrag(e) { if (!this.dragging) return; this.x = e.clientX - this.startX; this.y = e.clientY - this.startY; },
		stopDrag() { this.dragging = false; },
		onTouchStart(e) {
			if (!e.touches || e.touches.length !== 1) return;
			this.touchStartX = e.touches[0].clientX;
			this.touchStartY = e.touches[0].clientY;
			this.onActivity();
		},
		onTouchEnd(e) {
			if (!e.changedTouches || e.changedTouches.length !== 1) return;
			const endX = e.changedTouches[0].clientX;
			const endY = e.changedTouches[0].clientY;
			const dx = endX - this.touchStartX;
			const dy = endY - this.touchStartY;
			if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
				if (dx < 0) this.nextImage();
				else this.previousImage();
			}
		},
		nextImage() { if (this.hasNext) this.currentIndex++; else this.stopSlideshow(); },
		previousImage() { if (this.hasPrevious) this.currentIndex--; },
		toggleSlideshow() { this.slideshowRunning ? this.stopSlideshow() : this.startSlideshow(); this.onActivity(); },
		startSlideshow() {
			if (this.slideshowRunning) return;
			this.slideshowRunning = true;
			this.timer = setInterval(() => { this.hasNext ? this.currentIndex++ : this.stopSlideshow(); }, 3000);
		},
		stopSlideshow() {
			this.slideshowRunning = false;
			if (this.timer) { clearInterval(this.timer); this.timer = null; }
		},
		onActivity() { this.showUi(); if (this.slideshowRunning) this.startUiHideTimer(); },
		showUi() { this.uiHidden = false; },
		startUiHideTimer() {
			this.clearUiHideTimer();
			this.uiHideTimer = setTimeout(() => { if (!this.dragging) this.uiHidden = true; }, 2000);
		},
		clearUiHideTimer() { if (this.uiHideTimer) { clearTimeout(this.uiHideTimer); this.uiHideTimer = null; } },
		formatDate(ts) { return ts ? new Date(ts * 1000).toLocaleString() : ''; },
		formatBytes(b) {
			if (!b && b !== 0) return '';
			const u = ['B', 'KB', 'MB', 'GB', 'TB'];
			let i = 0;
			let value = b;
			while (value >= 1024 && i < u.length - 1) { value /= 1024; i++; }
			return `${value.toFixed(value >= 10 || i === 0 ? 0 : 1)} ${u[i]}`;
		},
		updateMediaResolution() {
			if (this.isVideo && this.$refs.videoEl) {
				this.mediaWidth = this.$refs.videoEl.videoWidth || null
				this.mediaHeight = this.$refs.videoEl.videoHeight || null
				return
			}

			if (this.$refs.imageEl) {
				this.mediaWidth = this.$refs.imageEl.naturalWidth || null
				this.mediaHeight = this.$refs.imageEl.naturalHeight || null
			}
		},
	},
}
</script>

<style scoped>
.viewer-overlay {
	position: fixed;
	inset: 0;
	z-index: 100000;
	background: rgba(0, 0, 0, 0.95);
	display: flex;
	flex-direction: column;
	cursor: default;
}

.viewer-toolbar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 16px;
	padding: 12px 16px;
	color: white;
	background: rgba(0, 0, 0, 0.4);
	transition: opacity 0.2s ease;
}

.viewer-title {
	font-size: 16px;
	font-weight: 600;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.viewer-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	justify-content: flex-end;
	align-items: center;
}

.viewer-actions button {
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
	background: rgba(255, 255, 255, 0.08);
	color: white;
	border-radius: 8px;
	cursor: pointer;
	font: inherit;
	line-height: 1;
	box-sizing: border-box;
}

.viewer-actions button:hover {
	background: rgba(255, 255, 255, 0.18);
}

.viewer-actions button:disabled {
	opacity: 0.35;
	cursor: default;
}

.viewer-actions svg {
	width: 18px;
	height: 18px;
	stroke: white;
	stroke-width: 2;
	fill: none;
}

.viewer-body {
	flex: 1;
	display: flex;
	align-items: center;
	padding: 16px;
	gap: 16px;
	min-height: 0;
	transition: opacity 0.2s ease;
}

.viewer-side {
	width: 90px;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 90px;
}

.viewer-side-left {
	justify-content: flex-start;
}

.viewer-side-right {
	justify-content: flex-end;
}

.viewer-image-wrap {
	flex: 1;
	display: flex;
	justify-content: center;
	align-items: center;
	overflow: hidden;
	user-select: none;
	min-width: 0;
	min-height: 0;
	touch-action: pan-y;
}

.viewer-image {
	max-width: 100%;
	max-height: calc(100vh - 180px);
	object-fit: contain;
	will-change: transform;
	transition: transform 0.08s linear;
}

.viewer-video {
	max-width: 100%;
	max-height: calc(100vh - 180px);
	border-radius: 12px;
	background: black;
}

.nav-button {
	width: 60px;
	height: 60px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: rgba(255, 255, 255, 0.1);
	color: white;
	border: none;
	border-radius: 999px;
	cursor: pointer;
	flex: 0 0 auto;
	transition: background 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
}

.nav-button:hover:not(:disabled) {
	background: rgba(255, 255, 255, 0.2);
	box-shadow: 0 0 18px rgba(255, 255, 255, 0.18);
}

.nav-button:disabled {
	opacity: 0.35;
	cursor: default;
}

.nav-button svg {
	width: 28px;
	height: 28px;
	stroke: white;
	stroke-width: 2;
	fill: none;
}

.viewer-info {
	position: absolute;
	top: 76px;
	right: 20px;
	background: rgba(0, 0, 0, 0.7);
	padding: 12px;
	color: white;
	border-radius: 10px;
	max-width: min(420px, calc(100vw - 40px));
	line-height: 1.5;
	word-break: break-word;
	transition: opacity 0.2s ease;
}

.viewer-footer {
	text-align: center;
	color: white;
	padding: 10px;
	transition: opacity 0.2s ease;
}

.viewer-ui-hidden {
	opacity: 0;
	pointer-events: none;
}
</style>
