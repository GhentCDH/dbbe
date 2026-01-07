<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <panel header="Edit roles">
        <editListRow
            :schema="schema"
            :model="model"
            name="origin"
            :conditions="{
            add: true,
            edit: model.role,
            del: model.role,
          }"
            @add="edit(true)"
            @edit="edit()"
            @del="del()"
        />
      </panel>
      <div class="loading-overlay" v-if="openRequests > 0">
        <div class="spinner" />
      </div>
    </article>

    <Edit
        :show="editModalValue"
        :schema="editSchema"
        :submit-model="submitModel"
        :original-submit-model="originalSubmitModel"
        :alerts="editAlerts"
        @cancel="() => {
          cancelEdit(submitModel);
          $nextTick(() => editRef?.validate());
        }"
        @reset="() => {
          resetEdit(submitModel);
          $nextTick(() => editRef?.validate());
        }"
        @confirm="submitEdit"
        @dismiss-alert="editAlerts.splice($event, 1)"
        ref="editRef"
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
import { ref, reactive, watch, computed } from 'vue'
import axios from 'axios'
import VueFormGenerator from 'vue3-form-generator-legacy'
import Alerts from '@/components/Alerts.vue'
import Panel from '@/components/Edit/Panel.vue'
import Edit from '@/components/Edit/Modals/Edit.vue'
import Delete from '@/components/Edit/Modals/Delete.vue'
import EditListRow from '@/components/Edit/EditListRow.vue'

import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete'
import { isLoginError } from '@/helpers/errorUtil'
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'

const props = defineProps({
  initUrls: String,
  initData: String
})
const depUrls = computed(() => ({}))

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
} = useEditMergeMigrateDelete(props.initUrls, props.initData, depUrls)

VueFormGenerator.validators.requiredMultiSelect = function (value) {
  return value && value.length > 0 ? [] : ['This field is required!']
}

const schema = reactive({
  fields: [
    {
      type: 'multiselect',
      label: 'Role',
      model: 'role',
      values: [], // will be set below
      multiSelect: {
        multiple: false
      }
    }
  ]
})


const editSchema = reactive({
  fields: {
    usage: createMultiSelect(
        'Usage',
        {
          model: 'role.usage',
          values: [
            { id: 'article', name: 'Article' },
            { id: 'book', name: 'Book' },
            { id: 'bookChapter', name: 'Book chapter' },
            { id: 'manuscript', name: 'Manuscript' },
            { id: 'occurrence', name: 'Occurrence' },
            { id: 'type', name: 'Type' }
          ],
          required: true,
          validator: VueFormGenerator.validators.requiredMultiSelect
        },
        {
          multiple: true,
          closeOnSelect: false
        }
    ),
    systemName: {
      type: 'input',
      inputType: 'text',
      label: 'System name',
      model: 'role.systemName',
      required: true,
      validator: VueFormGenerator.validators.regexp,
      pattern: '^[a-z_]+$',
      hint: 'Only use lowercase letters and underscores'
    },
    name: {
      type: 'input',
      inputType: 'text',
      label: 'Role name',
      model: 'role.name',
      required: true,
      validator: VueFormGenerator.validators.string
    },
    contributorRole: {
      type: 'checkbox',
      label: 'Acknowledge contributor role',
      model: 'role.contributorRole'
    },
    rank: {
      type: 'checkbox',
      label: 'For this role, the order of persons is important',
      model: 'role.rank'
    }
  }
})

const model = reactive({ role: null })

const submitModel = reactive({
  submitType: 'role',
  role: null
})

watch(values, (newValues) => {
  schema.fields[0].values = Array.isArray(newValues) ? newValues : []
}, { immediate: true })

function edit(add = false) {
  if (add) {
    submitModel.role = {
      usage: [],
      name: null,
      systemName: '',
      contributorRole: false,
      rank: false
    }
    editSchema.fields.systemName.disabled = false
    editSchema.fields.contributorRole.disabled = false
    editSchema.fields.rank.disabled = false
  } else {
    submitModel.role = JSON.parse(JSON.stringify(model.role))
    submitModel.role.usage = submitModel.role.usage.map(id =>
        editSchema.fields.usage.values.find(v => v.id === id)
    )
    editSchema.fields.systemName.disabled = true
    editSchema.fields.contributorRole.disabled = true
    editSchema.fields.rank.disabled = true
  }
  enableField(editSchema.fields.usage)
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function del() {
  submitModel.role = model.role
  deleteDependencies()
}

function update() {
  openRequests.value++
  axios.get(urls.roles_get)
      .then(res => {
        values.value.splice(0, values.value.length, ...res.data)
        schema.fields[0].values = values
        model.role = JSON.parse(JSON.stringify(submitModel.role))
      })
      .catch(error => {
        alerts.value.push({
          type: 'error',
          message: 'Something went wrong while renewing the role data.',
          login: isLoginError(error)
        })
        console.error(error)
      })
      .finally(() => openRequests.value--)
}

async function submitEdit() {
  editModalValue.value = false
  openRequests.value++

  const payload = {
    name: submitModel.role.name,
    usage: submitModel.role.usage?.map(i => i.id),
    systemName: submitModel.role.systemName,
    contributorRole: submitModel.role.contributorRole,
    rank: submitModel.role.rank
  }

  try {
    let res
    if (!submitModel.role.id) {
      res = await axios.post(urls.role_post, payload)
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      const changes = {}
      if (submitModel.role.name !== originalSubmitModel.role.name) changes.name = payload.name
      if (JSON.stringify(payload.usage) !== JSON.stringify(originalSubmitModel.role.usage.map(i => i.id))) {
        changes.usage = payload.usage
      }
      res = await axios.put(urls.role_put.replace('role_id', submitModel.role.id), changes)
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
    submitModel.role = res.data
    update()
    editAlerts.value.splice(0)
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message: submitModel.role.id ? 'Something went wrong while updating the role.' : 'Something went wrong while adding the role.',
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
    await axios.delete(urls.role_delete.replace('role_id', submitModel.role.id))
    submitModel.role = null
    update()
    deleteAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the role.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}
</script>
