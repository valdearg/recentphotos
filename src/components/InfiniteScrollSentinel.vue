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
  },
  mounted() {
    this.observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting && !this.disabled) {
          this.$emit('intersect')
        }
      }
    }, { rootMargin: '300px 0px' })

    this.observer.observe(this.$refs.sentinel)
  },
  beforeDestroy() {
    if (this.observer) {
      this.observer.disconnect()
    }
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
