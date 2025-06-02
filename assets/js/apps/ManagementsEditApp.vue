<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <panel header="Edit management collections">
        <editListRow
            :schema="schema"
            :model="model"
            name="management collection"
            :conditions="{
            add: true,
            edit: model.management,
            del: model.management,
          }"
            @add="edit(true)"
            @edit="edit()"
            @del="del()"
        />
      </panel>

      <div v-if="openRequests" class="loading-overlay">
        <div class="spinner" />
      </div>
    </article>

    <editModal
        :show="editModal"
        :schema="editSchema"
        :submit-model="submitModel"
        :original-submit-model="originalSubmitModel"
        :alerts="editAlerts"
        @cancel="cancelEdit"
        @reset="resetEdit()"
        @confirm="submitEdit()"
        @dismiss-alert="editAlerts.splice($event, 1)"
    />

    <deleteModal
        :show="deleteModal"
        :del-dependencies="delDependencies"
        :submit-model="submitModel"
        :alerts="deleteAlerts"
        @cancel="cancelDelete"
        @confirm="submitDelete()"
        @dismiss-alert="deleteAlerts.splice($event, 1)"
    />
  </div>
</template>

<script>
import axios from 'axios'
import VueFormGenerator from 'vue-form-generator'
import { useListEdit } from '../Components/Edit/AbstractListEdit'
import { createMultiSelect, enableField } from '@/Components/FormFields/formFieldUtils'

import EditModal from '../Components/Edit/Modals/Edit.vue'
import DeleteModal from '../Components/Edit/Modals/Delete.vue'

import { toRef } from 'vue'

VueFormGenerator.validators.requiredMultiSelect = function (value) {
  if (!value || value.length === 0) {
    return ['This field is required!']
  }
  return []
}

export default {
  props: {
    initUrls: { type: String, default: '{}' },
    initData: { type: String, default: '{}' },
  },

  components: {
    editModal: EditModal,
    deleteModal: DeleteModal,
  },

  setup(props) {
    const listEdit = useListEdit(props.initUrls, props.initData)

    return {
      ...listEdit,
      deleteModal: toRef(listEdit, 'deleteModal'),
    }
  },

  data() {
    return {
      schema: {
        fields: {
          management: createMultiSelect('Management'),
        },
      },
      editSchema: {
        fields: {
          name: {
            type: 'input',
            inputType: 'text',
            label: 'Name',
            labelClasses: 'control-label',
            model: 'management.name',
            required: true,
            validator: VueFormGenerator.validators.string,
          },
        },
      },
      model: {
        management: null,
      },
      submitModel: {
        submitType: 'management',
        management: null,
      },
    }
  },

  mounted() {
    this.schema.fields.management.values = this.values
    enableField(this.schema.fields.management)
  },

  methods: {
    edit(add = false) {
      this.submitModel.submitType = 'management'
      this.submitModel.management = add ? {} : JSON.parse(JSON.stringify(this.model.management))
      this.originalSubmitModel.submitType = this.submitModel.submitType
      this.originalSubmitModel.management = JSON.parse(JSON.stringify(this.submitModel.management))
      this.editModal = true
    },

    del() {
      this.submitModel.submitType = 'management'
      this.submitModel.management = JSON.parse(JSON.stringify(this.model.management))
      this.deleteDependencies()
    },


    submitEdit() {
      this.editModal = false
      this.openRequests++

      const isNew = this.submitModel.management.id == null
      const submitUrl = isNew
          ? this.urls['management_post']
          : this.urls['management_put'].replace('management_id', this.submitModel.management.id)

      const payload = isNew
          ? { name: this.submitModel.management.name }
          : (this.submitModel.management.name !== this.originalSubmitModel.management.name
              ? { name: this.submitModel.management.name }
              : {})

      const request = isNew
          ? axios.post(submitUrl, payload)
          : axios.put(submitUrl, payload)

      request
          .then((response) => {
            this.submitModel.management = response.data
            this.update()
            this.editAlerts = []
            this.alerts.push({
              type: 'success',
              message: isNew ? 'Addition successful.' : 'Update successful.',
            })
          })
          .catch((error) => {
            this.editModal = true
            this.editAlerts.push({
              type: 'error',
              message: isNew
                  ? 'Something went wrong while adding the management.'
                  : 'Something went wrong while updating the management collection.',
              login: this.isLoginError(error),
            })
            console.error(error)
          })
          .finally(() => {
            this.openRequests--
          })
    },

    submitDelete() {
      this.deleteModal = false
      this.openRequests++

      axios
          .delete(this.urls['management_delete'].replace('management_id', this.submitModel.management.id))
          .then(() => {
            this.submitModel.management = null
            this.update()
            this.deleteAlerts = []
            this.alerts.push({ type: 'success', message: 'Deletion successful.' })
          })
          .catch((error) => {
            this.deleteModal = true
            this.deleteAlerts.push({
              type: 'error',
              message: 'Something went wrong while deleting the management collection.',
              login: this.isLoginError(error),
            })
            console.error(error)
          })
          .finally(() => {
            this.openRequests--
          })
    },

    update() {
      this.openRequests++
      axios
          .get(this.urls['managements_get'])
          .then((response) => {
            this.values = response.data
            this.schema.fields.management.values = this.values
            this.model.management = JSON.parse(JSON.stringify(this.submitModel.management))
          })
          .catch((error) => {
            this.alerts.push({
              type: 'error',
              message: 'Something went wrong while refreshing the management data.',
              login: this.isLoginError(error),
            })
            console.error(error)
          })
          .finally(() => {
            this.openRequests--
          })
    },
  },
}
</script>
