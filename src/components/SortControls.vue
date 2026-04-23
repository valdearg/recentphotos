<template>
  <div class="controls">
    <label>
      Sort by
      <select :value="sortBy" @change="emitChange('sortBy', $event.target.value)">
        <option value="date_taken">Date taken</option>
        <option value="created">Created</option>
        <option value="modified">Modified</option>
        <option value="name">Name</option>
        <option value="size">Size</option>
      </select>
    </label>

    <label>
      Direction
      <select :value="sortDir" @change="emitChange('sortDir', $event.target.value)">
        <option value="desc">Descending</option>
        <option value="asc">Ascending</option>
      </select>
    </label>

    <label>
      Display mode
      <select :value="displayMode" @change="emitChange('displayMode', $event.target.value)">
        <option value="pagination">Pagination</option>
        <option value="infinite">Infinite scroll</option>
      </select>
    </label>

    <label>
      Items per page
      <input type="number" min="1" :max="maxPageSize" :value="pageSize" @change="emitChange('pageSize', parseInt($event.target.value, 10) || 1)">
    </label>
  </div>
</template>

<script>
export default {
  name: 'SortControls',
  props: { sortBy: { type: String, required: true }, sortDir: { type: String, required: true }, displayMode: { type: String, required: true }, pageSize: { type: Number, required: true }, maxPageSize: { type: Number, required: true } },
  methods: { emitChange(key, value) { this.$emit('change', { [key]: value }) } },
}
</script>

<style scoped>
.controls { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
label { display: flex; flex-direction: column; gap: 6px; min-width: 150px; }
select, input { padding: 6px 8px; }
</style>
