<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit occupations">
                <editListRow
                    :schema="occupationTypeSchema"
                    :model="model" />
                <editListRow
                    :schema="occupationSchema"
                    :model="model"
                    name="origin"
                    :conditions="{
                        add: model.occupationType,
                        edit: model.occupation,
                        del: model.occupation,
                    }"
                    @add="editOccupation(true)"
                    @edit="editOccupation()"
                    @del="delOccupation()" />
            </panel>
            <div
                class="loading-overlay"
                v-if="openRequests">
                <div class="spinner" />
            </div>
        </article>
        <editModal
            :show="editModal"
            :schema="editOccupationSchema"
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
            occupationTypeSchema: {
                fields: {
                    occupationType: this.createMultiSelect('Occupation Type', {model: 'occupationType'}),
                },
            },
            occupationSchema: {
                fields: {
                    occupation: this.createMultiSelect('Occupation', {dependency: 'occupationType', dependencyName: 'occupation type'}),
                },
            },
            editOccupationSchema: {
                fields: {
                    occupationType: this.createMultiSelect('Occupation Type', {model: 'occupationType'}, {loading: false}),
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Occupation name',
                        labelClasses: 'control-label',
                        model: 'occupation.name',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            model: {
                occupationType: null,
                occupation: null,
            },
            submitModel: {
                type: 'occupation',
                occupationType: null,
                occupation: null,
            },
        }
    },
    computed: {
        depUrls: function() {
            return {
                'Persons': {
                    depUrl: this.urls['person_deps_by_occupation'].replace('occupation_id', this.submitModel.occupation.id),
                    url: this.urls['occupation_get'],
                    urlIdentifier: 'occupation_id',
                }
            }
        },
    },
    watch: {
        'model.occupationType'() {
            if (this.model.occupationType == null) {
                this.dependencyField(this.occupationSchema.fields.occupation)
            }
            else {
                this.loadOccupationField()
                this.enableField(this.occupationSchema.fields.occupation)
            }
        },
    },
    mounted () {
        this.loadOccupationTypeField(this.occupationTypeSchema.fields.occupationType)
        this.enableField(this.occupationTypeSchema.fields.occupationType)
        this.dependencyField(this.occupationSchema.fields.occupation)
    },
    methods: {
        editOccupation(add = false) {
            // TODO: check if name already exists
            this.submitModel = {
                type: 'occupation',
                occupationType: null,
                occupation: null,
            }
            this.submitModel.occupationType = JSON.parse(JSON.stringify(this.model.occupationType))
            this.loadOccupationTypeField(this.editOccupationSchema.fields.occupationType)
            if (add) {
                this.submitModel.occupation =  {
                    name: null,
                    isFunction: this.model.occupationType.id === 'functions',
                }
            }
            else {
                this.submitModel.occupation = this.model.occupation
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        delOccupation() {
            this.submitModel.occupation = JSON.parse(JSON.stringify(this.model.occupation))
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.occupation.id == null) {
                axios.post(this.urls['occupation_post'], {
                    name: this.submitModel.occupation.name,
                    isFunction: this.submitModel.occupation.isFunction,
                })
                    .then( (response) => {
                        this.submitModel.occupation = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the occupation.', login: true})
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['occupation_put'].replace('occupation_id', this.submitModel.occupation.id), {
                    name: this.submitModel.occupation.name,
                })
                    .then( (response) => {
                        this.submitModel.occupation = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the occupation.', login: true})
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['occupation_delete'].replace('occupation_id', this.submitModel.occupation.id))
                .then( (response) => {
                    this.submitModel.occupation = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the occupation.', login: true})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['occupations_get'])
                .then( (response) => {
                    this.values = response.data
                    this.loadOccupationField()
                    this.model.occupation = JSON.parse(JSON.stringify(this.submitModel.occupation))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the occupation data.', login: true})
                    console.log(error)
                })
        },
        loadOccupationTypeField(field) {
            field.values = [
                {
                    id: 'types',
                    name: 'Types',
                },
                {
                    id: 'functions',
                    name: 'Functions',
                },
            ]
        },
        loadOccupationField() {
            this.occupationSchema.fields.occupation.values = this.values
                .filter((occupation) => occupation.isFunction === (this.model.occupationType === 'functions'))
        },
    }
}
</script>
