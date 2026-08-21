<script setup>
import { computed } from 'vue'
import { buildCategoryForest } from '../../../Composables/useCategoryTree.js'
import CategoryTreeNode from './CategoryTreeNode.vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: true },
  selectedIds: { type: Array, default: () => [] },
  selectedId: { type: String, default: '' },
  emptyText: { type: String, default: '' },
  inputRequired: { type: Boolean, default: true },
})

const emit = defineEmits(['toggle', 'select'])

const forest = computed(() => buildCategoryForest(props.categories))
</script>

<template>
  <div class="max-h-56 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
    <p v-if="forest.length === 0" class="text-muted muted-color p-3">
      {{ emptyText }}
    </p>

    <ul v-else class="py-1" role="list">
      <CategoryTreeNode
        v-for="node in forest"
        :key="node.public_id"
        :node="node"
        :depth="0"
        :multiple="multiple"
        :selectedIds="selectedIds"
        :selectedId="selectedId"
        :inputRequired="inputRequired"
        @toggle="emit('toggle', $event)"
        @select="emit('select', $event)"
      />
    </ul>
  </div>
</template>
