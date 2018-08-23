<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit journals">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="origin"
                    :conditions="{
                        add: true,
                        edit: model.journal,
                        del: model.journal,
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
                        model: 'journal.title',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                    year: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year',
                        labelClasses: 'control-label',
                        model: 'journal.year',
                        required: true,
                        validator: VueFormGenerator.validators.number,
                    },
                    volume: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Volume',
                        labelClasses: 'control-label',
                        model: 'journal.volume',
                        validator: VueFormGenerator.validators.number,
                    },
                    number: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Number',
                        labelClasses: 'control-label',
                        model: 'journal.number',
                        validator: VueFormGenerator.validators.number,
                    },
                },
            },
            model: {
                journal: null,
            },
            submitModel: {
                type: 'journal',
                journal: null,
            },
        }
    },
    computed: {
        depUrls: function() {
            return {
                'Articles': {
                    depUrl: this.urls['article_deps_by_journal'].replace('journal_id', this.submitModel.journal.id),
                    url: this.urls['article_get'],
                    urlIdentifier: 'article_id',
                }
            }
        },
    },
    mounted () {
        this.schema.fields.journal.values = this.values
        this.enableField(this.schema.fields.journal)
    },
    methods: {
        edit(add = false) {
            // TODO: check if title already exists
            this.submitModel = {
                type: 'journal',
                journal: null,
            }
            if (add) {
                this.submitModel.journal =  {
                    title: null,
                    year: null,
                    volume: null,
                    number: null,
                }
            }
            else {
                this.submitModel.journal = this.model.journal
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        del() {
            this.submitModel.journal = JSON.parse(JSON.stringify(this.model.journal))
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++

            let data = {}
            for (let key of Object.keys(this.submitModel.journal)) {
                if (this.submitModel.journal.id == null || this.submitModel.journal[key] !== this.originalSubmitModel.journal[key]) {
                    data[key] = this.submitModel.journal[key]
                }
            }

            if (this.submitModel.journal.id == null) {
                axios.post(this.urls['journal_post'], data)
                    .then( (response) => {
                        this.submitModel.journal = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the journal.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {

                axios.put(this.urls['journal_put'].replace('journal_id', this.submitModel.journal.id), data)
                    .then( (response) => {
                        this.submitModel.journal = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the journal.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['journal_delete'].replace('journal_id', this.submitModel.journal.id))
                .then( (response) => {
                    this.submitModel.journal = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the journal.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['journals_get'])
                .then( (response) => {
                    this.values = response.data
                    this.schema.fields.journal.values = this.values
                    this.model.journal = JSON.parse(JSON.stringify(this.submitModel.journal))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the journal data.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
    }
}
</script>
