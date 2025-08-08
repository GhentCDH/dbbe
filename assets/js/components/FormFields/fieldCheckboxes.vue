<template lang="pug">
    .wrapper(v-attributes="'wrapper'")
        .checkbox-list
            label(v-for="item in items", :class="getItemCssClasses(item)")
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

<script>
import {
  isObject,
  isNil,
  clone,
} from 'lodash';
import {schema } from 'vue-form-generator';
import { get as objGet, forEach, isFunction, isString, isArray, debounce, uniqueId, uniq as arrayUniq } from "lodash";
import {slugifyFormID} from "@/helpers/slugifyUtil";
import validatorUtil from "@/helpers/validatorUtil";


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
        items() {
            const { values } = this.schema;
            if (typeof values === 'function') {
                return values.apply(this, [this.model, this.schema]);
            } return values;
        },
        selectedCount() {
            if (this.value) return this.value.length;
            return 0;
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
    methods: {
        getInputName(item) {
            if (this.schema && this.schema.inputName && this.schema.inputName.length > 0) {
                return schema.slugify(`${this.schema.inputName}_${this.getItemValue(item)}`);
            }
            return schema.slugify(this.getItemValue(item));
        },
        getItemValue(item) {
            if (isObject(item)) {
                if (typeof this.schema.checklistOptions !== 'undefined'
                && typeof this.schema.checklistOptions.value !== 'undefined') {
                    return item[this.schema.checklistOptions.value];
                }
                if (typeof item.value !== 'undefined') {
                    return item.value;
                }
                throw '`value` is not defined. If you want to use another key name, add a `value` property under `checklistOptions` in the schema. https://icebob.gitbooks.io/vueformgenerator/content/fields/checklist.html#checklist-field-with-object-values';
            } else {
                return item;
            }
        },
        getItemName(item) {
            if (isObject(item)) {
                if (typeof this.schema.checklistOptions !== 'undefined'
                && typeof this.schema.checklistOptions.name !== 'undefined') {
                    return item[this.schema.checklistOptions.name];
                }
                if (typeof item.name !== 'undefined') {
                    return item.name;
                }
                throw '`name` is not defined. If you want to use another key name, add a `name` property under `checklistOptions` in the schema. https://icebob.gitbooks.io/vueformgenerator/content/fields/checklist.html#checklist-field-with-object-values';
            } else {
                return item;
            }
        },
        getItemCssClasses(item) {
            return {
                'is-checked': this.isItemChecked(item),
                'is-disabled': this.isItemDisabled(item),
            };
        },
        isItemChecked(item) {
            return this.value && this.value.indexOf(this.getItemValue(item)) !== -1;
        },
        isItemDisabled(item) {
            if (this.disabled) {
                return true;
            }
            const disabled = item?.disabled ?? false;
            if (typeof disabled === 'function') {
                return disabled(this.model, this.schema, item);
            }
            return disabled;
        },
        onChanged(event, item) {
            if (isNil(this.value) || !Array.isArray(this.value)) {
                this.value = [];
            }
            if (event.target.checked) {
                // Note: If you modify this.value array, it won't trigger the `set` in computed field
                let arr = clone(this.value);
                const that = this;
                // Remove all from same toggle group
                if (item?.toggleGroup) {
                    const valuesToRemove = this.items
                        .filter((i) => i?.toggleGroup === item.toggleGroup)
                        .map((i) => this.getItemValue(i));
                    arr = arr.filter((value) => !valuesToRemove.includes(value));
                }
                arr.push(this.getItemValue(item));
                this.value = arr;
            } else {
                // Note: If you modify this.value array, it won't trigger the `set` in computed field
                let arr = clone(this.value);
                arr = arr.filter((value) => value !== this.getItemValue(item));
                // Add first or same toggle group
                if (item?.toggleGroup) {
                    const toggleItem = this.items.find((i) => i?.toggleGroup === item.toggleGroup
                    && this.getItemValue(i) !== this.getItemValue(item));
                    arr.push(this.getItemValue(toggleItem));
                }
                this.value = arr;
            }
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
        // convert array indexes to properties
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

      getFieldID(schema, unique = false) {
        const idPrefix = objGet(this.formOptions, "fieldIdPrefix", "");
        return slugifyFormID(schema, idPrefix) + (unique ? "-" + uniqueId() : "");
      },

      getFieldClasses() {
        return objGet(this.schema, "fieldClasses", []);
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
<style lang="scss">
</style>
