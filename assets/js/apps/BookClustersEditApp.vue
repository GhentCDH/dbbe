<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <Panel header="Edit book clusters">
                <EditListRow
                    :schema="schema"
                    :model="model"
                    name="origin"
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
            <div
                v-if="openRequests"
                class="loading-overlay"
            >
                <div class="spinner" />
            </div>
        </article>
        <Edit
            :show="editModal"
            :schema="editSchema"
            :submit-model="submitModel"
            :original-submit-model="originalSubmitModel"
            :alerts="editAlerts"
            @cancel="cancelEdit()"
            @reset="resetEdit()"
            @confirm="submitEdit()"
            @dismiss-alert="editAlerts.splice($event, 1)"
        >
            <urlPanel
                id="urls"
                ref="urls"
                header="Urls"
                slot="extra"
                :model="submitModel.bookCluster"
                :as-slot="true"
            />
        </Edit>
        <mergeModal
            :show="mergeModal"
            :schema="mergeSchema"
            :merge-model="mergeModel"
            :original-merge-model="originalMergeModel"
            :alerts="mergeAlerts"
            @cancel="cancelMerge()"
            @reset="resetMerge()"
            @confirm="submitMerge()"
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
        </mergeModal>
        <deleteModal
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
<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)" />
      <panel header="Edit contents">
        <editListRow
            :schema="contentSchema"
            :model="model"
            name="content"
            :conditions="{
                        add: true,
                        edit: model.content,
                        merge: model.content,
                        del: model.content,
                    }"
            @add="editContent(true)"
            @edit="editContent()"
            @merge="mergeContent()"
            @del="delContent()" />
      </panel>
      <div
          class="loading-overlay"
          v-if="openRequests">
        <div class="spinner" />
      </div>
    </article>
    <editModal
        ref="editModal"
        :show="editModal"
        :schema="editContentSchema"
        :submit-model="submitModel"
        :original-submit-model="originalSubmitModel"
        :alerts="editAlerts"
        @cancel="cancelEdit()"
        @reset="resetEdit()"
        @confirm="submitEdit()"
        @dismiss-alert="editAlerts.splice($event, 1)" />
    <mergeModal
        :show="mergeModal"
        :schema="mergeContentSchema"
        :merge-model="mergeModel"
        :original-merge-model="originalMergeModel"
        :alerts="mergeAlerts"
        @cancel="cancelMerge()"
        @reset="resetMerge()"
        @confirm="submitMerge()"
        @dismiss-alert="mergeAlerts.splice($event, 1)">
      <table
          v-if="mergeModel.primary && mergeModel.secondary"
          slot="preview"
          class="table table-striped table-hover">
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
    </mergeModal>
    <deleteModal
        :show="deleteModal"
        :del-dependencies="delDependencies"
        :submit-model="submitModel"
        :alerts="deleteAlerts"
        @cancel="cancelDelete()"
        @confirm="submitDelete()"
        @dismiss-alert="deleteAlerts.splice($event, 1)" />
  </div>
</template>

<script>
import axios from 'axios'

import AbstractListEdit from '@/mixins/AbstractListEdit'
import qs from "qs";
import {createMultiSelect, enableField} from "@/helpers/formFieldUtils";
import {isLoginError} from "@/helpers/errorUtil";
import Edit from "@/Components/Edit/Modals/Edit.vue";
import Merge from "@/Components/Edit/Modals/Merge.vue";
import Delete from "@/Components/Edit/Modals/Delete.vue";

export default {
  mixins: [
    AbstractListEdit,
  ],
  props: {
    initPersons: {
      type: String,
      default: '',
    },
  },
  components: {
    Edit,
    editModal: Edit,
    mergeModal: Merge,
    deleteModal: Delete
  },
  data() {
    return {
      persons: JSON.parse(this.initPersons),
      contentSchema: {
        fields: {
          content: createMultiSelect('Content'),
        },
      },
      editContentSchema: {
        fields: {
          parent: createMultiSelect('Parent', {model: 'content.parent'}),
          individualName: {
            type: 'input',
            inputType: 'text',
            label: 'Content name',
            labelClasses: 'control-label',
            model: 'content.individualName',
            validator: [VueFormGenerator.validators.string, this.validateIndividualNameOrPerson],
          },
          individualPerson: createMultiSelect('Person', {model: 'content.individualPerson', validator: this.validateIndividualNameOrPerson}),
        },
      },
      mergeContentSchema: {
        fields: {
          primary: createMultiSelect('Primary', {required: true, validator: VueFormGenerator.validators.required}),
          secondary: createMultiSelect('Secondary', {required: true, validator: VueFormGenerator.validators.required}),
        },
      },
      model: {
        content: null,
      },
      submitModel: {
        submitType: 'content',
        content: {
          id: null,
          parent: null,
          name: null,
          individualPerson: null,
        }
      },
      mergeModel: {
        submitType: 'contents',
        primary: null,
        secondary: null,
      },
    }
  },
  computed: {
    depUrls: function () {
      return {
        'Contents': {
          depUrl: this.urls['content_deps_by_content'].replace('content_id', this.submitModel.content.id),
        },
        'Manuscripts': {
          depUrl: this.urls['manuscript_deps_by_content'].replace('content_id', this.submitModel.content.id),
          url: this.urls['manuscript_get'],
          urlIdentifier: 'manuscript_id',
        },
      }
    },
  },
  watch: {
    'model.content'() {
      // set full parent, so the name can be formatted correctly
      if (this.model.content != null && this.model.content.parent != null) {
        this.model.content.parent = this.values.filter((contentWithParents) => contentWithParents.id === this.model.content.parent.id)[0]
      }
    },
    // reset parent to null if nothing is entered
    'submitModel.content.individualName'() {
      this.$refs.editModal.revalidate()
      if (this.submitModel.content.individualName === '') {
        this.submitModel.content.individualName = null
      }
    },
  },
  mounted () {
    this.contentSchema.fields.content.values = this.values
    const params = qs.parse(window.location.href.split('?', 2)[1]);
    if (!isNaN(params['id'])) {
      const filteredValues = this.values.filter(v => v.id === parseInt(params['id']));
      if (filteredValues.length === 1) {
        this.model.content = JSON.parse(JSON.stringify(filteredValues[0]));
      }
    }
    window.history.pushState({}, null, window.location.href.split('?', 2)[0]);
    enableField(this.contentSchema.fields.content)
  },
  methods: {
    editContent(add = false) {
      // TODO: check if name already exists
      if (add) {
        this.submitModel.content =  {
          id: null,
          name: null,
          parent: this.model.content,
          individualName: null,
          individualPerson: null,
        }
      }
      else {
        this.submitModel.content = JSON.parse(JSON.stringify(this.model.content))
      }
      this.editContentSchema.fields.parent.values = this.values
          .filter((content) => !this.isOrIsChild(content, this.model.content)) // Remove values that create cycles
      enableField(this.editContentSchema.fields.parent)
      this.editContentSchema.fields.individualPerson.values = this.persons
      enableField(this.editContentSchema.fields.individualPerson)
      this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
      this.editModal = true
    },
    mergeContent() {
      this.mergeModel.primary = JSON.parse(JSON.stringify(this.model.content))
      this.mergeModel.secondary = null
      this.mergeContentSchema.fields.primary.values = this.values
      this.mergeContentSchema.fields.secondary.values = this.values
      enableField(this.mergeContentSchema.fields.primary)
      enableField(this.mergeContentSchema.fields.secondary)
      this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel))
      this.mergeModal = true
    },
    delContent() {
      this.submitModel.content = this.model.content
      this.deleteDependencies()
    },
    submitEdit() {
      this.editModal = false
      this.openRequests++
      if (this.submitModel.content.id == null) {
        axios.post(this.urls['content_post'], {
          parent: this.submitModel.content.parent == null ? null : {
            id: this.submitModel.content.parent.id,
          },
          individualName: this.submitModel.content.individualName,
          individualPerson: this.submitModel.content.individualPerson == null ? null : {
            id: this.submitModel.content.individualPerson.id,
          }
        })
            .then( (response) => {
              this.submitModel.content = response.data
              this.update()
              this.editAlerts = []
              this.alerts.push({type: 'success', message: 'Addition successful.'})
              this.openRequests--
            })
            .catch( (error) => {
              this.openRequests--
              this.editModal = true
              this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the content.', login: isLoginError(error)})
              console.log(error)
            })
      }
      else {
        let data = {}
        if (JSON.stringify(this.submitModel.content.parent) !== JSON.stringify(this.originalSubmitModel.content.parent)) {
          if (this.submitModel.content.parent == null) {
            data.parent = null
          }
          else {
            data.parent = {
              id: this.submitModel.content.parent.id
            }
          }
        }
        if (this.submitModel.content.individualName !== this.originalSubmitModel.content.individualName) {
          data.individualName = this.submitModel.content.individualName
        }
        if (JSON.stringify(this.submitModel.content.individualPerson) !== JSON.stringify(this.originalSubmitModel.content.individualPerson)) {
          if (this.submitModel.content.individualPerson == null) {
            data.individualPerson = null
          }
          else {
            data.individualPerson = {
              id: this.submitModel.content.individualPerson.id
            }
          }
        }
        axios.put(this.urls['content_put'].replace('content_id', this.submitModel.content.id), data)
            .then( (response) => {
              this.submitModel.content = response.data
              this.update()
              this.editAlerts = []
              this.alerts.push({type: 'success', message: 'Update successful.'})
              this.openRequests--
            })
            .catch( (error) => {
              this.openRequests--
              this.editModal = true
              this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the content.', login: isLoginError(error)})
              console.log(error)
            })
      }
    },
    submitMerge() {
      this.mergeModal = false
      this.openRequests++
      axios.put(this.urls['content_merge'].replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id))
          .then( (response) => {
            this.submitModel.content = response.data
            this.update()
            this.mergeAlerts = []
            this.alerts.push({type: 'success', message: 'Merge successful.'})
            this.openRequests--
          })
          .catch( (error) => {
            this.openRequests--
            this.mergeModal = true
            this.mergeAlerts.push({type: 'error', message: 'Something went wrong while merging the content.', login: isLoginError(error)})
            console.log(error)
          })
    },
    submitDelete() {
      this.deleteModal = false
      this.openRequests++
      axios.delete(this.urls['content_delete'].replace('content_id', this.submitModel.content.id))
          .then( (response) => {
            this.submitModel.content = null
            this.update()
            this.deleteAlerts = []
            this.alerts.push({type: 'success', message: 'Deletion successful.'})
            this.openRequests--
          })
          .catch( (error) => {
            this.openRequests--
            this.deleteModal = true
            this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the content.', login: isLoginError(error)})
            console.log(error)
          })
    },
    update() {
      this.openRequests++
      axios.get(this.urls['contents_get'])
          .then( (response) => {
            this.values = response.data
            this.contentSchema.fields.content.values = this.values
            this.model.content = JSON.parse(JSON.stringify(this.submitModel.content))
            this.openRequests--
          })
          .catch( (error) => {
            this.openRequests--
            this.alerts.push({type: 'error', message: 'Something went wrong while renewing the content data.', login: isLoginError(error)})
            console.log(error)
          })
    },
    validateIndividualNameOrPerson() {
      if (
          (
              this.submitModel.content.individualName == null
              && this.submitModel.content.individualPerson == null
          ) || (
              this.submitModel.content.individualName != null
              && this.submitModel.content.individualPerson != null
          )
      ) {
        return ['Please provide a content name or select a person (but not both).']
      }
      return []
    },
  }
}
</script>

<!--<script>-->

<!--import qs from 'qs'-->

<!--import VueFormGenerator from 'vue-form-generator'-->
<!--import axios from 'axios'-->

<!--import AbstractListEdit from '@/mixins/AbstractListEdit'-->
<!--import Url from '@/Components/Edit/Panels/Url'-->
<!--import {createMultiSelect,enableField} from "@/helpers/formFieldUtils";-->
<!--import {isLoginError} from "@/helpers/errorUtil";-->
<!--import Edit from "@/Components/Edit/Modals/Edit.vue";-->
<!--import Merge from "@/Components/Edit/Modals/Merge.vue";-->
<!--import Delete from "@/Components/Edit/Modals/Delete.vue";-->

<!--export default {-->
<!--    mixins: [-->
<!--        AbstractListEdit,-->
<!--    ],-->
<!--    components: {-->
<!--      editModal: Edit,-->
<!--      mergeModal: Merge,-->
<!--      deleteModal: Delete,-->
<!--      UrlPanel: Url-->

<!--    },-->
<!--    data() {-->
<!--        return {-->
<!--            schema: {-->
<!--                fields: {-->
<!--                    bookCluster: createMultiSelect('BookCluster', {label: 'Book cluster'}),-->
<!--                },-->
<!--            },-->
<!--            editSchema: {-->
<!--                fields: {-->
<!--                    title: {-->
<!--                        type: 'input',-->
<!--                        inputType: 'text',-->
<!--                        label: 'Title',-->
<!--                        labelClasses: 'control-label',-->
<!--                        model: 'bookCluster.name',-->
<!--                        required: true,-->
<!--                        validator: VueFormGenerator.validators.string,-->
<!--                    },-->
<!--                },-->
<!--            },-->
<!--            mergeSchema: {-->
<!--                fields: {-->
<!--                    primary: createMultiSelect('Primary', {required: true, validator: VueFormGenerator.validators.required}),-->
<!--                    secondary: createMultiSelect('Secondary', {required: true, validator: VueFormGenerator.validators.required}),-->
<!--                },-->
<!--            },-->
<!--            model: {-->
<!--                bookCluster: null,-->
<!--            },-->
<!--            submitModel: {-->
<!--                submitType: 'bookCluster',-->
<!--                bookCluster: {-->
<!--                    name: null,-->
<!--                    urls: null,-->
<!--                },-->
<!--            },-->
<!--            mergeModel: {-->
<!--                submitType: 'bookClusters',-->
<!--                primary: null,-->
<!--                secondary: null,-->
<!--            },-->
<!--        }-->
<!--    },-->
<!--    computed: {-->
<!--        depUrls: function() {-->
<!--            return {-->
<!--                'Books': {-->
<!--                    depUrl: this.urls['book_deps_by_book_cluster'].replace('book_cluster_id', this.submitModel.bookCluster.id),-->
<!--                    url: this.urls['book_get'],-->
<!--                    urlIdentifier: 'book_id',-->
<!--                }-->
<!--            }-->
<!--        },-->
<!--    },-->
<!--    mounted () {-->
<!--        this.schema.fields.bookCluster.values = this.values;-->
<!--        const params = qs.parse(window.location.href.split('?', 2)[1]);-->
<!--        if (!isNaN(params['id'])) {-->
<!--            const filteredValues = this.values.filter(v => v.id === parseInt(params['id']));-->
<!--            if (filteredValues.length === 1) {-->
<!--                this.model.bookCluster = JSON.parse(JSON.stringify(filteredValues[0]));-->
<!--            }-->
<!--        }-->
<!--        window.history.pushState({}, null, window.location.href.split('?', 2)[0]);-->
<!--        enableField(this.schema.fields.bookCluster);-->
<!--    },-->
<!--    methods: {-->
<!--        edit(add = false) {-->
<!--            // TODO: check if title already exists-->
<!--            this.submitModel = {-->
<!--                submitType: 'bookCluster',-->
<!--                bookCluster: {-->
<!--                    name: null,-->
<!--                    urls: null,-->
<!--                },-->
<!--            };-->
<!--            if (add) {-->
<!--                this.submitModel.bookCluster = {-->
<!--                    name: null,-->
<!--                    urls: null,-->
<!--                }-->
<!--            }-->
<!--            else {-->
<!--                this.submitModel.bookCluster = JSON.parse(JSON.stringify(this.model.bookCluster))-->
<!--                this.submitModel.bookCluster.urls = this.submitModel.bookCluster.urls == null ? null : this.submitModel.bookCluster.urls.map(-->
<!--                    function(url, index) {-->
<!--                        url.tgIndex = index + 1-->
<!--                        return url-->
<!--                    }-->
<!--                )-->
<!--            }-->
<!--            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel));-->
<!--            this.editModal = true-->
<!--        },-->
<!--        merge() {-->
<!--            this.mergeModel.primary = JSON.parse(JSON.stringify(this.model.bookCluster));-->
<!--            this.mergeModel.secondary = null;-->
<!--            this.mergeSchema.fields.primary.values = this.values;-->
<!--            this.mergeSchema.fields.secondary.values = this.values;-->
<!--            enableField(this.mergeSchema.fields.primary);-->
<!--            enableField(this.mergeSchema.fields.secondary);-->
<!--            this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel));-->
<!--            this.mergeModal = true-->
<!--        },-->
<!--        del() {-->
<!--            this.submitModel.bookCluster = JSON.parse(JSON.stringify(this.model.bookCluster));-->
<!--            this.submitModel.bookCluster.urls = this.submitModel.bookCluster.urls == null ? null : this.submitModel.bookCluster.urls.map(-->
<!--                function(url, index) {-->
<!--                    url.tgIndex = index + 1-->
<!--                    return url-->
<!--                }-->
<!--            )-->
<!--            this.deleteDependencies()-->
<!--        },-->
<!--        submitEdit() {-->
<!--            this.editModal = false;-->
<!--            this.openRequests++;-->

<!--            let data = {};-->
<!--            for (let key of Object.keys(this.submitModel.bookCluster)) {-->
<!--                if ((key === 'id' && this.submitModel.bookCluster.id == null) || this.submitModel.bookCluster[key] !== this.originalSubmitModel.bookCluster[key]) {-->
<!--                    data[key] = this.submitModel.bookCluster[key]-->
<!--                }-->
<!--            }-->

<!--            if (this.submitModel.bookCluster.id == null) {-->
<!--                axios.post(this.urls['book_cluster_post'], data)-->
<!--                    .then( (response) => {-->
<!--                        this.submitModel.bookCluster = response.data;-->
<!--                        this.submitModel.bookCluster.urls = this.submitModel.bookCluster.urls == null ? null : this.submitModel.bookCluster.urls.map(-->
<!--                            function(url, index) {-->
<!--                                url.tgIndex = index + 1-->
<!--                                return url-->
<!--                            }-->
<!--                        )-->
<!--                        this.update();-->
<!--                        this.editAlerts = [];-->
<!--                        this.alerts.push({type: 'success', message: 'Addition successful.'});-->
<!--                        this.openRequests&#45;&#45;-->
<!--                    })-->
<!--                    .catch( (error) => {-->
<!--                        this.openRequests&#45;&#45;;-->
<!--                        this.editModal = true;-->
<!--                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the book cluster.', login: isLoginError(error)});-->
<!--                        console.log(error)-->
<!--                    })-->
<!--            }-->
<!--            else {-->
<!--                axios.put(this.urls['book_cluster_put'].replace('book_cluster_id', this.submitModel.bookCluster.id), data)-->
<!--                    .then( (response) => {-->
<!--                        this.submitModel.bookCluster = response.data;-->
<!--                        this.submitModel.bookCluster.urls = this.submitModel.bookCluster.urls == null ? null : this.submitModel.bookCluster.urls.map(-->
<!--                            function(url, index) {-->
<!--                                url.tgIndex = index + 1-->
<!--                                return url-->
<!--                            }-->
<!--                        )-->
<!--                        this.update();-->
<!--                        this.editAlerts = [];-->
<!--                        this.alerts.push({type: 'success', message: 'Update successful.'});-->
<!--                        this.openRequests&#45;&#45;-->
<!--                    })-->
<!--                    .catch( (error) => {-->
<!--                        this.openRequests&#45;&#45;;-->
<!--                        this.editModal = true;-->
<!--                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the book cluster.', login: isLoginError(error)});-->
<!--                        console.log(error)-->
<!--                    })-->
<!--            }-->
<!--        },-->
<!--        submitMerge() {-->
<!--            this.mergeModal = false;-->
<!--            this.openRequests++;-->
<!--            axios.put(this.urls['book_cluster_merge'].replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id))-->
<!--                .then( (response) => {-->
<!--                    this.submitModel.bookCluster = response.data;-->
<!--                    this.submitModel.bookCluster.urls = this.submitModel.bookCluster.urls == null ? null : this.submitModel.bookCluster.urls.map(-->
<!--                        function(url, index) {-->
<!--                            url.tgIndex = index + 1-->
<!--                            return url-->
<!--                        }-->
<!--                    )-->
<!--                    this.update();-->
<!--                    this.mergeAlerts = [];-->
<!--                    this.alerts.push({type: 'success', message: 'Merge successful.'});-->
<!--                    this.openRequests&#45;&#45;-->
<!--                })-->
<!--                .catch( (error) => {-->
<!--                    this.openRequests&#45;&#45;;-->
<!--                    this.mergeModal = true;-->
<!--                    this.mergeAlerts.push({type: 'error', message: 'Something went wrong while merging the book clusters.', login: isLoginError(error)});-->
<!--                    console.log(error)-->
<!--                })-->
<!--        },-->
<!--        submitDelete() {-->
<!--            this.deleteModal = false;-->
<!--            this.openRequests++;-->
<!--            axios.delete(this.urls['book_cluster_delete'].replace('book_cluster_id', this.submitModel.bookCluster.id))-->
<!--                .then( (response) => {-->
<!--                    this.submitModel.bookCluster = {-->
<!--                        name: null,-->
<!--                        urls: null,-->
<!--                    };-->
<!--                    this.update();-->
<!--                    this.deleteAlerts = [];-->
<!--                    this.alerts.push({type: 'success', message: 'Deletion successful.'});-->
<!--                    this.openRequests&#45;&#45;-->
<!--                })-->
<!--                .catch( (error) => {-->
<!--                    this.openRequests&#45;&#45;;-->
<!--                    this.deleteModal = true;-->
<!--                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the book cluster.', login: isLoginError(error)});-->
<!--                    console.log(error)-->
<!--                })-->
<!--        },-->
<!--        update() {-->
<!--            this.openRequests++;-->
<!--            axios.get(this.urls['book_clusters_get'])-->
<!--                .then( (response) => {-->
<!--                    this.values = response.data;-->
<!--                    this.schema.fields.bookCluster.values = this.values;-->
<!--                    this.model.bookCluster = JSON.parse(JSON.stringify(this.submitModel.bookCluster));-->
<!--                    this.openRequests&#45;&#45;-->
<!--                })-->
<!--                .catch( (error) => {-->
<!--                    this.openRequests&#45;&#45;;-->
<!--                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the book cluster data.', login: isLoginError(error)});-->
<!--                    console.log(error)-->
<!--                })-->
<!--        },-->
<!--    }-->
<!--}-->
<!--</script>-->
