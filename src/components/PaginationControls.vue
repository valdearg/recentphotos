<template>
  <div class="pagination" v-if="pages > 1">
    <button :disabled="page <= 1" @click="$emit('change', page - 1)">Previous</button>
    <span>Page {{ page }} of {{ pages }}</span>
    <button :disabled="page >= pages" @click="$emit('change', page + 1)">Next</button>
  </div>
</template>

<script>
export default {
  name: 'PaginationControls',
  props: {
    page: { type: Number, required: true },
    pages: { type: Number, required: true },
  },
}
</script>

<style scoped>
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 20px 0 8px;
}
</style>
