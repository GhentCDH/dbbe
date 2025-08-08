<template lang="pug">
div(@keyup.esc.stop.prevent="onEscStopPrevent")
  multiselect(
    :id="selectOptions.id",
    :options="options",
    :value="value",
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
    @input="updateSelected",
    @search-change="onSearchChange",
    @tag="addTag",
    :option-height="selectOptions.optionHeight"
  )
    template(slot="clear")
      .multiselect__clear(
        v-if="!disabled && value != null",
        @mousedown.prevent.stop="clearAll()"
      )
    template(slot="caret", slot-scope="props")
      .multiselect__select(
        v-if="!disabled && value == null",
        @mousedown.prevent.stop="props.toggle()"
      )
    template(slot="option", slot-scope="props") {{ getOptionLabel(props.option) }}
      span.badge(v-if="props.option.count != null") {{ props.option.count }}
</template>
<script>

import { get as objGet, forEach, isFunction, isString, isArray, debounce, uniqueId, uniq as arrayUniq } from "lodash";
import validatorUtil from "@/helpers/validatorUtil";
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
      return null; // caller need to handle null
    }
  }
  return validator;
}

function attributesDirective(el, binding, vnode) {
  let attrs = objGet(vnode.context, "schema.attributes", {});
  let container = binding.value || "input";
  if (isString(container)) {
    attrs = objGet(attrs, container) || attrs;
  }
  forEach(attrs, (val, key) => {
    el.setAttribute(key, val);
  });
}


export default {
    props: ["vfg", "model", "schema", "formOptions", "disabled"],

    data() {
      return {
        errors: [],
        debouncedValidateFunc: null,
        debouncedFormatFunc: null
      };
    },

    directives: {
      attributes: {
        bind: attributesDirective,
        updated: attributesDirective,
        componentUpdated: attributesDirective
      }
    },
    computed: {
        selectOptions() {
            return this.schema.selectOptions || {};
        },

        options() {
            const { values } = this.schema;
            if (typeof (values) === 'function') {
                return values.apply(this, [this.model, this.schema]);
            }
            return values;
        },
        customLabel() {
            if (
                typeof this.schema.selectOptions !== 'undefined'
                && typeof this.schema.selectOptions.customLabel !== 'undefined'
                && typeof this.schema.selectOptions.customLabel === 'function'
            ) {
                return this.schema.selectOptions.customLabel;
            }
            return undefined;
        },
      value: {
        cache: false,
        get() {
          let val;
          if (isFunction(objGet(this.schema, "get"))) {
            val = this.schema.get(this.model);
          } else {
            val = objGet(this.model, this.schema.model);
          }

          return this.formatValueToField(val);
        },

        set(newValue) {
          let oldValue = this.value;
          newValue = this.formatValueToModel(newValue);

          if (isFunction(newValue)) {
            newValue(newValue, oldValue);
          } else {
            this.updateModelValue(newValue, oldValue);
          }
        }
      }
    },
    created() {
        if (!this.$root.$options.components.multiselect) {
            // eslint-disable-next-line max-len
            console.error("'vue-multiselect' is missing. Please download from https://github.com/monterail/vue-multiselect and register the component globally!");
        }
    },
    methods: {
        updateSelected(value, _id) {
            this.value = value;
        },
        addTag(newTag, id) {
            const { onNewTag } = this.selectOptions;
            if (typeof (onNewTag) === 'function') {
                onNewTag(newTag, id, this.options, this.value);
            }
        },
        onSearchChange(searchQuery, id) {
            const { onSearch } = this.selectOptions;
            if (typeof (onSearch) === 'function') {
                onSearch(searchQuery, id, this.options);
            }
        },
        clearAll() {
            this.value = null;
        },
        customLabelWrapper(option, label) {
            if (this.customLabel !== undefined) {
                return this.customLabel(option, label);
            }
            if (isEmpty(option)) return '';
            return label ? option[label] : option;
        },
        getOptionLabel(option) {
            if (isEmpty(option)) return '';
            /* istanbul ignore else */
            if (option.isTag) return option.label;
            /* istanbul ignore else */
            if (option.$isLabel) return option.$groupLabel;

            const label = this.customLabelWrapper(option, this.label);
            /* istanbul ignore else */
            if (isEmpty(label)) return '';
            return label;
        },
      validate(calledParent) {
        this.clearValidationErrors();
        let validateAsync = objGet(this.formOptions, "validateAsync", false);

        let results = [];

        if (this.schema.validator && this.schema.readonly !== true && this.disabled !== true) {
          let validators = [];
          if (!isArray(this.schema.validator)) {
            validators.push(convertValidator(this.schema.validator).bind(this));
          } else {
            forEach(this.schema.validator, validator => {
              validators.push(convertValidator(validator).bind(this));
            });
          }

          forEach(validators, validator => {
            if (validateAsync) {
              results.push(validator(this.value, this.schema, this.model));
            } else {
              let result = validator(this.value, this.schema, this.model);
              if (result && isFunction(result.then)) {
                result.then(err => {
                  if (err) {
                    this.errors = this.errors.concat(err);
                  }
                  let isValid = this.errors.length === 0;
                  this.$emit("validated", isValid, this.errors, this);
                });
              } else if (result) {
                results = results.concat(result);
              }
            }
          });
        }

        let handleErrors = (errors) => {
          let fieldErrors = [];
          forEach(arrayUniq(errors), err => {
            if (isArray(err) && err.length > 0) {
              fieldErrors = fieldErrors.concat(err);
            } else if (isString(err)) {
              fieldErrors.push(err);
            }
          });
          if (isFunction(this.schema.onValidated)) {
            this.schema.onValidated.call(this, this.model, fieldErrors, this.schema);
          }

          let isValid = fieldErrors.length === 0;
          if (!calledParent) {
            this.$emit("validated", isValid, fieldErrors, this);
          }
          this.errors = fieldErrors;
          return fieldErrors;
        };

        if (!validateAsync) {
          return handleErrors(results);
        }

        return Promise.all(results).then(handleErrors);
      },

      debouncedValidate() {
        if (!isFunction(this.debouncedValidateFunc)) {
          this.debouncedValidateFunc = debounce(
              this.validate.bind(this),
              objGet(this.schema, "validateDebounceTime", objGet(this.formOptions, "validateDebounceTime", 500))
          );
        }
        this.debouncedValidateFunc();
      },

      updateModelValue(newValue, oldValue) {
        let changed = false;
        if (isFunction(this.schema.set)) {
          this.schema.set(this.model, newValue);
          changed = true;
        } else if (this.schema.model) {
          this.setModelValueByPath(this.schema.model, newValue);
          changed = true;
        }

        if (changed) {
          this.$emit("model-updated", newValue, this.schema.model);

          if (isFunction(this.schema.onChanged)) {
            this.schema.onChanged.call(this, this.model, newValue, oldValue, this.schema);
          }

          if (objGet(this.formOptions, "validateAfterChanged", false) === true) {
            if (objGet(this.schema, "validateDebounceTime", objGet(this.formOptions, "validateDebounceTime", 0)) > 0) {
              this.debouncedValidate();
            } else {
              this.validate();
            }
          }
        }
      },

      clearValidationErrors() {
        this.errors.splice(0);
      },

      setModelValueByPath(path, value) {
        let s = path.replace(/\[(\w+)\]/g, ".$1");

        // strip a leading dot
        s = s.replace(/^\./, "");

        let o = this.model;
        const a = s.split(".");
        let i = 0;
        const n = a.length;
        while (i < n) {
          let k = a[i];
          if (i < n - 1)
            if (o[k] !== undefined) {
              // Found parent property. Step in
              o = o[k];
            } else {
              // Create missing property (new level)
              this.$root.$set(o, k, {});
              o = o[k];
            }
          else {
            // Set final property value
            this.$root.$set(o, k, value);
            return;
          }

          ++i;
        }
      },

      formatValueToField(value) {
        return value;
      },

      formatValueToModel(value) {
        return value;
      }
    },
};
</script>