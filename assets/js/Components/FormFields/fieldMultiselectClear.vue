<template lang="pug">
    div(
        @keyup.esc.stop.prevent="onEscStopPrevent",
    )
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
            :option-height="selectOptions.optionHeight",
        )
            template(slot="clear")
                div.multiselect__clear(
                    v-if="!disabled && value != null",
                    @mousedown.prevent.stop="clearAll()"
                )
            template(slot="caret", slot-scope="props")
                div.multiselect__select(
                    v-if="!disabled && value == null",
                    @mousedown.prevent.stop="props.toggle()"
                )
            template(slot="option", slot-scope="props") {{ getOptionLabel(props.option) }}
                span.badge(
                    v-if="props.option.count != null"
                ) {{ props.option.count }}
</template>
<script>
import { abstractField } from 'vue-form-generator';

function isEmpty(opt) {
    if (opt === 0) return false;
    if (Array.isArray(opt) && opt.length === 0) return true;
    return !opt;
}

export default {
    mixins: [abstractField],
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
            // this will let the multiselect library use the default behavior if customLabel is not specified
            return undefined;
        },
    },
    created() {
        // Check if the component is loaded globally
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
    },
};
</script>
