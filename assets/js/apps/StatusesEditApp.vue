<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <Panel header="Edit statuses">
        <EditListRow
            :schema="statusSchema"
            :model="model"
            name="origin"
            :conditions="{
            add: model.statusType,
            edit: model.status,
            del: model.status
          }"
            @add="editStatus(true)"
            @edit="editStatus()"
            @del="delStatus()"
        />
      </Panel>
      <div v-if="openRequests" class="loading-overlay">
        <div class="spinner" />
      </div>
    </article>

    <Edit
        :show="editModalValue"
        :schema="editStatusSchema"
        :submit-model="submitModel"
        :original-submit-model="originalSubmitModel"
        :alerts="editAlerts"
        @cancel="cancelEdit"
        @reset="resetEdit(submitModel)"
        @confirm="submitEdit"
        @dismiss-alert="editAlerts.splice($event, 1)"
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

import Edit from '@/Components/Edit/Modals/Edit.vue'
import Delete from '@/Components/Edit/Modals/Delete.vue'
import Panel from '@/Components/Edit/Panel.vue'
import Alerts from '@/Components/Alerts.vue'
import EditListRow from '@/Components/Edit/EditListRow.vue'

import { isLoginError } from '@/helpers/errorUtil'
import { createMultiSelect, enableField, dependencyField } from '@/helpers/formFieldUtils'
import { useEditMergeMigrateDelete } from '@/composables/useEditMergeMigrateDelete'

const props = defineProps({
  initUrls: {
    type: String
  },
  initData: {
    type: String
  }
})

const {
  urls,
  values,
  alerts,
  editAlerts,
  deleteAlerts,
  delDependencies,
  deleteModal,
  editModalValue,
  originalSubmitModel,
  openRequests,
  deleteDependencies,
  cancelEdit,
  cancelDelete,
  resetEdit
} = useEditMergeMigrateDelete(props.initUrls, props.initData)

// Schemas
const statusSchema = reactive({
  fields: {
    statusType: createMultiSelect('Status Type', { model: 'statusType' }),
    status: createMultiSelect('Status', { dependency: 'statusType', dependencyName: 'status type' })
  }
})

const editStatusSchema = reactive({
  fields: {
    statusType: createMultiSelect('Status Type', { model: 'statusType' }, { loading: false }),
    name: {
      type: 'input',
      inputType: 'text',
      label: 'Status name',
      labelClasses: 'control-label',
      model: 'status.name',
      required: true,
      validator: VueFormGenerator.validators.string
    }
  }
})

const model = reactive({
  statusType: null,
  status: null
})

const submitModel = reactive({
  submitType: 'status',
  statusType: null,
  status: null
})

// Watchers
watch(() => model.statusType, (newVal) => {
  if (!newVal) {
    dependencyField(statusSchema.fields.status, model)
  } else {
    loadStatusField()
    enableField(statusSchema.fields.status, model)
  }
})

watch(values, (newValues) => {
  statusSchema.fields.status.values = newValues.filter(s => s.type === model.statusType?.id)
}, { immediate: true })

// Methods
function loadStatusField() {
  statusSchema.fields.status.values = values.value.filter(
      status => status.type === model.statusType?.id
  )
}

function loadStatusTypeField(field) {
  const statusTypes = [
    'manuscript',
    'occurrence_record',
    'occurrence_text',
    'occurrence_divided',
    'occurrence_source',
    'type_text',
    'type_critical'
  ]
  field.values = statusTypes.map(type => ({
    id: type,
    name: formatStatusType(type)
  }))
}

function formatStatusType(type) {
  return type.charAt(0).toUpperCase() + type.slice(1).replace(/_/g, ' ')
}

onMounted(() => {
  loadStatusTypeField(statusSchema.fields.statusType)
  enableField(statusSchema.fields.statusType, model)
  dependencyField(statusSchema.fields.status, model)
})

// Edit status modal handlers
function editStatus(add = false) {
  submitModel.statusType = model.statusType
  loadStatusTypeField(editStatusSchema.fields.statusType)

  if (add) {
    submitModel.status = {
      name: null,
      type: model.statusType.id
    }
  } else {
    submitModel.status = JSON.parse(JSON.stringify(model.status))
  }
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function delStatus() {
  submitModel.status = JSON.parse(JSON.stringify(model.status))
  deleteDependencies()
}

async function update() {
  openRequests.value++
  try {
    const response = await axios.get(urls.statuses_get)
    if (Array.isArray(values.value)) {
      values.value.splice(0, values.value.length, ...response.data)
    }
    loadStatusField()
    model.status = JSON.parse(JSON.stringify(submitModel.status))
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the status data.',
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
    let response
    if (!submitModel.status.id) {
      response = await axios.post(urls.status_post, {
        type: submitModel.status.type,
        name: submitModel.status.name
      })
      submitModel.status = response.data
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      response = await axios.put(
          urls.status_put.replace('status_id', submitModel.status.id),
          { name: submitModel.status.name }
      )
      submitModel.status = response.data
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
    await update()
    editAlerts.value.splice(0)
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message: !submitModel.status.id
          ? 'Something went wrong while adding the status.'
          : 'Something went wrong while updating the status.',
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
    await axios.delete(urls.status_delete.replace('status_id', submitModel.status.id))
    submitModel.status = null
    await update()
    deleteAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the status.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}
</script>
