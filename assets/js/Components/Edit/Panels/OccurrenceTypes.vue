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
                <a
                    href="#"
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
            <draggable
                v-if="displayOrder"
                v-model="model.types"
                @change="onChange"
            >
                <transition-group>
                    <div
                        v-for="type in model.types"
                        :key="type.id"
                        class="panel panel-default draggable-item"
                    >
                        <div class="panel-body">
                            <i class="fa fa-arrows draggable-icon" />{{ type.id }} - {{ type.name }}
                        </div>
                    </div>
                </transition-group>
            </draggable>
        </div>
    </panel>
</template>
<script>
import Vue from 'vue';
import VueFormGenerator from 'vue-form-generator';

import AbstractPanelForm from '../../../mixins/AbstractPanelForm';
import {
  createMultiSelect,
  removeGreekAccents
} from '@/helpers/formFieldUtils';
import Panel from '../Panel.vue';

Vue.use(VueFormGenerator);
Vue.component('panel', Panel);

export default {
    mixins: [
        AbstractPanelForm,
    ],
    props: {
        keys: {
            type: Object,
            default: () => ({ types: { field: 'types', init: false } }),
        },
    },
    data() {
        return {
            displayOrder: false,
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
        };
    },
    methods: {
        greekSearch(searchQuery) {
            this.schema.fields.types.values = this.schema.fields.types.originalValues.filter(
                (option) =>
                    removeGreekAccents(`${option.id} - ${option.name}`)
                    .includes(removeGreekAccents(searchQuery)),
            );
        },
        onChange() {
            this.calcChanges();
            this.$emit('validated');
        },
    },
};
</script>
