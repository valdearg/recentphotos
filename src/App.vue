<template>
	<div class="recent-photos-app">
		<header class="toolbar">
			<div>
				<h1>Recent Photos</h1>
				<p class="subtitle">A new app with configurable pagination or infinite scroll.</p>
			</div>

			<div class="toolbar-right">
				<button type="button" class="icon-button" :class="{ 'is-loading': loading }" title="Refresh results"
					aria-label="Refresh results" @click="refreshResults" :disabled="loading">
					<svg viewBox="0 0 24 24">
						<path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6" />
					</svg>
				</button>

				<IndexStatusPanel :status="indexStatus" :busy="rebuildingIndex" @rebuild="rebuildIndex" />
			</div>
		</header>

		<SortControls :sort-by="settings.sortBy" :sort-dir="settings.sortDir" :display-mode="settings.displayMode"
			:page-size="settings.pageSize" :max-page-size="settings.maxPageSize" @change="onControlsChange" />

		<ImageGrid :images="images" :loading="loading" @open="openViewer" />

		<PaginationControls v-if="settings.displayMode === 'pagination'" :page="page" :pages="pages"
			@change="goToPage" />

		<InfiniteScrollSentinel v-else :disabled="loading || page >= pages" @intersect="loadNextPage" />

		<ImageViewer v-if="viewerOpen" :images="images" :start-index="viewerIndex" @close="closeViewer"
			@load-more="loadNextPage" />
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
			settings: { ...this.initialSettings },
			indexStatus: { ...this.initialIndexStatus },
			images: [],
			page: 1,
			pages: 1,
			total: 0,
			loading: false,
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

			if (this.settings.displayMode === 'infinite') {
				await this.loadPage(1, false)
			} else {
				await this.loadPage(this.page, false)
			}
		},

		async goToPage(page) {
			if (page < 1 || page > this.pages || this.loading) return
			this.scrollToTop()
			await this.loadPage(page, false)
			this.scrollToTop()
		},

		async loadNextPage() {
			if (this.loading || this.page >= this.pages) return
			await this.loadPage(this.page + 1, true)
		},

		async onControlsChange(changes) {
			this.settings = { ...this.settings, ...changes }
			await axios.post(generateUrl('/apps/recentphotos/api/settings/personal'), this.settings)
			await this.loadPage(1, false)
			if (this.settings.displayMode === 'pagination') {
				this.scrollToTop()
			}
		},

		async rebuildIndex() {
			this.rebuildingIndex = true
			try {
				await axios.post(generateUrl('/apps/recentphotos/api/index/rebuild'))
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
	},
}
</script>

<style scoped>
.recent-photos-app {
	width: 100%;
	min-height: 100%;
	padding: 20px;
	box-sizing: border-box;
	overflow: visible;
}

.toolbar {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 16px;
	flex-wrap: wrap;
}

.toolbar-right {
	display: flex;
	align-items: center;
	gap: 12px;
}

.subtitle {
	opacity: 0.75;
	margin-top: 4px;
}

.icon-button {
	appearance: none;
	-webkit-appearance: none;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 38px;
	height: 38px;
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

.icon-button.is-loading svg {
	animation: recentphotos-spin 0.9s linear infinite;
}

@keyframes recentphotos-spin {
	from {
		transform: rotate(0deg);
	}

	to {
		transform: rotate(360deg);
	}
}
</style>