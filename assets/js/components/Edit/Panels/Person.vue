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
import Vue from 'vue';
import VueFormGenerator from 'vue-form-generator'
import draggable from 'vuedraggable'


import {
  createMultiSelect,
  disableField,
  enableField,
} from '@/helpers/formFieldUtils';

import Panel from '../Panel'
import {calcChanges} from "@/helpers/modelChangeUtil";

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)
Vue.component('draggable', draggable)

export default {
    props: {

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
        header: {
          type: String,
          default: '',
        },
        links: {
          type: Array,
          default: () => {return []},
        },
        model: {
          type: Object,
          default: () => {return {}},
        },
        values: {
          type: Array,
          default: () => {return []},
        },
        keys: {
          type: Object,
          default: () => {return {}},
        },
        reloads: {
          type: Array,
          default: () => {return []},
        },
    },
    data() {
        let data = {
            schemas: {},
            refs: {},
            displayOrder: {},
        }
        return {
          ...data,
          changes: [],
          formOptions: {
            validateAfterChanged: true,
            validationErrorClass: 'has-error',
            validationSuccessClass: 'success',
          },
          isValid: true,
          originalModel: {}
        }
    },
    watch: {
      roles: {
        immediate: true,
        handler(newRoles) {
          this.buildSchemas(newRoles);
        }
      }
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
      buildSchemas(roles) {
        let schemas = {};
        let refs = {};
        let displayOrder = {};
        for (let role of roles) {
          schemas[role.systemName] = {
            fields: {
              [role.systemName]: createMultiSelect(
                  role.name,
                  {
                    required: role.required,
                    model: role.systemName,
                  },
                  {
                    multiple: true,
                    closeOnSelect: false,
                    customLabel: ({id, name}) => `${id} - ${name}`,
                  }
              )
            }
          }
          refs[role.systemName] = role.systemName + 'Form';
          displayOrder[role.systemName] = false;
        }
        this.schemas = schemas;
        this.refs = refs;
        this.displayOrder = displayOrder;
      },
      init() {
        this.originalModel = JSON.parse(JSON.stringify(this.model));
        this.enableFields();
      },
        reload(type) {
          if (!this.reloads.includes(type)) {
            this.$emit('reload', type);
          }
        },
        enableFields(enableKeys) {
            for (let key of Object.keys(this.keys)) {
                if ((this.keys[key].init && enableKeys == null) || (enableKeys != null && enableKeys.includes(key))) {
                    for (let role of this.roles) {
                        this.schemas[role.systemName]['fields'][role.systemName].values = this.values;
                        enableField(this.schemas[role.systemName]['fields'][role.systemName]);
                    }
                }
            }
        },
        disableFields(disableKeys) {
            for (let key of Object.keys(this.keys)) {
                if (disableKeys.includes(key)) {
                    for (let role of this.roles) {
                        disableField(this.schemas[role.systemName]['fields'][role.systemName]);
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
          this.changes = calcChanges(this.model, this.originalModel, this.fields);
            this.$emit('validated')
        },
        validated(isValid, errors) {
          this.isValid = isValid
          this.changes = calcChanges(this.model, this.originalModel, this.fields);
          this.$emit('validated', isValid, this.errors, this)
        },
    }
}
</script>

