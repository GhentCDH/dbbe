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

        <a href="#"
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
      <div
          v-if="displayOrder && model.types && model.types.length"
          ref="sortableContainer"
      >
        <div
            v-for="(type, index) in model.types"
            :key="type.id"
            :data-index="index"
            class="panel panel-default draggable-item"
        >
          <div class="panel-body">
            <i class="fa fa-arrows draggable-icon" />{{ type.id }} - {{ type.name }}
          </div>
        </div>
      </div>
    </div>
  </panel>
</template>

<script>
import { nextTick } from 'vue';
import Sortable from 'sortablejs';
import {
  createMultiSelect, disableFields, enableFields,
  removeGreekAccents
} from '@/helpers/formFieldUtils';
import {calcChanges} from "@/helpers/modelChangeUtil";

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
      default: () => ({ types: { field: 'types', init: false } }),
    },
  },
  data() {
    return {
      displayOrder: false,
      sortableInstance: null,
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
  watch: {
    displayOrder(newVal) {
      if (newVal) {
        nextTick(() => {
          this.initSortable();
        });
      } else {
        this.destroySortable();
      }
    },
    'model.types': {
      handler() {
        if (this.displayOrder) {
          nextTick(() => {
            this.destroySortable();
            this.initSortable();
          });
        }
      },
      deep: true
    }
  },
  methods: {
    initSortable() {
      if (this.$refs.sortableContainer && !this.sortableInstance && this.model.types && this.model.types.length) {
        this.sortableInstance = new Sortable(this.$refs.sortableContainer, {
          animation: 150,
          handle: '.draggable-icon',
          onEnd: (evt) => {
            const { oldIndex, newIndex } = evt;
            if (oldIndex !== newIndex && this.model.types) {
              const movedItem = this.model.types.splice(oldIndex, 1)[0];
              this.model.types.splice(newIndex, 0, movedItem);
              this.onChange();
            }
          }
        });
      }
    },
    destroySortable() {
      if (this.sortableInstance) {
        this.sortableInstance.destroy();
        this.sortableInstance = null;
      }
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
      this.changes = calcChanges(this.model, this.originalModel, this.fields);
      this.$emit('validated', this.isValid, null, this);
    },
  },
  beforeUnmount() {
    this.destroySortable();
  }
};
</script>

<style scoped>
.draggable-item {
  cursor: move;
  margin-bottom: 10px;
}

.draggable-icon {
  cursor: grab;
  margin-right: 10px;
  color: #999;
}

.draggable-icon:active {
  cursor: grabbing;
}
</style>