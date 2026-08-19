<script setup>
import { computed } from 'vue'
import { Ckeditor } from '@ckeditor/ckeditor5-vue'
import 'ckeditor5/ckeditor5.css'
import { ClassicEditor, createRichTextEditorConfig } from '../../Composables/useRichTextEditorConfig.js'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  dir: {
    type: String,
    default: 'ltr',
  },
  direction: {
    type: String,
    default: null,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  active: {
    type: Boolean,
    default: true,
  },
  minHeight: {
    type: String,
    default: '180px',
  },
  inputId: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue'])

const textDirection = computed(() => props.direction || props.dir)

const content = computed({
  get: () => props.modelValue ?? '',
  set: (value) => emit('update:modelValue', value),
})

const editorConfig = computed(() => createRichTextEditorConfig({
  placeholder: props.placeholder,
}))

const onReady = (editor) => {
  editor.editing.view.change((writer) => {
    writer.setAttribute('dir', textDirection.value, editor.editing.view.document.getRoot())
  })

  const editable = editor.ui.getEditableElement()
  if (editable) {
    editable.style.minHeight = props.minHeight
  }
}
</script>

<template>
  <div class="rich-text-editor" :dir="textDirection">
    <Ckeditor
      v-if="active"
      :id="inputId"
      v-model="content"
      :editor="ClassicEditor"
      :config="editorConfig"
      :disabled="disabled"
      @ready="onReady"
    />
  </div>
</template>
