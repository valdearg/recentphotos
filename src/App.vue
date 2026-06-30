<template>
	<div class="recent-photos-app">
		<div class="top-bar">
			<div class="top-left">
				<h1>Recent Photos</h1>
			</div>

			<div class="top-center">
				<SortControls :sort-by="settings.sortBy" :sort-dir="settings.sortDir"
					:media-filter="settings.mediaFilter" :display-mode="settings.displayMode"
					:thumbnail-mode="settings.thumbnailMode" :page-size="settings.pageSize"
					:max-page-size="settings.maxPageSize" @change="onControlsChange" />
			</div>

			<div class="top-right">
				<button type="button" class="icon-button" :class="{ 'is-active': selectMode }"
					:title="selectMode ? 'Exit select mode' : 'Select images'"
					:aria-label="selectMode ? 'Exit select mode' : 'Select images'"
					:aria-pressed="selectMode ? 'true' : 'false'" :disabled="deletingSelected" @click="toggleSelectMode">
					<svg viewBox="0 0 24 24">
						<path d="M9 11l3 3L22 4" />
						<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
					</svg>
				</button>

				<button type="button" class="icon-button" :class="{ 'is-active': showGridTags }"
					:title="showGridTags ? 'Hide grid tags' : 'Show grid tags'"
					:aria-label="showGridTags ? 'Hide grid tags' : 'Show grid tags'"
					:aria-pressed="showGridTags ? 'true' : 'false'" @click="toggleGridTags">
					<svg viewBox="0 0 24 24">
						<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
						<path d="M7 7h.01" />
					</svg>
				</button>

				<button type="button" class="icon-button" :class="{ 'is-active': showGridInfo }"
					:title="showGridInfo ? 'Hide info popups' : 'Show info popups'"
					:aria-label="showGridInfo ? 'Hide info popups' : 'Show info popups'"
					:aria-pressed="showGridInfo ? 'true' : 'false'" @click="toggleGridInfo">
					<svg viewBox="0 0 24 24">
						<circle cx="12" cy="12" r="10" />
						<path d="M12 16v-4" />
						<circle cx="12" cy="8" r="0.75" />
					</svg>
				</button>

				<button type="button" class="icon-button" :class="{ 'is-active': settings.hideThumbnailInfo }"
					:title="settings.hideThumbnailInfo ? 'Show thumbnail info' : 'Hide thumbnail info'"
					:aria-label="settings.hideThumbnailInfo ? 'Show thumbnail info' : 'Hide thumbnail info'"
					:aria-pressed="settings.hideThumbnailInfo ? 'true' : 'false'" @click="toggleThumbnailInfo">
					<svg viewBox="0 0 24 24">
						<rect x="4" y="5" width="16" height="14" rx="2" />
						<path d="M7 15h10" />
						<path d="M7 18h6" />
					</svg>
				</button>

				<button type="button" class="icon-button" :class="{ 'is-loading': loading }" title="Refresh results"
					aria-label="Refresh results" @click="refreshResults" :disabled="loading">
					<svg viewBox="0 0 24 24">
						<path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6" />
					</svg>
				</button>

				<IndexStatusPanel :status="indexStatus" :busy="rebuildingIndex" @rebuild="rebuildIndex" compact />
			</div>
		</div>

		<div v-if="selectMode" class="selection-toolbar" aria-live="polite">
			<span class="selection-count">{{ selectedCount }} selected</span>
			<div class="selection-actions">
				<button type="button" class="selection-button selection-button--danger"
					:title="selectedCount ? 'Move selected items to deleted files' : 'Select images to delete'"
					aria-label="Move selected items to deleted files" :disabled="selectedCount === 0 || deletingSelected"
					@click="deleteSelectedImages">
					<svg viewBox="0 0 24 24" :class="{ spinning: deletingSelected }">
						<path d="M3 6h18" />
						<path d="M8 6V4h8v2" />
						<path d="M6 6l1 15h10l1-15" />
						<path d="M10 11v6" />
						<path d="M14 11v6" />
					</svg>
					<span>Delete</span>
				</button>
				<button type="button" class="selection-button" title="Clear selection" aria-label="Clear selection"
					:disabled="deletingSelected" @click="clearSelection">
					<svg viewBox="0 0 24 24">
						<path d="M18 6 6 18" />
						<path d="m6 6 12 12" />
					</svg>
					<span>Clear</span>
				</button>
			</div>
		</div>

		<ImageGrid :images="images" :loading="loading" :show-tags="showGridTags" :show-info="showGridInfo"
			:thumbnail-mode="settings.thumbnailMode" :hide-thumbnail-info="settings.hideThumbnailInfo"
			:select-mode="selectMode" :selected-ids="selectedImageIds" @open="openViewer"
			@toggle-select="toggleImageSelection" />

		<PaginationControls v-if="settings.displayMode === 'pagination'" :page="page" :pages="pages"
			@change="goToPage" />

		<InfiniteScrollSentinel v-else :disabled="loading || page >= pages" @intersect="loadNextPage" />

		<ImageViewer v-if="viewerOpen" :images="images" :start-index="viewerIndex" @close="closeViewer"
			@load-more="loadNextPage" />

		<div class="floating-actions">
			<button type="button" class="floating-button" title="Refresh results" aria-label="Refresh results"
				@click="refreshAndTop" :disabled="loading">
				<svg viewBox="0 0 24 24" :class="{ spinning: loading }">
					<path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6" />
				</svg>
			</button>

			<button type="button" class="floating-button" title="Back to top" aria-label="Back to top"
				@click="scrollToTop">
				<svg viewBox="0 0 24 24">
					<path d="M12 19V5M5 12l7-7 7 7" />
				</svg>
			</button>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import ImageGrid from './components/ImageGrid.vue'
import ImageViewer from './components/ImageViewer.vue'
import IndexStatusPanel from './components/IndexStatusPanel.vue'
import InfiniteScrollSentinel from './components/InfiniteScrollSentinel.vue'
import PaginationControls from './components/PaginationControls.vue'
import SortControls from './components/SortControls.vue'

export default {
	name: 'App',
	components: {
		ImageGrid,
		SortControls,
		PaginationControls,
		InfiniteScrollSentinel,
		IndexStatusPanel,
		ImageViewer,
	},
	props: {
		initialSettings: { type: Object, required: true },
		initialIndexStatus: { type: Object, required: true },
	},
	data() {
		return {
			settings: {
				mediaFilter: 'all',
				thumbnailMode: 'square',
				hideThumbnailInfo: false,
				...this.initialSettings,
			},
			indexStatus: { ...this.initialIndexStatus },
			images: [],
			page: 1,
			pages: 1,
			total: 0,
			loading: false,
			loadingNextPage: null,
			showGridInfo: true,
			showGridTags: false,
			selectMode: false,
			selectedImageIds: {},
			deletingSelected: false,
			rebuildingIndex: false,
			viewerOpen: false,
			viewerIndex: 0,
		}
	},
	computed: {
		selectedIds() {
			return Object.keys(this.selectedImageIds).map(id => Number(id)).filter(id => id > 0)
		},

		selectedCount() {
			return this.selectedIds.length
		},

		selectedImages() {
			const selected = new Set(this.selectedIds)
			return this.images.filter(image => selected.has(Number(image.id)))
		},
	},
	mounted() {
		this.loadPage(1, false)
	},
	methods: {
		async loadPage(page, append = false) {
			this.loading = true
			try {
				const response = await axios.get(generateUrl('/apps/recentphotos/api/images'), {
					params: {
						page,
						limit: this.settings.pageSize,
						sortBy: this.settings.sortBy,
						sortDir: this.settings.sortDir,
						mediaFilter: this.settings.mediaFilter,
					},
				})

				const data = response.data
				this.page = data.page
				this.pages = data.pages
				this.total = data.total
				this.images = append ? [...this.images, ...data.items] : data.items
				this.pruneSelection()
			} finally {
				this.loading = false
			}
		},

		scrollToTop() {
			this.$nextTick(() => {
				const selectors = ['#app-content', '#app-content-wrapper', '#recentphotos-root']

				for (const selector of selectors) {
					const el = document.querySelector(selector)
					if (el && typeof el.scrollTo === 'function') {
						el.scrollTo({ top: 0, behavior: 'auto' })
					}
					if (el) {
						el.scrollTop = 0
					}
				}

				window.scrollTo(0, 0)
				document.documentElement.scrollTop = 0
				document.body.scrollTop = 0
			})
		},

		async refreshResults() {
			if (this.loading) return

			await this.loadIndexStatus()

			if (this.settings.displayMode === 'infinite') {
				await this.loadPage(1, false)
				this.scrollToTop()
			} else {
				await this.loadPage(this.page, false)
			}
		},

		async refreshAndTop() {
			if (this.loading) return

			await this.loadIndexStatus()
			await this.loadPage(1, false)
			this.scrollToTop()
		},

		async goToPage(page) {
			if (page < 1 || page > this.pages || this.loading) return
			this.scrollToTop()
			await this.loadPage(page, false)
			this.scrollToTop()
		},

		async loadNextPage() {
			if (this.loading || this.page >= this.pages) return

			const nextPage = this.page + 1
			if (this.loadingNextPage === nextPage) return

			this.loadingNextPage = nextPage
			try {
				await this.loadPage(nextPage, true)
			} finally {
				this.loadingNextPage = null
			}
		},

		async onControlsChange(changes) {
			const layoutOnlySettings = ['thumbnailMode', 'hideThumbnailInfo']
			const shouldReload = Object.keys(changes).some(key => !layoutOnlySettings.includes(key))
			this.settings = { ...this.settings, ...changes }
			await axios.post(generateUrl('/apps/recentphotos/api/settings/personal'), this.settings)
			if (shouldReload) {
				await this.loadPage(1, false)
				this.scrollToTop()
			}
		},

		async rebuildIndex() {
			this.rebuildingIndex = true
			try {
				await axios.post(generateUrl('/apps/recentphotos/api/index/rebuild'))
				await this.loadIndexStatus()
			} finally {
				this.rebuildingIndex = false
			}
		},

		openViewer({ index }) {
			if (this.selectMode) return

			this.viewerIndex = index
			this.viewerOpen = true
		},

		closeViewer() {
			this.viewerOpen = false
		},

		async loadIndexStatus() {
			try {
				const response = await axios.get(generateUrl('/apps/recentphotos/api/index/status'))
				this.indexStatus = response.data
			} catch (e) {
				// Keep existing status if refresh fails
			}
		},

		toggleGridTags() {
			this.showGridTags = !this.showGridTags
		},

		toggleGridInfo() {
			this.showGridInfo = !this.showGridInfo
		},

		async toggleThumbnailInfo() {
			await this.onControlsChange({ hideThumbnailInfo: !this.settings.hideThumbnailInfo })
		},

		toggleSelectMode() {
			if (this.deletingSelected) return

			this.selectMode = !this.selectMode
			if (!this.selectMode) {
				this.clearSelection()
			}
		},

		toggleImageSelection(image) {
			if (!image?.id || this.deletingSelected) return

			const id = String(image.id)
			if (this.selectedImageIds[id]) {
				this.$delete(this.selectedImageIds, id)
			} else {
				this.$set(this.selectedImageIds, id, true)
			}
		},

		clearSelection() {
			this.selectedImageIds = {}
		},

		pruneSelection() {
			if (!this.selectMode || this.images.length === 0) return

			const visibleIds = new Set(this.images.map(image => String(image.id)))
			for (const id of Object.keys(this.selectedImageIds)) {
				if (!visibleIds.has(id)) {
					this.$delete(this.selectedImageIds, id)
				}
			}
		},

		async deleteSelectedImages() {
			const images = this.selectedImages
			if (images.length === 0 || this.deletingSelected) return

			const label = images.length === 1 ? 'this item' : `${images.length} items`
			if (!window.confirm(`Move ${label} to deleted files?`)) {
				return
			}

			this.deletingSelected = true
			try {
				const results = await Promise.all(images.map(async image => {
					try {
						await axios.delete(this.davUrl(image))
						return { image, ok: true }
					} catch (error) {
						return { image, ok: false, error }
					}
				}))

				const failed = results.filter(result => !result.ok)
				this.clearSelection()
				this.selectMode = false
				await this.refreshResults()

				if (failed.length > 0) {
					const firstError = this.deleteErrorMessage(failed[0].error)
					window.alert(`${failed.length} item${failed.length === 1 ? '' : 's'} could not be deleted: ${firstError}`)
				}
			} catch (e) {
				window.alert(`Could not delete the selected items: ${this.deleteErrorMessage(e)}`)
			} finally {
				this.deletingSelected = false
			}
		},

		davUrl(image) {
			const match = String(image?.path || '').match(/^\/([^/]+)\/files\/(.+)$/)
			if (!match) {
				throw new Error('Could not resolve the file path.')
			}

			const uid = encodeURIComponent(match[1])
			const relativePath = match[2].split('/').map(part => encodeURIComponent(part)).join('/')
			return generateRemoteUrl(`dav/files/${uid}/${relativePath}`)
		},

		deleteErrorMessage(error) {
			return error?.response?.data?.message
				|| error?.response?.data?.error
				|| error?.message
				|| 'Unknown error'
		},
	},
}
</script>

<style scoped>
.recent-photos-app {
	width: 100%;
	min-height: 100%;
	padding: 10px 14px;
	box-sizing: border-box;
	overflow: visible;
}

.top-bar {
	display: grid;
	grid-template-columns: auto 1fr auto;
	align-items: center;
	gap: 12px;
	margin-bottom: 10px;
}

.top-left h1 {
	font-size: 16px;
	font-weight: 600;
	margin: 0;
	white-space: nowrap;
}

.top-center {
	min-width: 0;
	display: flex;
	align-items: center;
}

.top-right {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
	justify-content: flex-end;
}

.icon-button {
	appearance: none;
	-webkit-appearance: none;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	padding: 0;
	margin: 0;
	border: 1px solid var(--color-border, rgba(255, 255, 255, 0.25));
	background: var(--color-main-background, rgba(255, 255, 255, 0.08));
	color: var(--color-main-text, currentColor);
	border-radius: 8px;
	cursor: pointer;
	box-sizing: border-box;
}

.icon-button:hover:not(:disabled) {
	filter: brightness(0.96);
}

.top-right button.icon-button:focus,
.top-right button.icon-button:active {
	outline: none !important;
	border-color: var(--color-border, rgba(255, 255, 255, 0.25)) !important;
	box-shadow: none !important;
}

.top-right button.icon-button:focus-visible {
	outline: 2px solid var(--color-primary-element, currentColor) !important;
	outline-offset: 2px !important;
}

.icon-button.is-active {
	border-color: var(--color-primary-element, currentColor);
	color: var(--color-primary-element-text, white);
	background: var(--color-primary-element, currentColor);
	box-shadow: inset 0 0 0 1px color-mix(in srgb, white 28%, transparent);
}

.top-right button.icon-button.is-active:focus,
.top-right button.icon-button.is-active:active {
	border-color: var(--color-primary-element, currentColor) !important;
	box-shadow: inset 0 0 0 1px color-mix(in srgb, white 28%, transparent) !important;
}

.icon-button.is-active:hover:not(:disabled) {
	filter: brightness(1.05);
}

.icon-button:disabled {
	opacity: 0.7;
	cursor: default;
}

.icon-button svg {
	width: 18px;
	height: 18px;
	fill: none;
}

.icon-button svg path,
.icon-button svg circle {
	stroke: currentColor;
	stroke-width: 2;
}

.icon-button svg circle[r="0.75"] {
	fill: currentColor;
	stroke: none;
}

.selection-toolbar {
	position: sticky;
	top: 10px;
	z-index: 900;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	min-height: 48px;
	margin: 0 0 12px;
	padding: 7px 8px 7px 14px;
	border: 1px solid var(--color-border, rgba(255, 255, 255, 0.25));
	border-radius: 8px;
	background: color-mix(in srgb, var(--color-main-background, #fff) 92%, var(--color-primary-element, #0082c9) 8%);
	box-shadow: 0 8px 22px rgba(0, 0, 0, 0.18);
	box-sizing: border-box;
}

.selection-count {
	font-size: 14px;
	font-weight: 700;
	white-space: nowrap;
}

.selection-actions {
	display: inline-flex;
	align-items: center;
	gap: 8px;
}

.selection-button {
	appearance: none;
	-webkit-appearance: none;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 5px;
	height: 34px;
	padding: 0 12px;
	border: 1px solid var(--color-border, rgba(255, 255, 255, 0.25));
	border-radius: 8px;
	background: var(--color-main-background, rgba(255, 255, 255, 0.08));
	color: var(--color-main-text, currentColor);
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	box-sizing: border-box;
}

.selection-button:hover:not(:disabled) {
	filter: brightness(0.96);
}

.selection-button:disabled {
	opacity: 0.6;
	cursor: default;
}

.selection-button--danger:not(:disabled) {
	border-color: var(--color-error, #e9322d);
	background: var(--color-error, #e9322d);
	color: white;
	box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
}

.selection-button--danger:hover:not(:disabled) {
	filter: brightness(1.08);
}

.selection-button svg {
	width: 16px;
	height: 16px;
	fill: none;
	stroke: currentColor;
	stroke-width: 2;
}

.icon-button.is-loading svg,
.spinning {
	animation: recentphotos-spin 0.9s linear infinite;
}

.floating-actions {
	position: fixed;
	right: 18px;
	bottom: 18px;
	z-index: 1000;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.floating-button {
	width: 38px;
	height: 38px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	border: 1px solid var(--color-border, rgba(255, 255, 255, 0.25));
	border-radius: 999px;
	background: var(--color-main-background, rgba(0, 0, 0, 0.55));
	color: var(--color-main-text, currentColor);
	opacity: 0.28;
	cursor: pointer;
	box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18);
	transition: opacity 0.15s ease, transform 0.15s ease, background 0.15s ease;
}

.floating-button:hover {
	opacity: 0.95;
	transform: translateY(-2px);
}

.floating-button:disabled {
	cursor: default;
	opacity: 0.45;
}

.floating-button svg {
	width: 18px;
	height: 18px;
	stroke: currentColor;
	stroke-width: 2;
	fill: none;
}

@keyframes recentphotos-spin {
	from {
		transform: rotate(0deg);
	}

	to {
		transform: rotate(360deg);
	}
}

@media (max-width: 900px) {
	.top-bar {
		grid-template-columns: 1fr;
		align-items: stretch;
	}

	.top-right {
		justify-content: flex-start;
	}

	.selection-toolbar {
		top: 8px;
		align-items: stretch;
		flex-direction: column;
		gap: 8px;
	}

	.selection-actions {
		width: 100%;
	}

	.selection-button {
		flex: 1;
	}
}
</style>
