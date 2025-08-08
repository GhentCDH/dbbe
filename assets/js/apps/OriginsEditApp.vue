<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts :alerts="alerts" @dismiss="alerts.splice($event, 1)" />

      <Panel
          header="Edit origins"
          :links="[{ url: urls.regions_edit, text: 'Add, edit or delete regions' }]"
      >
        <EditListRow
            :schema="regionSchema"
            :model="model"
            name="origin"
            :conditions="{ edit: model.regionWithParents }"
            @edit="editRegion"
        />

        <EditListRow
            :schema="monasterySchema"
            :model="model"
            name="origin"
            :conditions="{
            add: model.regionWithParents,
            edit: model.institution,
            del: model.institution
          }"
            @add="editMonastery(true)"
            @edit="editMonastery()"
            @del="delMonastery"
        />
      </Panel>
      <div v-if="openRequests"
           class="loading-overlay">
        <div class="spinner" />
      </div>
    </article>
    <Edit
        :show="editModalValue"
        :schema="editSchema"
        :submit-model="submitModel"
        :original-submit-model="originalSubmitModel"
        :format-type="formatType"
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
        :format-type="formatType"
        :alerts="deleteAlerts"
        @cancel="cancelDelete"
        @confirm="submitDelete"
        @dismiss-alert="deleteAlerts.splice($event, 1)"
    />
  </div>
</template>

<script setup>
import { reactive, watch, onMounted, computed } from 'vue'
import axios from 'axios'
import VueFormGenerator from 'vue-form-generator'

import Edit from '@/Components/Edit/Modals/Edit.vue'
import Delete from '@/Components/Edit/Modals/Delete.vue'
import Panel from '@/Components/Edit/Panel.vue'
import Alerts from '@/Components/Alerts.vue'
import EditListRow from '@/Components/Edit/EditListRow.vue'

import {
  createMultiSelect,
  enableField,
  dependencyField,
  loadLocationField
} from '@/helpers/formFieldUtils'
import { isLoginError } from '@/helpers/errorUtil'
import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete'

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

const model = reactive({
  regionWithParents: null,
  institution: null
})

const submitModel = reactive({
  submitType: null,
  regionWithParents: null,
  institution: null
})

const regionSchema = reactive({
  fields: {
    region: createMultiSelect('Region', { model: 'regionWithParents' }, { customLabel: formatHistoricalName })
  }
})

const monasterySchema = reactive({
  fields: {
    monastery: createMultiSelect('Monastery', {
      model: 'institution',
      dependency: 'regionWithParents',
      dependencyName: 'region'
    })
  }
})

const editRegionSchema = reactive({
  fields: {
    individualHistoricalName: {
      type: 'input',
      inputType: 'text',
      label: 'Region name',
      labelClasses: 'control-label',
      model: 'regionWithParents.individualHistoricalName',
      required: true,
      validator: VueFormGenerator.validators.string
    }
  }
})

const editMonasterySchema = reactive({
  fields: {
    region: createMultiSelect('Region', { model: 'regionWithParents', required: true }, { customLabel: formatHistoricalName }),
    name: {
      type: 'input',
      inputType: 'text',
      label: 'Monastery name',
      labelClasses: 'control-label',
      model: 'institution.name',
      required: true,
      validator: VueFormGenerator.validators.string
    }
  }
})

const editSchema = computed(() => {
  return submitModel.submitType === 'regionWithParents' ? editRegionSchema : editMonasterySchema
})

watch(() => model.regionWithParents, () => {
  if (!model.regionWithParents) {
    dependencyField(monasterySchema.fields.monastery, model)
  } else {
    loadLocationField(monasterySchema.fields.monastery, model, values.value)
    enableField(monasterySchema.fields.monastery, model)
  }
})

onMounted(() => {
  loadLocationField(regionSchema.fields.region, model, values.value)
  enableField(regionSchema.fields.region, model)
  dependencyField(monasterySchema.fields.monastery, model)
})

function editRegion() {
  submitModel.submitType = 'regionWithParents'
  submitModel.regionWithParents = JSON.parse(JSON.stringify(model.regionWithParents))
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))

  editModalValue.value = true
}

function editMonastery(add = false) {
  submitModel.submitType = 'institution'
  submitModel.regionWithParents = JSON.parse(JSON.stringify(model.regionWithParents))
  submitModel.institution = add
      ? { id: null, name: '' }
      : JSON.parse(JSON.stringify(model.institution))

  loadLocationField(editMonasterySchema.fields.region, model, values.value)
  enableField(editMonasterySchema.fields.region, model)
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))

  editModalValue.value = true
}

function delMonastery() {
  submitModel.submitType = 'institution'
  submitModel.regionWithParents = JSON.parse(JSON.stringify(model.regionWithParents))
  submitModel.institution = JSON.parse(JSON.stringify(model.institution))
  deleteDependencies()
}

async function submitEdit() {
  editModalValue.value = false
  openRequests.value++

  let url = ''
  let data = {}

  try {
    if (submitModel.submitType === 'regionWithParents') {
      url = urls.region_put.replace('region_id', submitModel.regionWithParents.id)
      data = {
        individualHistoricalName: submitModel.regionWithParents.individualHistoricalName
      }
    } else if (submitModel.submitType === 'institution') {
      const { id, name } = submitModel.institution
      const regionId = submitModel.regionWithParents.id

      if (id == null) {
        url = urls.monastery_post
        data = { name, regionWithParents: { id: regionId } }
      } else {
        url = urls.monastery_put.replace('monastery_id', id)
        if (name !== originalSubmitModel.institution.name) {
          data.name = name
        }
        if (regionId !== originalSubmitModel.regionWithParents.id) {
          data.regionWithParents = { id: regionId }
        }
      }
    }

    const response = submitModel[submitModel.submitType].id == null
        ? await axios.post(url, data)
        : await axios.put(url, data)

    submitModel[submitModel.submitType] = response.data

    alerts.value.push({ type: 'success', message: submitModel[submitModel.submitType].id == null ? 'Addition successful.' : 'Update successful.' })
    await update()
    editAlerts.value = []
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message: `Something went wrong while ${submitModel[submitModel.submitType].id == null ? 'adding' : 'updating'} the ${formatType(submitModel.submitType)}.`,
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
    await axios.delete(urls.monastery_delete.replace('monastery_id', submitModel.institution.id))
    submitModel.institution = null
    await update()
    deleteAlerts.value = []
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: `Something went wrong while deleting the ${formatType(submitModel.submitType)}.`,
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
    const response = await axios.get(urls.origins_get)
    values.value = response.data
    if (submitModel.submitType === 'regionWithParents') {
      model.regionWithParents = JSON.parse(JSON.stringify(submitModel.regionWithParents))
      loadLocationField(regionSchema.fields.region, model, values.value)
    } else {
      model.regionWithParents = JSON.parse(JSON.stringify(submitModel.regionWithParents))
      model.institution = JSON.parse(JSON.stringify(submitModel.institution))
      loadLocationField(regionSchema.fields.region, model, values.value)
      loadLocationField(monasterySchema.fields.monastery, model, values.value)
      enableField(monasterySchema.fields.monastery, model)
    }
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the origin data.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

function formatType(type) {
  return type === 'regionWithParents' ? 'region' : type === 'institution' ? 'monastery' : type
}

function formatHistoricalName(regionWithParents) {
  return regionWithParents.historicalName
}
</script>
