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
                    v-if="props.row.type.id === 0"
                    slot="title"
                    slot-scope="props"
                    :href="urls['book_get'].replace('book_id', props.row.id)"
                    v-html="formatTitle(props.row.title)" />
                <template
                    slot="actions"
                    slot-scope="props">
                    <a
                        v-if="props.row.type.id === 0"
                        :href="urls['book_edit'].replace('book_id', props.row.id)"
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
                type: 'bibliography',
                bibliography: {},
            },
            defaultOrdering: 'title',
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
                    depUrl: this.urls['manuscript_deps_by_bibliography'].replace('bibliography_id', this.submitModel.bibliography.id),
                    url: this.urls['manuscript_get'],
                    urlIdentifier: 'manuscript_id',
                },
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
            this.submitModel.bibliography = row
            AbstractListEdit.methods.deleteDependencies.call(this)
        },
        submitDelete() {
            this.openRequests++
            this.deleteModal = false
            let url = ''
            switch (this.submitModel.bibliography.type.id) {
            case 0:
                url = this.urls['book_delete'].replace('book_id', this.submitModel.bibliography.id)
                break
            }
            axios.delete(url)
                .then((response) => {
                    this.$refs.resultTable.refresh()
                    this.openRequests--
                    this.alerts.push({type: 'success', message: 'Bibliography deleted successfully.'})
                })
                .catch((error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while deleting the bibliography.'})
                    console.log(error)
                })
        },
        formatTitle(title) {
            if (Array.isArray(title)) {
                return title[0]
            }
            else {
                return title
            }
        },
    }
}
</script>
