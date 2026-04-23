<template>
  <div class="index-panel">
    <div class="status-line">
      <strong>Index:</strong> {{ status.status }}
    </div>
    <div class="status-line">
      <span>Last indexed:</span> {{ formatDate(status.lastRun) }}
    </div>
    <div class="status-line">
      <span>Last count:</span> {{ status.lastCount }}
    </div>
    <button :disabled="busy" @click="$emit('rebuild')">
      {{ busy ? 'Queueing…' : 'Rebuild index' }}
    </button>
  </div>
</template>

<script>
export default {
  name: 'IndexStatusPanel',
  props: {
    status: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  methods: {
    formatDate(ts) {
      return ts ? new Date(ts * 1000).toLocaleString() : 'Never'
    },
  },
}
</script>

<style scoped>
.index-panel {
  border: 1px solid var(--color-border);
  padding: 12px;
  border-radius: 10px;
  min-width: 220px;
}
.status-line {
  font-size: 13px;
  margin-bottom: 6px;
}
button {
  margin-top: 8px;
  padding: 8px 12px;
}
</style>
