<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <panel header="Edit journals">
                <editListRow
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
                :model="submitModel.journal"
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

import VueFormGenerator from 'vue-form-generator'
import axios from 'axios'

import AbstractField from '../Components/FormFields/AbstractField'
import AbstractListEdit from '../Components/Edit/AbstractListEdit'
import Url from '../Components/Edit/Panels/Url'

export default {
    components: {
        UrlPanel: Url
    },
    mixins: [
        AbstractField,
        AbstractListEdit,
    ],
    data() {
        return {
            schema: {
                fields: {
                    journal: this.createMultiSelect('Journal'),
                },
            },
            editSchema: {
                fields: {
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        labelClasses: 'control-label',
                        model: 'journal.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            mergeSchema: {
                fields: {
                    primary: this.createMultiSelect('Primary', {required: true, validator: VueFormGenerator.validators.required}),
                    secondary: this.createMultiSelect('Secondary', {required: true, validator: VueFormGenerator.validators.required}),
                },
            },
            model: {
                journal: null,
            },
            submitModel: {
                submitType: 'journal',
                journal: {
                    name: null,
                    urls: null,
                },
            },
            mergeModel: {
                submitType: 'journals',
                primary: null,
                secondary: null,
            },
        }
    },
    computed: {
        depUrls: function() {
            return {
                'Journal issues': {
                    depUrl: this.urls['journal_issue_deps_by_journal'].replace('journal_id', this.submitModel.journal.id),
                }
            }
        },
    },
    mounted () {
        this.schema.fields.journal.values = this.values;
        const params = qs.parse(window.location.href.split('?', 2)[1]);
        if (!isNaN(params['id'])) {
            const filteredValues = this.values.filter(v => v.id === parseInt(params['id']));
            if (filteredValues.length === 1) {
                this.model.journal = JSON.parse(JSON.stringify(filteredValues[0]));
            }
        }
        window.history.pushState({}, null, window.location.href.split('?', 2)[0]);
        this.enableField(this.schema.fields.journal);
    },
    methods: {
        edit(add = false) {
            // TODO: check if title already exists
            this.submitModel = {
                submitType: 'journal',
                journal: {
                    name: null,
                    urls: null,
                },
            };
            if (add) {
                this.submitModel.journal =  {
                    name: null,
                    urls: null,
                }
            }
            else {
                this.submitModel.journal = JSON.parse(JSON.stringify(this.model.journal))
                this.submitModel.journal.urls = this.submitModel.journal.urls == null ? null : this.submitModel.journal.urls.map(
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
            this.mergeModel.primary = JSON.parse(JSON.stringify(this.model.journal));
            this.mergeModel.secondary = null;
            this.mergeSchema.fields.primary.values = this.values;
            this.mergeSchema.fields.secondary.values = this.values;
            this.enableField(this.mergeSchema.fields.primary);
            this.enableField(this.mergeSchema.fields.secondary);
            this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel));
            this.mergeModal = true
        },
        del() {
            this.submitModel.journal = JSON.parse(JSON.stringify(this.model.journal));
            this.submitModel.journal.urls = this.submitModel.journal.urls == null ? null : this.submitModel.journal.urls.map(
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
            for (let key of Object.keys(this.submitModel.journal)) {
                if ((key === 'id' && this.submitModel.journal.id == null) || this.submitModel.journal[key] !== this.originalSubmitModel.journal[key]) {
                    data[key] = this.submitModel.journal[key]
                }
            }

            if (this.submitModel.journal.id == null) {
                axios.post(this.urls['journal_post'], data)
                    .then( (response) => {
                        this.submitModel.journal = response.data;
                        this.submitModel.journal.urls = this.submitModel.journal.urls == null ? null : this.submitModel.journal.urls.map(
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
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the journal.', login: this.isLoginError(error)});
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['journal_put'].replace('journal_id', this.submitModel.journal.id), data)
                    .then( (response) => {
                        this.submitModel.journal = response.data;
                        this.submitModel.journal.urls = this.submitModel.journal.urls == null ? null : this.submitModel.journal.urls.map(
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
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the journal.', login: this.isLoginError(error)});
                        console.log(error)
                    })
            }
        },
        submitMerge() {
            this.mergeModal = false;
            this.openRequests++;
            axios.put(this.urls['journal_merge'].replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id))
                .then( (response) => {
                    this.submitModel.journal = response.data;
                    this.submitModel.journal.urls = this.submitModel.journal.urls == null ? null : this.submitModel.journal.urls.map(
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
                    this.mergeAlerts.push({type: 'error', message: 'Something went wrong while merging the journals.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
        submitDelete() {
            this.deleteModal = false;
            this.openRequests++;
            axios.delete(this.urls['journal_delete'].replace('journal_id', this.submitModel.journal.id))
                .then( (response) => {
                    this.submitModel.journal = null;
                    this.update();
                    this.deleteAlerts = [];
                    this.alerts.push({type: 'success', message: 'Deletion successful.'});
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.deleteModal = true;
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the journal.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
        update() {
            this.openRequests++;
            axios.get(this.urls['journals_get'])
                .then( (response) => {
                    this.values = response.data;
                    this.schema.fields.journal.values = this.values;
                    this.model.journal = JSON.parse(JSON.stringify(this.submitModel.journal));
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the journal data.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
    }
}
</script>
