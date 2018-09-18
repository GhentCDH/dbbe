<template>
    <panel :header="header">
        <template v-for="role in roles">
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
        </template>
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
        roles: {
            type: Array,
            default: () => {return []}
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
        }
        for (let role of this.roles) {
            data.schemas[role.systemName] = {
                fields: {
                    [role.systemName]: this.createMultiSelect(
                        role.name,
                        {
                            model: role.systemName,
                            values: this.values,
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                        }
                    )
                },
            }
            data.refs[role.systemName] = role.systemName + 'Form'
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
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
            this.enableFields()
        },
        enableFields() {
            for (let role of this.roles) {
                this.enableField(this.schemas[role.systemName]['fields'][role.systemName])
            }
        },
        validate() {
            for (let form of this.$refs.forms) {
                form.validate()
            }
        },
    }
}
</script>
