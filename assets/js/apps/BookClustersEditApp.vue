<template>
  <div>
  <article class="col-sm-9 mbottom-large">
    <Alerts
        :alerts="alerts"
        @dismiss="alerts.splice($event, 1)"
    />
    <Panel header="Edit book clusters">
      <EditListRow
          :schema="schema"
          :model="model"
          name="bookCluster"
          :conditions="{
          add: true,
          edit: model.bookCluster,
          merge: model.bookCluster,
          del: model.bookCluster,
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
      <UrlPanel
          id="urls"
          ref="urls"
          header="Urls"
          slot="extra"
          :model="submitModel.bookCluster"
          :as-slot="true"
      />
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
                v-if="mergeModel.primary.urls?.length"
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
import VueFormGenerator from 'vue-form-generator'

import Edit from '@/components/Edit/Modals/Edit.vue'
import Merge from '@/components/Edit/Modals/Merge.vue'
import Delete from '@/components/Edit/Modals/Delete.vue'
import Panel from '@/components/Edit/Panel.vue'
import Alerts from '@/components/Alerts.vue'
import EditListRow from '@/components/Edit/EditListRow.vue'
import UrlPanel from '@/components/Edit/Panels/Url.vue'
import { isLoginError } from '@/helpers/errorUtil'
import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete'
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'

const props = defineProps({
  initUrls: { type: String},
  initData: { type: String }
})
const depUrls = computed(() => ({}))

const {
  urls,
  values,
  alerts,
  editAlerts,
  deleteAlerts,
  mergeAlerts,
  delDependencies,
  deleteModal,
  editModalValue,
  mergeModal,
  originalSubmitModel,
  originalMergeModel,
  openRequests,
  deleteDependencies,
  cancelEdit,
  cancelDelete,
  cancelMerge,
  resetEdit,
  resetMerge,
} = useEditMergeMigrateDelete(props.initUrls, props.initData,depUrls)

const schema = reactive({
  fields: {
    bookCluster: createMultiSelect('BookCluster', { label: 'Book cluster' }),
  },
})

const editSchema = reactive({
  fields: [
    {
      type: 'input',
      inputType: 'text',
      label: 'Title',
      labelClasses: 'control-label',
      model: 'bookCluster.name',
      required: true,
      validator: VueFormGenerator.validators.string,
    },
  ],
})

const mergeSchema = reactive({
  fields: {
    primary: createMultiSelect('Primary', { required: true, validator: VueFormGenerator.validators.required }),
    secondary: createMultiSelect('Secondary', { required: true, validator: VueFormGenerator.validators.required }),
  },
})

const model = reactive({
  bookCluster: null,
})

const submitModel = reactive({
  submitType: 'bookCluster',
  bookCluster: {
    id: null,
    name: null,
    urls: null,
  },
})

const mergeModel = reactive({
  submitType: 'bookClusters',
  primary: null,
  secondary: null,
})

watch(values, (newValues) => {
  schema.fields.bookCluster.values = Array.isArray(newValues) ? newValues : []
}, { immediate: true })

watch(originalSubmitModel, (newVal) => {
  Object.assign(submitModel.bookCluster, newVal.bookCluster)
})

watch(values, () => {
  enableField(schema.fields.bookCluster)
})

onMounted(() => {
  schema.fields.bookCluster.values = values.value || []
  enableField(schema.fields.bookCluster, model)
})

function edit(add = false) {
  if (add) {
    submitModel.bookCluster = {
      id: null,
      name: null,
      urls: null,
    }
  } else {
    submitModel.bookCluster = JSON.parse(JSON.stringify(model.bookCluster))
    if (submitModel.bookCluster.urls) {
      submitModel.bookCluster.urls = submitModel.bookCluster.urls.map((url, i) => ({
        ...url,
        tgIndex: i + 1,
      }))
    }
  }
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function merge() {
  mergeModel.primary = JSON.parse(JSON.stringify(model.bookCluster))
  mergeModel.secondary = null
  mergeSchema.fields.primary.values = values.value
  mergeSchema.fields.secondary.values = values.value
  enableField(mergeSchema.fields.primary)
  enableField(mergeSchema.fields.secondary)
  Object.assign(originalMergeModel, JSON.parse(JSON.stringify(mergeModel)))
  mergeModal.value = true
}

function del() {
  submitModel.bookCluster = JSON.parse(JSON.stringify(model.bookCluster))
  if (submitModel.bookCluster.urls) {
    submitModel.bookCluster.urls = submitModel.bookCluster.urls.map((url, i) => ({
      ...url,
      tgIndex: i + 1,
    }))
  }
  deleteDependencies()
}

async function update() {
  openRequests.value++
  try {
    const response = await axios.get(urls.book_clusters_get)
    if (Array.isArray(values.value)) {
      values.value.splice(0, values.value.length, ...response.data)
    }
    schema.fields.bookCluster.values = values.value
    model.bookCluster = JSON.parse(JSON.stringify(submitModel.bookCluster))
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the book cluster data.',
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
    const data = {}
    for (const key in submitModel.bookCluster) {
      if (
          (key === 'id' && submitModel.bookCluster.id == null) ||
          submitModel.bookCluster[key] !== originalSubmitModel.bookCluster[key]
      ) {
        data[key] = submitModel.bookCluster[key]
      }
    }

    let response
    if (submitModel.bookCluster.id == null) {
      response = await axios.post(urls.book_cluster_post, data)
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      response = await axios.put(
          urls.book_cluster_put.replace('book_cluster_id', submitModel.bookCluster.id),
          data
      )
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }

    submitModel.bookCluster = response.data
    if (submitModel.bookCluster.urls) {
      submitModel.bookCluster.urls = submitModel.bookCluster.urls.map((url, i) => ({
        ...url,
        tgIndex: i + 1,
      }))
    }

    await update()
    editAlerts.value.splice(0)
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message: 'Error saving book cluster.',
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
    const { primary, secondary } = mergeModel
    if (!primary || !secondary) return

    await axios.put(
        urls.book_cluster_merge.replace('primary_book_cluster_id', primary.id).replace('secondary_book_cluster_id', secondary.id)
    )
    alerts.value.push({ type: 'success', message: 'Merge successful.' })
    await update()
    mergeAlerts.value.splice(0)
  } catch (error) {
    mergeModal.value = true
    mergeAlerts.value.push({
      type: 'error',
      message: 'Error merging book clusters.',
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
    await axios.delete(
        urls.book_cluster_delete.replace('book_cluster_id', submitModel.bookCluster.id)
    )
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
    await update()
    deleteAlerts.value.splice(0)
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Error deleting book cluster.',
      login: isLoginError(error),
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}
</script>
