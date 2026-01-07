<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <Alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <Panel header="Edit (self) designations">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="selfDesignation"
                    :conditions="{
                        add: true,
                        edit: model.selfDesignation,
                        merge: model.selfDesignation,
                        del: model.selfDesignation,
                    }"
                    @add="edit(true)"
                    @edit="edit()"
                    @merge="merge()"
                    @del="del()"
                />
            </Panel>
            <div
                v-if="openRequests"
                class="loading-overlay"
            >
                <div class="spinner" />
            </div>
        </article>
        <Edit
            :show="editModalValue"
            :schema="editSchema"
            :submit-model="submitModel"
            :original-submit-model="originalSubmitModel"
            :alerts="editAlerts"
            @cancel="cancelEdit(submitModel)"
            @reset="resetEdit(submitModel)"
            @confirm="submitEdit()"
            @dismiss-alert="editAlerts.splice($event, 1)"
            ref="editRef"
        />
        <Merge
            :show="mergeModal"
            :schema="mergeSchema"
            :merge-model="mergeModel"
            :original-merge-model="originalMergeModel"
            :alerts="mergeAlerts"
            @cancel="cancelMerge()"
            @reset="resetMerge(mergeModel)"
            @confirm="submitMerge()"
            @dismiss-alert="mergeAlerts.splice($event, 1)"
        >
          <template #preview>
          <table
                v-if="mergeModel.primary && mergeModel.secondary"
                class="table table-striped table-hover"
            >
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Title</td>
                        <td>{{ mergeModel.primary.name }}</td>
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
            @cancel="cancelDelete()"
            @confirm="submitDelete()"
            @dismiss-alert="deleteAlerts.splice($event, 1)"
        />
    </div>
</template>

<script setup>
import { reactive, watch, onMounted,ref, nextTick } from 'vue'
import axios from 'axios'

import Edit from '@/components/Edit/Modals/Edit.vue'
import Delete from '@/components/Edit/Modals/Delete.vue'
import Merge from '@/components/Edit/Modals/Merge.vue'
import Panel from '@/components/Edit/Panel.vue'
import Alerts from '@/components/Alerts.vue'
import EditListRow from '@/components/Edit/EditListRow.vue'
import { isLoginError } from '@/helpers/errorUtil'
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'
import VueFormGenerator from 'vue3-form-generator-legacy'
import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete'
import { removeGreekAccents } from '@/helpers/formFieldUtils'
import validatorUtil from "@/helpers/validatorUtil";
const editRef = ref(null)
const props = defineProps({
  initUrls: {
    type: String,
    required: true,
  },
  initData: {
    type: String,
    required: true,
  },
})

const depUrls = {
  Persons: {
    depUrl: '',
    url: '',
    urlIdentifier: '',
  }
}

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
  deleteDependencies,
  cancelEdit,
  cancelMerge,
  cancelDelete,
  resetEdit,
  resetMerge,
} = useEditMergeMigrateDelete(props.initUrls, props.initData, depUrls)

const schema = reactive({
  fields: {
    selfDesignation: createMultiSelect(
        '(Self) designation',
        {
          model: 'selfDesignation',
          styleClasses: 'greek',
        },
        {
          customLabel: ({ id, name }) => `${id} - ${name}`,
          internalSearch: false,
          onSearch: greekSearch,
        }
    ),
  },
})

const editSchema = reactive({
  fields: [
    {
      type: 'input',
      inputType: 'text',
      label: 'Name',
      labelClasses: 'control-label',
      model: 'selfDesignation.name',
      required: true,
      validator: validatorUtil.regexp,
      pattern: '^[\\u0370-\\u03ff\\u1f00-\\u1fff ]+$',
    },
  ],
})

const mergeSchema = reactive({
  fields: {
    primary: createMultiSelect(
        'Primary',
        {
          styleClasses: 'greek',
        },
        {
          customLabel: ({ id, name }) => `${id} - ${name}`,
          internalSearch: false,
          onSearch: greekSearchPrimary,
        }
    ),
    secondary: createMultiSelect(
        'Secondary',
        {
          styleClasses: 'greek',
        },
        {
          customLabel: ({ id, name }) => `${id} - ${name}`,
          internalSearch: false,
          onSearch: greekSearchSecondary,
        }
    ),
  },
})

const model = reactive({
  selfDesignation: null,
})

const submitModel = reactive({
  submitType: 'selfDesignation',
  selfDesignation: {
    id: null,
    name: null,
  },
})

const mergeModel = reactive({
  submitType: 'selfDesignations',
  primary: null,
  secondary: null,
})

watch(values, (newValues) => {
  schema.fields.selfDesignation.values = Array.isArray(newValues) ? newValues : []
  mergeSchema.fields.primary.values = schema.fields.selfDesignation.values
  mergeSchema.fields.secondary.values = schema.fields.selfDesignation.values
}, { immediate: true })

watch(originalSubmitModel, (newVal) => {
  Object.assign(submitModel.selfDesignation, newVal.selfDesignation)
})

watch(values, () => {
  enableField(schema.fields.selfDesignation)
  enableField(mergeSchema.fields.primary)
  enableField(mergeSchema.fields.secondary)
})

onMounted(() => {
  schema.fields.selfDesignation.values = values.value || []
  mergeSchema.fields.primary.values = values.value || []
  mergeSchema.fields.secondary.values = values.value || []
  enableField(schema.fields.selfDesignation, model)
  enableField(mergeSchema.fields.primary)
  enableField(mergeSchema.fields.secondary)
})

function edit(add = false) {
  if (add) {
    submitModel.selfDesignation = { id: null, name: null }
  } else {
    submitModel.selfDesignation = JSON.parse(JSON.stringify(model.selfDesignation))
  }
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
  nextTick(() => {
    editRef.value?.validate()
  })
}

function merge() {
  mergeModel.primary = JSON.parse(JSON.stringify(model.selfDesignation))
  mergeModel.secondary = null
  mergeSchema.fields.primary.values = schema.fields.selfDesignation.values
  mergeSchema.fields.secondary.values = schema.fields.selfDesignation.values
  enableField(mergeSchema.fields.primary)
  enableField(mergeSchema.fields.secondary)
  Object.assign(originalMergeModel, JSON.parse(JSON.stringify(mergeModel)))
  mergeModal.value = true
}

function del() {
  submitModel.selfDesignation = model.selfDesignation
  depUrls.Persons.depUrl = urls.person_deps_by_self_designation.replace('self_designation_id', submitModel.selfDesignation.id)
  deleteDependencies()
}

async function update() {
  openRequests.value++
  try {
    const response = await axios.get(urls.self_designations_get)
    if (Array.isArray(values.value)) {
      values.value.splice(0, values.value.length, ...response.data)
    }
    schema.fields.selfDesignation.values = values.value
    model.selfDesignation = JSON.parse(JSON.stringify(submitModel.selfDesignation))
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the (self) designation data.',
      login: isLoginError(error),
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
    if (submitModel.selfDesignation.id == null) {
      response = await axios.post(urls.self_designation_post, {
        name: submitModel.selfDesignation.name,
      })
      submitModel.selfDesignation = response.data
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      response = await axios.put(
          urls.self_designation_put.replace('self_designation_id', submitModel.selfDesignation.id),
          { name: submitModel.selfDesignation.name }
      )
      submitModel.selfDesignation = response.data
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
    await update()
    editAlerts.value.splice(0)
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message:
          submitModel.selfDesignation.id == null
              ? 'Something went wrong while adding the (self) designation.'
              : 'Something went wrong while updating the (self) designation.',
      login: isLoginError(error),
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
        urls.self_designation_merge
            .replace('primary_id', mergeModel.primary.id)
            .replace('secondary_id', mergeModel.secondary.id)
    )
    submitModel.selfDesignation = response.data
    await update()
    mergeAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Merge successful.' })
  } catch (error) {
    mergeModal.value = true
    mergeAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while merging the self designations.',
      login: isLoginError(error),
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
    await axios.delete(urls.self_designation_delete.replace('self_designation_id', submitModel.selfDesignation.id))
    submitModel.selfDesignation = null
    await update()
    deleteAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the (self) designation.',
      login: isLoginError(error),
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

function greekSearch(searchQuery) {
  schema.fields.selfDesignation.values = schema.fields.selfDesignation.originalValues.filter(
      option => removeGreekAccents(`${option.id} - ${option.name}`).includes(removeGreekAccents(searchQuery))
  )
}
function greekSearchPrimary(searchQuery) {
  mergeSchema.fields.primary.values = schema.fields.selfDesignation.originalValues.filter(
      option => removeGreekAccents(`${option.id} - ${option.name}`).includes(removeGreekAccents(searchQuery))
  )
}
function greekSearchSecondary(searchQuery) {
  mergeSchema.fields.secondary.values = schema.fields.selfDesignation.originalValues.filter(
      option => removeGreekAccents(`${option.id} - ${option.name}`).includes(removeGreekAccents(searchQuery))
  )
}

</script>
