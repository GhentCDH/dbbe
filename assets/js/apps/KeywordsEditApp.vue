<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <Panel :header="`Edit ${isSubject ? 'keywords' : 'tags'}`">
        <EditListRow
            :schema="schema"
            :model="model"
            name="keyword"
            to-name="person"
            :conditions="{
            add: true,
            edit: model.keyword,
            del: model.keyword,
            migrate: isSubject && model.keyword,
          }"
            @add="edit(true)"
            @edit="edit()"
            @migrate="migrate()"
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

    <Migrate
        :show="migrateModal"
        :schema="migrateSchema"
        :migrate-model="migrateModel"
        :original-migrate-model="originalMigrateModel"
        :alerts="migrateAlerts"
        @cancel="cancelMigrate"
        @reset="resetMigrate"
        @confirm="submitMigrate"
        @dismiss-alert="migrateAlerts.splice($event, 1)"
    />

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
import { reactive, ref, watch, onMounted } from 'vue'
import axios from 'axios'
import VueFormGenerator from 'vue-form-generator'

import Alerts from '@/Components/Alerts.vue'
import Panel from '@/Components/Edit/Panel.vue'
import EditListRow from '@/Components/Edit/EditListRow.vue'
import Edit from '@/Components/Edit/Modals/Edit.vue'
import Migrate from '@/Components/Edit/Modals/Migrate.vue'
import Delete from '@/Components/Edit/Modals/Delete.vue'

import { isLoginError } from '@/helpers/errorUtil'
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'
import { useEditMergeMigrateDelete } from '@/composables/useEditMergeMigrateDelete'

const props = defineProps({
  initPersons: { type: String, default: '[]' },
  initIsSubject: { type: String, default: 'true' },
  initUrls: { type: String, required: true },
  initData: { type: String, required: true },
})

const persons = JSON.parse(props.initPersons)
const isSubject = JSON.parse(props.initIsSubject)

// Initialize composable with urls and data props
const {
  urls,
  values,
  alerts,
  editAlerts,
  migrateAlerts,
  deleteAlerts,
  delDependencies,
  deleteModal,
  editModalValue,
  migrateModal,
  originalSubmitModel,
  originalMigrateModel,
  openRequests,
  deleteDependencies,
  cancelEdit,
  cancelMigrate,
  cancelDelete,
  resetEdit,
  resetMigrate,
} = useEditMergeMigrateDelete(props.initUrls, props.initData)

// Reactive schema with dynamic field based on isSubject
const schema = reactive({
  fields: {
    keyword: createMultiSelect(isSubject ? 'Keyword' : 'Tag', { model: 'keyword' }),
  },
})

const editSchema = reactive({
  fields: [
    {
      type: 'input',
      inputType: 'text',
      label: 'Name',
      labelClasses: 'control-label',
      model: 'keyword.name',
      required: true,
      validator: VueFormGenerator.validators.string,
    },
  ],
})

const migrateSchema = reactive({
  fields: {
    primary: createMultiSelect('Primary', { required: true, validator: VueFormGenerator.validators.required }),
    secondary: createMultiSelect('Secondary', { required: true, validator: VueFormGenerator.validators.required }),
  },
})

// The current selected model keyword
const model = reactive({
  keyword: null,
})

// Model used for submit operations (edit, migrate, delete)
const submitModel = reactive({
  submitType: 'keyword',
  keyword: {
    id: null,
    name: null,
  },
})

const migrateModel = reactive({
  submitType: 'keyword',
  toType: 'person',
  primary: null,
  secondary: null,
})

// Watch to update keyword values in schema whenever `values` change
watch(values, (newValues) => {
  schema.fields.keyword.values = Array.isArray(newValues) ? newValues : []
}, { immediate: true })

// Watch original submit model changes to keep submitModel synced
watch(originalSubmitModel, (newVal) => {
  Object.assign(submitModel.keyword, newVal.keyword || {})
})

// Enable field whenever values change
watch(values, () => {
  enableField(schema.fields.keyword)
})

// On mounted, initialize schema field values and enable it
onMounted(() => {
  schema.fields.keyword.values = values.value || []
  enableField(schema.fields.keyword)
})

// Edit modal logic
function edit(add = false) {
  if (add) {
    submitModel.keyword = { id: null, name: null }
  } else {
    submitModel.keyword = JSON.parse(JSON.stringify(model.keyword))
  }
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

// Migrate modal logic
function migrate() {
  migrateModel.primary = JSON.parse(JSON.stringify(model.keyword))
  migrateModel.secondary = null
  migrateSchema.fields.primary.values = values.value || []
  migrateSchema.fields.secondary.values = persons
  enableField(migrateSchema.fields.primary)
  migrateSchema.fields.primary.disabled = true
  enableField(migrateSchema.fields.secondary)
  Object.assign(originalMigrateModel, JSON.parse(JSON.stringify(migrateModel)))
  migrateModal.value = true
}

// Delete modal logic
function del() {
  submitModel.keyword = model.keyword
  deleteDependencies()
}

// Update keywords list after changes
async function update() {
  openRequests.value++
  try {
    const response = await axios.get(urls.keywords_get)
    if (Array.isArray(values.value)) {
      values.value.splice(0, values.value.length, ...response.data)
    }
    schema.fields.keyword.values = values.value
    model.keyword = JSON.parse(JSON.stringify(submitModel.keyword))
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the keyword data.',
      login: isLoginError(error),
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

// Submit editing (add/update)
async function submitEdit() {
  editModalValue.value = false
  openRequests.value++

  try {
    let response
    if (submitModel.keyword.id == null) {
      response = await axios.post(urls.keyword_post, {
        name: submitModel.keyword.name,
        isSubject,
      })
      submitModel.keyword = response.data
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      response = await axios.put(
          urls.keyword_put.replace('keyword_id', submitModel.keyword.id),
          { name: submitModel.keyword.name }
      )
      submitModel.keyword = response.data
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
    await update()
    editAlerts.value.splice(0)
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message:
          submitModel.keyword.id == null
              ? 'Something went wrong while adding the keyword.'
              : 'Something went wrong while updating the keyword.',
      login: isLoginError(error),
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function submitMigrate() {
  migrateModal.value = false
  openRequests.value++

  try {
    await axios.put(
        urls.keyword_migrate_person
            .replace('primary_id', migrateModel.primary.id)
            .replace('secondary_id', migrateModel.secondary.id)
    )
    submitModel.keyword = null
    await update()
    migrateAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Migration successful.' })
  } catch (error) {
    migrateModal.value = true
    migrateAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while migrating the keyword.',
      login: isLoginError(error),
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

// Submit delete
async function submitDelete() {
  deleteModal.value = false
  openRequests.value++

  try {
    await axios.delete(urls.keyword_delete.replace('keyword_id', submitModel.keyword.id))
    submitModel.keyword = null
    await update()
    deleteAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the keyword.',
      login: isLoginError(error),
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}
</script>
