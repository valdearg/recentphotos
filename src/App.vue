<template>
	<div class="recent-photos-app">
		<div class="top-bar">
			<div class="top-left">
				<h1>Recent Photos</h1>
			</div>

			<div class="top-center">
				<SortControls :sort-by="settings.sortBy" :sort-dir="settings.sortDir"
					:media-filter="settings.mediaFilter" :display-mode="settings.displayMode"
					:page-size="settings.pageSize" :max-page-size="settings.maxPageSize" @change="onControlsChange" />
			</div>

			<div class="top-right">
				<button type="button" class="icon-button" :class="{ 'is-loading': loading }" title="Refresh results"
					aria-label="Refresh results" @click="refreshResults" :disabled="loading">
					<svg viewBox="0 0 24 24">
						<path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6" />
					</svg>
				</button>

				<IndexStatusPanel :status="indexStatus" :busy="rebuildingIndex" @rebuild="rebuildIndex" compact />
			</div>
		</div>

		<ImageGrid :images="images" :loading="loading" @open="openViewer" />

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
import { generateUrl } from '@nextcloud/router'
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
				...this.initialSettings,
			},
			indexStatus: { ...this.initialIndexStatus },
			images: [],
			page: 1,
			pages: 1,
			total: 0,
			loading: false,
			loadingNextPage: null,
			rebuildingIndex: false,
			viewerOpen: false,
			viewerIndex: 0,
		}
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
			this.settings = { ...this.settings, ...changes }
			await axios.post(generateUrl('/apps/recentphotos/api/settings/personal'), this.settings)
			await this.loadPage(1, false)
			this.scrollToTop()
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

.icon-button:disabled {
	opacity: 0.7;
	cursor: default;
}

.icon-button svg {
	width: 18px;
	height: 18px;
	stroke: currentColor;
	stroke-width: 2;
	fill: none;
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
}
</style>
