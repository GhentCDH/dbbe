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
            <btn
                :disabled="cancelDisabled"
                @click="cancelClick()"
            >Cancel</btn>
            <btn
                :disabled="confirmDisabled"
                type="success"
                data-action="auto-focus"
                @click="confirmClick()"
            >
                Save
            </btn>
        </div>
    </modal>
</template>
<script>

import axios from 'axios'

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
    data () {
        return {
            cancelDisabled: false,
            confirmDisabled: false,
        }
    },
    methods: {
        cancelClick() {
            this.cancelDisabled = true
            setTimeout(() => {
                this.cancelDisabled = false
            }, 1000)
            this.$emit('cancel')
        },
        confirmClick() {
            this.confirmDisabled = true
            setTimeout(() => {
                this.confirmDisabled = false
            }, 1000)
            this.$emit('confirm')
        },
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
