<template>
    <panel :header="header">
        <div
            v-for="(identifier, index) in identifiers"
            :key="index"
            class="pbottom-default"
        >
            <h3>{{ identifier.name }}</h3>
            <table
                v-if="model[identifier.systemName]"
                class="table table-striped table-bordered table-hover"
            >
                <thead>
                    <tr>
                        <td v-if="identifier.volumes > 1">Volume</td>
                        <td>Identification</td>
                        <td v-if="identifier.extra">Extra</td>
                        <td>Actions</td>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(identification, identificationIndex) in model[identifier.systemName]"
                        :key="identificationIndex"
                    >
                        <td v-if="identifier.volumes > 1">{{ numberToRoman(identification.volume) }}</td>
                        <td>{{ identification.identification }}</td>
                        <td v-if="identifier.extra">{{ identification.extra }}</td>
                        <td>
                            <a
                                href="#"
                                title="Edit"
                                class="action"
                                @click.prevent="edit(identifier, identification, identificationIndex)"
                            >
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                href="#"
                                title="Delete"
                                class="action"
                                @click.prevent="del(identifier, identificationIndex)"
                            >
                                <i class="fa fa-trash-o" />
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <btn @click="add(identifier)"><i class="fa fa-plus" />&nbsp;Add an identification ({{ identifier.name }})</btn>
        </div>
        <modal
            v-model="editModal"
            size="lg"
            auto-focus
        >
            <vue-form-generator
                ref="editForm"
                :schema="editSchema"
                :model="editModel"
                :options="formOptions"
                @validated="validated"
            />
            <div slot="header">
                <h4
                    v-if="editModel.index"
                    class="modal-title"
                >
                    Edit identification
                </h4>
                <h4
                    v-if="!editModel.index"
                    class="modal-title"
                >
                    Add a new identification
                </h4>
            </div>
            <div slot="footer">
                <btn @click="editModal=false">Cancel</btn>
                <btn
                    type="success"
                    :disabled="!isValid"
                    @click="submit()"
                >
                    {{ editModel.index != null ? 'Update' : 'Add' }}
                </btn>
            </div>
        </modal>
        <modal
            v-model="delModal"
            title="Delete identification"
            auto-focus
        >
            <p>Are you sure you want to delete this identification?</p>
            <div slot="footer">
                <btn @click="delModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="submitDelete()"
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

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator);
Vue.component('panel', Panel);

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    props: {
        identifiers: {
            type: Array,
            default: () => {return []}
        },
        values: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        return {
            delModal: false,
            editModal: false,
            editModel: {
                index: null,
                identifier: null,
                identification: null,
                volume: null,
                extra: null,
                extraRequired: null,
            },
            editSchema: {},
            identificationSchema: {
                type: 'input',
                inputType: 'text',
                label: 'Identification',
                labelClasses: 'control-label',
                model: 'identification',
                validator: VueFormGenerator.validators.regexp,
                required: true,
            },
            extraSchema: {
                type: 'input',
                inputType: 'text',
                label: 'Extra',
                labelClasses: 'control-label',
                model: 'extra',
                validator: VueFormGenerator.validators.string,
                required: false,
            },
        };
    },
    methods: {
        add(identifier) {
            this.editModel.index = null;
            this.editModel.identifier = identifier;
            this.editModel.identification = null;
            this.editModel.volume = null;
            this.editModel.extra = null;
            this.createForm();
        },
        edit(identifier, identification, index) {
            this.editModel.index = index;
            this.editModel.identifier = identifier;
            this.editModel.identification = identification.identification;
            if (identifier.volumes > 1) {
                this.editModel.volume = {id: identification.volume, name: this.numberToRoman(identification.volume)};
            }
            if (identifier.extra) {
                this.editModel.extra = identification.extra;
            }
            this.createForm();
        },
        createForm() {
            this.editSchema = {
                fields: {},
            };

            if (this.editModel.identifier.volumes > 1) {
                this.editSchema.fields.volume = this.createMultiSelect(
                    'Volume',
                    {
                        validator: VueFormGenerator.validators.required,
                        required: true,
                        // Values = [{id: 1, name: 'I'}, {id: 2, name: 'II'}, ...]
                        values: Array.from({length: this.editModel.identifier.volumes}, (x,i) => {return {id: i + 1, name: this.numberToRoman(i + 1),}}),
                    }
                );
                this.enableField(this.editSchema.fields.volume);
            }


            this.editSchema.fields.identification = this.identificationSchema;
            if (this.editModel.identifier.regex) {
                this.editSchema.fields.identification.pattern = this.editModel.identifier.regex;
            }
            else {
                this.editSchema.fields.identification.validator = VueFormGenerator.validators.string;
            }
            if (this.editModel.identifier.description) {
                this.editSchema.fields.identification.hint = this.editModel.identifier.description;
            }

            if (this.editModel.identifier.extra) {
                if (this.editModel.identifier.extraRequired) {
                    this.extraSchema.required = true;
                }
                this.editSchema.fields.extra = this.extraSchema;
            }
            else {
                delete this.editSchema.fields.extra;
            }

            this.editModal = true;
        },
        del(identifier, index) {
            this.editModel.index = index;
            this.editModel.identifier = identifier;
            this.editModel.identification = identification.identification;
            if (identifier.volumes > 1) {
                this.editModel.volume = {id: identification.volume, name: this.numberToRoman(identification.volume)};
            }
            if (identifier.extra) {
                this.editModel.extra = identification.extra;
            }
            this.delModal = true;
        },
        submit() {
            this.$refs.editForm.validate();
            if (this.$refs.editForm.errors.length === 0) {
                // Prepare item to be saved
                let item = JSON.parse(JSON.stringify(this.editModel));
                delete item.index;
                delete item.identifier;
                if (item.volume != null) {
                    item.volume = item.volume.id;
                }

                // Edit existing identification
                if (this.editModel.index != null) {
                    this.model[this.editModel.identifier.systemName][this.editModel.index] = item;
                }
                // Add new identification
                else {
                    if (this.model[this.editModel.identifier.systemName] == null) {
                        this.model[this.editModel.identifier.systemName] = [item];
                    }
                    else {
                        this.model[this.editModel.identifier.systemName].push(item)
                    }
                }
                this.calcChanges();
                this.$emit('validated', 0, null, this);
                this.editModal = false
            }
        },
        submitDelete() {
            this.model[this.editModel.identifier.systemName].splice(this.editModel.index, 1);
            if (this.model[this.editModel.identifier.systemName].length === 0) {
                this.model[this.editModel.identifier.systemName] = undefined;
            }
            this.calcChanges();
            this.$emit('validated', 0, null, this);
            this.delModal = false;
        },
        validate() {},
        calcChanges() {
            this.changes = [];
            for (let key of Object.keys(this.model)) {
                if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key]) && !(this.model[key] == null && this.originalModel[key] == null)) {
                    // identification is regarded as a single item
                    this.changes.push({
                        'key': 'identification',
                        'label': 'Identification',
                        'old': this.displayIdentification(this.originalModel),
                        'new': this.displayIdentification(this.model),
                        'value': this.model,
                    });
                    break
                }
            }
        },
        displayIdentification(object) {
            let result = [];
            for (let key of Object.keys(object)) {
                if (object[key] != null) {
                    for (let identification of object[key]) {
                        let identifier = this.identifiers.filter(identifier => identifier.systemName === key)[0];
                        result.push(identifier.name
                            + ', '
                            + (identifier.volumes > 1 ? this.numberToRoman(identification.volume) + '.' : '')
                            + identification.identification
                            + ((identifier.extra && identification.extra) ? ': "' + identification.extra + '"' : '')
                        );
                    }
                }
            }
            return result;
        },
        numberToRoman(num) {
            let lookup = {M:1000,CM:900,D:500,CD:400,C:100,XC:90,L:50,XL:40,X:10,IX:9,V:5,IV:4,I:1},roman = '',i;
            for ( i in lookup ) {
                while ( num >= lookup[i] ) {
                    roman += i;
                    num -= lookup[i];
                }
            }
            return roman;
        },
    },
}
</script>
