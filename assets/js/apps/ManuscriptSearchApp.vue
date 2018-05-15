<template>
    <div>
        <div class="col-xs-12">
            <alert
                v-for="(item, index) in alerts"
                :key="index"
                :type="item.type"
                dismissible
                @dismissed="alerts.splice(index, 1)">
                {{ item.message }}
            </alert>
        </div>
        <aside class="col-sm-3">
            <div class="bg-tertiary padding-default">
                <div
                    v-if="Object.keys(model).length !== 0"
                    class="form-group">
                    <button
                        class="btn btn-block"
                        @click="resetAllFilters">
                        Reset all filters
                    </button>
                </div>
                <vue-form-generator
                    ref="form"
                    :schema="schema"
                    :model="model"
                    :options="formOptions"
                    @model-updated="modelUpdated"
                    @validated="onValidated" />
            </div>
        </aside>
        <article class="col-sm-9">
            <v-server-table
                ref="resultTable"
                :url="manuscriptsSearchApiUrl"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData"
                @loaded="onLoaded">
                <a
                    slot="name"
                    slot-scope="props"
                    :href="showManuscriptUrl.replace('manuscript_id', props.row.id)">
                    {{ props.row.name }}
                </a>
                <template
                    v-if="props.row.date_floor_year && props.row.date_ceiling_year"
                    slot="date"
                    slot-scope="props">
                    <template v-if="props.row.date_floor_year === props.row.date_ceiling_year">
                        {{ props.row.date_floor_year }}
                    </template>
                    <template v-else>
                        {{ props.row.date_floor_year }} - {{ props.row.date_ceiling_year }}
                    </template>
                </template>
                <template
                    v-if="props.row.content"
                    slot="content"
                    slot-scope="props">
                    <template v-for="(displayContent, index) in [props.row.content.filter((content) => content['display'])]">
                        <ul
                            v-if="displayContent.length > 1"
                            :key="index">
                            <li
                                v-for="(content, contentIndex) in displayContent"
                                :key="contentIndex">
                                {{ content.name }}
                            </li>
                        </ul>
                        <template v-else>
                            {{ displayContent[0].name }}
                        </template>
                    </template>
                </template>
                <template
                    slot="actions"
                    slot-scope="props">
                    <a
                        :href="editManuscriptUrl.replace('manuscript_id', props.row.id)"
                        class="action"
                        title="Edit">
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        href="#"
                        class="action"
                        title="Delete"
                        @click.prevent="del(props.row)">
                        <i class="fa fa-trash-o" />
                    </a>
                </template>
            </v-server-table>
        </article>
        <modal
            v-model="delModal"
            auto-focus>
            <div v-if="delDependencies.length !== 0">
                <p>This manuscript has following dependencies that need to be resolved first:</p>
                <ul>
                    <li
                        v-for="dependency in delDependencies"
                        :key="dependency.id">
                        <a :href="getOccurrenceUrl.replace('occurrence_id', dependency.id)">{{ dependency.name }}</a>
                    </li>
                </ul>
            </div>
            <div v-else>
                <p>Are you sure you want to delete manuscript "{{ delManuscript.name }}"?</p>
            </div>
            <div slot="header">
                <h4 class="modal-title">Delete manuscript "{{ delManuscript.name }}"</h4>
            </div>
            <div slot="footer">
                <btn @click="delModal=false">
                    Cancel
                </btn>
                <btn
                    type="danger"
                    :disabled="delDependencies.length !== 0"
                    @click="submitDelete()">
                    Delete
                </btn>
            </div>
        </modal>
        <div
            v-if="openRequests"
            class="loading-overlay">
            <div class="spinner" />
        </div>
    </div>
</template>
<script>
window.axios = require('axios')

import * as uiv from 'uiv'
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import VueMultiselect from 'vue-multiselect'
import VueTables from 'vue-tables-2'

import fieldMultiselectClear from '../Components/FormFields/fieldMultiselectClear'

import Fields from '../Components/Fields'
import Search from '../Components/Search'

Vue.use(uiv)
Vue.use(VueFormGenerator)
Vue.use(VueTables.ServerTable)

Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)

export default {
    mixins: [
        Fields,
        Search,
    ],
    props: {
        manuscriptsSearchApiUrl: {
            type: String,
            default: '',
        },
        getOccurrenceDepsByManuscriptUrl: {
            type: String,
            default: '',
        },
        getOccurrenceUrl: {
            type: String,
            default: '',
        },
        showManuscriptUrl: {
            type: String,
            default: '',
        },
        editManuscriptUrl: {
            type: String,
            default: '',
        },
        delManuscriptUrl: {
            type: String,
            default: '',
        },
    },
    data() {
        let data = {
            schema: {
                fields: {
                    city: this.createMultiSelect('City'),
                    library: this.createMultiSelect('Library', {dependency: 'city'}),
                    collection: this.createMultiSelect('Collection', {dependency: 'library', model: 'collection'}),
                    shelf: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Shelf Number',
                        model: 'shelf'
                    },
                    year_from: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year from',
                        model: 'year_from',
                        min: Search.YEAR_MIN,
                        max: Search.YEAR_MAX,
                        validator: VueFormGenerator.validators.number
                    },
                    year_to: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year to',
                        model: 'year_to',
                        min: Search.YEAR_MIN,
                        max: Search.YEAR_MAX,
                        validator: VueFormGenerator.validators.number
                    },
                    content: this.createMultiSelect('Content'),
                    patron: this.createMultiSelect('Patron'),
                    scribe: this.createMultiSelect('Scribe'),
                    origin: this.createMultiSelect('Origin')
                }
            },
            tableOptions: {
                'filterable': false,
                'orderBy': {
                    'column': 'name'
                },
                'perPage': 25,
                'perPageValues': [25, 50, 100],
                'sortable': ['name', 'date'],
                customFilters: ['filters'],
                requestFunction: Search.requestFunction,
                rowClassCallback: function(row) {
                    return row.public ? '' : 'warning'
                },
            },
            delManuscript: {
                id: 0,
                name: ''
            },
            defaultOrdering: 'name',
        }
        if (this.isEditor) {
            data.schema.fields['public'] = this.createMultiSelect(
                'Public',
                null,
                {
                    customLabel: ({id, name}) => {
                        return name === 'true' ? 'Public only' : 'Internal only'
                    },
                }
            )
        }
        return data
    },
    computed: {
        tableColumns() {
            let columns = ['name', 'date', 'content']
            if (this.isEditor) {
                columns.push('actions')
            }
            return columns
        },
    },
    methods: {
        del(row) {
            this.delManuscript = row
            this.deleteDependencies()
        },
        deleteDependencies() {
            this.openRequests++
            axios.get(this.getOccurrenceDepsByManuscriptUrl.replace('manuscript_id', this.delManuscript.id))
                .then((response) => {
                    this.delDependencies = response.data
                    this.delModal = true
                    this.openRequests--
                })
                .catch((error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while checking for dependencies.'})
                    console.log(error)
                })
        },
        submitDelete() {
            this.openRequests++
            this.delModal = false
            axios.delete(this.delManuscriptUrl.replace('manuscript_id', this.delManuscript.id))
                .then((response) => {
                    this.$refs.resultTable.refresh()
                    this.openRequests--
                    this.alerts.push({type: 'success', message: 'Manuscript deleted successfully.'})
                })
                .catch((error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the manuscript.'})
                    console.log(error)
                })
        },
    }
}
</script>
