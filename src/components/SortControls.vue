<template>
  <div class="controls">
    <label class="control-group">
      <span>Sort by</span>
      <select :value="sortBy" @change="emitChange('sortBy', $event.target.value)">
        <option value="date_taken">Date taken</option>
        <option value="created">Created</option>
        <option value="modified">Modified</option>
        <option value="name">Name</option>
        <option value="size">Size</option>
      </select>
    </label>

    <label class="control-group">
      <span>Direction</span>
      <select :value="sortDir" @change="emitChange('sortDir', $event.target.value)">
        <option value="desc">Descending</option>
        <option value="asc">Ascending</option>
      </select>
    </label>

    <label class="control-group">
      <span>Media</span>
      <select :value="mediaFilter" @change="emitChange('mediaFilter', $event.target.value)">
        <option value="all">All</option>
        <option value="image">Images</option>
        <option value="gif">GIFs</option>
        <option value="video">Videos</option>
      </select>
    </label>

    <label class="control-group">
      <span>Display mode</span>
      <select :value="displayMode" @change="emitChange('displayMode', $event.target.value)">
        <option value="pagination">Pagination</option>
        <option value="infinite">Infinite scroll</option>
      </select>
    </label>

    <label class="control-group page-size">
      <span>Items</span>
      <input type="number" min="1" :max="maxPageSize" :value="pageSize"
        @change="emitChange('pageSize', parseInt($event.target.value, 10) || 1)">
    </label>
  </div>
</template>

<script>
export default {
  name: 'SortControls',
  props: {
    sortBy: { type: String, required: true },
    sortDir: { type: String, required: true },
    mediaFilter: { type: String, required: true },
    displayMode: { type: String, required: true },
    pageSize: { type: Number, required: true },
    maxPageSize: { type: Number, required: true },
  },
  methods: {
    emitChange(key, value) {
      this.$emit('change', { [key]: value })
    },
  },
}
</script>

<style scoped>
.controls {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: flex-end;
  margin: 0;
}

.control-group {
  display: flex;
  flex-direction: column;
  gap: 3px;
  font-size: 11px;
  min-width: 130px;
  margin: 0;
}

.control-group span {
  opacity: 0.8;
}

.control-group select,
.control-group input {
  height: 30px;
  padding: 3px 8px;
  box-sizing: border-box;
}

.page-size {
  min-width: 80px;
  max-width: 90px;
}

.page-size input {
  width: 100%;
}
</style>