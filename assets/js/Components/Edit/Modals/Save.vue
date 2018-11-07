<template>
    <modal
        :value="show"
        :title="'Save ' + title"
        size="lg"
        auto-focus
        @input="$emit('cancel')"
    >
        <alerts
            :alerts="alerts"
            @dismiss="$emit('dismiss-alert', $event)"
        />
        <p>Are you sure you want to save this {{ title }} information?</p>
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th class="col-md-2">Field</th>
                    <th class="col-md-5">Previous value</th>
                    <th class="col-md-5">New value</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in diff"
                    :key="row.keyGroup == null ? row.key : row.keyGroup + '.' + row.key"
                >
                    <td>{{ row['label'] }}</td>
                    <template v-for="key in ['old', 'new']">
                        <td
                            v-if="Array.isArray(row[key])"
                            :key="key"
                            class="word-break"
                        >
                            <ul v-if="row[key].length > 0">
                                <!-- eslint-disable vue/no-v-html -->
                                <li
                                    v-for="(item, index) in row[key]"
                                    :key="index"
                                    v-html="getDisplay(item)"
                                />
                                <!-- eslint-enable -->
                            </ul>
                        </td>
                        <!-- eslint-disable vue/no-v-html -->
                        <td
                            v-else
                            :key="key"
                            v-html="getDisplay(row[key])"
                        />
                        <!-- eslint-enable -->
                    </template>
                </tr>
            </tbody>
        </table>
        <div slot="footer">
            <btn @click="$emit('cancel')">Cancel</btn>
            <btn
                type="success"
                data-action="auto-focus"
                @click="$emit('confirm')"
            >
                Save
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
        title: {
            type: String,
            default: '',
        },
        diff: {
            type: Array,
            default: () => {return []},
        },
        alerts: {
            type: Array,
            default: () => {return []}
        },
    },
    methods: {
        getDisplay(item) {
            if (item == null) {
                return null
            }
            else if (item.hasOwnProperty('name')) {
                return item['name']
            }
            else if (typeof item === 'string') {
                return item.split('\n').join('<br />')
            }
            return item
        },
    },
}
</script>
