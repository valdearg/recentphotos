<template>
  <div ref="sentinel" class="sentinel">
    <span v-if="!disabled">Scroll for more…</span>
  </div>
</template>

<script>
export default {
  name: 'InfiniteScrollSentinel',
  props: {
    disabled: { type: Boolean, default: false },
    preloadScreens: { type: Number, default: 1.25 },
  },
  data() {
    return {
      observer: null,
      resizeTimer: null,
    }
  },
  watch: {
    disabled(value) {
      if (!value) {
        this.$nextTick(this.checkPosition)
      }
    },
  },
  mounted() {
    this.createObserver()
    window.addEventListener('resize', this.onResize)
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.onResize)
    this.disconnectObserver()

    if (this.resizeTimer) {
      clearTimeout(this.resizeTimer)
      this.resizeTimer = null
    }
  },
  methods: {
    createObserver() {
      this.disconnectObserver()

      const root = this.getScrollRoot()
      const rootHeight = root?.clientHeight || window.innerHeight || 800
      const preloadDistance = Math.max(600, Math.round(rootHeight * this.preloadScreens))

      this.observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting && !this.disabled) {
            this.$emit('intersect')
          }
        }
      }, {
        root,
        rootMargin: `0px 0px ${preloadDistance}px 0px`,
      })

      this.observer.observe(this.$refs.sentinel)
      this.$nextTick(this.checkPosition)
    },

    disconnectObserver() {
      if (this.observer) {
        this.observer.disconnect()
        this.observer = null
      }
    },

    getScrollRoot() {
      return document.querySelector('#app-content') || null
    },

    checkPosition() {
      if (this.disabled || !this.$refs.sentinel) return

      const root = this.getScrollRoot()
      const preloadDistance = Math.max(
        600,
        Math.round((root?.clientHeight || window.innerHeight || 800) * this.preloadScreens)
      )
      const sentinelTop = this.$refs.sentinel.getBoundingClientRect().top
      const rootBottom = root ? root.getBoundingClientRect().bottom : window.innerHeight

      if (sentinelTop <= rootBottom + preloadDistance) {
        this.$emit('intersect')
      }
    },

    onResize() {
      if (this.resizeTimer) {
        clearTimeout(this.resizeTimer)
      }

      this.resizeTimer = setTimeout(() => {
        this.createObserver()
      }, 150)
    },
  },
}
</script>

<style scoped>
.sentinel {
  text-align: center;
  padding: 18px 0 28px;
  opacity: 0.7;
}
</style>
