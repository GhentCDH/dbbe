<template>
    <panel :header="header">
        <draggable
            v-model="model.occurrenceOrder"
            @change="onChange">
            <transition-group>
                <div
                    class="panel panel-default draggable-item"
                    v-for="occurrence in model.occurrenceOrder"
                    :key="occurrence.id">
                    <div class="panel-body">
                        <i class="fa fa-arrows draggable-icon" />[{{ occurrence.id }}] <span class="greek">{{ occurrence.name }}</span> ({{ occurrence.location}})
                    </div>
                </div>
            </transition-group>
        </draggable>
    </panel>
</template>
<script>
import Vue from 'vue';
import draggable from 'vuedraggable'
import Panel from '../Panel'
import {disableFields, enableFields} from "@/helpers/formFieldUtils";
import {calcChanges} from "@/helpers/modelChangeUtil";

Vue.component('panel', Panel)
Vue.component('draggable', draggable)

export default {
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
      default: () => {return {}},
    },
  },
    computed: {
        fields() {
            return {
                occurrenceOrder: {
                    label: 'Occurrence Order',
                },
            }
        }
    },
    data() {
      return {
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
    methods: {
        validate() {
            calcChanges();
        },
        onChange() {
            calcChanges();
            this.$emit('validated')
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

    }
}
</script>
