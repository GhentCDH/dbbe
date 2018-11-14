<template>
    <panel :header="header">
        <draggable
            v-model="model.occurrenceOrder"
            @change="onChange">
            <transition-group>
                <div
                    class="panel panel-default draggable-item"
                    v-for="occurrence in model.occurrenceOrder"
                    :key="occurrence.id">
                    <div class="panel-body">
                        <i class="fa fa-arrows draggable-icon" />[{{ occurrence.id }}] <span class="greek">{{ occurrence.name }}</span> ({{ occurrence.location}})
                    </div>
                </div>
            </transition-group>
        </draggable>
    </panel>
</template>
<script>
import Vue from 'vue'
import draggable from 'vuedraggable'

import AbstractPanelForm from '../AbstractPanelForm'
import Panel from '../Panel'

Vue.component('panel', Panel)
Vue.component('draggable', draggable)

export default {
    mixins: [
        AbstractPanelForm,
    ],
    computed: {
        fields() {
            return {
                occurrenceOrder: {
                    label: 'Occurrence Order',
                },
            }
        }
    },
    methods: {
        validate() {
            this.calcChanges()
        },
        onChange() {
            this.calcChanges()
            this.$emit('validated')
        }
    }
}
</script>
