<template>
    <div class="radio radio-primary">
        <ul class="list-unstyled">
            <li
                v-for="(item, index) in items"
                :key="index">
                <input
                    :id="getItemValue(item)"
                    type="radio"
                    :disabled="disabled"
                    :name="id"
                    @click="onSelection(item)"
                    :value="getItemValue(item)"
                    :checked="isItemChecked(item)"
                    :class="schema.fieldClasses">
                <label
                    @click="onSelection(item)"
                    :for="id">
                    {{ getItemName(item) }}
                </label>
            </li>
        </ul>
    </div>
</template>

<script>
import {abstractField} from 'vue-form-generator'

export default {
    mixins: [ abstractField ],

    computed: {
        items() {
            let values = this.schema.values;
            if (typeof(values) == "function") {
                return values.apply(this, [this.model, this.schema]);
            } else {
                return values;
            }
        },
        id() {
            return this.schema.model;
        }
    },

    methods: {
        isObject(value) {
            const type = typeof value
            return value != null && (type == 'object' || type == 'function')
        },
        getItemValue(item) {
            if (this.isObject(item)){
                if (typeof this.schema["radiosOptions"] !== "undefined" && typeof this.schema["radiosOptions"]["value"] !== "undefined") {
                    return item[this.schema.radiosOptions.value];
                } else {
                    if (typeof item["value"] !== "undefined") {
                        return item.value;
                    } else {
                        throw "`value` is not defined. If you want to use another key name, add a `value` property under `radiosOptions` in the schema. https://icebob.gitbooks.io/vueformgenerator/content/fields/radios.html#radios-field-with-object-values";
                    }
                }
            } else {
                return item;
            }
        },
        getItemName(item) {
            if (this.isObject(item)){
                if (typeof this.schema["radiosOptions"] !== "undefined" && typeof this.schema["radiosOptions"]["name"] !== "undefined") {
                    return item[this.schema.radiosOptions.name];
                } else {
                    if (typeof item["name"] !== "undefined") {
                        return item.name;
                    } else {
                        throw "`name` is not defined. If you want to use another key name, add a `name` property under `radiosOptions` in the schema. https://icebob.gitbooks.io/vueformgenerator/content/fields/radios.html#radios-field-with-object-values";
                    }
                }
            } else {
                return item;
            }
        },
        onSelection(item) {
            this.value = this.getItemValue(item);
        },
        isItemChecked(item) {
            let currentValue = this.getItemValue(item);
            return (currentValue === this.value);
        },
    }
};
</script>
