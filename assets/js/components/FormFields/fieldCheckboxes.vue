<template lang="pug">
  .wrapper(v-attributes="'wrapper'")
    .checkbox-list
      label(v-for="item in items", :key="getItemValue(item)", :class="getItemCssClasses(item)")
        input(
          :id="getFieldID(schema, true)",
          type="checkbox",
          :checked="isItemChecked(item)",
          :disabled="isItemDisabled(item)",
          @change="onChanged($event, item)",
          :name="getInputName(item)",
          v-attributes="'input'"
        )
        | {{ getItemName(item) }}
</template>

<script setup>
import { ref, computed, getCurrentInstance } from 'vue';
import {
  isObject,
  isNil,
  clone,
  get as objGet,
  forEach,
  isFunction,
  isString,
  isArray,
  debounce,
  uniqueId,
  uniq as arrayUniq
} from 'lodash';
import { slugifyFormID } from '@/helpers/slugifyUtil';
import validatorUtil from '@/helpers/validatorUtil';

const props = defineProps({
  vfg: Object,
  model: Object,
  schema: Object,
  formOptions: Object,
  disabled: Boolean
});

const emit = defineEmits(['model-updated', 'validated']);

const instance = getCurrentInstance();
const errors = ref([]);
const debouncedValidateFunc = ref(null);
const debouncedFormatFunc = ref(null);

function convertValidator(validator) {
  if (isString(validator)) {
    if (validatorUtil[validator] != null) return validatorUtil[validator];
    else {
      console.warn(`'${validator}' is not a validator function!`);
      return null;
    }
  }
  return validator;
}

// Custom directive for attributes
const vAttributes = {
  mounted(el, binding) {
    applyAttributes(el, binding);
  },
  updated(el, binding) {
    applyAttributes(el, binding);
  }
};

function applyAttributes(el, binding) {
  let attrs = objGet(props.schema, 'attributes', {});
  let container = binding.value || 'input';
  if (isString(container)) {
    attrs = objGet(attrs, container) || attrs;
  }
  forEach(attrs, (val, key) => {
    el.setAttribute(key, val);
  });
}

const items = computed(() => {
  const { values } = props.schema;
  if (typeof values === 'function') {
    return values.apply(instance.proxy, [props.model, props.schema]);
  }
  return values;
});

const selectedCount = computed(() => {
  if (value.value) return value.value.length;
  return 0;
});

const value = computed({
  get() {
    let val;
    if (isFunction(objGet(props.schema, 'get'))) {
      val = props.schema.get(props.model);
    } else {
      val = objGet(props.model, props.schema.model);
    }
    return formatValueToField(val);
  },
  set(newValue) {
    let oldValue = value.value;
    newValue = formatValueToModel(newValue);

    if (isFunction(newValue)) {
      newValue(newValue, oldValue);
    } else {
      updateModelValue(newValue, oldValue);
    }
  }
});

function getInputName(item) {
  if (props.schema && props.schema.inputName && props.schema.inputName.length > 0) {
    return slugifyFormID({ model: `${props.schema.inputName}_${getItemValue(item)}` });
  }
  return slugifyFormID({ model: getItemValue(item) });
}


function getItemValue(item) {
  if (isObject(item)) {
    if (
        typeof props.schema.checklistOptions !== 'undefined' &&
        typeof props.schema.checklistOptions.value !== 'undefined'
    ) {
      return item[props.schema.checklistOptions.value];
    }
    if (typeof item.value !== 'undefined') {
      return item.value;
    }
    throw '`value` is not defined. If you want to use another key name, add a `value` property under `checklistOptions` in the schema. https://icebob.gitbooks.io/vueformgenerator/content/fields/checklist.html#checklist-field-with-object-values';
  } else {
    return item;
  }
}

function getItemName(item) {
  if (isObject(item)) {
    if (
        typeof props.schema.checklistOptions !== 'undefined' &&
        typeof props.schema.checklistOptions.name !== 'undefined'
    ) {
      return item[props.schema.checklistOptions.name];
    }
    if (typeof item.name !== 'undefined') {
      return item.name;
    }
    throw '`name` is not defined. If you want to use another key name, add a `name` property under `checklistOptions` in the schema. https://icebob.gitbooks.io/vueformgenerator/content/fields/checklist.html#checklist-field-with-object-values';
  } else {
    return item;
  }
}

function getItemCssClasses(item) {
  return {
    'is-checked': isItemChecked(item),
    'is-disabled': isItemDisabled(item)
  };
}

function isItemChecked(item) {
  return value.value && value.value.indexOf(getItemValue(item)) !== -1
      ? true
      : null;
}
function isItemDisabled(item) {
  if (props.disabled) {
    return true;
  }
  const disabled = item?.disabled ?? false;
  if (typeof disabled === 'function') {
    return disabled(props.model, props.schema, item);
  }
  return disabled;
}

function onChanged(event, item) {
  if (isNil(value.value) || !Array.isArray(value.value)) {
    value.value = [];
  }
  if (event.target.checked) {
    let arr = clone(value.value);
    // Remove all from same toggle group
    if (item?.toggleGroup) {
      const valuesToRemove = items.value
          .filter((i) => i?.toggleGroup === item.toggleGroup)
          .map((i) => getItemValue(i));
      arr = arr.filter((val) => !valuesToRemove.includes(val));
    }
    arr.push(getItemValue(item));
    value.value = arr;
  } else {
    let arr = clone(value.value);
    arr = arr.filter((val) => val !== getItemValue(item));
    // Add first of same toggle group
    if (item?.toggleGroup) {
      const toggleItem = items.value.find(
          (i) =>
              i?.toggleGroup === item.toggleGroup &&
              getItemValue(i) !== getItemValue(item)
      );
      if (toggleItem) {
        arr.push(getItemValue(toggleItem));
      }
    }
    value.value = arr;
  }
}

function validate(calledParent) {
  clearValidationErrors();
  let validateAsync = objGet(props.formOptions, 'validateAsync', false);

  let results = [];

  if (props.schema.validator && props.schema.readonly !== true && props.disabled !== true) {
    let validators = [];
    if (!isArray(props.schema.validator)) {
      validators.push(convertValidator(props.schema.validator).bind(instance.proxy));
    } else {
      forEach(props.schema.validator, (validator) => {
        validators.push(convertValidator(validator).bind(instance.proxy));
      });
    }

    forEach(validators, (validator) => {
      if (validateAsync) {
        results.push(validator(value.value, props.schema, props.model));
      } else {
        let result = validator(value.value, props.schema, props.model);
        if (result && isFunction(result.then)) {
          result.then((err) => {
            if (err) {
              errors.value = errors.value.concat(err);
            }
            let isValid = errors.value.length === 0;
            emit('validated', isValid, errors.value, instance.proxy);
          });
        } else if (result) {
          results = results.concat(result);
        }
      }
    });
  }

  let handleErrors = (errs) => {
    let fieldErrors = [];
    forEach(arrayUniq(errs), (err) => {
      if (isArray(err) && err.length > 0) {
        fieldErrors = fieldErrors.concat(err);
      } else if (isString(err)) {
        fieldErrors.push(err);
      }
    });
    if (isFunction(props.schema.onValidated)) {
      props.schema.onValidated.call(instance.proxy, props.model, fieldErrors, props.schema);
    }

    let isValid = fieldErrors.length === 0;
    if (!calledParent) {
      emit('validated', isValid, fieldErrors, instance.proxy);
    }
    errors.value = fieldErrors;
    return fieldErrors;
  };

  if (!validateAsync) {
    return handleErrors(results);
  }

  return Promise.all(results).then(handleErrors);
}

function debouncedValidate() {
  if (!isFunction(debouncedValidateFunc.value)) {
    debouncedValidateFunc.value = debounce(
        validate,
        objGet(props.schema, 'validateDebounceTime', objGet(props.formOptions, 'validateDebounceTime', 500))
    );
  }
  debouncedValidateFunc.value();
}

function updateModelValue(newValue, oldValue) {
  let changed = false;
  if (isFunction(props.schema.set)) {
    props.schema.set(props.model, newValue);
    changed = true;
  } else if (props.schema.model) {
    setModelValueByPath(props.schema.model, newValue);
    changed = true;
  }

  if (changed) {
    emit('model-updated', newValue, props.schema.model);

    if (isFunction(props.schema.onChanged)) {
      props.schema.onChanged.call(instance.proxy, props.model, newValue, oldValue, props.schema);
    }

    if (objGet(props.formOptions, 'validateAfterChanged', false) === true) {
      if (objGet(props.schema, 'validateDebounceTime', objGet(props.formOptions, 'validateDebounceTime', 0)) > 0) {
        debouncedValidate();
      } else {
        validate();
      }
    }
  }
}

function clearValidationErrors() {
  errors.value.splice(0);
}

function setModelValueByPath(path, val) {
  let s = path.replace(/\[(\w+)\]/g, '.$1');
  s = s.replace(/^\./, '');

  let o = props.model;
  const a = s.split('.');
  let i = 0;
  const n = a.length;
  while (i < n) {
    let k = a[i];
    if (i < n - 1) {
      if (o[k] !== undefined) {
        o = o[k];
      } else {
        o[k] = {};
        o = o[k];
      }
    } else {
      o[k] = val;
      return;
    }
    ++i;
  }
}

function getFieldID(sch, unique = false) {
  const idPrefix = objGet(props.formOptions, 'fieldIdPrefix', '');
  return slugifyFormID(sch, idPrefix) + (unique ? '-' + uniqueId() : '');
}

function getFieldClasses() {
  return objGet(props.schema, 'fieldClasses', []);
}

function formatValueToField(val) {
  return val;
}

function formatValueToModel(val) {
  return val;
}

defineExpose({
  validate,
  clearValidationErrors
});
</script>

<style lang="scss" scoped>
</style>