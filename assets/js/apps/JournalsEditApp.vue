<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <Panel header="Edit journals">
        <EditListRow
            :schema="schema"
            :model="model"
            name="origin"
            :conditions="{
            add: true,
            edit: model.journal,
            merge: model.journal,
            del: model.journal,
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
          v-slot:extra
          :model="submitModel.journal"
      /></template>
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
                  <strong>Url</strong> {{ url.url }}
                  <br />
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
import { ref, reactive, toRefs, watch, onMounted } from 'vue'
import qs from 'qs'
import axios from 'axios'

import { isLoginError } from '@/helpers/errorUtil'
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'

import Edit from '@/Components/Edit/Modals/Edit.vue'
import Delete from '@/Components/Edit/Modals/Delete.vue'
import Panel from '@/Components/Edit/Panel.vue'
import Alerts from '@/Components/Alerts.vue'
import EditListRow from '@/Components/Edit/EditListRow.vue'
import UrlPanel from '@/Components/Edit/Panels/Url.vue'
import Merge from "@/Components/Edit/Modals/Merge.vue";

import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete'

const props = defineProps({
  initUrls: { type: String, default: '{}' },
  initData: { type: String, default: '[]' },
})

const {
  urls,
  values,
  alerts,
  editAlerts,
  mergeAlerts,
  migrateAlerts,
  deleteAlerts,
  delDependencies,
  deleteModal,
  editModalValue,
  mergeModal,
  migrateModal,
  originalMergeModel,
  originalMigrateModel,
  originalSubmitModel,
  openRequests,
  resetEdit,
  resetMerge,
  resetMigrate,
  deleteDependencies,
  cancelEdit,
  cancelMerge,
  cancelMigrate,
  cancelDelete,
  isOrIsChild
} = useEditMergeMigrateDelete(props.initUrls, props.initData)

const schema = reactive({
  fields: {
    journal: createMultiSelect('Journal'),
  }
})

const editSchema = reactive({
  fields: {
    title: {
      type: 'input',
      inputType: 'text',
      label: 'Title',
      labelClasses: 'control-label',
      model: 'journal.name',
      required: true,
      validator: (value) => value && value.length > 0, // Simplified validator
    },
  }
})

const mergeSchema = reactive({
  fields: {
    primary: createMultiSelect('Primary', { required: true, validator: (v) => !!v }),
    secondary: createMultiSelect('Secondary', { required: true, validator: (v) => !!v }),
  }
})

const model = reactive({
  journal: null,
})

const submitModel = reactive({
  submitType: 'journal',
  journal: {
    name: null,
    urls: null,
  },
})

const mergeModel = reactive({
  submitType: 'journals',
  primary: null,
  secondary: null,
})

const urlsReactive = toRefs(urls)

const depUrls = reactive({
  get 'Journal issues'() {
    return {
      depUrl: urls['journal_issue_deps_by_journal']?.replace('journal_id', submitModel.journal?.id ?? ''),
    }
  }
})

onMounted(() => {
  schema.fields.journal.values = values.value

  const params = qs.parse(window.location.href.split('?', 2)[1])
  if (!isNaN(params['id'])) {
    const filteredValues = values.value.filter(v => v.id === parseInt(params['id']))
    if (filteredValues.length === 1) {
      model.journal = JSON.parse(JSON.stringify(filteredValues[0]))
    }
  }
  window.history.pushState({}, null, window.location.href.split('?', 2)[0])
  enableField(schema.fields.journal)
})

function edit(add = false) {
  submitModel.submitType = 'journal'
  submitModel.journal = { name: null, urls: null }

  if (add) {
    submitModel.journal = { name: null, urls: null }
  } else {
    submitModel.journal = JSON.parse(JSON.stringify(model.journal))
    if (submitModel.journal.urls) {
      submitModel.journal.urls = submitModel.journal.urls.map((url, index) => {
        url.tgIndex = index + 1
        return url
      })
    }
  }
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function merge() {
  mergeModel.primary = JSON.parse(JSON.stringify(model.journal))
  mergeModel.secondary = null
  mergeSchema.fields.primary.values = values.value
  mergeSchema.fields.secondary.values = values.value
  enableField(mergeSchema.fields.primary)
  enableField(mergeSchema.fields.secondary)
  Object.assign(originalMergeModel, JSON.parse(JSON.stringify(mergeModel)))
  mergeModal.value = true
}

function del() {
  submitModel.journal = JSON.parse(JSON.stringify(model.journal))
  if (submitModel.journal.urls) {
    submitModel.journal.urls = submitModel.journal.urls.map((url, index) => {
      url.tgIndex = index + 1
      return url
    })
  }
  deleteDependencies()
}

// The submit handlers (submitEdit, submitMerge, submitDelete)
// and update function follow the same logic as before but rewritten with Composition API:

async function submitEdit() {
  editModalValue.value = false
  openRequests.value++

  let data = {}
  for (const key of Object.keys(submitModel.journal)) {
    if ((key === 'id' && submitModel.journal.id == null) || submitModel.journal[key] !== originalSubmitModel.journal[key]) {
      data[key] = submitModel.journal[key]
    }
  }

  try {
    let response
    if (submitModel.journal.id == null) {
      response = await axios.post(urls['journal_post'], data)
    } else {
      response = await axios.put(urls['journal_put'].replace('journal_id', submitModel.journal.id), data)
    }
    submitModel.journal = response.data
    if (submitModel.journal.urls) {
      submitModel.journal.urls = submitModel.journal.urls.map((url, index) => {
        url.tgIndex = index + 1
        return url
      })
    }
    await update()
    editAlerts.value = []
    alerts.value.push({ type: 'success', message: submitModel.journal.id == null ? 'Addition successful.' : 'Update successful.' })
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({ type: 'error', message: 'Something went wrong while saving the journal.', login: isLoginError(error) })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function submitMerge() {
  mergeModal.value = false
  openRequests.value++
  try {
    const response = await axios.put(urls['journal_merge']
        .replace('primary_id', mergeModel.primary.id)
        .replace('secondary_id', mergeModel.secondary.id))
    submitModel.journal = response.data
    if (submitModel.journal.urls) {
      submitModel.journal.urls = submitModel.journal.urls.map((url, index) => {
        url.tgIndex = index + 1
        return url
      })
    }
    await update()
    mergeAlerts.value = []
    alerts.value.push({ type: 'success', message: 'Merge successful.' })
  } catch (error) {
    mergeModal.value = true
    mergeAlerts.value.push({ type: 'error', message: 'Something went wrong while merging the journals.', login: isLoginError(error) })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function submitDelete() {
  deleteModal.value = false
  openRequests.value++
  try {
    await axios.delete(urls['journal_delete'].replace('journal_id', submitModel.journal.id))
    submitModel.journal = null
    await update()
    deleteAlerts.value = []
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({ type: 'error', message: 'Something went wrong while deleting the journal.', login: isLoginError(error) })
    console.error(error)
  } finally {
    openRequests.value--
  }
}

async function update() {
  openRequests.value++
  try {
    const response = await axios.get(urls['journals_get'])
    values.value = response.data
    schema.fields.journal.values = values.value
    model.journal = JSON.parse(JSON.stringify(submitModel.journal))
  } catch (error) {
    alerts.value.push({ type: 'error', message: 'Something went wrong while renewing the journal data.', login: isLoginError(error) })
    console.error(error)
  } finally {
    openRequests.value--
  }
}
</script>
