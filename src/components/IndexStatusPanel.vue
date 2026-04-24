<template>
  <div :class="['index-panel', { compact }]">
    <div class="index-lines">
      <div>
        <strong>Index:</strong> {{ status.status || 'unknown' }}
      </div>
      <div v-if="!compact">
        Last indexed: {{ formatDate(status.lastRun) }}
      </div>
      <div v-if="!compact">
        Last count: {{ status.lastCount || 0 }}
      </div>
    </div>

    <button type="button" @click="$emit('rebuild')" :disabled="busy || status.status === 'running'">
      {{ compact ? 'Rebuild' : 'Rebuild index' }}
    </button>
  </div>
</template>

<script>
export default {
  name: 'IndexStatusPanel',
  props: {
    status: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
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
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.index-panel.compact {
  padding: 6px 8px;
  border-radius: 8px;
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  white-space: nowrap;
}

.index-lines {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.index-panel.compact .index-lines {
  display: block;
}

button {
  height: 32px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  background: var(--color-primary-element);
  color: var(--color-primary-element-text);
  cursor: pointer;
}

.index-panel.compact button {
  height: 28px;
  padding: 0 8px;
}

button:disabled {
  opacity: 0.6;
  cursor: default;
}
</style>