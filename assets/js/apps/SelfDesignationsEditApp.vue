<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <panel header="Edit (self) designations">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="selfDesignation"
                    :conditions="{
                        add: true,
                        edit: model.selfDesignation,
                        del: model.selfDesignation,
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
        return {
            schema: {
                fields: {
                    selfDesignation: this.createMultiSelect(
                        '(Self) designation',
                        {
                            model: 'selfDesignation',
                            styleClasses: 'greek',
                        }
                    ),
                },
            },
            editSchema: {
                fields: {
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Name',
                        labelClasses: 'control-label',
                        model: 'selfDesignation.name',
                        required: true,
                        validator: [VueFormGenerator.validators.regexp],
                        pattern: '^[\\u0370-\\u03ff\\u1f00-\\u1fff ]+$',
                    },
                },
            },
            model: {
                selfDesignation: null,
            },
            submitModel: {
                submitType: 'selfDesignation',
                selfDesignation: {
                    id: null,
                    name: null,
                }
            },
        }
    },
    computed: {
        depUrls: function () {
            return {
                'Persons': {
                    depUrl: this.urls['person_deps_by_self_designation'].replace('self_designation_id', this.submitModel.selfDesignation.id),
                    url: this.urls['person_get'],
                    urlIdentifier: 'person_id',
                },
            }
        },
    },
    mounted () {
        this.schema.fields.selfDesignation.values = this.values;
        this.enableField(this.schema.fields.selfDesignation)
    },
    methods: {
        edit(add = false) {
            // TODO: check if name already exists
            if (add) {
                this.submitModel.selfDesignation =  {
                    id: null,
                    name: null,
                }
            }
            else {
                this.submitModel.selfDesignation = JSON.parse(JSON.stringify(this.model.selfDesignation))
            }
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel));
            this.editModal = true
        },
        del() {
            this.submitModel.selfDesignation = this.model.selfDesignation;
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false;
            this.openRequests++;
            if (this.submitModel.selfDesignation.id == null) {
                axios.post(this.urls['self_designation_post'], {
                    name: this.submitModel.selfDesignation.name,
                })
                    .then( (response) => {
                        this.submitModel.selfDesignation = response.data;
                        this.update();
                        this.editAlerts = [];
                        this.alerts.push({type: 'success', message: 'Addition successful.'});
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--;
                        this.editModal = true;
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the (self) designation.', login: this.isLoginError(error)});
                        console.log(error)
                    })
            }
            else {
                axios.put(this.urls['self_designation_put'].replace('self_designation_id', this.submitModel.selfDesignation.id), {
                    name: this.submitModel.selfDesignation.name,
                })
                    .then( (response) => {
                        this.submitModel.selfDesignation = response.data;
                        this.update();
                        this.editAlerts = [];
                        this.alerts.push({type: 'success', message: 'Update successful.'});
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--;
                        this.editModal = true;
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the (self) designation.', login: this.isLoginError(error)});
                        console.log(error)
                    })
            }
        },
        submitDelete() {
            this.deleteModal = false;
            this.openRequests++;
            axios.delete(this.urls['self_designation_delete'].replace('self_designation_id', this.submitModel.selfDesignation.id))
                .then( (response) => {
                    this.submitModel.selfDesignation = null;
                    this.update();
                    this.deleteAlerts = [];
                    this.alerts.push({type: 'success', message: 'Deletion successful.'});
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.deleteModal = true;
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the (self) designation.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
        update() {
            this.openRequests++;
            axios.get(this.urls['self_designations_get'])
                .then( (response) => {
                    this.values = response.data;
                    this.schema.fields.selfDesignation.values = this.values;
                    this.model.selfDesignation = JSON.parse(JSON.stringify(this.submitModel.selfDesignation));
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the (self) designation data.', login: this.isLoginError(error)});
                    console.log(error)
                })
        },
    }
}
</script>
