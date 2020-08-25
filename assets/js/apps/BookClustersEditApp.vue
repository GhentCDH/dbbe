<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <panel header="Edit book clusters">
                <editListRow
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
        />
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

import AbstractField from '../Components/FormFields/AbstractField'
import AbstractListEdit from '../Components/Edit/AbstractListEdit'

export default {
    mixins: [
        AbstractField,
        AbstractListEdit,
    ],
    data() {
        return {
            schema: {
                fields: {
                    bookCluster: this.createMultiSelect('BookCluster', {label: 'Book cluster'}),
                },
            },
            editSchema: {
                fields: {
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        labelClasses: 'control-label',
                        model: 'bookCluster.name',
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
                bookCluster: null,
            },
            submitModel: {
                submitType: 'bookCluster',
                bookCluster: null,
            },
            mergeModel: {
                submitType: 'bookClusters',
                primary: null,
                secondary: null,
            },
        }
    },
    computed: {
        depUrls: function() {
            return {
                'Books': {
                    depUrl: this.urls['book_deps_by_book_cluster'].replace('book_cluster_id', this.submitModel.bookCluster.id),
                    url: this.urls['book_get'],
                    urlIdentifier: 'book_id',
                }
            }
        },
    },
    mounted () {
        this.schema.fields.bookCluster.values = this.values;
        const params = qs.parse(window.location.href.split('?', 2)[1]);
        if (!isNaN(params['id'])) {
            const filteredValues = this.values.filter(v => v.id === parseInt(params['id']));
            if (filteredValues.length === 1) {
                this.model.bookCluster = JSON.parse(JSON.stringify(filteredValues[0]));
            }
        }
        window.history.pushState({}, null, window.location.href.split('?', 2)[0]);
        this.enableField(this.schema.fields.bookCluster);
    },
    methods: {
        edit(add = false) {
            // TODO: check if title already exists
            this.submitModel = {
                submitType: 'bookCluster',
                bookCluster: null,
            };
            if (add) {
                this.submitModel.bookCluster =  {
                    name: null,
                }
            }
            else {
                this.submitModel.bookCluster = this.model.bookCluster
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel));
            this.editModal = true
        },
        merge() {
            this.mergeModel.primary = JSON.parse(JSON.stringify(this.model.bookCluster));
            this.mergeModel.secondary = null;
            this.mergeSchema.fields.primary.values = this.values;
            this.mergeSchema.fields.secondary.values = this.values;
            this.enableField(this.mergeSchema.fields.primary);
            this.enableField(this.mergeSchema.fields.secondary);
            this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel));
            this.mergeModal = true
        },
        del() {
            this.submitModel.bookCluster = JSON.parse(JSON.stringify(this.model.bookCluster));
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false;
            this.openRequests++;

            let data = {};
            for (let key of Object.keys(this.submitModel.bookCluster)) {
                if (this.submitModel.bookCluster.id == null || this.submitModel.bookCluster[key] !== this.originalSubmitModel.bookCluster[key]) {
                    data[key] = this.submitModel.bookCluster[key]
                }
            }

            if (this.submitModel.bookCluster.id == null) {
                axios.post(this.urls['book_cluster_post'], data)
                    .then( (response) => {
                        this.submitModel.bookCluster = response.data;
                        this.update();
                        this.editAlerts = [];
                        this.alerts.push({type: 'success', message: 'Addition successful.'});
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--;
                        this.editModal = true;
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the book cluster.', login: this.isLoginError(error)});
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['book_cluster_put'].replace('book_cluster_id', this.submitModel.bookCluster.id), data)
                    .then( (response) => {
                        this.submitModel.bookCluster = response.data;
                        this.update();
                        this.editAlerts = [];
                        this.alerts.push({type: 'success', message: 'Update successful.'});
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--;
                        this.editModal = true;
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the book cluster.', login: this.isLoginError(error)});
                        console.log(error)
                    })
            }
        },
        submitMerge() {
            this.mergeModal = false;
            this.openRequests++;
            axios.put(this.urls['book_cluster_merge'].replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id))
                .then( (response) => {
                    this.submitModel.bookCluster = response.data;
                    this.update();
                    this.mergeAlerts = [];
                    this.alerts.push({type: 'success', message: 'Merge successful.'});
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.mergeModal = true;
                    this.mergeAlerts.push({type: 'error', message: 'Something went wrong while merging the book clusters.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
        submitDelete() {
            this.deleteModal = false;
            this.openRequests++;
            axios.delete(this.urls['book_cluster_delete'].replace('book_cluster_id', this.submitModel.bookCluster.id))
                .then( (response) => {
                    this.submitModel.bookCluster = null;
                    this.update();
                    this.deleteAlerts = [];
                    this.alerts.push({type: 'success', message: 'Deletion successful.'});
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.deleteModal = true;
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the book cluster.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
        update() {
            this.openRequests++;
            axios.get(this.urls['book_clusters_get'])
                .then( (response) => {
                    this.values = response.data;
                    this.schema.fields.bookCluster.values = this.values;
                    this.model.bookCluster = JSON.parse(JSON.stringify(this.submitModel.bookCluster));
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the book cluster data.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
    }
}
</script>
