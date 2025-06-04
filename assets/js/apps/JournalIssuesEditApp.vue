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
            ref="editModal"
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

import qs from 'qs'
import axios from 'axios'

import VueFormGenerator from 'vue-form-generator'

import AbstractListEdit from '../mixins/AbstractListEdit'
import {createMultiSelect,enableField} from "@/helpers/formFieldUtils";
import {isLoginError} from "@/helpers/errorUtil";

export default {
    mixins: [
        AbstractListEdit,
    ],
    data() {
        let data = JSON.parse(this.initData);
        return {
            revalidate: false,
            values: data.journalIssues,
            journals: data.journals,
            schema: {
                fields: {
                    journalIssue: createMultiSelect('JournalIssue', {label: 'Journal issue'}),
                },
            },
            editSchema: {
                fields: {
                    journal: createMultiSelect(
                        'Journal',
                        {
                            model: 'journal issue.journal',
                            required: true,
                            validator: VueFormGenerator.validators.required,
                        }
                    ),
                    year: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Year',
                        labelClasses: 'control-label',
                        model: 'journal issue.year',
                        validator: [
                            this.yearOrForthcoming,
                        ],
                    },
                    forthcoming: {
                        type: 'checkbox',
                        label: 'Forthcoming',
                        labelClasses: 'control-label',
                        model: 'journal issue.forthcoming',
                        validator: this.yearOrForthcoming,
                    },
                    series: {
                      type: 'input',
                      inputType: 'text',
                      label: 'Series',
                      labelClasses: 'control-label',
                      model: 'journal issue.series',
                    },
                    volume: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Volume',
                        labelClasses: 'control-label',
                        model: 'journal issue.volume',
                    },
                    number: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Number',
                        labelClasses: 'control-label',
                        model: 'journal issue.number',
                    },
                },
            },
            model: {
                journalIssue: null,
            },
            submitModel: {
                submitType: 'journal issue',
                'journal issue': {},
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
        const params = qs.parse(window.location.href.split('?', 2)[1]);
        if (!isNaN(params['id'])) {
            const filteredValues = this.values.filter(v => v.id === parseInt(params['id']));
            if (filteredValues.length === 1) {
                this.model.journalIssue = JSON.parse(JSON.stringify(filteredValues[0]));
            }
        }
        window.history.pushState({}, null, window.location.href.split('?', 2)[0]);
        enableField(this.schema.fields.journalIssue)

        // Use $watch API because 'journal issue' contains a space
        this.$watch(
            () => this.submitModel['journal issue'].year,
            () => {
                if (this.submitModel['journal issue'].year === '') {
                    this.submitModel['journal issue'].year = null;
                    this.revalidate = true;
                    this.$refs.editModal.validate();
                    this.revalidate = false;
                }
            },
        );
        this.$watch(
            () => this.submitModel['journal issue'].volume,
            () => {
                if (this.submitModel['journal issue'].volume === '') {
                    this.submitModel['journal issue'].volume = null;
                    this.revalidate = true;
                    this.$refs.editModal.validate();
                    this.revalidate = false;
                }
            },
        );
        this.$watch(
            () => this.submitModel['journal issue'].series,
            () => {
              if (this.submitModel['journal issue'].series === '') {
                this.submitModel['journal issue'].series = null;
                this.revalidate = true;
                this.$refs.editModal.validate();
                this.revalidate = false;
              }
            },
        );
        this.$watch(
            () => this.submitModel['journal issue'].number,
            () => {
                if (this.submitModel['journal issue'].number === '') {
                    this.submitModel['journal issue'].number = null;
                    this.revalidate = true;
                    this.$refs.editModal.validate();
                    this.revalidate = false;
                }
            },
        );
    },
    methods: {
        edit(add = false) {
            // TODO: check if combination journal, year, volume, number already exists
            this.submitModel = {
                submitType: 'journal issue',
                'journal issue': {},
            };
            if (add) {
                this.submitModel['journal issue'] = {
                    journal: null,
                    year: null,
                    forthcoming: null,
                    series: null,
                    volume: null,
                    number: null,
                }
            }
            else {
                this.submitModel['journal issue'] = JSON.parse(JSON.stringify(this.model.journalIssue))
            }
            this.editSchema.fields.journal.values = this.journals;
            enableField(this.editSchema.fields.journal);
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel));
            // Make sure forthcoming is set
            if (this.submitModel['journal issue'].forthcoming == null) {
                this.submitModel['journal issue'].forthcoming = false
            }
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
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the journal issue.', login: isLoginError(error)});
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
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the journal issue.', login: isLoginError(error)});
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
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the journal.', login: isLoginError(error)});
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
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the journal issue data.', login: isLoginError(error)});
                    console.log(error)
                })
        },
        yearOrForthcoming() {
            if (!this.revalidate) {
                this.revalidate = true;
                this.$refs.editModal.validate();
                this.revalidate = false;
            }
            if (
                (
                    this.submitModel['journal issue'].year == null
                    && this.submitModel['journal issue'].forthcoming === false
                )
                || (
                    this.submitModel['journal issue'].year != null
                    && this.submitModel['journal issue'].forthcoming === true
                )
            ) {
                return ['Exactly one of the fields "Year", "Forthcoming" is required.'];
            }
            return [];
        },
    }
}
</script>
