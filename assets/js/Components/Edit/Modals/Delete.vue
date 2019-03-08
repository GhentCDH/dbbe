<template>
    <modal
        :value="show"
        size="lg"
        auto-focus
        @input="$emit('cancel')">
        <alerts
            :alerts="alerts"
            @dismiss="$emit('dismiss-alert', $event)" />
        <div v-if="Object.keys(delDependencies).length !== 0">
            <p>This {{ submitModel.submitType }} has following dependencies that need to be resolved first:</p>
            <template v-for="(dependencyCategory, key) in delDependencies">
                <em :key="'header-' + key">{{ key }}</em>
                <ul :key="'list-' + key">
                    <li
                        v-for="dependency in dependencyCategory.list"
                        :key="dependency.id"
                        :class="{greek: ['Occurrences', 'Types'].includes(key)}">
                        <a
                            v-if="dependencyCategory.url"
                            :href="dependencyCategory.url.replace(dependencyCategory.urlIdentifier, dependency.id)">
                            {{ dependency.name }}
                        </a>
                        <template v-else>
                            {{ dependency.name }}
                        </template>
                    </li>
                </ul>
            </template>
        </div>
        <div v-else-if="submitModel[submitModel.submitType] != null">
            <p>Are you sure you want to delete {{ formatType(submitModel.submitType) }} "<span :class="{greek: ['occurrence', 'type'].includes(submitModel.submitType)}">{{ submitModel[submitModel.submitType].name }}</span>"?</p>
        </div>
        <div slot="header">
            <h4
                v-if="submitModel[submitModel.submitType] != null"
                class="modal-title">
                Delete {{ formatType(submitModel.submitType) }} "{{ submitModel[submitModel.submitType].name }}"
            </h4>
        </div>
        <div slot="footer">
            <btn @click="$emit('cancel')">Cancel</btn>
            <btn
                type="danger"
                :disabled="Object.keys(delDependencies).length !== 0"
                @click="$emit('confirm')">
                Delete
            </btn>
        </div>
    </modal>
</template>
<script>
export default {
    props: {
        show: {
            type: Boolean,
            default: false,
        },
        delDependencies: {
            type: Object,
            default: () => {return {}},
        },
        submitModel: {
            type: Object,
            default: () => {return {}},
        },
        formatType: {
            type: Function,
            default: (type) => {return type},
        },
        alerts: {
            type: Array,
            default: () => {return []}
        },
    },
}
</script>
