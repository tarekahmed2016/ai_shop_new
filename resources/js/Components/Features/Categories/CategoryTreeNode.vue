<script setup>
import { computed } from 'vue'
import { faFolder } from '@fortawesome/free-solid-svg-icons'
import CategoryTreeNode from './CategoryTreeNode.vue'

const props = defineProps({
  node: { type: Object, required: true },
  depth: { type: Number, default: 0 },
  multiple: { type: Boolean, default: true },
  selectedIds: { type: Array, default: () => [] },
  selectedId: { type: String, default: '' },
  inputRequired: { type: Boolean, default: true },
})

const emit = defineEmits(['toggle', 'select'])

const isRoot = computed(() => props.depth === 0)
const hasChildren = computed(() => (props.node.children || []).length > 0)
const selected = computed(() => {
  if (props.multiple) {
    return props.selectedIds.includes(props.node.public_id)
  }

  return props.selectedId === props.node.public_id
})

const onChange = () => {
  if (props.multiple) {
    emit('toggle', props.node.public_id)
    return
  }

  emit('select', props.node.public_id)
}
</script>

<template>
  <li>
    <label
      :class="[
        'flex items-center gap-2 cursor-pointer text-body text-gray-900 dark:text-gray-100 px-3 py-1.5',
        isRoot
          ? 'font-semibold bg-gray-50 dark:bg-gray-800/80 border-b border-gray-100 dark:border-gray-700'
          : 'font-normal'
      ]"
      :style="{ paddingInlineStart: `${0.75 + depth * 1.25}rem` }"
    >
      <input
        :type="multiple ? 'checkbox' : 'radio'"
        :name="multiple ? undefined : 'merchant-category-tree'"
        :value="node.public_id"
        :checked="selected"
        :required="!multiple && inputRequired"
        class="shrink-0"
        @change="onChange"
      />
      <font-awesome-icon
        v-if="isRoot"
        :icon="faFolder"
        class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0"
      />
      <span>{{ node.name_ar }} / {{ node.name_en }}</span>
    </label>

    <ul
      v-if="hasChildren"
      class="border-s border-gray-200 dark:border-gray-600 ms-6"
      role="list"
    >
      <CategoryTreeNode
        v-for="child in node.children"
        :key="child.public_id"
        :node="child"
        :depth="depth + 1"
        :multiple="multiple"
        :selectedIds="selectedIds"
        :selectedId="selectedId"
        :inputRequired="inputRequired"
        @toggle="emit('toggle', $event)"
        @select="emit('select', $event)"
      />
    </ul>
  </li>
</template>
