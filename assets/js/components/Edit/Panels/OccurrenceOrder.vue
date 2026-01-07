<template>
  <panel :header="header">
    <div ref="sortableContainer">
      <div
          class="panel panel-default draggable-item"
          v-for="occurrence in model.occurrenceOrder"
          :key="occurrence.id"
          :data-id="occurrence.id">
        <div class="panel-body">
          <i class="fa fa-arrows draggable-icon" />[{{ occurrence.id }}] <span class="greek">{{ occurrence.name }}</span> ({{ occurrence.location}})
        </div>
      </div>
    </div>
  </panel>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import Sortable from 'sortablejs'
import Panel from '../Panel.vue'
import { disableFields, enableFields } from "@/helpers/formFieldUtils"
import { calcChanges } from "@/helpers/modelChangeUtil"

const props = defineProps({
  header: {
    type: String,
    default: '',
  },
  links: {
    type: Array,
    default: () => [],
  },
  model: {
    type: Object,
    default: () => ({}),
  },
  reloads: {
    type: Array,
    default: () => [],
  },
  values: {
    type: Array,
    default: () => [],
  },
  keys: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['validated', 'reload'])

// Refs
const sortableContainer = ref(null)
let sortableInstance = null

// Data
const changes = ref([])
const isValid = ref(true)
const originalModel = ref({})

const formOptions = ref({
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
})

// Computed
const fields = computed(() => ({
  occurrenceOrder: {
    label: 'Occurrence Order',
  },
}))

// Methods
const validate = () => {
  changes.value = calcChanges(props.model, originalModel.value, fields.value)
}

const onChange = () => {
  changes.value = calcChanges(props.model, originalModel.value, fields.value)
  emit('validated', isValid.value, [])
}

const init = () => {
  originalModel.value = JSON.parse(JSON.stringify(props.model))
  enableFieldsMethod()
}

const reload = (type) => {
  if (!props.reloads.includes(type)) {
    emit('reload', type)
  }
}

const disableFieldsMethod = (disableKeys) => {
  disableFields(props.keys, fields.value, disableKeys)
}

const enableFieldsMethod = (enableKeys) => {
  enableFields(props.keys, fields.value, props.values, enableKeys)
}

const validated = (isValidParam, errors) => {
  isValid.value = isValidParam
  changes.value = calcChanges(props.model, originalModel.value, fields.value)
  emit('validated', isValidParam, errors)
}

// Initialize Sortable
onMounted(() => {
  if (sortableContainer.value) {
    sortableInstance = new Sortable(sortableContainer.value, {
      animation: 150,
      handle: '.draggable-icon',
      onEnd: (evt) => {
        const { oldIndex, newIndex } = evt
        if (oldIndex !== newIndex) {
          const movedItem = props.model.occurrenceOrder.splice(oldIndex, 1)[0]
          props.model.occurrenceOrder.splice(newIndex, 0, movedItem)
          onChange()
        }
      }
    })
  }
})

// Expose methods for parent component
defineExpose({
  init,
  reload,
  disableFields: disableFieldsMethod,
  enableFields: enableFieldsMethod,
  validate,
  validated,
  isValid,
  changes,
})
</script>

<style scoped>
.draggable-item {
  cursor: move;
  margin-bottom: 10px;
}

.draggable-icon {
  cursor: grab;
  margin-right: 10px;
}

.draggable-icon:active {
  cursor: grabbing;
}
</style>