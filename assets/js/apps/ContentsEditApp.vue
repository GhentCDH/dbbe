<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <Panel header="Edit contents">
        <EditListRow
            :schema="schema"
            :model="model"
            name="content"
            :conditions="{
            add: true,
            edit: model.content,
            merge: model.content,
            del: model.content,
          }"
            @add="edit(true)"
            @edit="edit()"
            @merge="merge()"
            @del="del()"
        />
      </Panel>

      <div v-if="openRequests" class="loading-overlay">
        <div class="spinner" />
      </div>
    </article>

    <Edit
        :show="editModalValue"
        :schema="editSchema"
        :submit-model="submitModel"
        :original-submit-model="originalSubmitModel"
        :alerts="editAlerts"
        @cancel="cancelEdit"
        @reset="resetEdit(submitModel)"
        @confirm="submitEdit"
        @dismiss-alert="editAlerts.splice($event, 1)"
    />

    <Merge
        :show="mergeModal"
        :schema="mergeSchema"
        :merge-model="mergeModel"
        :original-merge-model="originalMergeModel"
        :alerts="mergeAlerts"
        @cancel="cancelMerge"
        @reset="resetMerge"
        @confirm="submitMerge"
        @dismiss-alert="mergeAlerts.splice($event, 1)"
    >
      <template #preview>
        <table v-if="mergeModel.primary && mergeModel.secondary" class="table table-striped table-hover">
          <thead>
          <tr>
            <th>Field</th>
            <th>Value</th>
          </tr>
          </thead>
          <tbody>
          <tr>
            <td>Name</td>
            <td>{{ mergeModel.primary.name || mergeModel.secondary.name }}</td>
          </tr>
          </tbody>
        </table>
      </template>
    </Merge>

    <Delete
        :show="deleteModal"
        :del-dependencies="delDependencies"
        :submit-model="submitModel"
        :alerts="deleteAlerts"
        @cancel="cancelDelete"
        @confirm="submitDelete"
        @dismiss-alert="deleteAlerts.splice($event, 1)"
    />
  </div>
</template>

<script setup>
import { reactive, ref, watch, onMounted, computed } from 'vue'
import axios from 'axios'
import VueFormGenerator from 'vue3-form-generator-legacy'

import Edit from '@/components/Edit/Modals/Edit.vue'
import Merge from '@/components/Edit/Modals/Merge.vue'
import Delete from '@/components/Edit/Modals/Delete.vue'
import Panel from '@/components/Edit/Panel.vue'
import Alerts from '@/components/Alerts.vue'
import EditListRow from '@/components/Edit/EditListRow.vue'

import { isLoginError } from '@/helpers/errorUtil'
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'
import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete'
import validatorUtil from "@/helpers/validatorUtil";

// Props
const props = defineProps({
  initUrls: {
    type: String
  },
  initData: {
    type: String
  },
  initPersons: {
    type: String,
    default: '[]'
  }
})

const persons = ref(JSON.parse(props.initPersons))

const depUrls = computed(() => ({}))

// Use composable for common logic (you may want to adapt or create your own)
const {
  urls,
  values,
  alerts,
  editAlerts,
  mergeAlerts,
  deleteAlerts,
  delDependencies,
  deleteModal,
  editModalValue,
  mergeModal,
  originalSubmitModel,
  originalMergeModel,
  openRequests,
  isOrIsChild,
  cancelEdit,
  cancelMerge,
  cancelDelete,
  resetEdit,
  resetMerge,
  deleteDependencies,
} = useEditMergeMigrateDelete(props.initUrls, props.initData, depUrls)

// Main schema for the list selector
const schema = reactive({
  fields: {
    content: createMultiSelect('Content')
  }
})

// Schema for edit modal
const editSchema = reactive({
  fields: [
    createMultiSelect('Parent', { model: 'content.parent' }),
    {
      type: 'input',
      inputType: 'text',
      label: 'Content name',
      labelClasses: 'control-label',
      model: 'content.individualName',
      validator: [validatorUtil.string, validateIndividualNameOrPerson]
    },
    createMultiSelect('Person', { model: 'content.individualPerson', validator: validateIndividualNameOrPerson })
  ]
})

// Schema for merge modal
const mergeSchema = reactive({
  fields: [
    createMultiSelect('Primary', { required: true, validator: validatorUtil.required }),
    createMultiSelect('Secondary', { required: true, validator: validatorUtil.required })
  ]
})

const model = reactive({
  content: null
})

const submitModel = reactive({
  submitType: 'content',
  content: {
    id: null,
    parent: null,
    name: null,
    individualPerson: null,
    individualName: null,
  }
})

const mergeModel = reactive({
  submitType: 'contents',
  primary: null,
  secondary: null,
})

// Watchers
watch(values, (newValues) => {
  schema.fields.content.values = Array.isArray(newValues) ? newValues : []
}, { immediate: true })

watch(() => model.content, (newContent) => {
  if (newContent && newContent.parent) {
    // Update full parent object for correct formatting
    const fullParent = values.value.find(v => v.id === newContent.parent.id)
    if (fullParent) newContent.parent = fullParent
  }
})

watch(() => submitModel.content?.individualName, (val) => {
  if (val === '') submitModel.content.individualName = null
})

// Enable fields when values update
watch(values, () => {
  enableField(schema.fields.content)
})

// Lifecycle
onMounted(() => {
  schema.fields.content.values = values.value || []
  enableField(schema.fields.content)

  // URL params check, load if id param present
  const params = new URLSearchParams(window.location.search)
  const idParam = params.get('id')
  if (idParam && !isNaN(idParam)) {
    const found = values.value.find(v => v.id === +idParam)
    if (found) model.content = JSON.parse(JSON.stringify(found))
  }
  // Clear URL query parameters
  history.replaceState(null, '', window.location.pathname)
})

// Methods

function validateIndividualNameOrPerson() {
  if (
      (submitModel.content.individualName == null && submitModel.content.individualPerson == null) ||
      (submitModel.content.individualName != null && submitModel.content.individualPerson != null)
  ) {
    return ['Please provide a content name or select a person (but not both).']
  }
  return []
}

function edit(add = false) {
  if (add) {
    submitModel.content = {
      id: null,
      name: null,
      parent: model.content,
      individualName: null,
      individualPerson: null
    }
  } else {
    submitModel.content = JSON.parse(JSON.stringify(model.content))
  }
  // Filter out values that would cause cycles for parent selection
  editSchema.fields[0].values = values.value.filter(c => !isOrIsChild(c, model.content))
  enableField(editSchema.fields[0])
  editSchema.fields[2].values = persons.value
  enableField(editSchema.fields[2])

  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function merge() {
  mergeModel.primary = JSON.parse(JSON.stringify(model.content))
  mergeModel.secondary = null

  mergeSchema.fields[0].values = values.value
  mergeSchema.fields[1].values = values.value
  enableField(mergeSchema.fields[0])
  enableField(mergeSchema.fields[1])

  Object.assign(originalMergeModel, JSON.parse(JSON.stringify(mergeModel)))
  mergeModal.value = true
}

function del() {
  submitModel.content = model.content
  deleteDependencies()
}

async function update() {
  openRequests.value++
  try {
    const response = await axios.get(urls.contents_get)
    if (Array.isArray(values.value)) {
      values.value.splice(0, values.value.length, ...response.data)
    }
    schema.fields.content.values = values.value
    model.content = JSON.parse(JSON.stringify(submitModel.content))
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the content data.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function submitEdit() {
  editModalValue.value = false
  openRequests.value++

  try {
    if (submitModel.content.id == null) {
      const postData = {
        parent: submitModel.content.parent
            ? { id: submitModel.content.parent.id }
            : null,
        individualName: submitModel.content.individualName,
        individualPerson: submitModel.content.individualPerson
            ? { id: submitModel.content.individualPerson.id }
            : null,
      }
      const response = await axios.post(urls.content_post, postData)
      submitModel.content = response.data
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      const data = {}
      if (JSON.stringify(submitModel.content.parent) !== JSON.stringify(originalSubmitModel.content.parent)) {
        data.parent = submitModel.content.parent
            ? { id: submitModel.content.parent.id }
            : null
      }
      if (submitModel.content.individualName !== originalSubmitModel.content.individualName) {
        data.individualName = submitModel.content.individualName
      }
      if (JSON.stringify(submitModel.content.individualPerson) !== JSON.stringify(originalSubmitModel.content.individualPerson)) {
        data.individualPerson = submitModel.content.individualPerson
            ? { id: submitModel.content.individualPerson.id }
            : null
      }
      const response = await axios.put(
          urls.content_put.replace('content_id', submitModel.content.id),
          data
      )
      submitModel.content = response.data
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
    await update()
    editAlerts.value.splice(0)
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message:
          submitModel.content.id == null
              ? 'Something went wrong while adding the content.'
              : 'Something went wrong while updating the content.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function submitMerge() {
  mergeModal.value = false
  openRequests.value++

  try {
    const response = await axios.put(
        urls.content_merge
            .replace('primary_id', mergeModel.primary.id)
            .replace('secondary_id', mergeModel.secondary.id)
    )
    submitModel.content = response.data
    alerts.value.push({ type: 'success', message: 'Merge successful.' })
    await update()
    mergeAlerts.value.splice(0)
  } catch (error) {
    mergeModal.value = true
    mergeAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while merging the content.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function submitDelete() {
  deleteModal.value = false
  openRequests.value++

  try {
    await axios.delete(urls.content_delete.replace('content_id', submitModel.content.id))
    submitModel.content = null
    await update()
    deleteAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the content.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

</script>
