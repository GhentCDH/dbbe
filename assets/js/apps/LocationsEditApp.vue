<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)" />
      <Panel
          header="Edit Locations"
          :links="[{
                    url: urls['regions_edit'],
                    text: 'Add, edit or delete cities (regions)',
                }]"
      >
        <EditListRow
            :schema="citySchema"
            :model="model"
            name="city"
            :conditions="{
                        edit: model.regionWithParents,
                    }"
            :titles="{edit: 'Edit the name of the selected city'}"
            @edit="editCity()" />
        <EditListRow
            :schema="librarySchema"
            :model="model"
            name="library"
            :conditions="{
                        add: model.regionWithParents,
                        edit: model.institution,
                        del: model.institution && collectionSchema.fields.collection.values.length === 0,
                    }"
            @add="editLibrary(true)"
            @edit="editLibrary()"
            @del="delLibrary()" />
        <EditListRow
            :schema="collectionSchema"
            :model="model"
            name="collection"
            :conditions="{
                        add: model.institution,
                        edit: model.collection,
                        del: model.collection,
                    }"
            @add="editCollection(true)"
            @edit="editCollection()"
            @del="delCollection()" />
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
        :format-type="formatType"
        :alerts="editAlerts"
        @cancel="cancelEdit()"
        @reset="resetEdit(submitModel)"
        @confirm="submitEdit()"
        @dismiss-alert="editAlerts.splice($event, 1)" />
    <Delete
        :show="deleteModal"
        :del-dependencies="delDependencies"
        :submit-model="submitModel"
        :format-type="formatType"
        :alerts="deleteAlerts"
        @cancel="cancelDelete()"
        @confirm="submitDelete()"
        @dismiss-alert="deleteAlerts.splice($event, 1)" />
  </div>
</template>

<script setup>
import { reactive, computed, watch, onMounted } from 'vue'
import VueFormGenerator from 'vue-form-generator'
import axios from 'axios'
import Panel from '@/Components/Edit/Panel.vue'

import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete'
import {
  createMultiSelect, dependencyField,
  enableField, loadLocationField
} from '@/helpers/formFieldUtils'
import { isLoginError } from "@/helpers/errorUtil"
import Edit from "@/Components/Edit/Modals/Edit.vue"
import Merge from "@/Components/Edit/Modals/Merge.vue"
import Delete from "@/Components/Edit/Modals/Delete.vue"
import EditListRow from '@/Components/Edit/EditListRow.vue'
import Alerts from '@/Components/Alerts.vue'

const props = defineProps({
  initUrls: {
    type: String,
    default: '{}'
  },
  initData: {
    type: String,
    default: '{}'
  }
})

const emit = defineEmits(['update'])

const model = reactive({
  regionWithParents: null,
  institution: null,
  collection: null,
})

const submitModel = reactive({
  submitType: null,
  regionWithParents: null,
  institution: null,
  collection: null,
})

const depUrls = computed(() => {
  let depUrls = {
    'Manuscripts': {
      url: urls['manuscript_get'],
      urlIdentifier: 'manuscript_id',
    }
  }
  if (submitModel.submitType === 'institution') {
    depUrls['Manuscripts']['depUrl'] = urls['manuscript_deps_by_institution'].replace('institution_id', submitModel.institution.id)
    depUrls['Manuscripts'] = {
      url: urls['online_source_get'],
      urlIdentifier: 'online_source_id',
      depUrl: urls['online_source_deps_by_institution'].replace('institution_id', submitModel.institution.id)
    }
  }
  else {
    depUrls['Manuscripts']['depUrl'] = urls['manuscript_deps_by_collection'].replace('collection_id', submitModel.collection.id)
  }
  return depUrls
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

const citySchema = reactive({
  fields: {
    city: createMultiSelect('City', {model: 'regionWithParents'}),
  }
})

const librarySchema = reactive({
  fields: {
    library: createMultiSelect('Library', {model: 'institution', dependency: 'regionWithParents', dependencyName: 'city'}),
  }
})

const collectionSchema = reactive({
  fields: {
    collection: createMultiSelect('Collection', {model: 'collection', dependency: 'institution', dependencyName: 'library'}),
  }
})

const editCitySchema = reactive({
  fields: {
    individualName: {
      type: 'input',
      inputType: 'text',
      label: 'City name',
      labelClasses: 'control-label',
      model: 'regionWithParents.individualName',
      required: true,
      validator: VueFormGenerator.validators.string,
    }
  }
})

const editLibrarySchema = reactive({
  fields: {
    city: createMultiSelect('City', {model: 'regionWithParents', required: true, validator: VueFormGenerator.validators.required}),
    name: {
      type: 'input',
      inputType: 'text',
      label: 'Library name',
      labelClasses: 'control-label',
      model: 'institution.name',
      required: true,
      validator: VueFormGenerator.validators.string,
    }
  }
})

const editCollectionSchema = reactive({
  fields: {
    city: createMultiSelect('City', {model: 'regionWithParents', required: true, validator: VueFormGenerator.validators.required}),
    library: createMultiSelect('Library', {model: 'institution', required: true, validator: VueFormGenerator.validators.required, dependency: 'regionWithParents', dependencyName: 'city'}),
    name: {
      type: 'input',
      inputType: 'text',
      label: 'Collection name',
      labelClasses: 'control-label',
      model: 'collection.name',
      required: true,
      validator: VueFormGenerator.validators.string,
    }
  }
})

const editSchema = computed(() => {
  switch (submitModel.submitType) {
    case 'regionWithParents':
      return editCitySchema
    case 'institution':
      return editLibrarySchema
    default:
      return editCollectionSchema
  }
})

watch(() => model.regionWithParents, (newVal) => {
  if (newVal == null) {
    dependencyField(librarySchema.fields.library, model)
  } else {
    loadLocationField(librarySchema.fields.library, model, values.value)
    enableField(librarySchema.fields.library, model)
  }
})

watch(() => submitModel.regionWithParents, (newVal) => {
  if (submitModel.submitType === 'collection') {
    if (newVal == null) {
      dependencyField(editCollectionSchema.fields.library, model)
    } else {
      loadLocationField(editCollectionSchema.fields.library, model, values.value)
      enableField(editCollectionSchema.fields.library, model)
    }
  }
})

watch(() => model.institution, (newVal) => {
  if (newVal == null) {
    dependencyField(collectionSchema.fields.collection, model)
  } else {
    loadLocationField(collectionSchema.fields.collection, model, values.value)
    enableField(collectionSchema.fields.collection, model)
  }
})

function editCity() {
  submitModel.submitType = 'regionWithParents'
  submitModel.regionWithParents = JSON.parse(JSON.stringify(model.regionWithParents))
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function editLibrary(add = false) {
  submitModel.submitType = 'institution'
  submitModel.regionWithParents = JSON.parse(JSON.stringify(model.regionWithParents))
  if (add) {
    submitModel.institution = {
      id: null,
      name: '',
    }
  } else {
    submitModel.institution = JSON.parse(JSON.stringify(model.institution))
  }

  loadLocationField(editLibrarySchema.fields.city, model, values.value)
  enableField(editLibrarySchema.fields.city, model)

  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function delLibrary() {
  submitModel.submitType = 'institution'
  submitModel.regionWithParents = JSON.parse(JSON.stringify(model.regionWithParents))
  submitModel.institution = model.institution
  deleteDependencies()
}

function editCollection(add = false) {
  submitModel.submitType = 'collection'
  submitModel.regionWithParents = JSON.parse(JSON.stringify(model.regionWithParents))
  submitModel.institution = JSON.parse(JSON.stringify(model.institution))
  if (add) {
    submitModel.collection = {
      id: null,
      name: '',
    }
  } else {
    submitModel.collection = JSON.parse(JSON.stringify(model.collection))
  }

  loadLocationField(editCollectionSchema.fields.city, model, values.value)
  loadLocationField(editCollectionSchema.fields.library, model, values.value)
  enableField(editCollectionSchema.fields.city, model)
  enableField(editCollectionSchema.fields.library, model)

  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function delCollection() {
  submitModel.submitType = 'collection'
  submitModel.regionWithParents = JSON.parse(JSON.stringify(model.regionWithParents))
  submitModel.institution = JSON.parse(JSON.stringify(model.institution))
  submitModel.collection = JSON.parse(JSON.stringify(model.collection))
  deleteDependencies()
}

function submitEdit() {
  editModalValue.value = false
  openRequests.value++
  let url = ''
  let data = {}

  switch(submitModel.submitType) {
    case 'regionWithParents':
      url = urls['region_put'].replace('region_id', submitModel.regionWithParents.id)
      data = {
        individualName: submitModel.regionWithParents.individualName,
      }
      break
    case 'institution':
      if (submitModel.institution.id == null) {
        url = urls['library_post']
        data = {
          name: submitModel.institution.name,
          regionWithParents: {
            id: submitModel.regionWithParents.id
          }
        }
      } else {
        url = urls['library_put'].replace('library_id', submitModel.institution.id)
        data = {}
        if (submitModel.institution.name !== originalSubmitModel.institution.name) {
          data.name = submitModel.institution.name
        }
        if (submitModel.regionWithParents.id !== originalSubmitModel.regionWithParents.id) {
          data.regionWithParents = {
            id: submitModel.regionWithParents.id
          }
        }
      }
      break
    case 'collection':
      if (submitModel.collection.id == null) {
        url = urls['collection_post']
        data = {
          name: submitModel.collection.name,
          institution: {
            id: submitModel.institution.id,
          },
        }
      } else {
        url = urls['collection_put'].replace('collection_id', submitModel.collection.id)
        data = {}
        if (submitModel.collection.name !== originalSubmitModel.collection.name) {
          data.name = submitModel.collection.name
        }
        if (submitModel.institution.id !== originalSubmitModel.institution.id) {
          data.institution = {
            id: submitModel.institution.id
          }
        }
      }
      break
  }

  const request = submitModel[submitModel.submitType].id == null
      ? axios.post(url, data)
      : axios.put(url, data)

  request
      .then((response) => {
        switch(submitModel.submitType) {
          case 'regionWithParents':
            submitModel.regionWithParents = response.data
            break
          case 'institution':
            submitModel.institution = response.data
            break
          case 'collection':
            submitModel.collection = response.data
            break
        }
        update()
        editAlerts.value = []
        const action = submitModel[submitModel.submitType].id == null ? 'Addition' : 'Update'
        alerts.value.push({type: 'success', message: `${action} successful.`})
        openRequests.value--
      })
      .catch((error) => {
        openRequests.value--
        editModalValue.value = true
        const action = submitModel[submitModel.submitType].id == null ? 'adding' : 'updating'
        editAlerts.value.push({
          type: 'error',
          message: `Something went wrong while ${action} the ${formatType(submitModel.submitType)}.`,
          login: isLoginError(error)
        })
        console.log(error)
      })
}

function submitDelete() {
  deleteModal.value = false
  openRequests.value++
  let url = ''

  switch(submitModel.submitType) {
    case 'institution':
      url = urls['library_delete'].replace('library_id', submitModel.institution.id)
      break
    case 'collection':
      url = urls['collection_delete'].replace('collection_id', submitModel.collection.id)
      break
  }

  axios.delete(url)
      .then((response) => {
        switch(submitModel.submitType) {
          case 'institution':
            submitModel.institution = null
            break
          case 'collection':
            submitModel.collection = null
            break
        }
        update()
        deleteAlerts.value = []
        alerts.value.push({type: 'success', message: 'Deletion successful.'})
        openRequests.value--
      })
      .catch((error) => {
        openRequests.value--
        deleteModal.value = true
        deleteAlerts.value.push({
          type: 'error',
          message: `Something went wrong while deleting the ${formatType(submitModel.submitType)}.`,
          login: isLoginError(error)
        })
        console.log(error)
      })
}

function update() {
  openRequests.value++
  axios.get(urls['locations_get'])
      .then((response) => {
        Object.assign(values.value, response.data)

        switch(submitModel.submitType) {
          case 'regionWithParents':
            model.regionWithParents = JSON.parse(JSON.stringify(submitModel.regionWithParents))
            loadLocationField(citySchema.fields.city, model, values.value)
            break
          case 'institution':
            model.regionWithParents = JSON.parse(JSON.stringify(submitModel.regionWithParents))
            model.institution = JSON.parse(JSON.stringify(submitModel.institution))
            loadLocationField(citySchema.fields.city, model, values.value)
            loadLocationField(librarySchema.fields.library, model, values.value)
            enableField(librarySchema.fields.library, model)
            break
          case 'collection':
            model.regionWithParents = JSON.parse(JSON.stringify(submitModel.regionWithParents))
            model.institution = JSON.parse(JSON.stringify(submitModel.institution))
            model.collection = JSON.parse(JSON.stringify(submitModel.collection))
            loadLocationField(citySchema.fields.city, model, values.value)
            loadLocationField(librarySchema.fields.library, model, values.value)
            loadLocationField(collectionSchema.fields.collection, model, values.value)
            enableField(collectionSchema.fields.collection, model)
            break
        }
        openRequests.value--
      })
      .catch((error) => {
        openRequests.value--
        alerts.value.push({
          type: 'error',
          message: 'Something went wrong while renewing the location data.',
          login: isLoginError(error)
        })
        console.log(error)
      })
}

function formatType(type) {
  if (type === 'regionWithParents') {
    return 'city'
  }
  if (type === 'institution') {
    return 'library'
  }
  return type
}

onMounted(() => {
  loadLocationField(citySchema.fields.city, model, values.value)
  enableField(citySchema.fields.city, model)
  dependencyField(librarySchema.fields.library, model)
  dependencyField(collectionSchema.fields.collection, model)
})
</script>