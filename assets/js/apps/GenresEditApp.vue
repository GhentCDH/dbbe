<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit genres">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="genre"
                    :conditions="{
                        add: true,
                        edit: model.genre,
                        del: model.genre,
                    }"
                    @add="edit(true)"
                    @edit="edit()"
                    @del="del()" />
            </panel>
            <div
                class="loading-overlay"
                v-if="openRequests">
                <div class="spinner" />
            </div>
        </article>
        <editModal
            :show="editModal"
            :schema="editSchema"
            :submit-model="submitModel"
            :original-submit-model="originalSubmitModel"
            :alerts="editAlerts"
            @cancel="cancelEdit()"
            @reset="resetEdit()"
            @confirm="submitEdit()"
            @dismiss-alert="editAlerts.splice($event, 1)" />
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
import VueFormGenerator from 'vue-form-generator'
import axios from 'axios'

import AbstractListEdit from '@/mixins/AbstractListEdit'
import {createMultiSelect, enableField} from "@/helpers/formFieldUtils";
import {isLoginError} from "@/helpers/errorUtil";
import Edit from "@/Components/Edit/Modals/Edit.vue";
import Merge from "@/Components/Edit/Modals/Merge.vue";
import Delete from "@/Components/Edit/Modals/Delete.vue";

export default {
    mixins: [
        AbstractListEdit,
    ],
    components: {
      editModal: Edit,
      mergeModal: Merge,
      deleteModal: Delete
    },
    data() {
        return {
            schema: {
                fields: {
                    genre: createMultiSelect('Genre'),
                },
            },
            editSchema: {
                fields: {
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Name',
                        labelClasses: 'control-label',
                        model: 'genre.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            model: {
                genre: null,
            },
            submitModel: {
                submitType: 'genre',
                genre: {
                    id: null,
                    name: null,
                }
            },
        }
    },
    computed: {
        depUrls: function () {
            return {
                'Occurrences': {
                    depUrl: this.urls['occurrence_deps_by_genre'].replace('genre_id', this.submitModel.genre.id),
                    url: this.urls['occurrence_get'],
                    urlIdentifier: 'occurrence_id',
                },
                'Types': {
                    depUrl: this.urls['type_deps_by_genre'].replace('genre_id', this.submitModel.genre.id),
                    url: this.urls['type_get'],
                    urlIdentifier: 'type_id',
                },
            }
        },
    },
    mounted () {
        this.schema.fields.genre.values = this.values
        enableField(this.schema.fields.genre)
    },
    methods: {
        edit(add = false) {
            // TODO: check if name already exists
            if (add) {
                this.submitModel.genre =  {
                    id: null,
                    name: null,
                }
            }
            else {
                this.submitModel.genre = JSON.parse(JSON.stringify(this.model.genre))
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        del() {
            this.submitModel.genre = this.model.genre
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.genre.id == null) {
                axios.post(this.urls['genre_post'], {
                    name: this.submitModel.genre.name,
                })
                    .then( (response) => {
                        this.submitModel.genre = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the genre.', login: isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['genre_put'].replace('genre_id', this.submitModel.genre.id), {
                    name: this.submitModel.genre.name,
                })
                    .then( (response) => {
                        this.submitModel.genre = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the genre.', login: isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['genre_delete'].replace('genre_id', this.submitModel.genre.id))
                .then( (response) => {
                    this.submitModel.genre = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the genre.', login: isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['genres_get'])
                .then( (response) => {
                    this.values = response.data
                    this.schema.fields.genre.values = this.values
                    this.model.genre = JSON.parse(JSON.stringify(this.submitModel.genre))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the genre data.', login: isLoginError(error)})
                    console.log(error)
                })
        },
    }
}
</script>
