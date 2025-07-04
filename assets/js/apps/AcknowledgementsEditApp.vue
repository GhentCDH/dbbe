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
        <editModal
            :show="editModal"
            :schema="editSchema"
            :submit-model="submitModel"
            :original-submit-model="originalSubmitModel"
            :alerts="editAlerts"
            @cancel="cancelEdit()"
            @reset="resetEdit()"
            @confirm="submitEdit()"
            @dismiss-alert="editAlerts.splice($event, 1)" />
        <deleteModal
            :show="deleteModal"
            :del-dependencies="delDependencies"
            :submit-model="submitModel"
            :alerts="deleteAlerts"
            @cancel="cancelDelete()"
            @confirm="submitDelete()"
            @dismiss-alert="deleteAlerts.splice($event, 1)" />
    </div>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue'
import axios from 'axios'
import VueFormGenerator from 'vue-form-generator'
import { useEditMergeMigrateDelete } from '@/composables/useEditMergeMigrateDelete'
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'
import Edit from '@/Components/Edit/Modals/Edit.vue'
import Merge from '@/Components/Edit/Modals/Merge.vue'
import Delete from '@/Components/Edit/Modals/Delete.vue'
import Panel from '@/Components/Edit/Panel.vue'
import EditListRow from "@/Components/Edit/EditListRow.vue";

const props = defineProps({
  initUrls: String,
  initData: String
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
  cancelDelete
} = useEditMergeMigrateDelete(props.initUrls, props.initData, {})

const schema = reactive({
  fields: {
    acknowledgement: createMultiSelect('Acknowledgement')
  }
})

const editSchema = reactive({
  fields: {
    name: {
      type: 'input',
      inputType: 'text',
      label: 'Name',
      labelClasses: 'control-label',
      model: 'acknowledgement.name',
      required: true,
      validator: VueFormGenerator.validators.string,
    },
  }
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

const editModal = computed({
  get: () => editModalValue.value,
  set: (val) => editModalValue.value = val
})

const formatType = (type) => type

function edit(add = false) {
  if (add) {
    submitModel.acknowledgement = { id: null, name: null }
  } else {
    submitModel.acknowledgement = JSON.parse(JSON.stringify(model.acknowledgement))
  }
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModal.value = true
}

function del() {
  submitModel.acknowledgement = model.acknowledgement
  deleteDependencies()
}

function submitEdit() {
  editModal.value = false
  openRequests.value++
  const ack = submitModel.acknowledgement

  const successHandler = (response, message) => {
    submitModel.acknowledgement = response.data
    update()
    editAlerts.value = []
    alerts.value.push({ type: 'success', message })
    openRequests.value--
  }

  const errorHandler = (error, message) => {
    openRequests.value--
    editModal.value = true
    editAlerts.value.push({
      type: 'error',
      message,
      login: isLoginError(error)
    })
    console.error(error)
  }

  if (ack.id == null) {
    axios.post(urls.acknowledgement_post, { name: ack.name })
        .then(res => successHandler(res, 'Addition successful.'))
        .catch(err => errorHandler(err, 'Something went wrong while adding the acknowledgement.'))
  } else {
    axios.put(urls.acknowledgement_put.replace('acknowledgement_id', ack.id), { name: ack.name })
        .then(res => successHandler(res, 'Update successful.'))
        .catch(err => errorHandler(err, 'Something went wrong while updating the acknowledgement.'))
  }
}

function submitDelete() {
  deleteModal.value = false
  openRequests.value++
  axios.delete(urls.acknowledgement_delete.replace('acknowledgement_id', submitModel.acknowledgement.id))
      .then(() => {
        submitModel.acknowledgement = null
        update()
        deleteAlerts.value = []
        alerts.value.push({ type: 'success', message: 'Deletion successful.' })
        openRequests.value--
      })
      .catch(error => {
        openRequests.value--
        deleteModal.value = true
        deleteAlerts.value.push({
          type: 'error',
          message: 'Something went wrong while deleting the acknowledgement.',
          login: isLoginError(error)
        })
        console.error(error)
      })
}

function update() {
  openRequests.value++
  axios.get(urls.acknowledgements_get)
      .then((response) => {
        values.value = response.data
        schema.fields.acknowledgement.values = response.data
        model.acknowledgement = JSON.parse(JSON.stringify(submitModel.acknowledgement))
        openRequests.value--
      })
      .catch((error) => {
        openRequests.value--
        alerts.value.push({ type: 'error', message: 'Something went wrong while renewing the acknowledgement data.', login: isLoginError(error) })
        console.error(error)
      })
}

schema.fields.acknowledgement.values = values.value
enableField(schema.fields.acknowledgement)
</script>
