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
import { isObject, isNil, clone } from 'lodash';
import { abstractField, schema } from 'vue-form-generator';
export default {
    mixins: [abstractField],
    data() {
        return {
        };
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
    },
};
</script>
<style lang="scss">
</style>
