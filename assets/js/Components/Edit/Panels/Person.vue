<template>
    <panel
        :header="header"
        :links="links"
        :reloads="reloads"
        @reload="reload"
    >
        <div
            v-for="role in roles"
            :key="role.id"
            class="pbottom-default"
        >
            <vue-form-generator
                ref="forms"
                :key="'form_' + role.systemName"
                :schema="schemas[role.systemName]"
                :model="model"
                :options="formOptions"
                @validated="validated"
            />
            <div
                v-if="occurrencePersonRoles[role.systemName]"
                :key="'occ_' + role.systemName"
                class="small"
            >
                <p>{{ role.name }}(s) provided by occurrences:</p>
                <ul>
                    <li
                        v-for="person in occurrencePersonRoles[role.systemName]"
                        :key="person.id"
                    >
                        {{ person.name }}
                        <ul>
                            <li
                                v-for="(occurrence, index) in person.occurrences"
                                :key="index"
                                class="greek"
                            >
                                {{ occurrence }}
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
            <div
                v-if="role.rank && model[role.systemName] && model[role.systemName].length > 1"
                :key="'order_' + role.systemName"
            >
                <p>
                    <a
                        href="#"
                        class="action"
                        @click.prevent="displayOrder[role.systemName] = !displayOrder[role.systemName]"
                    >
                        <i
                            v-if="displayOrder[role.systemName]"
                            class="fa fa-caret-down"
                        />
                        <i
                            v-else
                            class="fa fa-caret-up"
                        />
                        Change order
                    </a>
                </p>
                <draggable
                    v-if="displayOrder[role.systemName]"
                    v-model="model[role.systemName]"
                    @change="onChange"
                >
                    <transition-group>
                        <div
                            v-for="person in model[role.systemName]"
                            :key="person.id"
                            class="panel panel-default draggable-item"
                        >
                            <div class="panel-body">
                                <i class="fa fa-arrows draggable-icon" />{{ person.name }}
                            </div>
                        </div>
                    </transition-group>
                </draggable>
            </div>
        </div>
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import draggable from 'vuedraggable'

import VueMultiselect from 'vue-multiselect'
import fieldMultiselectClear from '../../FormFields/fieldMultiselectClear'

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)
Vue.component('draggable', draggable)

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    props: {
        header: {
            type: String,
            default: '',
        },
        roles: {
            type: Array,
            default: () => {return []}
        },
        url: {
            type: String,
            default: '',
        },
        occurrencePersonRoles: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        let data = {
            schemas: {},
            refs: {},
            displayOrder: {},
        }
        for (let role of this.roles) {
            data.schemas[role.systemName] = {
                fields: {
                    [role.systemName]: this.createMultiSelect(
                        role.name,
                        {
                            required: role.required,
                            model: role.systemName,
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                        }
                    )
                },
            }
            data.refs[role.systemName] = role.systemName + 'Form'
            data.displayOrder[role.systemName] = false
        }
        return data
    },
    computed: {
        fields() {
            let fields = {}
            for (let role of this.roles) {
                fields[role.systemName] = this.schemas[role.systemName]['fields'][role.systemName]
            }
            return fields
        }
    },
    methods: {
        enableFields(enableKeys) {
            for (let key of Object.keys(this.keys)) {
                if ((this.keys[key].init && enableKeys == null) || (enableKeys != null && enableKeys.includes(key))) {
                    for (let role of this.roles) {
                        this.schemas[role.systemName]['fields'][role.systemName].values = this.values;
                        this.enableField(this.schemas[role.systemName]['fields'][role.systemName]);
                    }
                }
            }
        },
        disableFields(disableKeys) {
            for (let key of Object.keys(this.keys)) {
                if (disableKeys.includes(key)) {
                    for (let role of this.roles) {
                        this.disableField(this.schemas[role.systemName]['fields'][role.systemName]);
                    }
                }
            }
        },
        validate() {
            for (let form of this.$refs.forms) {
                form.validate()
            }
        },
        onChange() {
            this.calcChanges()
            this.$emit('validated')
        }
    }
}
</script>
