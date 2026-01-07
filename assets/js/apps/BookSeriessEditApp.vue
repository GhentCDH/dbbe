<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts :alerts="alerts" @dismiss="alerts.splice($event, 1)" />
      <Panel header="Edit book series">
        <EditListRow
            :schema="schema"
            :model="model"
            name="bookSeries"
            :conditions="{
            add: true,
            edit: model.bookSeries,
            merge: model.bookSeries,
            del: model.bookSeries
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
    >
      <template #extra>
      <UrlPanel
          id="urls"
          ref="urls"
          header="Urls"
          :model="submitModel.bookSeries"
          :as-slot="true"
      />
      </template>
    </Edit>

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
      <template #preview>
      <table
          v-if="mergeModel.primary && mergeModel.secondary"
          slot="preview"
          class="table table-striped table-hover"
      >
        <thead>
        <tr><th>Field</th><th>Value</th></tr>
        </thead>
        <tbody>
        <tr><td>Title</td><td>{{ mergeModel.primary.name }}</td></tr>
        <tr>
          <td>Urls</td>
          <td>
            <div
                v-if="mergeModel.primary.urls && mergeModel.primary.urls.length"
                v-for="(url, index) in mergeModel.primary.urls"
                :key="index"
                class="panel"
            >
              <div class="panel-body">
                <strong>Url</strong> {{ url.url }}<br />
                <strong>Title</strong> {{ url.title }}
              </div>
            </div>
          </td>
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
import { reactive, watch, onMounted, computed } from 'vue'
import axios from 'axios'

import Edit from '@/components/Edit/Modals/Edit.vue'
import Merge from '@/components/Edit/Modals/Merge.vue'
import Delete from '@/components/Edit/Modals/Delete.vue'
import Panel from '@/components/Edit/Panel.vue'
import Alerts from '@/components/Alerts.vue'
import EditListRow from '@/components/Edit/EditListRow.vue'
import UrlPanel from '@/components/Edit/Panels/Url.vue'

import { isLoginError } from '@/helpers/errorUtil'
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'
import VueFormGenerator from 'vue3-form-generator-legacy'
import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete'

const depUrls = computed(() => ({}))

const props = defineProps({
  initUrls: String,
  initData: String
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
  originalSubmitModel,
  originalMergeModel,
  openRequests,
  deleteDependencies,
  cancelEdit,
  cancelMerge,
  cancelDelete,
  resetEdit,
  resetMerge
} = useEditMergeMigrateDelete(props.initUrls, props.initData, depUrls)

const schema = reactive({
  fields: {
    bookSeries: createMultiSelect('BookSeries', { label: 'Book series' })
  }
})

const editSchema = reactive({
  fields: [
    {
      type: 'input',
      inputType: 'text',
      label: 'Title',
      labelClasses: 'control-label',
      model: 'bookSeries.name',
      required: true,
      validator: VueFormGenerator.validators.string
    }
  ]
})

const mergeSchema = reactive({
  fields: {
    primary: createMultiSelect('Primary', { required: true, validator: VueFormGenerator.validators.required }),
    secondary: createMultiSelect('Secondary', { required: true, validator: VueFormGenerator.validators.required })
  }
})

const model = reactive({ bookSeries: null })

const submitModel = reactive({
  submitType: 'bookSeries',
  bookSeries: { name: null, urls: [] }
})

const mergeModel = reactive({
  submitType: 'bookSeries',
  primary: null,
  secondary: null
})

watch(values, (newValues) => {
  schema.fields.bookSeries.values = Array.isArray(newValues) ? newValues : []
}, { immediate: true })

onMounted(() => {
  schema.fields.bookSeries.values = values.value || []
  enableField(schema.fields.bookSeries, model)
})

function edit(add = false) {
  submitModel.bookSeries = add
      ? { name: null, urls: [] }
      : JSON.parse(JSON.stringify(model.bookSeries))
  if (submitModel.bookSeries.urls)
    submitModel.bookSeries.urls = submitModel.bookSeries.urls.map((url, i) => ({ ...url, tgIndex: i + 1 }))
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function merge() {
  mergeModel.primary = JSON.parse(JSON.stringify(model.bookSeries))
  mergeModel.secondary = null
  mergeSchema.fields.primary.values = values.value
  mergeSchema.fields.secondary.values = values.value
  enableField(mergeSchema.fields.primary)
  enableField(mergeSchema.fields.secondary)
  Object.assign(originalMergeModel, JSON.parse(JSON.stringify(mergeModel)))
  mergeModal.value = true
}

function del() {
  submitModel.bookSeries = JSON.parse(JSON.stringify(model.bookSeries))
  if (submitModel.bookSeries.urls)
    submitModel.bookSeries.urls = submitModel.bookSeries.urls.map((url, i) => ({ ...url, tgIndex: i + 1 }))
  deleteDependencies()
}

async function update() {
  openRequests.value++
  try {
    const response = await axios.get(urls.book_seriess_get)
    values.value.splice(0, values.value.length, ...response.data)
    schema.fields.bookSeries.values = values.value
    if (submitModel.bookSeries) {
      model.bookSeries = JSON.parse(JSON.stringify(submitModel.bookSeries))
    } else {
      model.bookSeries = null
    }

  } catch (err) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the book series data.',
      login: isLoginError(err)
    })
    console.error(err)
  } finally {
    openRequests.value--
  }
}

async function submitEdit() {
  editModalValue.value = false
  openRequests.value++
  try {
    let response
    if (!submitModel.bookSeries.id) {
      response = await axios.post(urls.book_series_post, submitModel.bookSeries)
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      response = await axios.put(
          urls.book_series_put.replace('book_series_id', submitModel.bookSeries.id),
          submitModel.bookSeries
      )
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
    submitModel.bookSeries = response.data
    if (submitModel.bookSeries.urls)
      submitModel.bookSeries.urls = submitModel.bookSeries.urls.map((url, i) => ({ ...url, tgIndex: i + 1 }))
    await update()
    editAlerts.value = []
  } catch (err) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message: submitModel.bookSeries.id ? 'Something went wrong while updating the book series.' : 'Something went wrong while adding the book series.',
      login: isLoginError(err)
    })
    console.error(err)
  } finally {
    openRequests.value--
  }
}

async function submitMerge() {
  mergeModal.value = false
  openRequests.value++
  try {
    const response = await axios.put(
        urls.book_series_merge.replace('primary_id', mergeModel.primary.id).replace('secondary_id', mergeModel.secondary.id)
    )
    submitModel.bookSeries = response.data
    if (submitModel.bookSeries.urls)
      submitModel.bookSeries.urls = submitModel.bookSeries.urls.map((url, i) => ({ ...url, tgIndex: i + 1 }))
    await update()
    mergeAlerts.value = []
    alerts.value.push({ type: 'success', message: 'Merge successful.' })
  } catch (err) {
    mergeModal.value = true
    mergeAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while merging the book series.',
      login: isLoginError(err)
    })
    console.error(err)
  } finally {
    openRequests.value--
  }
}

async function submitDelete() {
  deleteModal.value = false
  openRequests.value++
  try {
    await axios.delete(urls.book_series_delete.replace('book_series_id', submitModel.bookSeries.id))
    submitModel.bookSeries = { name: null, urls: [] }

    await update()
    deleteAlerts.value = []
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (err) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the book series.',
      login: isLoginError(err)
    })
    console.error(err)
  } finally {
    openRequests.value--
  }
}
</script>