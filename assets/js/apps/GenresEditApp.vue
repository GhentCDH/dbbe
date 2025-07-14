<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <panel header="Edit genres">
        <editListRow
            :schema="schema"
            :model="model"
            name="genre"
            :conditions="{
            add: true,
            edit: model.genre,
            del: model.genre
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
import { reactive, ref, watch } from 'vue'
import axios from 'axios'
import VueFormGenerator from 'vue-form-generator'

import Panel from '@/Components/Edit/Panel.vue'
import Edit from '@/Components/Edit/Modals/Edit.vue'
import Delete from '@/Components/Edit/Modals/Delete.vue'
import Merge from '@/Components/Edit/Modals/Merge.vue'
import Alerts from '@/Components/Alerts.vue'


import EditListRow from '@/Components/Edit/EditListRow.vue'
import { isLoginError } from '@/helpers/errorUtil'
import { useEditMergeMigrateDelete } from '@/composables/useEditMergeMigrateDelete'

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

const schema = reactive({
  fields: [
    {
      type: 'multiselect',
      label: 'Genre',
      model: 'genre',
      values: values
    }
  ]
})

const editSchema = reactive({
  fields: [
    {
      type: 'input',
      inputType: 'text',
      label: 'Name',
      labelClasses: 'control-label',
      model: 'genre.name',
      required: true,
      validator: VueFormGenerator.validators.string
    }
  ]
})

const model = reactive({
  genre: null
})

const submitModel = reactive({
  submitType: 'genre',
  genre: {
    id: null,
    name: null
  }
})

// Keep schema values in sync with incoming updates
watch(values, (newValues) => {
  schema.fields[0].values = Array.isArray(newValues) ? newValues : []
}, { immediate: true })

watch(originalSubmitModel, (newVal) => {
  Object.assign(submitModel.genre, newVal.genre)
})

watch(editModalValue, (newVal) => {
  console.log('editmodalvalue', newVal)
})
function edit(add = false) {
  if (add) {
    submitModel.genre = { id: null, name: null }
  } else {
    submitModel.genre = JSON.parse(JSON.stringify(model.genre))
  }
  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)))
  editModalValue.value = true
}

function del() {
  submitModel.genre = model.genre
  deleteDependencies()
}

function update() {
  openRequests.value++
  axios.get(urls.genres_get)
      .then((response) => {
        if (Array.isArray(values.value)) {
          values.value.splice(0, values.value.length, ...response.data)
        }
        schema.fields[0].values = values
        model.genre = JSON.parse(JSON.stringify(submitModel.genre))
      })
      .catch((error) => {
        alerts.value.push({
          type: 'error',
          message: 'Something went wrong while renewing the genre data.',
          login: isLoginError(error)
        })
        console.error(error)
      })
      .finally(() => {
        openRequests.value--
      })
}

async function submitEdit() {
  editModalValue.value = false
  openRequests.value++

  try {
    let response
    if (submitModel.genre.id == null) {
      response = await axios.post(urls.genre_post, {
        name: submitModel.genre.name
      })
      submitModel.genre = response.data
      alerts.value.push({ type: 'success', message: 'Addition successful.' })
    } else {
      response = await axios.put(
          urls.genre_put.replace('genre_id', submitModel.genre.id),
          { name: submitModel.genre.name }
      )
      submitModel.genre = response.data
      alerts.value.push({ type: 'success', message: 'Update successful.' })
    }
    update()
    editAlerts.value.splice(0)
  } catch (error) {
    editModalValue.value = true
    editAlerts.value.push({
      type: 'error',
      message: submitModel.genre.id == null
          ? 'Something went wrong while adding the genre.'
          : 'Something went wrong while updating the genre.',
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
    await axios.delete(urls.genre_delete.replace('genre_id', submitModel.genre.id))
    submitModel.genre = null
    update()
    deleteAlerts.value.splice(0)
    alerts.value.push({ type: 'success', message: 'Deletion successful.' })
  } catch (error) {
    deleteModal.value = true
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the genre.',
      login: isLoginError(error)
    })
    console.error(error)
  } finally {
    openRequests.value--
  }
}
</script>