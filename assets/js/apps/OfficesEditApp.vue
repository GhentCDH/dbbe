<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <Panel header="Edit offices">
        <EditListRow
            :schema="schema"
            :model="model"
            name="office"
            :conditions="{
            add: true,
            edit: model.office,
            merge: model.office,
            del: model.office,
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
        ref="editRef"
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
      <table
          v-if="mergeModel.primary && mergeModel.secondary"
          slot="preview"
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
          <td>Name</td>
          <td>{{ mergeModel.primary.name || mergeModel.secondary.name }}</td>
        </tr>
        </tbody>
      </table>
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
import { reactive, computed, watch, onMounted, ref, nextTick } from 'vue'
import axios from 'axios'
import VueFormGenerator from 'vue-form-generator'

import Edit from '@/components/Edit/Modals/Edit.vue'
import Merge from '@/components/Edit/Modals/Merge.vue'
import Delete from '@/components/Edit/Modals/Delete.vue'
import Panel from '@/components/Edit/Panel.vue'
import Alerts from '@/components/Alerts.vue'
import EditListRow from '@/components/Edit/EditListRow.vue'
import { isLoginError } from '@/helpers/errorUtil'
import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete'
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'
import validatorUtil from "@/helpers/validatorUtil";

const props = defineProps({
  initUrls: {
    type: String
  },
  initData: {
    type: String
  }
})

const depUrls = computed(() => ({
  'Offices': {
    depUrl: urls.office_deps_by_office.replace('office_id', submitModel.office?.id || ''),
  },
  'Persons': {
    depUrl: urls.person_deps_by_office.replace('office_id', submitModel.office?.id || ''),
    url: urls.person_get,
    urlIdentifier: 'person_id',
  }
}))

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
  originalMergeModel,
  originalSubmitModel,
  openRequests,
  deleteDependencies,
  cancelEdit,
  cancelMerge,
  cancelDelete,
  resetEdit,
  resetMerge,
  isOrIsChild
} = useEditMergeMigrateDelete(props.initUrls, props.initData, depUrls)

// Form schemas
const schema = reactive({
  fields: {
    office: createMultiSelect('Office')
  }
})

const editSchema = reactive({
  fields: {
    individualName: {
      type: 'input',
      inputType: 'text',
      label: 'Office name',
      labelClasses: 'control-label',
      model: 'office.individualName',
      validator: [validatorUtil.string, nameOrRegionWithParents, uniqueName],
    },
    individualRegionWithParents: createMultiSelect(
        'Region',
        {
          model: 'office.individualRegionWithParents',
          validator: [nameOrRegionWithParents, uniqueRegionWithParents]
        }
    ),
    parent: createMultiSelect('Parent', { model: 'office.parent' }),
  }
})

const mergeSchema = reactive({
  fields: {
    primary: createMultiSelect('Primary', { required: true, validator: validatorUtil.required }),
    secondary: createMultiSelect('Secondary', { required: true, validator: validatorUtil.required }),
  }
})

// Models
const model = reactive({
  office: null
})

const submitModel = reactive({
  submitType: 'office',
  office: null
})

const mergeModel = reactive({
  submitType: 'offices',
  primary: null,
  secondary: null
})

// Refs
const editRef = ref(null)
const revalidate = ref(false)
const isUpdating = ref(false) // Prevent recursive updates

const data = JSON.parse(props.initData)
const regions = ref(data.regions)
const offices = ref(data.offices)

// Ensure values is properly initialized
if (!values.value) {
  values.value = offices.value
}

// Watchers with guards to prevent infinite loops
watch(() => model.office, (newOffice, oldOffice) => {
  // Prevent unnecessary updates if values are the same
  if (JSON.stringify(newOffice) === JSON.stringify(oldOffice)) {
    return
  }

  // Set full parent, so the name can be formatted correctly
  if (newOffice != null && newOffice.parent != null) {
    const safeValues = Array.isArray(values.value) ? values.value : Object.values(values.value || {})
    const fullParent = safeValues.find((officeWithParents) => officeWithParents.id === newOffice.parent.id)
    if (fullParent && JSON.stringify(newOffice.parent) !== JSON.stringify(fullParent)) {
      model.office.parent = fullParent
    }
  }
})

watch(() => submitModel.office?.individualName, (newName, oldName) => {
  // Prevent unnecessary updates
  if (newName === oldName) return

  if (newName === '' && originalSubmitModel.office?.individualName == null) {
    submitModel.office.individualName = null
  }
})

watch(values, (newValues, oldValues) => {
  // Prevent update during our own update process
  if (isUpdating.value) return

  // Only update if values actually changed
  if (JSON.stringify(newValues) === JSON.stringify(oldValues)) return

  const safeValues = Array.isArray(newValues) ? newValues : Object.values(newValues || {})
  schema.fields.office.values = safeValues
}, { immediate: true, deep: true })

onMounted(() => {
  // Ensure values are set and schema is initialized
  if (!values.value || values.value.length === 0) {
    values.value = offices.value
  }

  const safeValues = Array.isArray(values.value) ? values.value : Object.values(values.value || {})
  schema.fields.office.values = safeValues
  enableField(schema.fields.office, model)

  // Force reactivity update if needed
  if (schema.fields.office.values.length === 0 && offices.value.length > 0) {
    schema.fields.office.values = [...offices.value]
  }
})

function edit(add = false) {
  submitModel.submitType = 'office'
  submitModel.office = null

  if (add) {
    submitModel.office = {
      id: null,
      individualName: null,
      individualRegionWithParents: null,
      parent: model.office,
    }
  } else {
    submitModel.office = JSON.parse(JSON.stringify(model.office))
  }

  editSchema.fields.individualRegionWithParents.values = regions.value
  enableField(editSchema.fields.individualRegionWithParents)

  const safeValues = Array.isArray(values.value) ? values.value : Object.values(values.value || {})
  editSchema.fields.parent.values = safeValues
      .filter((office) => !isOrIsChild(office, model.office))
  enableField(editSchema.fields.parent)

  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function merge() {
  mergeModel.primary = JSON.parse(JSON.stringify(model.office))
  mergeModel.secondary = null

  const safeValues = Array.isArray(values.value) ? values.value : Object.values(values.value || {})
  mergeSchema.fields.primary.values = safeValues
  mergeSchema.fields.secondary.values = safeValues
  enableField(mergeSchema.fields.primary)
  enableField(mergeSchema.fields.secondary)

  Object.assign(originalMergeModel, JSON.parse(JSON.stringify(mergeModel)))
  mergeModal.value = true
}

function del() {
  if (!submitModel.office) return
  submitModel.office = JSON.parse(JSON.stringify(model.office))
  deleteDependencies()
}

async function submitEdit() {
  if (openRequests.value > 0) return // Prevent duplicate submissions

  editModalValue.value = false
  openRequests.value++

  try {
    let response
    if (submitModel.office.id == null) {
      response = await axios.post(urls.office_post, {
        parent: submitModel.office.parent == null ? null : {
          id: submitModel.office.parent.id,
        },
        individualName: submitModel.office.individualName,
        individualRegionWithParents: submitModel.office.individualRegionWithParents == null ? null : {
          id: submitModel.office.individualRegionWithParents.id,
        },
      })
      submitModel.office = response.data
      await update()
      editAlerts.value.splice(0)
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      let data = {}
      if (JSON.stringify(submitModel.office.parent) !== JSON.stringify(originalSubmitModel.office.parent)) {
        data.parent = submitModel.office.parent == null ? null : {
          id: submitModel.office.parent.id
        }
      }
      if (submitModel.office.individualName !== originalSubmitModel.office.individualName) {
        data.individualName = submitModel.office.individualName
      }
      if (JSON.stringify(submitModel.office.individualRegionWithParents) !== JSON.stringify(originalSubmitModel.office.individualRegionWithParents)) {
        data.individualRegionWithParents = submitModel.office.individualRegionWithParents == null ? null : {
          id: submitModel.office.individualRegionWithParents.id
        }
      }

      response = await axios.put(urls.office_put.replace('office_id', submitModel.office.id), data)
      submitModel.office = response.data
      await update()
      editAlerts.value.splice(0)
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message: submitModel.office.id == null
          ? 'Something went wrong while adding the office.'
          : 'Something went wrong while updating the office.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function submitMerge() {
  if (openRequests.value > 0) return // Prevent duplicate submissions

  mergeModal.value = false
  openRequests.value++

  try {
    const response = await axios.put(
        urls.office_merge
            .replace('primary_id', mergeModel.primary.id)
            .replace('secondary_id', mergeModel.secondary.id)
    )
    submitModel.office = response.data
    await update()
    mergeAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Merge successful.' })
  } catch (error) {
    mergeModal.value = true
    mergeAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while merging the office.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function submitDelete() {
  if (openRequests.value > 0) return // Prevent duplicate submissions

  deleteModal.value = false
  openRequests.value++

  try {
    await axios.delete(urls.office_delete.replace('office_id', submitModel.office.id))
    submitModel.office = null
    await update()
    deleteAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the office.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function update() {
  if (isUpdating.value) return // Prevent recursive updates

  isUpdating.value = true
  openRequests.value++

  try {
    const response = await axios.get(urls.offices_get)
    const newData = response.data

    // Only update if data actually changed
    const currentData = Array.isArray(values.value) ? values.value : Object.values(values.value || {})
    if (JSON.stringify(currentData) !== JSON.stringify(newData)) {
      if (Array.isArray(values.value)) {
        values.value.splice(0, values.value.length, ...newData)
      } else {
        Object.assign(values.value, newData)
      }

      const safeValues = Array.isArray(values.value) ? values.value : Object.values(values.value || {})
      schema.fields.office.values = safeValues

      // Only update model.office if submitModel.office exists and changed
      if (submitModel.office) {
        const updatedOffice = safeValues.find(office => office.id === submitModel.office.id)
        if (updatedOffice && JSON.stringify(model.office) !== JSON.stringify(updatedOffice)) {
          model.office = JSON.parse(JSON.stringify(updatedOffice))
        }
      }
    }
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the office data.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
    isUpdating.value = false
  }
}

function nameOrRegionWithParents(value, field, model) {
  const name = model.office?.individualName
  const region = model.office?.individualRegionWithParents

  const hasName = name != null && name !== ''
  const hasRegion = region != null

  if ((hasName && hasRegion) || (!hasName && !hasRegion)) {
    return ['Exactly one of the fields "Office Name" or "Region" must be filled.']
  }

  return []
}

function uniqueName(value, field, model) {
  if (value == null) {
    return []
  }

  let id = model.office?.id
  let name = value
  if (model.office?.parent != null) {
    name = model.office.parent.name + ' > ' + name
  }

  const safeValues = Array.isArray(values.value) ? values.value : Object.values(values.value || {})
  if (safeValues.filter((value) => value.id !== id && value.name === name).length > 0) {
    return ['An office with this name already exists.']
  }

  return []
}

function uniqueRegionWithParents(value, field, model) {
  if (value == null) {
    return []
  }

  let id = model.office?.id
  let name = ' of ' + value.name.split(' > ').reverse().join(' < ')
  if (model.office?.parent != null) {
    name = model.office.parent.name + name
  }

  const safeValues = Array.isArray(values.value) ? values.value : Object.values(values.value || {})
  if (safeValues.filter((value) => value.id !== id && value.name === name).length > 0) {
    return ['An office with this region already exists.']
  }

  return []
}
</script>