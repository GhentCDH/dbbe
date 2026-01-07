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
          :ref="el => setFormRef(el, role.systemName)"
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

          <a href="#"
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
            item-key="id"
            @change="onChange"
        >
          <template #item="{ element }">
            <div class="panel panel-default draggable-item">
              <div class="panel-body">
                <i class="fa fa-arrows draggable-icon" />{{ element.name }}
              </div>
            </div>
          </template>
        </draggable>
      </div>
    </div>
  </panel>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import draggable from 'vuedraggable';
import Panel from '../Panel.vue';
import { calcChanges } from '@/helpers/modelChangeUtil';
import {
  createMultiSelect,
  disableField,
  enableField,
} from '@/helpers/formFieldUtils';

const props = defineProps({
  roles: {
    type: Array,
    default: () => [],
  },
  url: {
    type: String,
    default: '',
  },
  occurrencePersonRoles: {
    type: Object,
    default: () => ({}),
  },
  header: {
    type: String,
    default: '',
  },
  links: {
    type: Array,
    default: () => [],
  },
  model: {
    type: Object,
    default: () => ({}),
  },
  values: {
    type: Array,
    default: () => [],
  },
  keys: {
    type: Object,
    default: () => ({}),
  },
  reloads: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['validated', 'reload']);

// Reactive state
const schemas = ref({});
const refs = ref({});
const displayOrder = ref({});
const changes = ref([]);
const isValid = ref(true);
const originalModel = ref({});
const formRefs = ref({});

// Form options
const formOptions = {
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
};

// Computed
const fields = computed(() => {
  const result = {};
  for (const role of props.roles) {
    if (schemas.value[role.systemName]?.fields?.[role.systemName]) {
      result[role.systemName] = schemas.value[role.systemName].fields[role.systemName];
    }
  }
  return result;
});

// Methods
const buildSchemas = (roles) => {
  const newSchemas = {};
  const newRefs = {};
  const newDisplayOrder = {};

  for (const role of roles) {
    newSchemas[role.systemName] = {
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
              customLabel: ({ id, name }) => `${id} - ${name}`,
            }
        ),
      },
    };
    newRefs[role.systemName] = role.systemName + 'Form';
    newDisplayOrder[role.systemName] = false;
  }

  schemas.value = newSchemas;
  refs.value = newRefs;
  displayOrder.value = newDisplayOrder;
};

const setFormRef = (el, systemName) => {
  if (el) {
    formRefs.value[systemName] = el;
  }
};

const init = () => {
  originalModel.value = JSON.parse(JSON.stringify(props.model));
  enableFields();
};

const reload = (type) => {
  if (!props.reloads.includes(type)) {
    emit('reload', type);
  }
};

const enableFields = (enableKeys = null) => {
  for (const key of Object.keys(props.keys)) {
    if (
        (props.keys[key].init && enableKeys == null) ||
        (enableKeys != null && enableKeys.includes(key))
    ) {
      for (const role of props.roles) {
        if (schemas.value[role.systemName]?.fields?.[role.systemName]) {
          schemas.value[role.systemName].fields[role.systemName].values = props.values;
          enableField(schemas.value[role.systemName].fields[role.systemName]);
        }
      }
    }
  }
};

const disableFields = (disableKeys) => {
  for (const key of Object.keys(props.keys)) {
    if (disableKeys.includes(key)) {
      for (const role of props.roles) {
        if (schemas.value[role.systemName]?.fields?.[role.systemName]) {
          disableField(schemas.value[role.systemName].fields[role.systemName]);
        }
      }
    }
  }
};

const validate = () => {
  Object.values(formRefs.value).forEach((form) => {
    if (form && typeof form.validate === 'function') {
      form.validate();
    }
  });
};

const onChange = () => {
  changes.value = calcChanges(props.model, originalModel.value, fields.value);
  emit('validated');
};

const validated = (valid, errors) => {
  isValid.value = valid;
  changes.value = calcChanges(props.model, originalModel.value, fields.value);
  emit('validated', valid, errors, {
    changes: changes.value,
    isValid: isValid.value,
  });
};

// Watch roles to rebuild schemas
watch(
    () => props.roles,
    (newRoles) => {
      if (newRoles && newRoles.length > 0) {
        buildSchemas(newRoles);
      }
    },
    { immediate: true, deep: true }
);

// Expose methods for parent component access
defineExpose({
  validate,
  init,
  reload,
  enableFields,
  disableFields,
  changes,
  isValid,
});
</script>

<style scoped>
.draggable-item {
  cursor: move;
  margin-bottom: 10px;
}

.draggable-icon {
  margin-right: 10px;
  color: #999;
}

.pbottom-default {
  padding-bottom: 1rem;
}
</style>