<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
            <panel header="Edit offices">
                <editListRow
                    :schema="schema"
                    :model="model"
                    name="office"
                    :conditions="{
                        add: true,
                        edit: model.office,
                        merge: model.office,
                        del: model.office,
                    }"
                    @add="edit(true)"
                    @edit="edit()"
                    @merge="merge()"
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
            @dismiss-alert="editAlerts.splice($event, 1)"
            ref="edit" />
        <mergeModal
            :show="mergeModal"
            :schema="mergeSchema"
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
import VueFormGenerator from 'vue-form-generator'
import axios from 'axios'

import { useListEdit } from '../Components/Edit/AbstractListEdit'
import {createMultiSelect,enableField} from "@/Components/FormFields/formFieldUtils";

export default {
    setup(props) {
      const listEdit = useListEdit(props.initUrls, props.initData)

      return {
        ...listEdit,
      }
    },
    data() {
        let data = JSON.parse(this.initData)
        return {
            values: data.offices,
            regions: data.regions,
            schema: {
                fields: {
                    office: createMultiSelect('Office'),
                },
            },
            editSchema: {
                fields: {
                    individualName: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Office name',
                        labelClasses: 'control-label',
                        model: 'office.individualName',
                        validator: [VueFormGenerator.validators.string, this.nameOrRegionWithParents, this.uniqueName],
                    },
                    individualRegionWithParents: createMultiSelect(
                        'Region',
                        {
                            model: 'office.individualRegionWithParents',
                            validator: [this.nameOrRegionWithParents, this.uniqueRegionWithParents]
                        }
                    ),
                    parent: createMultiSelect('Parent', {model: 'office.parent'}),
                },
            },
            mergeSchema: {
                fields: {
                    primary: createMultiSelect('Primary', {required: true, validator: VueFormGenerator.validators.required}),
                    secondary: createMultiSelect('Secondary', {required: true, validator: VueFormGenerator.validators.required}),
                },
            },
            model: {
                office: null,
            },
            revalidate: false,
            originalSubmitModel: {
                office: null,
            },
            submitModel: {
                submitType: 'office',
                office: null,
            },
            mergeModel: {
                submitType: 'offices',
                primary: null,
                secondary: null,
            },
        }
    },
    computed: {
        depUrls: function() {
            return {
                'Offices': {
                    depUrl: this.urls['office_deps_by_office'].replace('office_id', this.submitModel.office.id),
                },
                'Persons': {
                    depUrl: this.urls['person_deps_by_office'].replace('office_id', this.submitModel.office.id),
                    url: this.urls['person_get'],
                    urlIdentifier: 'person_id',
                }
            }
        },
    },
    watch: {
        'model.office'() {
            // set full parent, so the name can be formatted correctly
            if (this.model.office != null && this.model.office.parent != null) {
                this.model.office.parent = this.values.filter((officeWithParents) => officeWithParents.id === this.model.office.parent.id)[0]
            }
        },
        'submitModel.office.individualName'() {
            if (this.submitModel.office.individualName === '' && this.originalSubmitModel.office.individualName == null) {
                this.submitModel.office.individualName = null;
            }
        }
    },
    mounted () {
        this.schema.fields.office.values = this.values
        enableField(this.schema.fields.office)
    },
    methods: {
        edit(add = false) {
            // TODO: check if name already exists
            this.submitModel = {
                submitType: 'office',
                office: null,
            }
            if (add) {
                this.submitModel.office =  {
                    id: null,
                    individualName: null,
                    individualRegionWithParents: null,
                    parent: this.model.office,
                }
            }
            else {
                this.submitModel.office = JSON.parse(JSON.stringify(this.model.office))
            }
            this.editSchema.fields.individualRegionWithParents.values = this.regions
            enableField(this.editSchema.fields.individualRegionWithParents)
            this.editSchema.fields.parent.values = this.values
                .filter((office) => !this.isOrIsChild(office, this.model.office)) // Remove values that create cycles
            enableField(this.editSchema.fields.parent)
            this.originalSubmitModel = JSON.parse(JSON.stringify(this.submitModel))
            this.editModal = true
        },
        merge() {
            this.mergeModel.primary = JSON.parse(JSON.stringify(this.model.office))
            this.mergeModel.secondary = null
            this.mergeSchema.fields.primary.values = this.values
            this.mergeSchema.fields.secondary.values = this.values
            enableField(this.mergeSchema.fields.primary)
            enableField(this.mergeSchema.fields.secondary)
            this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel))
            this.mergeModal = true
        },
        del() {
            this.submitModel.office = JSON.parse(JSON.stringify(this.model.office))
            this.deleteDependencies()
        },
        submitEdit() {
            this.editModal = false
            this.openRequests++
            if (this.submitModel.office.id == null) {
                axios.post(this.urls['office_post'], {
                    parent: this.submitModel.office.parent == null ? null : {
                        id: this.submitModel.office.parent.id,
                    },
                    individualName: this.submitModel.office.individualName,
                    individualRegionWithParents: this.submitModel.office.individualRegionWithParents == null ? null : {
                        id: this.submitModel.office.individualRegionWithParents.id,
                    },
                })
                    .then( (response) => {
                        this.submitModel.office = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Addition successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while adding the office.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
            else {
                let data = {}
                if (JSON.stringify(this.submitModel.office.parent) !== JSON.stringify(this.originalSubmitModel.office.parent)) {
                    if (this.submitModel.office.parent == null) {
                        data.parent = null
                    }
                    else {
                        data.parent = {
                            id: this.submitModel.office.parent.id
                        }
                    }
                }
                if (this.submitModel.office.individualName !== this.originalSubmitModel.office.individualName) {
                    data.individualName = this.submitModel.office.individualName
                }
                if (JSON.stringify(this.submitModel.office.individualRegionWithParents) !== JSON.stringify(this.originalSubmitModel.office.individualRegionWithParents)) {
                    if (this.submitModel.office.individualRegionWithParents == null) {
                        data.individualRegionWithParents = null
                    }
                    else {
                        data.individualRegionWithParents = {
                            id: this.submitModel.office.individualRegionWithParents.id
                        }
                    }
                }
                axios.put(this.urls['office_put'].replace('office_id', this.submitModel.office.id), data)
                    .then( (response) => {
                        this.submitModel.office = response.data
                        this.update()
                        this.editAlerts = []
                        this.alerts.push({type: 'success', message: 'Update successful.'})
                        this.openRequests--
                    })
                    .catch( (error) => {
                        this.openRequests--
                        this.editModal = true
                        this.editAlerts.push({type: 'error', message: 'Something went wrong while updating the office.', login: this.isLoginError(error)})
                        console.log(error)
                    })
            }
        },
        submitMerge() {
            this.mergeModal = false
            this.openRequests++
            axios.put(this.urls['office_merge'].replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id))
                .then( (response) => {
                    this.submitModel.office = response.data
                    this.update()
                    this.mergeAlerts = []
                    this.alerts.push({type: 'success', message: 'Merge successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.mergeModal = true
                    this.mergeAlerts.push({type: 'error', message: 'Something went wrong while merging the office.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        submitDelete() {
            this.deleteModal = false
            this.openRequests++
            axios.delete(this.urls['office_delete'].replace('office_id', this.submitModel.office.id))
                .then( (response) => {
                    this.submitModel.office = null
                    this.update()
                    this.deleteAlerts = []
                    this.alerts.push({type: 'success', message: 'Deletion successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.deleteModal = true
                    this.deleteAlerts.push({type: 'error', message: 'Something went wrong while deleting the office.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        update() {
            this.openRequests++
            axios.get(this.urls['offices_get'])
                .then( (response) => {
                    this.values = response.data
                    this.schema.fields.office.values = this.values
                    this.model.office = JSON.parse(JSON.stringify(this.submitModel.office))
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while renewing the office data.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        nameOrRegionWithParents(value, field, model) {
            if (!this.revalidate) {
                this.revalidate = true;
                this.$refs.edit.validate();
                this.revalidate = false;
            }
            if (
                ((model.office.individualName == null || model.office.individualName === '') && model.office.individualRegionWithParents == null)
                || ((model.office.individualName !== null && model.office.individualName !== '') && model.office.individualRegionWithParents != null)
            ) {
                return ['Exactly one of the fields "Office Name", "Region" is required.']
            }
            return []
        },
        uniqueName(value, field, model) {
            if (value == null) {
                return []
            }

            let id = model.office.id
            let name = value
            if (model.office.parent != null) {
                name = model.office.parent.name + ' > ' + name
            }

            // Check if there is any other value (different id) with the same name
            if (this.values.filter((value) => value.id !== id && value.name === name).length > 0) {
                return ['An office with this name already exists.']
            }

            return []
        },
        uniqueRegionWithParents(value, field, model) {
            if (value == null) {
                return []
            }

            let id = model.office.id
            // calculate reverse region name
            let name = ' of ' + value.name.split(' > ').reverse().join(' < ');
            if (model.office.parent != null) {
                name = model.office.parent.name + name
            }

            // Check if there is any other value (different id) with the same constructed name
            if (this.values.filter((value) => value.id !== id && value.name === name).length > 0) {
                return ['An office with this region already exists.']
            }

            return []
        },
    }
}
</script>
