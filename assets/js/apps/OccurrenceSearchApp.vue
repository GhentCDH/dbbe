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
                    v-if="JSON.stringify(model) !== JSON.stringify(originalModel)"
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
                :url="occurrencesSearchApiUrl"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData"
                @loaded="onLoaded">
                <template
                    slot="text"
                    slot-scope="props">
                    <ol>
                        <li
                            v-for="(item, index) in props.row.text"
                            :key="index"
                            :value="Number(index) + 1"
                            v-html="item" />
                    </ol>
                </template>
                <template
                    slot="comment"
                    slot-scope="props">
                    <template v-if="props.row.public_comment">
                        <em v-if="isEditor">Public</em>
                        <ol>
                            <li
                                v-for="(item, index) in props.row.public_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="item" />
                        </ol>
                    </template>
                    <template v-if="props.row.private_comment">
                        <em>Private</em>
                        <ol>
                            <li
                                v-for="(item, index) in props.row.private_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="item" />
                        </ol>
                    </template>
                </template>
                <a
                    slot="incipit"
                    slot-scope="props"
                    :href="showOccurrenceUrl.replace('occurrence_id', props.row.id)"
                    v-html="props.row.incipit" />
                <a
                    slot="manuscript"
                    slot-scope="props"
                    :href="showManuscriptUrl.replace('manuscript_id', props.row.manuscript.id)">
                    {{ props.row.manuscript.name }}
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
                    slot="actions"
                    slot-scope="props">
                    <a
                        :href="editOccurrenceUrl.replace('occurrence_id', props.row.id)"
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
            <!-- <div v-if="delDependencies.length !== 0">
                <p>This occurrence has following dependencies that need to be resolved first:</p>
                <ul>
                    <li
                        v-for="dependency in delDependencies"
                        :key="dependency.id">
                        <a :href="getOccurrenceUrl.replace('occurrence_id', dependency.id)">{{ dependency.name }}</a>
                    </li>
                </ul>
            </div> -->
            <!-- <div v-else> -->
                <p>Are you sure you want to delete occurrence "{{ delOccurrence.name }}"?</p>
            <!-- </div> -->
            <div slot="header">
                <h4 class="modal-title">Delete occurrence "{{ delOccurrence.name }}"</h4>
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
import fieldRadio from '../Components/FormFields/fieldRadio'

import Fields from '../Components/Fields'
import Search from '../Components/Search'

Vue.use(uiv)
Vue.use(VueFormGenerator)
Vue.use(VueTables.ServerTable)

Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)
Vue.component('fieldRadio', fieldRadio)

export default {
    mixins: [
        Fields,
        Search,
    ],
    props: {
        occurrencesSearchApiUrl: {
            type: String,
            default: '',
        },
        getOccurrenceDepsByOccurrenceUrl: {
            type: String,
            default: '',
        },
        getOccurrenceUrl: {
            type: String,
            default: '',
        },
        showOccurrenceUrl: {
            type: String,
            default: '',
        },
        editOccurrenceUrl: {
            type: String,
            default: '',
        },
        delOccurrenceUrl: {
            type: String,
            default: '',
        },
        showManuscriptUrl: {
            type: String,
            default: '',
        },
    },
    data() {
        let data = {
            model: {
                text_type: 'any',
            },
            schema: {
                fields: {
                    text: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Text',
                        model: 'text',
                    },
                    text_type: {
                        type: 'radio',
                        label: 'Text search options:',
                        model: 'text_type',
                        values: [
                            { value: 'any', name: 'Match any words' },
                            { value: 'all', name: 'Match all words' },
                            { value: 'phrase', name: 'Match all words in correct order' },
                        ],
                    },
                    meter: this.createMultiSelect('Meter'),
                    subject: this.createMultiSelect('Subject'),
                    manuscript_content: this.createMultiSelect('Manuscript Content', {model: 'manuscript_content'}),
                    patron: this.createMultiSelect('Patron'),
                    scribe: this.createMultiSelect('Scribe'),
                    year_from: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year from',
                        model: 'year_from',
                        min: Search.YEAR_MIN,
                        max: Search.YEAR_MAX,
                        validator: VueFormGenerator.validators.number,
                    },
                    year_to: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year to',
                        model: 'year_to',
                        min: Search.YEAR_MIN,
                        max: Search.YEAR_MAX,
                        validator: VueFormGenerator.validators.number,
                    },
                    genre: this.createMultiSelect('Genre'),
                    comment: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Comment',
                        model: 'comment',
                        validator: VueFormGenerator.validators.string,
                    },
                }
            },
            tableOptions: {
                headings: {
                    text: 'Text (matching verses only)',
                    comment: 'Comment (matching lines only)',
                },
                filterable: false,
                orderBy: {
                    'column': 'incipit'
                },
                perPage: 25,
                perPageValues: [25, 50, 100],
                sortable: ['incipit', 'manuscript', 'date'],
                customFilters: ['filters'],
                requestFunction: Search.requestFunction,
                rowClassCallback: function(row) {
                    return (row.public == null || row.public) ? '' : 'warning'
                },
            },
            delOccurrence: {
                id: 0,
                name: ''
            },
            defaultOrdering: 'incipit',
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
            let columns = ['incipit', 'manuscript', 'date']
            if (this.textSearch) {
                columns.unshift('text')
            }
            if (this.commentSearch) {
                columns.unshift('comment')
            }
            if (this.isEditor) {
                columns.push('actions')
            }
            return columns
        },
    },
    methods: {
        del(row) {
            this.delOccurrence = row
            this.deleteDependencies()
        },
        deleteDependencies() {
            this.openRequests++
            axios.get(this.getOccurrenceDepsByOccurrenceUrl.replace('occurrence_id', this.delOccurrence.id))
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
            axios.delete(this.delOccurrenceUrl.replace('occurrence_id', this.delOccurrence.id))
                .then((response) => {
                    this.$refs.resultTable.refresh()
                    this.openRequests--
                    this.alerts.push({type: 'success', message: 'Occurrence deleted successfully.'})
                })
                .catch((error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the occurrence.'})
                    console.log(error)
                })
        },
    }
}
</script>
