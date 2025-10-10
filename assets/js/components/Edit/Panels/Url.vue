<template>
  <panel :header="header">
    <div
        v-if="model.urls && model.urls.length"
        ref="sortableContainer">
      <div
          class="panel panel-default draggable-item"
          v-for="(url, index) in model.urls"
          :key="url.tgIndex"
          :data-index="index">
        <div class="panel-body row">
          <div class="col-xs-1">
            <i class="fa fa-arrows draggable-icon" />
          </div>
          <div class="col-xs-9">
            <strong>Url</strong> {{ url.url }}
            <br />
            <strong>Title</strong> {{ url.title }}
          </div>
          <div class="col-xs-2 text-right">
            <a href="#"
               title="Edit"
               class="action"
               @click.prevent="edit(index)">
              <i class="fa fa-pencil-square-o" />
            </a>

            <a href="#"
               title="Delete"
               class="action"
               @click.prevent="del(index)">
              <i class="fa fa-trash-o" />
            </a>
          </div>
        </div>
      </div>
    </div>
    <btn @click.native ="add()"><i class="fa fa-plus" />&nbsp;Add a url</btn>
    <modal
        :model-value="editModal"
        size="lg"
        auto-focus
        :backdrop="false">
      <template #header>
        <h4 class="modal-title">
          <template v-if="editModel.index != null">
            Edit url
          </template>
          <template v-else>
            Add url
          </template>
        </h4>
      </template>
      <div class="pbottom-default">
        <vue-form-generator
            v-if="editModal"
            ref="editForm"
            :schema="editSchema"
            :model="editModel"
            :options="formOptions"
            @validated="onFormValidated"
        />
      </div>
      <template #footer>
        <btn @click.native="editModal=false">Cancel</btn>
        <btn
            type="success"
            :disabled="!canSubmit"
            @click.native="submit()">
          {{ editModel.index != null ? 'Update' : 'Add' }}
        </btn>
      </template>
    </modal>
    <modal
        :model-value="delModal"
        title="Delete url"
        auto-focus>
      <p>Are you sure you want to delete this url?</p>
      <template #footer>
        <btn @click.native="delModal=false">Cancel</btn>
        <btn
            type="danger"
            @click.native="submitDelete()">
          Delete
        </btn>
      </template>
    </modal>
  </panel>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import Sortable from 'sortablejs'
import Panel from '../Panel.vue'
import { disableFields, enableFields } from "@/helpers/formFieldUtils"
import { calcChanges } from "@/helpers/modelChangeUtil"
import validatorUtil from "@/helpers/validatorUtil"

const props = defineProps({
  asSlot: {
    type: Boolean,
    default: false,
  },
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
  keys: {
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
})

const emit = defineEmits(['validated', 'reload'])

// Refs
const sortableContainer = ref(null)
let sortableInstance = null

const delModal = ref(false)
const editModal = ref(false)
const editModel = ref({
  index: null,
  id: null,
  url: null,
  title: null,
  tgIndex: null,
})
const originalEditModel = ref({})
const hasFormChanges = ref(false)
const isValid = ref(true)
const originalModel = ref({})
const editForm = ref(null)
const changes = ref([])

const fields = computed(() => ({
  occurrenceOrder: {
    label: 'Occurrence Order',
  },
}))

const canSubmit = computed(() => isValid.value && hasFormChanges.value)

const editSchema = ref({
  fields: {
    url: {
      type: 'input',
      inputType: 'text',
      label: 'Url',
      labelClasses: 'control-label',
      model: 'url',
      required: true,
      validator: [
        validatorUtil.url,
        validatorUtil.required,
      ],
    },
    title: {
      type: 'input',
      inputType: 'text',
      label: 'URL title',
      labelClasses: 'control-label',
      model: 'title',
      validator: [
        validatorUtil.string,
      ],
    },
  },
})

const formOptions = ref({
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
})

const initSortable = () => {
  if (sortableContainer.value && !sortableInstance) {
    sortableInstance = new Sortable(sortableContainer.value, {
      animation: 150,
      handle: '.draggable-icon',
      onEnd: (evt) => {
        const { oldIndex, newIndex } = evt
        if (oldIndex !== newIndex && props.model.urls) {
          const movedItem = props.model.urls.splice(oldIndex, 1)[0]
          props.model.urls.splice(newIndex, 0, movedItem)
          onOrderChange()
        }
      }
    })
  }
}

const destroySortable = () => {
  if (sortableInstance) {
    sortableInstance.destroy()
    sortableInstance = null
  }
}

const init = () => {
  originalModel.value = JSON.parse(JSON.stringify(props.model))
  enableFieldsFunc()
  nextTick(() => {
    initSortable()
  })
}

const maxTgIndex = () => {
  if (props.model.urls == null || props.model.urls.length === 0) {
    return 0
  }
  return Math.max(...props.model.urls.map(u => u.tgIndex))
}

const add = () => {
  editModel.value.id = null
  editModel.value.url = null
  editModel.value.title = null
  editModel.value.index = null
  editModel.value.tgIndex = maxTgIndex() + 1

  originalEditModel.value = JSON.parse(JSON.stringify(editModel.value))
  hasFormChanges.value = false
  isValid.value = false

  editModal.value = true
}

const edit = (index) => {
  editModel.value.id = props.model.urls[index].id
  editModel.value.url = props.model.urls[index].url
  editModel.value.title = props.model.urls[index].title
  editModel.value.index = index
  editModel.value.tgIndex = props.model.urls[index].tgIndex

  originalEditModel.value = JSON.parse(JSON.stringify(editModel.value))
  hasFormChanges.value = false
  isValid.value = false

  editModal.value = true
}

const del = (index) => {
  editModel.value.index = index
  delModal.value = true
}

const submit = () => {
  editForm.value.validate()
  if (editForm.value.errors.length === 0) {
    const item = {
      id: editModel.value.id,
      url: editModel.value.url,
      title: editModel.value.title,
      tgIndex: editModel.value.tgIndex,
    }

    if (editModel.value.index != null) {
      props.model.urls[editModel.value.index] = item
    } else if (props.model.urls) {
      props.model.urls.push(item)
    } else {
      props.model.urls = [item]
    }

    calcChangesFunc()
    emit('validated', 0, null)
    editModal.value = false

    // Reinitialize sortable after adding new item
    nextTick(() => {
      destroySortable()
      initSortable()
    })
  }
}

const submitDelete = () => {
  props.model.urls.splice(editModel.value.index, 1)
  if (props.model.urls.length === 0) {
    props.model.urls = null
  }

  calcChangesFunc()
  emit('validated', 0, null)
  delModal.value = false

  // Reinitialize sortable after deleting item
  nextTick(() => {
    destroySortable()
    initSortable()
  })
}

const validate = () => {
  calcChangesFunc()
}

const calcChangesFunc = () => {
  if (
      JSON.stringify(props.model.urls) !== JSON.stringify(originalModel.value.urls) &&
      !(props.model.urls == null && originalModel.value.urls == null)
  ) {
    changes.value = [{
      key: 'urls',
      label: 'Urls',
      old: displayUrls(originalModel.value.urls),
      new: displayUrls(props.model.urls),
      value: props.model.urls,
    }]
  } else {
    changes.value = []
  }
}

const displayUrls = (urls) => {
  if (urls == null) {
    return null
  }
  const displays = []
  for (const url of urls) {
    let display = '<strong>Url</strong> ' + url.url
    if (url.title) {
      display += '<br /><strong>Title</strong> ' + url.title
    }
    displays.push(display)
  }
  return displays
}

const onOrderChange = () => {
  calcChangesFunc()
  emit('validated')
}

const reload = (type) => {
  if (!props.reloads.includes(type)) {
    emit('reload', type)
  }
}

const disableFieldsFunc = (disableKeys) => {
  disableFields(props.keys, fields.value, disableKeys)
}

const enableFieldsFunc = (enableKeys) => {
  enableFields(props.keys, fields.value, props.values, enableKeys)
}

const validated = (isValidValue, errors) => {
  isValid.value = isValidValue
  changes.value = calcChanges(props.model, originalModel.value, fields.value)
  emit('validated', isValidValue, errors)
}

const onFormValidated = (isValidValue) => {
  isValid.value = isValidValue
}

const checkFormChanges = () => {
  if (editModel.value.index === null) {
    hasFormChanges.value = !!(editModel.value.url || editModel.value.title)
  } else {
    hasFormChanges.value = (
        editModel.value.url !== originalEditModel.value.url ||
        editModel.value.title !== originalEditModel.value.title
    )
  }
}

// Watch for changes in model.urls to reinitialize sortable
watch(() => props.model.urls, () => {
  nextTick(() => {
    destroySortable()
    initSortable()
  })
}, { deep: true })

watch(editModel, checkFormChanges, { deep: true })

onMounted(() => {
  init()
})

// Expose methods for parent component access
defineExpose({
  validate,
  disableFields: disableFieldsFunc,
  enableFields: enableFieldsFunc,
  reload,
  init,
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