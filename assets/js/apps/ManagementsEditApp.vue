<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alert
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <Panel header="Edit management collections">
        <EditListRow
            :schema="schema"
            :model="model"
            name="management collection"
            :conditions="{
            add: true,
            edit: model.management,
            del: model.management,
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
import { reactive, watch, onMounted, defineProps,computed} from 'vue'
import axios from 'axios'
import VueFormGenerator from 'vue-form-generator'
import Edit from '@/components/Edit/Modals/Edit.vue'
import Delete from '@/components/Edit/Modals/Delete.vue'
import Panel from '@/components/Edit/Panel.vue'
import EditListRow from "@/components/Edit/EditListRow.vue";
import Alert from "@/components/Alerts.vue";
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'
import { isLoginError } from '@/helpers/errorUtil'
import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete'
const depUrls = computed(() => ({}))
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
  resetEdit,
  deleteDependencies,
  cancelEdit,
  cancelDelete,
} = useEditMergeMigrateDelete(props.initUrls, props.initData, depUrls)

const schema = reactive({
  fields: {
    management: createMultiSelect('Management')
  }
})


const editSchema = reactive({
  fields: {
    name: {
      type: 'input',
      inputType: 'text',
      label: 'Name',
      labelClasses: 'control-label',
      model: 'management.name',
      required: true,
      validator: VueFormGenerator.validators.string
    }
  }
})

const model = reactive({
  management: null
})

const submitModel = reactive({
  submitType: 'management',
  management: null
})

watch(values, (newValues) => {
  schema.fields.management.values = newValues
}, { immediate: true })

onMounted(() => {
  schema.fields.management.values = values.value || []
  enableField(schema.fields.management, model)
})

function edit(add = false) {
  submitModel.submitType = 'management'
  submitModel.management = null

  if (add) {
    submitModel.management = {}
  } else if (model.management) {
    submitModel.management = JSON.parse(JSON.stringify(model.management))
  }

  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function del() {
  if (model.management) {
    submitModel.management = JSON.parse(JSON.stringify(model.management))
    deleteDependencies()
  }
}
async function submitEdit() {
  editModalValue.value = false
  openRequests.value++

  try {
    if (!submitModel.management.id) {
      const response = await axios.post(urls['management_post'], {
        name: submitModel.management.name
      })
      submitModel.management = response.data
      await update()
      editAlerts.value = []
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      const data = {}
      if (submitModel.management.name !== originalSubmitModel.management.name) {
        data.name = submitModel.management.name
      }
      const response = await axios.put(
          urls['management_put'].replace('management_id', submitModel.management.id),
          data
      )
      submitModel.management = response.data
      await update()
      editAlerts.value = []
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message: submitModel.management.id
          ? 'Something went wrong while updating the management collection.'
          : 'Something went wrong while adding the management.',
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
    await axios.delete(urls['management_delete'].replace('management_id', submitModel.management.id))
    submitModel.management = null
    await update()
    deleteAlerts.value = []
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the management collection.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function update() {
  openRequests.value++
  try {
    const response = await axios.get(urls['managements_get'])
    const newValues = response.data
    if (Array.isArray(values.value)) {
      values.value.splice(0, values.value.length, ...newValues)
    } else {
      Object.assign(values, newValues)
    }
    schema.fields.management.values = newValues
    model.management = JSON.parse(JSON.stringify(submitModel.management))
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the management collection data.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}
</script>
