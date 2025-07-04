<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <Panel header="Edit metres">
        <EditListRow
            :schema="schema"
            :model="model"
            name="metre"
            :conditions="{
            add: true,
            edit: model.metre,
            del: model.metre
          }"
            @add="edit(true)"
            @edit="edit()"
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
import { useEditMergeMigrateDelete } from '@/composables/useEditMergeMigrateDelete'
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'

// Props
const props = defineProps({
  initUrls: {
    type: String,
    required: true
  },
  initData: {
    type: String,
    required: true
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

const schema = reactive({
  fields: {
    metre: createMultiSelect('Metre')
  }
})

const editSchema = reactive({
  fields: [
    {
      type: 'input',
      inputType: 'text',
      label: 'Name',
      labelClasses: 'control-label',
      model: 'metre.name',
      required: true,
      validator: VueFormGenerator.validators.string
    }
  ]
})

const model = reactive({
  metre: null
})

const submitModel = reactive({
  submitType: 'metre',
  metre: {
    id: null,
    name: null
  }
})

watch(values, (newValues) => {
  schema.fields.metre.values = Array.isArray(newValues) ? newValues : []
}, { immediate: true })

watch(originalSubmitModel, (newVal) => {
  Object.assign(submitModel.metre, newVal.metre)
})

watch(values, (val) => {
  enableField(schema.fields.metre)
})

onMounted(() => {
  schema.fields.metre.values = values.value || []
  enableField(schema.fields.metre, model)
})

function edit(add = false) {
  if (add) {
    submitModel.metre = { id: null, name: null }
  } else {
    submitModel.metre = JSON.parse(JSON.stringify(model.metre))
  }
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function del() {
  submitModel.metre = model.metre
  deleteDependencies()
}

async function update() {
  openRequests.value++
  try {
    const response = await axios.get(urls.metres_get)
    if (Array.isArray(values.value)) {
      values.value.splice(0, values.value.length, ...response.data)
    }
    schema.fields.metre.values = values.value
    model.metre = JSON.parse(JSON.stringify(submitModel.metre))
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the metre data.',
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
    if (submitModel.metre.id == null) {
      response = await axios.post(urls.metre_post, {
        name: submitModel.metre.name
      })
      submitModel.metre = response.data
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      response = await axios.put(
          urls.metre_put.replace('metre_id', submitModel.metre.id),
          { name: submitModel.metre.name }
      )
      submitModel.metre = response.data
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
    await update()
    editAlerts.value.splice(0)
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message:
          submitModel.metre.id == null
              ? 'Something went wrong while adding the metre.'
              : 'Something went wrong while updating the metre.',
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
    await axios.delete(urls.metre_delete.replace('metre_id', submitModel.metre.id))
    submitModel.metre = null
    await update()
    deleteAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the metre.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}
</script>
