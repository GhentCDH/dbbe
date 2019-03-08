<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <panel header="Edit journal issues">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="origin"
                    :conditions="{
                        add: true,
                        edit: model.journalIssue,
                        del: model.journalIssue,
                    }"
                    @add="edit(true)"
                    @edit="edit()"
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
import VueFormGenerator from 'vue-form-generator'

import AbstractField from '../Components/FormFields/AbstractField'
import AbstractListEdit from '../Components/Edit/AbstractListEdit'

export default {
    mixins: [
        AbstractField,
        AbstractListEdit,
    ],
    data() {
        let data = JSON.parse(this.initData);
        return {
            values: data.journalIssues,
            journals: data.journals,
            schema: {
                fields: {
                    journalIssue: this.createMultiSelect('JournalIssue', {label: 'Journal issue'}),
                },
            },
            editSchema: {
                fields: {
                    journal: this.createMultiSelect(
                        'Journal',
                        {
                            model: 'journal issue.journal',
                            required: true,
                            validator: VueFormGenerator.validators.required,
                        }
                    ),
                    year: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year',
                        labelClasses: 'control-label',
                        model: 'journal issue.year',
                        required: true,
                        validator: VueFormGenerator.validators.number,
                    },
                    volume: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Volume',
                        labelClasses: 'control-label',
                        model: 'journal issue.volume',
                        validator: VueFormGenerator.validators.number,
                    },
                    number: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Number',
                        labelClasses: 'control-label',
                        model: 'journal issue.number',
                        validator: VueFormGenerator.validators.number,
                    },
                },
            },
            model: {
                journalIssue: null,
            },
            submitModel: {
                submitType: 'journal issue',
                'journal issue': null,
            },
        }
    },
    computed: {
        depUrls: function() {
            return {
                'Articles': {
                    depUrl: this.urls['article_deps_by_journal_issue'].replace('journal_issue_id', this.submitModel['journal issue'].id),
                    url: this.urls['article_get'],
                    urlIdentifier: 'article_id',
                }
            }
        },
    },
    mounted () {
        this.schema.fields.journalIssue.values = this.values;
        this.enableField(this.schema.fields.journalIssue)
    },
    methods: {
        edit(add = false) {
            // TODO: check if combination journal, year, volume, number already exists
            this.submitModel = {
                submitType: 'journal issue',
                'journal issue': null,
            };
            if (add) {
                this.submitModel['journal issue'] =  {
                    journal: null,
                    year: null,
                    volume: null,
                    number: null,
                }
            }
            else {
                this.submitModel['journal issue'] = this.model.journalIssue
            }
            this.editSchema.fields.journal.values = this.journals;
            this.enableField(this.editSchema.fields.journal);
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel));
            this.editModal = true
        },
        del() {
            this.submitModel['journal issue'] = JSON.parse(JSON.stringify(this.model.journalIssue));
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false;
            this.openRequests++;

            let data = {};
            for (let key of Object.keys(this.submitModel['journal issue'])) {
                if (this.submitModel['journal issue'].id == null || this.submitModel['journal issue'][key] !== this.originalSubmitModel['journal issue'][key]) {
                    data[key] = this.submitModel['journal issue'][key]
                }
            }

            if (this.submitModel['journal issue'].id == null) {
                axios.post(this.urls['journal_issue_post'], data)
                    .then( (response) => {
                        this.submitModel['journal issue'] = response.data;
                        this.update();
                        this.editAlerts = [];
                        this.alerts.push({type: 'success', message: 'Addition successful.'});
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--;
                        this.editModal = true;
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the journal issue.', login: this.isLoginError(error)});
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['journal_issue_put'].replace('journal_issue_id', this.submitModel['journal issue'].id), data)
                    .then( (response) => {
                        this.submitModel['journal issue'] = response.data;
                        this.update();
                        this.editAlerts = [];
                        this.alerts.push({type: 'success', message: 'Update successful.'});
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--;
                        this.editModal = true;
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the journal issue.', login: this.isLoginError(error)});
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false;
            this.openRequests++;
            axios.delete(this.urls['journal_issue_delete'].replace('journal_issue_id', this.submitModel['journal issue'].id))
                .then( (response) => {
                    this.submitModel['journal issue'] = null;
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
            axios.get(this.urls['journal_issues_get'])
                .then( (response) => {
                    this.values = response.data;
                    this.schema.fields.journalIssue.values = this.values;
                    this.model.journalIssue = JSON.parse(JSON.stringify(this.submitModel['journal issue']));
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the journal issue data.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
    }
}
</script>
