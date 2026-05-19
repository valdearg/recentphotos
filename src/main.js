import { loadState } from '@nextcloud/initial-state'
import Vue from 'vue'
import App from './App.vue'
import './style.css'

const initialSettings = loadState('recentphotos', 'settings', {
  displayMode: 'pagination',
  thumbnailMode: 'square',
  pageSize: 100,
  maxPageSize: 500,
  sortBy: 'date_taken',
  sortDir: 'desc',
  mediaFilter: 'all',
  availableDisplayModes: ['pagination', 'infinite'],
  availableThumbnailModes: ['square', 'fit'],
  availableSortFields: ['date_taken', 'created', 'modified', 'name', 'size'],
  availableSortDirections: ['asc', 'desc'],
})

const initialIndexStatus = loadState('recentphotos', 'indexStatus', {
  status: 'idle',
  lastRun: 0,
  lastCount: 0,
})

new Vue({
  render: h => h(App, {
    props: {
      initialSettings,
      initialIndexStatus,
    },
  }),
}).$mount('#recentphotos-root')
