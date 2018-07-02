<template>
    <div>
        <div class="col-xs-12">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
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
        <article class="col-sm-9 search-page">
            <div
                v-if="countRecords"
                class="count-records">
                <h6>{{ countRecords }}</h6>
            </div>
            <v-server-table
                ref="resultTable"
                :url="urls['occurrences_search_api']"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData"
                @loaded="onLoaded">
                <span
                    slot="text"
                    slot-scope="props"
                    class="greek">
                    <template v-if="props.row.title">
                        <ol type="A">
                            <li
                                v-for="(item, index) in props.row.title"
                                :key="index"
                                value="20"
                                v-html="item" />
                        </ol>
                    </template>
                    <template v-if="props.row.text">
                        <ol>
                            <li
                                v-for="(item, index) in props.row.text"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="item" />
                        </ol>
                    </template>
                </span>
                <template
                    slot="comment"
                    slot-scope="props">
                    <template v-if="props.row.public_comment">
                        <em v-if="isViewInternal">Public</em>
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
                    :href="urls['occurrence_get'].replace('occurrence_id', props.row.id)"
                    class="greek"
                    v-html="props.row.incipit" />
                <a
                    v-if="props.row.manuscript"
                    slot="manuscript"
                    slot-scope="props"
                    :href="urls['manuscript_get'].replace('manuscript_id', props.row.manuscript.id)">
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
                        :href="urls['occurrence_edit'].replace('occurrence_id', props.row.id)"
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
        <deleteModal
            :show="deleteModal"
            :del-dependencies="delDependencies"
            :submit-model="submitModel"
            @cancel="deleteModal=false"
            @confirm="submitDelete()" />
        <div
            v-if="openRequests"
            class="loading-overlay">
            <div class="spinner" />
        </div>
    </div>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'

import AbstractField from '../Components/FormFields/AbstractField'
import AbstractSearch from '../Components/Search/AbstractSearch'

// used for deleteDependencies
import AbstractListEdit from '../Components/Edit/AbstractListEdit'

import fieldRadio from '../Components/FormFields/fieldRadio'

Vue.component('fieldRadio', fieldRadio)

export default {
    mixins: [
        AbstractField,
        AbstractSearch,
    ],
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
                        min: AbstractSearch.YEAR_MIN,
                        max: AbstractSearch.YEAR_MAX,
                        validator: VueFormGenerator.validators.number,
                    },
                    year_to: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year to',
                        model: 'year_to',
                        min: AbstractSearch.YEAR_MIN,
                        max: AbstractSearch.YEAR_MAX,
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
                    dbbe: this.createMultiSelect(
                        'Transcribed by DBBE',
                        {
                            model: 'dbbe',
                        },
                        {
                            customLabel: ({id, name}) => {
                                return name === 'true' ? 'Yes' : 'No'
                            },
                        }
                    )
                }
            },
            tableOptions: {
                headings: {
                    text: 'Title (T.) / text (matching verses only)',
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
                requestFunction: AbstractSearch.requestFunction,
                rowClassCallback: function(row) {
                    return (row.public == null || row.public) ? '' : 'warning'
                },
            },
            submitModel: {
                type: 'occurrence',
                occurrence: {},
            },
            defaultOrdering: 'incipit',
        }

        // Add view internal only fields
        if (this.isViewInternal) {
            data.schema.fields['text_status'] = this.createMultiSelect(
                'Text Status',
                {
                    model: 'text_status',
                    styleClasses: 'has-warning',
                }
            )
            data.schema.fields['public'] = this.createMultiSelect(
                'Public',
                {
                    styleClasses: 'has-warning',
                },
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
        depUrls: function () {
            return {
            }
        },
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
            this.submitModel.occurrence = {
                id: row.id,
                name: row.incipit,
            }
            AbstractListEdit.methods.deleteDependencies.call(this)
        },
        submitDelete() {
            this.openRequests++
            this.deleteModal = false
            axios.delete(this.urls['occurrence_delete'].replace('occurrence_id', this.submitModel.occurrence.id))
                .then((response) => {
                    this.$refs.resultTable.refresh()
                    this.openRequests--
                    this.alerts.push({type: 'success', message: 'Occurrence deleted successfully.'})
                })
                .catch((error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while deleting the occurrence.'})
                    console.log(error)
                })
        },
    }
}
</script>
