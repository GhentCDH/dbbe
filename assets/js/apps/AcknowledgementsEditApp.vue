<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <Panel header="Edit acknowledgements">
                <EditListRow
                    :schema="schema"
                    :model="model"
                    name="acknowledgement"
                    :conditions="{
                        add: true,
                        edit: model.acknowledgement,
                        del: model.acknowledgement,
                    }"
                    @add="edit(true)"
                    @edit="edit()"
                    @del="del()" />
            </Panel>
            <div
                class="loading-overlay"
                v-if="openRequests">
                <div class="spinner" />
            </div>
        </article>
        <Edit
            :show="editModalValue"
            :schema="editSchema"
            :submit-model="submitModel"
            :original-submit-model="originalSubmitModel"
            :alerts="editAlerts"
            @cancel="cancelEdit()"
            @reset="resetEdit()"
            @confirm="submitEdit()"
            @dismiss-alert="editAlerts.splice($event, 1)" />
        <Delete
            :show="deleteModal"
            :del-dependencies="delDependencies"
            :submit-model="submitModel"
            :alerts="deleteAlerts"
            @cancel="cancelDelete()"
            @confirm="submitDelete()"
            @dismiss-alert="deleteAlerts.splice($event, 1)" />
    </div>
</template>

<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <Panel header="Edit acknowledgements">
        <EditListRow
            :schema="schema"
            :model="model"
            name="acknowledgement"
            :conditions="{
            add: true,
            edit: model.acknowledgement,
            del: model.acknowledgement
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

const schema = reactive({
  fields: {
    acknowledgement: createMultiSelect('Acknowledgement')
  }
})

const editSchema = reactive({
  fields: [
    {
      type: 'input',
      inputType: 'text',
      label: 'Name',
      labelClasses: 'control-label',
      model: 'acknowledgement.name',
      required: true,
      validator: VueFormGenerator.validators.string
    }
  ]
})

const model = reactive({
  acknowledgement: null
})

const submitModel = reactive({
  submitType: 'acknowledgement',
  acknowledgement: {
    id: null,
    name: null
  }
})

watch(values, (newValues) => {
  schema.fields.acknowledgement.values = Array.isArray(newValues) ? newValues : []
}, { immediate: true })

watch(originalSubmitModel, (newVal) => {
  Object.assign(submitModel.acknowledgement, newVal.acknowledgement)
})

watch(values, () => {
  enableField(schema.fields.acknowledgement)
})

onMounted(() => {
  schema.fields.acknowledgement.values = values.value || []
  enableField(schema.fields.acknowledgement, model)
})

function edit(add = false) {
  if (add) {
    submitModel.acknowledgement = { id: null, name: null }
  } else {
    submitModel.acknowledgement = JSON.parse(JSON.stringify(model.acknowledgement))
  }
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function del() {
  submitModel.acknowledgement = model.acknowledgement
  deleteDependencies()
}

async function update() {
  openRequests.value++
  try {
    const response = await axios.get(urls.acknowledgements_get)
    if (Array.isArray(values.value)) {
      values.value.splice(0, values.value.length, ...response.data)
    }
    schema.fields.acknowledgement.values = values.value
    model.acknowledgement = JSON.parse(JSON.stringify(submitModel.acknowledgement))
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the acknowledgement data.',
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
    if (submitModel.acknowledgement.id == null) {
      response = await axios.post(urls.acknowledgement_post, {
        name: submitModel.acknowledgement.name
      })
      submitModel.acknowledgement = response.data
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      response = await axios.put(
          urls.acknowledgement_put.replace('acknowledgement_id', submitModel.acknowledgement.id),
          { name: submitModel.acknowledgement.name }
      )
      submitModel.acknowledgement = response.data
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
    await update()
    editAlerts.value.splice(0)
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message: submitModel.acknowledgement.id == null
          ? 'Something went wrong while adding the acknowledgement.'
          : 'Something went wrong while updating the acknowledgement.',
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
    await axios.delete(urls.acknowledgement_delete.replace('acknowledgement_id', submitModel.acknowledgement.id))
    submitModel.acknowledgement = null
    await update()
    deleteAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the acknowledgement.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}
</script>
