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
                :url="urls['bibliographies_search_api']"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData"
                @loaded="onLoaded">
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
                <template
                    slot="type"
                    slot-scope="props">
                    {{ props.row.type.name }}
                </template>
                <a
                    slot="title"
                    slot-scope="props"
                    :href="urls[types[props.row.type.id] + '_get'].replace(types[props.row.type.id] + '_id', props.row.id)"
                    v-html="formatTitle(props.row.title)" />
                <template
                    slot="actions"
                    slot-scope="props">
                    <a
                        :href="urls[types[props.row.type.id] + '_edit'].replace(types[props.row.type.id] + '_id', props.row.id)"
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
                title_type: 'any',
            },
            schema: {
                fields: {
                    type: this.createMultiSelect('Type'),
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        model: 'title',
                    },
                    title_type: {
                        type: 'radio',
                        label: 'Title search options:',
                        model: 'title_type',
                        values: [
                            { value: 'any', name: 'Match any words' },
                            { value: 'all', name: 'Match all words' },
                            { value: 'phrase', name: 'Match all words in correct order' },
                        ],
                    },
                    person: this.createMultiSelect('Person'),
                    role: this.createMultiSelect('Role', {dependency: 'person'}),
                    // comment: {
                    //     type: 'input',
                    //     inputType: 'text',
                    //     label: 'Comment',
                    //     model: 'comment',
                    //     validator: VueFormGenerator.validators.string,
                    // },
                }
            },
            tableOptions: {
                headings: {
                    comment: 'Comment (matching lines only)',
                },
                'filterable': false,
                'orderBy': {
                    'column': 'title'
                },
                'perPage': 25,
                'perPageValues': [25, 50, 100],
                'sortable': ['type', 'title'],
                customFilters: ['filters'],
                requestFunction: AbstractSearch.requestFunction,
                rowClassCallback: function(row) {
                    return (row.public == null || row.public) ? '' : 'warning'
                },
            },
            submitModel: {
                type: null,
                article: {},
                book: {},
                book_chapter: {},
                online_source: {},
            },
            defaultOrdering: 'title',
            types: {
                0: 'article',
                1: 'book',
                2: 'book_chapter',
                3: 'online_source',
            }
        }

        // Add identifier fields
        for (let identifier of JSON.parse(this.initIdentifiers)) {
            data.schema.fields[identifier.systemName] = this.createMultiSelect(identifier.name, {model: identifier.systemName})
        }

        // Add view internal only fields
        // if (this.isViewInternal) {
        //     data.schema.fields['public'] = this.createMultiSelect(
        //         'Public',
        //         {
        //             styleClasses: 'has-warning',
        //         },
        //         {
        //             customLabel: ({id, name}) => {
        //                 return name === 'true' ? 'Public only' : 'Internal only'
        //             },
        //         }
        //     )
        // }

        return data
    },
    computed: {
        depUrls: function () {
            return {
                'Manuscripts': {
                    depUrl: this.urls['manuscript_deps_by_' + this.submitModel.type].replace(this.submitModel.type + '_id', this.submitModel[this.submitModel.type].id),
                    url: this.urls['manuscript_get'],
                    urlIdentifier: 'manuscript_id',
                },
                // TODO: occurrence, type, person
            }
        },
        tableColumns() {
            let columns = ['type', 'title']
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
            this.submitModel.type = this.types[row.type.id]
            this.submitModel[this.types[row.type.id]] = row
            this.submitModel[this.types[row.type.id]].name = this.formatTitle(this.submitModel[this.types[row.type.id]].title, true)
            AbstractListEdit.methods.deleteDependencies.call(this)
        },
        submitDelete() {
            this.openRequests++
            this.deleteModal = false
            axios.delete(this.urls[this.submitModel.type + '_delete'].replace(this.submitModel.type + '_id', this.submitModel[this.submitModel.type].id))
                .then((response) => {
                    this.$refs.resultTable.refresh()
                    this.openRequests--
                    this.alerts.push({type: 'success', message: this.submitModel.type.replace(/^\w/, c => c.toUpperCase()) + ' deleted successfully.'})
                })
                .catch((error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while deleting the ' + this.submitModel.type + '.'})
                    console.log(error)
                })
        },
        formatTitle(title, strip = false) {
            if (Array.isArray(title)) {
                if (strip) {
                    return title[0].replace('<mark>', '').replace('</mark>', '')
                }
                else {
                    return title[0]
                }
            }
            else {
                return title
            }
        },
    }
}
</script>