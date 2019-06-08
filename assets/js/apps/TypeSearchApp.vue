<template>
    <div>
        <div class="col-xs-12">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
        </div>
        <aside class="col-sm-3">
            <div class="bg-tertiary padding-default">
                <div
                    v-if="JSON.stringify(model) !== JSON.stringify(originalModel)"
                    class="form-group"
                >
                    <button
                        class="btn btn-block"
                        @click="resetAllFilters"
                    >
                        Reset all filters
                    </button>
                </div>
                <div class="form-group">
                    <a :href="urls['help']" class="action" target="_blank"><i class="fa fa-info-circle" /> More information about the text search options.</a>
                </div>
                <vue-form-generator
                    ref="form"
                    :schema="schema"
                    :model="model"
                    :options="formOptions"
                    @model-updated="modelUpdated"
                    @validated="onValidated"
                />
            </div>
        </aside>
        <article class="col-sm-9 search-page">
            <div
                v-if="countRecords"
                class="count-records"
            >
                <h6>{{ countRecords }}</h6>
            </div>
            <div
                v-if="isViewInternal"
                class="collection-select-all top"
            >
                <a
                    href="#"
                    @click.prevent="clearCollection()"
                >
                    clear selection
                </a>
                |
                <a
                    href="#"
                    @click.prevent="collectionToggleAll()"
                >
                    (un)select all on this page
                </a>
            </div>
            <v-server-table
                ref="resultTable"
                :url="urls['types_search_api']"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData"
                @loaded="onLoaded"
            >
                <span
                    slot="text"
                    slot-scope="props"
                >
                    <template v-if="props.row.title">
                        <!-- T for title: T is the 20th letter in the alphabet -->
                        <ol
                            type="A"
                            class="greek"
                        >
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.title"
                                :key="index"
                                value="20"
                                v-html="item"
                            />
                            <!-- eslint-enable -->
                        </ol>
                    </template>
                    <template v-if="props.row.text">
                        <ol class="greek">
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.text"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="item"
                            />
                            <!-- eslint-enable -->
                        </ol>
                    </template>
                </span>
                <template
                    slot="comment"
                    slot-scope="props"
                >
                    <template v-if="props.row.public_comment">
                        <em v-if="isViewInternal">Public</em>
                        <ol>
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.public_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)"
                            />
                            <!-- eslint-enable -->
                        </ol>
                    </template>
                    <template v-if="props.row.private_comment">
                        <em>Private</em>
                        <ol>
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.private_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)"
                            />
                            <!-- eslint-enable -->
                        </ol>
                    </template>
                </template>
                <!-- eslint-disable vue/no-v-html -->
                <a
                    slot="id"
                    slot-scope="props"
                    :href="urls['type_get'].replace('type_id', props.row.id)"
                >
                    {{ props.row.id }}
                </a>
                <a
                    slot="incipit"
                    slot-scope="props"
                    :href="urls['type_get'].replace('type_id', props.row.id)"
                    class="greek"
                    v-html="props.row.incipit"
                />
                <!-- eslint-enable -->
                <template
                    slot="numberOfOccurrences"
                    slot-scope="props"
                >
                    {{ props.row.number_of_occurrences }}
                </template>
                <template
                    slot="created"
                    slot-scope="props"
                >
                    {{ formatDate(props.row.created) }}
                </template>
                <template
                    slot="modified"
                    slot-scope="props"
                >
                    {{ formatDate(props.row.modified) }}
                </template>
                <template
                    slot="actions"
                    slot-scope="props"
                >
                    <a
                        :href="urls['type_edit'].replace('type_id', props.row.id)"
                        class="action"
                        title="Edit"
                    >
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        href="#"
                        class="action"
                        title="Delete"
                        @click.prevent="del(props.row)"
                    >
                        <i class="fa fa-trash-o" />
                    </a>
                </template>
                <template
                    slot="c"
                    slot-scope="props"
                >
                    <span class="checkbox checkbox-primary">
                        <input
                            :id="props.row.id"
                            v-model="collectionArray"
                            :name="props.row.id"
                            :value="props.row.id"
                            type="checkbox"
                        >
                        <label :for="props.row.id" />
                    </span>
                </template>
            </v-server-table>
            <div
                v-if="isViewInternal"
                class="collection-select-all bottom"
            >
                <a
                    href="#"
                    @click.prevent="clearCollection()"
                >
                    clear selection
                </a>
                |
                <a
                    href="#"
                    @click.prevent="collectionToggleAll()"
                >
                    (un)select all on this page
                </a>
            </div>
            <collectionManager
                v-if="isViewInternal"
                :collection-array="collectionArray"
                :managements="managements"
                @addManagementsToSelection="addManagementsToSelection"
                @removeManagementsFromSelection="removeManagementsFromSelection"
                @addManagementsToResults="addManagementsToResults"
                @removeManagementsFromResults="removeManagementsFromResults"
            />
        </article>
        <div class="col-xs-12">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
        </div>
        <deleteModal
            :show="deleteModal"
            :del-dependencies="delDependencies"
            :submit-model="submitModel"
            @cancel="deleteModal=false"
            @confirm="submitDelete()"
        />
        <div
            v-if="openRequests"
            class="loading-overlay"
        >
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

Vue.component('fieldRadio', fieldRadio);

export default {
    mixins: [
        AbstractField,
        AbstractSearch,
    ],
    data() {
        let data = {
            model: {
                text_fields: 'text',
                text_combination: 'all',
            },
            schema: {
                fields: {},
            },
            tableOptions: {
                headings: {
                    text: 'Title (T.) / text (matching verses only)',
                    comment: 'Comment (matching lines only)',
                },
                columnsClasses: {
                    id: 'no-wrap',
                },
                filterable: false,
                orderBy: {
                    'column': 'incipit'
                },
                perPage: 25,
                perPageValues: [25, 50, 100],
                sortable: ['id', 'incipit', 'number_of_occurrences', 'created', 'modified'],
                customFilters: ['filters'],
                requestFunction: AbstractSearch.requestFunction,
                rowClassCallback: function(row) {
                    return (row.public == null || row.public) ? '' : 'warning'
                },
            },
            submitModel: {
                submitType: 'type',
                type: {},
            },
            defaultOrdering: 'incipit',
        };

        // Add fields
        data.schema.fields['text'] = {
            type: 'input',
            inputType: 'text',
            styleClasses: 'greek',
            labelClasses: 'control-label',
            label: 'Text',
            model: 'text'
        };
        if (this.isViewInternal) {
            data.model['text_stem'] = 'original';
            data.schema.fields['text_stem'] = {
                type: 'radio',
                styleClasses: 'has-warning',
                label: 'Stemmer options:',
                labelClasses: 'control-label',
                model: 'text_stem',
                values: [
                    {value: 'original', name: 'Original text'},
                    {value: 'stemmer', name: 'Stemmed text'},
                ],
            };
        }
        data.schema.fields['text_combination'] = {
            type: 'radio',
            label: 'Word combination options:',
            labelClasses: 'control-label',
            model: 'text_combination',
            values: [
                { value: 'all', name: 'Match all words' },
                { value: 'any', name: 'Match any words' },
                { value: 'phrase', name: 'Match only consecutive words (not compatible with wildcards)' },
            ],
        };
        data.schema.fields['text_fields'] = {
            type: 'radio',
            label: 'Which fields should be searched:',
            labelClasses: 'control-label',
            model: 'text_fields',
            values: [
                { value: 'text', name: 'Text only' },
                { value: 'title', name: 'Title only' },
                { value: 'all', name: 'Text and title' },
            ],
        };
        data.schema.fields['person'] = this.createMultiSelect('Person');
        data.schema.fields['role'] = this.createMultiSelect('Role', {dependency: 'person'});
        data.schema.fields['metre'] = this.createMultiSelect('Metre');
        data.schema.fields['genre'] = this.createMultiSelect('Genre');
        data.schema.fields['subject'] = this.createMultiSelect('Subject');
        data.schema.fields['tag'] = this.createMultiSelect('Tag');
        data.schema.fields['comment'] = {
            type: 'input',
            inputType: 'text',
            label: 'Comment',
            labelClasses: 'control-label',
            model: 'comment',
            validator: VueFormGenerator.validators.string,
        };

        // Add identifier fields
        for (let identifier of JSON.parse(this.initIdentifiers)) {
            data.schema.fields[identifier.systemName] = this.createMultiSelect(identifier.name, {model: identifier.systemName})
        }

        data.schema.fields['dbbe'] = this.createMultiSelect(
            'Text source DBBE',
            {
                model: 'dbbe',
            },
            {
                customLabel: ({id, name}) => {
                    return name === 'true' ? 'Yes' : 'No'
                },
            }
        );
        data.schema.fields['acknowledgement'] = this.createMultiSelect('Acknowledgements', {model: 'acknowledgement'});
        data.schema.fields['id'] = this.createMultiSelect('DBBE ID', {model: 'id'});
        data.schema.fields['prev_id'] = this.createMultiSelect('Former DBBE ID', {model: 'prev_id'});
        if (this.isViewInternal) {
            data.schema.fields['text_status'] = this.createMultiSelect(
                'Text Status',
                {
                    model: 'text_status',
                    styleClasses: 'has-warning',
                }
            );
            data.schema.fields['critical_status'] = this.createMultiSelect(
                'Editorial Status',
                {
                    model: 'critical_status',
                    styleClasses: 'has-warning',
                }
            );
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
            );
            data.schema.fields['management'] = this.createMultiSelect(
                'Management collection',
                {
                    model: 'management',
                    styleClasses: 'has-warning',
                }
            );
            data.schema.fields['management_inverse'] = {
                type: 'checkbox',
                styleClasses: 'has-warning',
                label: 'Inverse management collection selection',
                labelClasses: 'control-label',
                model: 'management_inverse',
            }
        }

        return data
    },
    computed: {
        depUrls: function () {
            return {
                'Occurrences': {
                    depUrl: this.urls['occurrence_deps_by_type'].replace('type_id', this.submitModel.type.id),
                    url: this.urls['occurrence_get'],
                    urlIdentifier: 'occurrence_id',
                },
            }
        },
        tableColumns() {
            let columns = ['id', 'incipit', 'number_of_occurrences'];
            if (this.textSearch) {
                columns.unshift('text')
            }
            if (this.commentSearch) {
                columns.unshift('comment')
            }
            if (this.isViewInternal) {
                columns.push('created');
                columns.push('modified');
                columns.push('actions');
                columns.push('c')
            }
            return columns
        },
    },
    methods: {
        del(row) {
            this.submitModel.type = {
                id: row.id,
                name: row.incipit,
            };
            AbstractListEdit.methods.deleteDependencies.call(this)
        },
        submitDelete() {
            this.openRequests++;
            this.deleteModal = false;
            axios.delete(this.urls['type_delete'].replace('type_id', this.submitModel.type.id))
                .then(() => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests--;
                    this.alerts.push({type: 'success', message: 'Type deleted successfully.'})
                })
                .catch((error) => {
                    this.openRequests--;
                    this.alerts.push({type: 'error', message: 'Something went wrong while deleting the type.'});
                    console.log(error)
                })
        },
    }
}
</script>
