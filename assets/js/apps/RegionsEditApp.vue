<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts :alerts="alerts" @dismiss="alerts.splice($event, 1)" />

      <Panel header="Edit regions">
        <EditListRow
            :schema="regionSchema"
            :model="model"
            name="region"
            :conditions="{
            add: true,
            edit: model.region,
            merge: model.region,
            del: model.region,
          }"
            @add="() => editRegion(true)"
            @edit="editRegion"
            @merge="mergeRegion"
            @del="delRegion"
        />
      </Panel>

      <div v-if="model.region" class="panel panel-default">
        <div class="panel-heading">Additional region information</div>
        <div class="panel-body">
          <table class="table table-striped table-hover">
            <thead>
            <tr>
              <th>Field</th>
              <th>Value</th>
            </tr>
            </thead>
            <tbody>
            <tr>
              <td>Historical name</td>
              <td>{{ model.region.historicalName }}</td>
            </tr>
            <tr>
              <td>Pleiades</td>
              <td>{{ model.region.pleiades }}</td>
            </tr>
            <tr>
              <td>Is city</td>
              <td>{{ model.region.isCity ? 'Yes' : 'No' }}</td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="loading-overlay" v-if="openRequests > 0">
        <div class="spinner" />
      </div>
    </article>

    <Edit
        :show="editModalValue"
        :schema="editRegionSchema"
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
        :schema="mergeRegionSchema"
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
          <tr>
            <td>Historical name</td>
            <td>{{ mergeHistoricalName }}</td>
          </tr>
          <tr>
            <td>Pleiades</td>
            <td>{{ mergeModel.primary.pleiades || mergeModel.secondary.pleiades }}</td>
          </tr>
          <tr>
            <td>Is city</td>
            <td>{{ mergeModel.primary.isCity ? 'Yes' : 'No' }}</td>
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
import { reactive, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import VueFormGenerator from 'vue-form-generator'
import { isLoginError } from '@/helpers/errorUtil'
import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete'
import Alerts from '@/components/Alerts.vue'
import Panel from '@/components/Edit/Panel.vue'
import EditListRow from '@/components/Edit/EditListRow.vue'

import Edit from '@/components/Edit/Modals/Edit.vue'
import Merge from '@/components/Edit/Modals/Merge.vue'
import Delete from '@/components/Edit/Modals/Delete.vue'

import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'

const props = defineProps({
  initUrls: String,
  initData: String
})

const model = reactive({ region: null })
const submitModel = reactive({
  submitType: 'region',
  region: {
    id: null,
    parent: null,
    individualName: null,
    individualHistoricalName: null,
    pleiades: null,
    isCity: null,
  }
})
const mergeModel = reactive({
  submitType: 'regions',
  primary: null,
  secondary: null,
})

const depUrls = computed(() => {
  const regionId = submitModel.region?.id;
  if (!regionId) return {};

  return {
    'Manuscripts': {
      depUrl: urls['manuscript_deps_by_region']?.replace('region_id', regionId) || '',
      url: urls['manuscript_get'],
      urlIdentifier: 'manuscript_id',
    },
    'Institutions': {
      depUrl: urls['institution_deps_by_region']?.replace('region_id', regionId) || '',
    },
    'Offices': {
      depUrl: urls['office_deps_by_region']?.replace('region_id', regionId) || '',
    },
    'Persons': {
      depUrl: urls['person_deps_by_region']?.replace('region_id', regionId) || '',
      url: urls['person_get'],
      urlIdentifier: 'person_id',
    },
    'Regions': {
      depUrl: urls['region_deps_by_region']?.replace('region_id', regionId) || '',
    }
  }
})

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
  resetEdit,
  resetMerge,
  deleteDependencies,
  cancelEdit,
  cancelMerge,
  cancelDelete,
  isOrIsChild,
} = useEditMergeMigrateDelete(props.initUrls, props.initData, depUrls)

const regionSchema = reactive({
  fields: {
    region: createMultiSelect('Region', null, { customLabel: formatNameHistoricalName }),
  }
})

const editRegionSchema = reactive({
  fields: {
    parent: createMultiSelect('Parent', { model: 'region.parent' }, { customLabel: formatNameHistoricalName }),
    individualName: {
      type: 'input',
      inputType: 'text',
      label: 'Region name',
      labelClasses: 'control-label',
      model: 'region.individualName',
      required: true,
      validator: VueFormGenerator.validators.string,
    },
    individualHistoricalName: {
      type: 'input',
      inputType: 'text',
      label: 'Historical name',
      labelClasses: 'control-label',
      model: 'region.individualHistoricalName',
      validator: VueFormGenerator.validators.string,
    },
    pleiades: {
      type: 'input',
      inputType: 'number',
      label: 'Pleiades Identifier',
      labelClasses: 'control-label',
      model: 'region.pleiades',
      validator: VueFormGenerator.validators.number,
    },
    city: {
      type: 'checkbox',
      styleClasses: 'has-warning',
      label: 'Is this region a city?',
      labelClasses: 'control-label',
      model: 'region.isCity',
    },
  }
})

const mergeRegionSchema = reactive({
  fields: {
    primary: createMultiSelect('Primary', { required: true, validator: VueFormGenerator.validators.required }, { customLabel: formatNameHistoricalName }),
    secondary: createMultiSelect('Secondary', { required: true, validator: VueFormGenerator.validators.required }, { customLabel: formatNameHistoricalName }),
  }
})

// Computed for merged historical name logic
const mergeHistoricalName = computed(() => {
  if (
      (mergeModel.primary?.individualHistoricalName) ||
      !mergeModel.secondary?.individualHistoricalName
  ) {
    return mergeModel.primary?.historicalName || ''
  } else {
    const primaryHistorical = mergeModel.primary?.historicalName || ''
    const lastIndex = primaryHistorical.lastIndexOf('>')
    if (lastIndex !== -1) {
      return primaryHistorical.substring(0, lastIndex) + '> ' + mergeModel.secondary.individualHistoricalName
    }
    return mergeModel.secondary.individualHistoricalName
  }
})

watch(() => model.region, (newRegion) => {
  if (newRegion && newRegion.parent) {
    model.region.parent = values.value.find(regionWithParents => regionWithParents.id === newRegion.parent.id) || newRegion.parent
  }
})

// Methods
function formatNameHistoricalName(regionWithParents) {
  return regionWithParents.name !== '' ? regionWithParents.name : '[' + regionWithParents.historicalName + ']'
}

function editRegion(add = false) {
  if (add) {
    submitModel.region = {
      id: null,
      name: null,
      parent: model.region,
      individualName: null,
      individualHistoricalName: null,
      pleiades: null,
      isCity: null,
    }
  } else {
    submitModel.region = JSON.parse(JSON.stringify(model.region))
  }
  editRegionSchema.fields.parent.values = values.value.filter(region => !isOrIsChild(region, model.region))
  enableField(editRegionSchema.fields.parent)
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function mergeRegion() {
  mergeModel.primary = JSON.parse(JSON.stringify(model.region))
  mergeModel.secondary = null
  mergeRegionSchema.fields.primary.values = values.value
  mergeRegionSchema.fields.secondary.values = values.value
  enableField(mergeRegionSchema.fields.primary)
  enableField(mergeRegionSchema.fields.secondary)
  Object.assign(originalMergeModel, JSON.parse(JSON.stringify(mergeModel)))
  mergeModal.value = true
}

function delRegion() {
  if (!model.region) {
    alerts.value.push({ type: 'error', message: 'No region selected for deletion.' });
    return;
  }

  submitModel.region = JSON.parse(JSON.stringify(model.region))
  deleteDependencies()
}

async function submitEdit() {
  editModalValue.value = false
  openRequests.value++
  try {
    if (!submitModel.region.id) {
      const response = await axios.post(urls.region_post, {
        parent: submitModel.region.parent ? { id: submitModel.region.parent.id } : null,
        individualName: submitModel.region.individualName,
        individualHistoricalName: submitModel.region.individualHistoricalName,
        pleiades: submitModel.region.pleiades,
        isCity: submitModel.region.isCity,
      })
      Object.assign(submitModel.region, response.data)
      await update()
      editAlerts.value = []
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      const data = {}
      if (JSON.stringify(submitModel.region.parent) !== JSON.stringify(originalSubmitModel.region.parent)) {
        data.parent = submitModel.region.parent ? { id: submitModel.region.parent.id } : null
      }
      for (const key of ['individualName', 'individualHistoricalName', 'pleiades', 'isCity']) {
        if (submitModel.region[key] !== originalSubmitModel.region[key]) {
          data[key] = submitModel.region[key]
        }
      }
      const response = await axios.put(urls.region_put.replace('region_id', submitModel.region.id), data)
      Object.assign(submitModel.region, response.data)
      await update()
      editAlerts.value = []
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({ type: 'error', message: 'Something went wrong while saving the region.', login: isLoginError(error) })
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
        urls.region_merge.replace('primary_id', mergeModel.primary.id).replace('secondary_id', mergeModel.secondary.id)
    )
    Object.assign(submitModel.region, response.data)
    await update()
    mergeAlerts.value = []
    alerts.value.push({ type: 'success', message: 'Merge successful.' })
  } catch (error) {
    mergeModal.value = true
    mergeAlerts.value.push({ type: 'error', message: 'Something went wrong while merging the regions.', login: isLoginError(error) })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function submitDelete() {
  deleteModal.value = false
  openRequests.value++
  try {
    await axios.delete(urls.region_delete.replace('region_id', submitModel.region.id))

    submitModel.region = {
      id: null,
      parent: null,
      individualName: null,
      individualHistoricalName: null,
      pleiades: null,
      isCity: null,
    }
    model.region = null

    await update()
    deleteAlerts.value = []
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({ type: 'error', message: 'Something went wrong while deleting the region.', login: isLoginError(error) })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function update() {
  openRequests.value++
  try {
    const response = await axios.get(urls.regions_get)
    values.value = response.data
    regionSchema.fields.region.values = values.value
    if (submitModel.region?.id) {
      model.region = JSON.parse(JSON.stringify(submitModel.region))
    }
  } catch (error) {
    alerts.value.push({ type: 'error', message: 'Something went wrong while renewing the region data.', login: isLoginError(error) })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

onMounted(() => {
  regionSchema.fields.region.values = values.value
  enableField(regionSchema.fields.region)
})
</script>