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
                <h4 v-if="model.text">Text:</h4>
                <delete-span v-if="model.text" :name="model.text" @deleted="model.text = ''; update()"></delete-span>
                <h4 v-if="model.person.length">Persons:</h4>
                <delete-span v-for="(person1, index) in model.person" :key="index" :name="person1.name" @deleted="model.person.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.metre.length">Metres:</h4>
                <delete-span v-for="(metre1, index) in model.metre" :key="index" :name="metre1.name" @deleted="model.metre.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.genre.length">Genres:</h4>
                <delete-span v-for="(genre1, index) in model.genre" :key="index" :name="genre1.name" @deleted="model.genre.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.subject.length">Subjects:</h4>
                <delete-span v-for="(subject1, index) in model.subject" :key="index" :name="subject1.name" @deleted="model.subject.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.tag.length">Tags:</h4>
                <delete-span v-for="(tag1, index) in model.tag" :key="index" :name="tag1.name" @deleted="model.tag.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.translation_language.length">Translations:</h4>
                <delete-span v-for="(translation1, index) in model.translation_language" :key="index" :name="translation1.name" @deleted="model.translation_languagesheee.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.comment">Comment:</h4>
                <delete-span v-if="model.comment" :name="model.comment" @deleted="model.comment = ''; update()"></delete-span>
                <h4 v-if="model.id">DBBE ID:</h4>
                <delete-span v-if="model.id" :name="model.id.name" @deleted="model.id = ''; update()"></delete-span>
                <h4 v-if="model.prev_id">Former DBBE ID:</h4>
                <delete-span v-if="model.prev_id" :name="model.prev_id.name" @deleted="model.prev_id = ''; update()"></delete-span>
                <h4 v-if="model.acknowledgement.length">Acknowledgements:</h4>
                <delete-span v-for="(ack1, index) in model.acknowledgement" :key="index" :name="ack1.name" @deleted="model.acknowledgement.splice(index, 1); update()"></delete-span>
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
                    <a
                        :href="urls['help']"
                        class="action"
                        target="_blank"
                    ><i class="fa fa-info-circle" /> More information about the text search options.</a>
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
        <transition name="fade">
            <div
                v-if="openRequests"
                class="loading-overlay"
            >
                <div class="spinner" />
            </div>
        </transition>
    </div>
</template>
<script>
import Vue from 'vue';
import VueFormGenerator from 'vue-form-generator';

import AbstractField from '../Components/FormFields/AbstractField';
import AbstractSearch from '../Components/Search/AbstractSearch';

// used for deleteDependencies
import AbstractListEdit from '../Components/Edit/AbstractListEdit';

import fieldRadio from '../Components/FormFields/fieldRadio.vue';
import DeleteSpan from '../Components/DeleteSpan.vue';

Vue.component('FieldRadio', fieldRadio);

export default {
  components: { DeleteSpan },
    mixins: [
        AbstractField,
        AbstractSearch,
    ],
    data() {
        const data = {
            model: {
                text_fields: 'text',
                text_combination: 'all',
                person: [],
                role: [],
                metre: [],
                metre_op: 'or',
                genre: [],
                genre_op: 'or',
                subject: [],
                subject_op: 'or',
                tag: [],
                tag_op: 'or',
                translation_language: [],
                translation_language_op: 'or',
                acknowledgement: [],
                acknowledgement_op: 'or',
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
                    column: 'incipit',
                },
                perPage: 25,
                perPageValues: [25, 50, 100],
                sortable: ['id', 'incipit', 'number_of_occurrences', 'created', 'modified'],
                customFilters: ['filters'],
                requestFunction: AbstractSearch.requestFunction,
                rowClassCallback(row) {
                    return (row.public == null || row.public) ? '' : 'warning';
                },
            },
            submitModel: {
                submitType: 'type',
                type: {},
            },
            defaultOrdering: 'incipit',
        };

        // Add fields
        data.schema.fields.text = {
            type: 'input',
            inputType: 'text',
            styleClasses: 'greek',
            labelClasses: 'control-label',
            label: 'Text',
            model: 'text',
        };
        if (this.isViewInternal) {
            data.model.text_stem = 'original';
            data.schema.fields.text_stem = {
                type: 'radio',
                styleClasses: 'has-warning',
                label: 'Stemmer options:',
                labelClasses: 'control-label',
                model: 'text_stem',
                values: [
                    { value: 'original', name: 'Original text' },
                    { value: 'stemmer', name: 'Stemmed text' },
                ],
            };
        }
        data.schema.fields.text_combination = {
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
        data.schema.fields.text_fields = {
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
        data.schema.fields.person = this.createMultiSelect(
            'Person',
            {},
            {
                multiple: true,
                closeOnSelect: false,
            },
        );
        data.schema.fields.role = this.createMultiSelect(
            'Role',
            {
                dependency: 'person',
            },
            {
                multiple: true,
                closeOnSelect: false,
            },
        );
        [data.schema.fields.metre_op, data.schema.fields.metre] = this.createMultiMultiSelect('Metre');
        [data.schema.fields.genre_op, data.schema.fields.genre] = this.createMultiMultiSelect('Genre');
        [data.schema.fields.subject_op, data.schema.fields.subject] = this.createMultiMultiSelect('Subject');
        [data.schema.fields.tag_op, data.schema.fields.tag] = this.createMultiMultiSelect('Tag');
        data.schema.fields.translated = this.createMultiSelect(
            'Translation(s) available?',
            {
                model: 'translated',
            },
            {
                customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No'),
            },
        );
        [
            data.schema.fields.translation_language_op,
            data.schema.fields.translation_language,
        ] = this.createMultiMultiSelect(
            'Translation language',
            {
                model: 'translation_language',
            },
        );
        data.schema.fields.comment = {
            type: 'input',
            inputType: 'text',
            label: 'Comment',
            labelClasses: 'control-label',
            model: 'comment',
            validator: VueFormGenerator.validators.string,
        };

        // Add identifier fields
        for (const identifier of JSON.parse(this.initIdentifiers)) {
            data.schema.fields[identifier.systemName] = this.createMultiSelect(
                identifier.name,
                {
                    model: identifier.systemName,
                },
            );
        }

        data.schema.fields.id = this.createMultiSelect('DBBE ID', { model: 'id' });
        data.schema.fields.prev_id = this.createMultiSelect('Former DBBE ID', { model: 'prev_id' });

        data.schema.fields.dbbe = this.createMultiSelect(
            'Text source DBBE?',
            {
                model: 'dbbe',
            },
            {
                customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No'),
            },
        );
        [data.schema.fields.acknowledgement_op, data.schema.fields.acknowledgement] = this.createMultiMultiSelect(
            'Acknowledgements',
            {
                model: 'acknowledgement',
            },
        );
        if (this.isViewInternal) {
            data.schema.fields.text_status = this.createMultiSelect(
                'Text Status',
                {
                    model: 'text_status',
                    styleClasses: 'has-warning',
                },
            );
            data.schema.fields.critical_status = this.createMultiSelect(
                'Editorial Status',
                {
                    model: 'critical_status',
                    styleClasses: 'has-warning',
                },
            );
            data.schema.fields.public = this.createMultiSelect(
                'Public',
                {
                    styleClasses: 'has-warning',
                },
                {
                    customLabel: ({ _id, name }) => (name === 'true' ? 'Public only' : 'Internal only'),
                },
            );
            data.schema.fields.management = this.createMultiSelect(
                'Management collection',
                {
                    model: 'management',
                    styleClasses: 'has-warning',
                },
            );
            data.schema.fields.management_inverse = {
                type: 'checkbox',
                styleClasses: 'has-warning',
                label: 'Inverse management collection selection',
                labelClasses: 'control-label',
                model: 'management_inverse',
            };
        }

        return data;
    },
    computed: {
        depUrls() {
            return {
                Occurrences: {
                    depUrl: this.urls.occurrence_deps_by_type.replace('type_id', this.submitModel.type.id),
                    url: this.urls.occurrence_get,
                    urlIdentifier: 'occurrence_id',
                },
            };
        },
        tableColumns() {
            const columns = ['id', 'incipit', 'number_of_occurrences'];
            if (this.textSearch) {
                columns.unshift('text');
            }
            if (this.commentSearch) {
                columns.unshift('comment');
            }
            if (this.isViewInternal) {
                columns.push('created');
                columns.push('modified');
                columns.push('actions');
                columns.push('c');
            }
            return columns;
        },
    },
    methods: {
        del(row) {
            this.submitModel.type = {
                id: row.id,
                name: row.incipit,
            };
            AbstractListEdit.methods.deleteDependencies.call(this);
        },
        submitDelete() {
            this.openRequests += 1;
            this.deleteModal = false;
            window.axios.delete(this.urls.type_delete.replace('type_id', this.submitModel.type.id))
                .then((_response) => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'success', message: 'Type deleted successfully.' });
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'error', message: 'Something went wrong while deleting the type.' });
                    console.error(error);
                });
        },
        update() {
            // Don't create a new history item
            this.noHistory = true;
            this.$refs.resultTable.refresh();
        },
    },
};
</script>
