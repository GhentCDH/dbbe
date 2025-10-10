<template>
    <panel
        :header="header"
        :links="links"
        :reloads="reloads"
        @reload="reload"
    >
        <vue-form-generator
            ref="form"
            :schema="schema"
            :model="model"
            :options="formOptions"
            @validated="validated"
        />
        <div>
            <p>
                <a
                    href="#"
                    class="action"
                    @click.prevent="displayOrder = !displayOrder"
                >
                    <i
                        v-if="displayOrder"
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
                v-if="displayOrder"
                v-model="model.types"
                @change="onChange"
            >
                <transition-group>
                    <div
                        v-for="type in model.types"
                        :key="type.id"
                        class="panel panel-default draggable-item"
                    >
                        <div class="panel-body">
                            <i class="fa fa-arrows draggable-icon" />{{ type.id }} - {{ type.name }}
                        </div>
                    </div>
                </transition-group>
            </draggable>
        </div>
    </panel>
</template>
<script>
import Vue from 'vue';

import {
  createMultiSelect, disableFields, enableFields,
  removeGreekAccents
} from '@/helpers/formFieldUtils';
import draggable from 'vuedraggable'
import {calcChanges} from "@/helpers/modelChangeUtil";

export default {
  components: {
    draggable,
  },
    props: {
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
        reloads: {
          type: Array,
          default: () => {return []},
        },
        values: {
          type: Array,
          default: () => {return []},
        },
        keys: {
            type: Object,
            default: () => ({ types: { field: 'types', init: false } }),
        },
    },
    data() {
        return {
            displayOrder: false,
            schema: {
                fields: {
                    types: createMultiSelect(
                        'Types',
                        {
                            styleClasses: 'greek',
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                            customLabel: ({ id, name }) => `${id} - ${name}`,
                            internalSearch: false,
                            onSearch: this.greekSearch,
                        },
                    ),
                },
            },
            changes: [],
            formOptions: {
              validateAfterChanged: true,
              validationErrorClass: 'has-error',
              validationSuccessClass: 'success',
            },
            isValid: true,
            originalModel: {}
        };
    },
    computed: {
    fields() {
      return this.schema.fields
    }
  },
    methods: {
        init() {
          this.originalModel = JSON.parse(JSON.stringify(this.model));
          this.enableFields();
        },
        reload(type) {
          if (!this.reloads.includes(type)) {
            this.$emit('reload', type);
          }
        },
        disableFields(disableKeys) {
          disableFields(this.keys, this.fields, disableKeys);
        },
        enableFields(enableKeys) {
          enableFields(this.keys, this.fields, this.values, enableKeys);
        },
        validated(isValid, errors) {
          this.isValid = isValid
          this.changes = calcChanges(this.model, this.originalModel, this.fields);
          this.$emit('validated', isValid, this.errors, this)
        },
        validate() {
          this.$refs.form.validate()
        },
        greekSearch(searchQuery) {
            this.schema.fields.types.values = this.schema.fields.types.originalValues.filter(
                (option) =>
                    removeGreekAccents(`${option.id} - ${option.name}`)
                    .includes(removeGreekAccents(searchQuery)),
            );
        },
        onChange() {
            calcChanges();
            this.$emit('validated');
        },
    },
};
</script>
