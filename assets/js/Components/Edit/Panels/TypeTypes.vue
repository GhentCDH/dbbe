<template>
    <panel
        :header="header"
        :links="links"
        :reloads="reloads"
        @reload="reload"
    >
        <div>
            <table
                v-if="model.relatedTypes && model.relatedTypes.length > 0"
                class="table table-striped table-bordered table-hover"
            >
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Relation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(relatedType, index) in model.relatedTypes"
                        :key="index"
                    >
                        <td class="greek">{{ relatedType.type.id }} - {{ relatedType.type.name }}</td>
                        <td>{{ relatedType.relationTypes.map(relationType => relationType.name).join(', ') }}</td>
                        <td>
                            <a
                                href="#"
                                title="Edit"
                                class="action"
                                @click.prevent="updateRelatedType(relatedType, index)"
                            >
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                href="#"
                                title="Delete"
                                class="action"
                                @click.prevent="delRelatedType(index)"
                            >
                                <i class="fa fa-trash-o" />
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <btn @click="newRelatedType()"><i class="fa fa-plus" />&nbsp;Add a new related type</btn>
        </div>
        <modal
            v-model="updateRelatedTypeModal"
            title="Edit related type"
            size="lg"
            auto-focus
        >
            <vue-form-generator
                ref="editForm"
                :schema="schema"
                :model="editRelatedType"
                :options="formOptions"
                @validated="validated"
            />
            <div slot="footer">
                <btn @click="updateRelatedTypeModal=false">Cancel</btn>
                <btn
                    type="alert"
                    :disabled="!(isValid && editRelatedType != null && editRelatedType.type != null && editRelatedType.relationTypes != null && editRelatedType.relationTypes.length > 0)"
                    @click="submitUpdateRelatedType()"
                >
                    {{ relatedTypeIndex > -1 ? 'Update' : 'Add' }}
                </btn>
            </div>
        </modal>
        <modal
            v-model="delRelatedTypeModal"
            title="Delete related type"
            auto-focus
        >
            <p>Are you sure you want to delete this related type?</p>
            <div slot="footer">
                <btn @click="delRelatedTypeModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="submitDeleteRelatedType()"
                >
                    Delete
                </btn>
            </div>
        </modal>
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import VueMultiselect from 'vue-multiselect'
import fieldMultiselectClear from '../../FormFields/fieldMultiselectClear'

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    props: {
        values: {
            type: Object,
            default: () => {return {}}
        },
        keys: {
            type: Object,
            default: () => {
                return {
                    types: {field: 'type', init: false},
                    relationTypes: {field: 'relationTypes', init: true},
                };
            },
        },
    },
    data() {
        return {
            updateRelatedTypeModal: false,
            delRelatedTypeModal: false,
            relatedTypeIndex: null,
            editRelatedType: {},
            schema: {
                fields: {
                    type: this.createMultiSelect(
                        'Type',
                        {
                            styleClasses: 'greek',
                            required: true,
                            validator: [VueFormGenerator.validators.required, this.noDuplicateType]
                        },
                        {
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                            internalSearch: false,
                            onSearch: this.greekSearch,
                        }
                    ),
                    relationTypes: this.createMultiSelect(
                        'Relation types',
                        {
                            model: 'relationTypes',
                            required: true,
                            validator: VueFormGenerator.validators.required,
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                        }
                    ),
                }
            },
        }
    },
    methods: {
        validate() {},
        calcChanges() {
            this.changes = []
            for (let key of Object.keys(this.model)) {
                if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key]) && !(this.model[key] == null && this.originalModel[key] == null)) {
                    // related types is regarded as a single item
                    this.changes.push({
                        'key': 'relatedTypes',
                        'label': 'Related types',
                        'old': this.displayRelatedTypes(this.originalModel),
                        'new': this.displayRelatedTypes(this.model),
                        'value': this.model.relatedTypes,
                    })
                    break
                }
            }
        },
        noDuplicateType(value, field, model) {
            if (
                this.relatedTypeIndex === -1
                && this.editRelatedType.type != null
                && this.model.relatedTypes.map(x => x.type.id).includes(this.editRelatedType.type.id)
            ) {
                return ['There already is a relation with this type'];
            }
            return [];
        },
        validated(isValid, errors) {
            this.isValid = isValid;
        },
        newRelatedType() {
            this.relatedTypeIndex = -1
            this.editRelatedType = {
                type: null,
                relationTypes: null,
            }
            this.updateRelatedTypeModal = true
        },
        updateRelatedType(relatedType, index) {
            this.relatedTypeIndex = index
            this.editRelatedType = JSON.parse(JSON.stringify(relatedType))
            this.updateRelatedTypeModal = true
        },
        delRelatedType(index) {
            this.relatedTypeIndex = index
            this.delRelatedTypeModal = true
        },
        submitUpdateRelatedType() {
            this.$refs.editForm.validate()
            if (this.isValid) {
                if (this.relatedTypeIndex > -1) {
                    // update existing
                    this.model.relatedTypes[this.relatedTypeIndex] = JSON.parse(JSON.stringify(this.editRelatedType))
                }
                else {
                    // add new
                    this.model.relatedTypes.push(JSON.parse(JSON.stringify(this.editRelatedType)))
                }
                this.calcChanges()
                this.$emit('validated', 0, null, this)
                this.updateRelatedTypeModal = false
            }
        },
        submitDeleteRelatedType() {
            this.model.relatedTypes.splice(this.relatedTypeIndex, 1);
            this.calcChanges();
            this.$emit('validated', 0, null, this);
            this.delRelatedTypeModal = false;
        },
        displayRelatedTypes(relatedTypes) {
            let result = []
            for (let relatedType of relatedTypes.relatedTypes) {
                result.push('<span class="greek">' + relatedType.type.id + ' - ' + relatedType.type.name + '</span>' + '<br />' + relatedType.relationTypes.map(relationType => relationType.name).join(', '))
            }
            return result
        },
        greekSearch(searchQuery) {
            this.schema.fields.type.values = this.schema.fields.type.originalValues.filter(
                option => this.removeGreekAccents(`${option.id} - ${option.name}`).includes(this.removeGreekAccents(searchQuery))
            );
        },
    }
}
</script>
