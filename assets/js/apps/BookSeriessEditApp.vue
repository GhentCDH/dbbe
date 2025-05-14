<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <panel header="Edit book series">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="origin"
                    :conditions="{
                        add: true,
                        edit: model.bookSeries,
                        merge: model.bookSeries,
                        del: model.bookSeries,
                    }"
                    @add="edit(true)"
                    @edit="edit()"
                    @merge="merge()"
                    @del="del()"
                />
            </panel>
            <div
                v-if="openRequests"
                class="loading-overlay"
            >
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
            @dismiss-alert="editAlerts.splice($event, 1)"
        >
            <urlPanel
                id="urls"
                ref="urls"
                header="Urls"
                slot="extra"
                :model="submitModel.bookSeries"
                :as-slot="true"
            />
        </editModal>
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

<script>

import qs from 'qs'
import axios from 'axios'

import VueFormGenerator from 'vue-form-generator'

import AbstractListEdit from '@/Components/Edit/AbstractListEdit'
import Url from '@/Components/Edit/Panels/Url'
import {createMultiSelect,enableField} from "@/Components/FormFields/formFieldUtils";

export default {
    components: {
        UrlPanel: Url
    },
    mixins: [
        AbstractListEdit,
    ],
    data() {
        return {
            schema: {
                fields: {
                    bookSeries: createMultiSelect('BookSeries', {label: 'Book series'}),
                },
            },
            editSchema: {
                fields: {
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        labelClasses: 'control-label',
                        model: 'bookSeries.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            mergeSchema: {
                fields: {
                    primary: createMultiSelect('Primary', {required: true, validator: VueFormGenerator.validators.required}),
                    secondary: createMultiSelect('Secondary', {required: true, validator: VueFormGenerator.validators.required}),
                },
            },
            model: {
                bookSeries: null,
            },
            submitModel: {
                submitType: 'bookSeries',
                bookSeries: {
                    name: null,
                    urls: null,
                },
            },
            mergeModel: {
                submitType: 'bookSeries',
                primary: null,
                secondary: null,
            },
        }
    },
    computed: {
        depUrls: function() {
            return {
                'Books': {
                    depUrl: this.urls['book_deps_by_book_series'].replace('book_series_id', this.submitModel.bookSeries.id),
                    url: this.urls['book_get'],
                    urlIdentifier: 'book_id',
                }
            }
        },
    },
    mounted () {
        this.schema.fields.bookSeries.values = this.values;
        const params = qs.parse(window.location.href.split('?', 2)[1]);
        if (!isNaN(params['id'])) {
            const filteredValues = this.values.filter(v => v.id === parseInt(params['id']));
            if (filteredValues.length === 1) {
                this.model.bookSeries = JSON.parse(JSON.stringify(filteredValues[0]));
            }
        }
        window.history.pushState({}, null, window.location.href.split('?', 2)[0]);
        enableField(this.schema.fields.bookSeries);
    },
    methods: {
        edit(add = false) {
            // TODO: check if title already exists
            this.submitModel = {
                submitType: 'bookSeries',
                bookSeries: {
                    name: null,
                    urls: null,
                },
            };
            if (add) {
                this.submitModel.bookSeries = {
                    name: null,
                    urls: null,
                }
            }
            else {
                this.submitModel.bookSeries = JSON.parse(JSON.stringify(this.model.bookSeries))
                this.submitModel.bookSeries.urls = this.submitModel.bookSeries.urls == null ? null : this.submitModel.bookSeries.urls.map(
                    function(url, index) {
                        url.tgIndex = index + 1
                        return url
                    }
                )
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel));
            this.editModal = true
        },
        merge() {
            this.mergeModel.primary = JSON.parse(JSON.stringify(this.model.bookSeries));
            this.mergeModel.secondary = null;
            this.mergeSchema.fields.primary.values = this.values;
            this.mergeSchema.fields.secondary.values = this.values;
            enableField(this.mergeSchema.fields.primary);
            enableField(this.mergeSchema.fields.secondary);
            this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel));
            this.mergeModal = true
        },
        del() {
            this.submitModel.bookSeries = JSON.parse(JSON.stringify(this.model.bookSeries));
            this.submitModel.bookSeries.urls = this.submitModel.bookSeries.urls == null ? null : this.submitModel.bookSeries.urls.map(
                function(url, index) {
                    url.tgIndex = index + 1
                    return url
                }
            )
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false;
            this.openRequests++;

            let data = {};
            for (let key of Object.keys(this.submitModel.bookSeries)) {
                if ((key === 'id' && this.submitModel.bookSeries.id == null) || this.submitModel.bookSeries[key] !== this.originalSubmitModel.bookSeries[key]) {
                    data[key] = this.submitModel.bookSeries[key]
                }
            }

            if (this.submitModel.bookSeries.id == null) {
                axios.post(this.urls['book_series_post'], data)
                    .then( (response) => {
                        this.submitModel.bookSeries = response.data;
                        this.submitModel.bookSeries.urls = this.submitModel.bookSeries.urls == null ? null : this.submitModel.bookSeries.urls.map(
                            function(url, index) {
                                url.tgIndex = index + 1
                                return url
                            }
                        )
                        this.update();
                        this.editAlerts = [];
                        this.alerts.push({type: 'success', message: 'Addition successful.'});
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--;
                        this.editModal = true;
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the book series.', login: this.isLoginError(error)});
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['book_series_put'].replace('book_series_id', this.submitModel.bookSeries.id), data)
                    .then( (response) => {
                        this.submitModel.bookSeries = response.data;
                        this.submitModel.bookSeries.urls = this.submitModel.bookSeries.urls == null ? null : this.submitModel.bookSeries.urls.map(
                            function(url, index) {
                                url.tgIndex = index + 1
                                return url
                            }
                        )
                        this.update();
                        this.editAlerts = [];
                        this.alerts.push({type: 'success', message: 'Update successful.'});
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--;
                        this.editModal = true;
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the book series.', login: this.isLoginError(error)});
                        console.log(error)
                    })
            }
        },
        submitMerge() {
            this.mergeModal = false;
            this.openRequests++;
            axios.put(this.urls['book_series_merge'].replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id))
                .then( (response) => {
                    this.submitModel.bookSeries = response.data;
                    this.submitModel.bookSeries.urls = this.submitModel.bookSeries.urls == null ? null : this.submitModel.bookSeries.urls.map(
                        function(url, index) {
                            url.tgIndex = index + 1
                            return url
                        }
                    )
                    this.update();
                    this.mergeAlerts = [];
                    this.alerts.push({type: 'success', message: 'Merge successful.'});
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.mergeModal = true;
                    this.mergeAlerts.push({type: 'error', message: 'Something went wrong while merging the book series.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
        submitDelete() {
            this.deleteModal = false;
            this.openRequests++;
            axios.delete(this.urls['book_series_delete'].replace('book_series_id', this.submitModel.bookSeries.id))
                .then( (response) => {
                    this.submitModel.bookSeries = null;
                    this.update();
                    this.deleteAlerts = [];
                    this.alerts.push({type: 'success', message: 'Deletion successful.'});
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.deleteModal = true;
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the book series.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
        update() {
            this.openRequests++;
            axios.get(this.urls['book_seriess_get'])
                .then( (response) => {
                    this.values = response.data;
                    this.schema.fields.bookSeries.values = this.values;
                    this.model.bookSeries = JSON.parse(JSON.stringify(this.submitModel.bookSeries));
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the book series data.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
    }
}
</script>
