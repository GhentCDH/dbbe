<template lang="pug">
  div(@keyup.esc.stop.prevent="onEscStopPrevent")
    multiselect(
      :id="selectOptions.id",
      :options="options",
      :model-value="value",
      :multiple="selectOptions.multiple",
      :track-by="selectOptions.trackBy || null",
      :label="selectOptions.label || null",
      :searchable="selectOptions.searchable",
      :clear-on-select="selectOptions.clearOnSelect",
      :hide-selected="selectOptions.hideSelected",
      :placeholder="schema.placeholder",
      :allow-empty="selectOptions.allowEmpty",
      :reset-after="selectOptions.resetAfter",
      :close-on-select="selectOptions.closeOnSelect",
      :custom-label="customLabel",
      :taggable="selectOptions.taggable",
      :tag-placeholder="selectOptions.tagPlaceholder",
      :max="schema.max || null",
      :options-limit="selectOptions.optionsLimit",
      :group-values="selectOptions.groupValues",
      :group-label="selectOptions.groupLabel",
      :block-keys="selectOptions.blockKeys",
      :internal-search="selectOptions.internalSearch",
      :select-label="selectOptions.selectLabel",
      :selected-label="selectOptions.selectedLabel",
      :deselect-label="selectOptions.deselectLabel",
      :show-labels="selectOptions.showLabels",
      :limit="selectOptions.limit",
      :limit-text="selectOptions.limitText",
      :loading="selectOptions.loading",
      :disabled="disabled",
      :max-height="selectOptions.maxHeight",
      :show-pointer="selectOptions.showPointer",
      @update:model-value="updateSelected",
      @search-change="onSearchChange",
      @tag="addTag",
      :option-height="selectOptions.optionHeight"
    )
      template(#clear)
        .multiselect__clear(
          v-if="!disabled && value != null",
          @mousedown.prevent.stop="clearAll()"
        )
      template(#caret="props")
        .multiselect__select(
          v-if="!disabled && value == null",
          @mousedown.prevent.stop="props.toggle()"
        )
      template(#option="props") {{ getOptionLabel(props.option) }}
      span.badge(v-if="props.option.count != null") {{ props.option.count }}
</template>

<script setup>
import { ref, computed, getCurrentInstance, onMounted } from 'vue';
import { get as objGet, forEach, isFunction, isString, isArray, debounce, uniq as arrayUniq } from 'lodash';
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

function isEmpty(opt) {
  if (opt === 0) return false;
  if (Array.isArray(opt) && opt.length === 0) return true;
  return !opt;
}

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

const selectOptions = computed(() => props.schema.selectOptions || {});

const options = computed(() => {
  const { values } = props.schema;
  if (typeof values === 'function') {
    return values.apply(instance.proxy, [props.model, props.schema]);
  }
  return values;
});

const customLabel = computed(() => {
  if (
      typeof props.schema.selectOptions !== 'undefined' &&
      typeof props.schema.selectOptions.customLabel !== 'undefined' &&
      typeof props.schema.selectOptions.customLabel === 'function'
  ) {
    return props.schema.selectOptions.customLabel;
  }
  return undefined;
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

onMounted(() => {
  if (!instance.appContext.components.multiselect) {
    console.error(
        "'vue-multiselect' is missing. Please download from https://github.com/monterail/vue-multiselect and register the component globally!"
    );
  }
});

function updateSelected(val, _id) {
  value.value = val;
}

function addTag(newTag, id) {
  const { onNewTag } = selectOptions.value;
  if (typeof onNewTag === 'function') {
    onNewTag(newTag, id, options.value, value.value);
  }
}

function onSearchChange(searchQuery, id) {
  const { onSearch } = selectOptions.value;
  if (typeof onSearch === 'function') {
    onSearch(searchQuery, id, options.value);
  }
}

function clearAll() {
  value.value = null;
}

function customLabelWrapper(option, label) {
  if (customLabel.value !== undefined) {
    return customLabel.value(option, label);
  }
  if (isEmpty(option)) return '';
  return label ? option[label] : option;
}

function getOptionLabel(option) {
  if (isEmpty(option)) return '';
  if (option.isTag) return option.label;
  if (option.$isLabel) return option.$groupLabel;

  const label = customLabelWrapper(option, selectOptions.value.label);
  if (isEmpty(label)) return '';
  return label;
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

function formatValueToField(val) {
  return val;
}

function formatValueToModel(val) {
  return val;
}

function onEscStopPrevent() {
  // Handler for escape key
}

// Expose methods that might be called from parent
defineExpose({
  validate,
  clearValidationErrors
});
</script>