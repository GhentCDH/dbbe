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
                <div>
                    <delete-span v-for="fieldkey in notEmptyFields" :key="fieldkey" :modelkey="fieldkey" :modelvalue="model[fieldkey]" @deleted="deleteOption"></delete-span>
                </div>
                <!-- <h4 v-if="model.text">Text:</h4>
                <delete-span v-if="model.text" :name="model.text" @deleted="model.text = ''; update()"></delete-span>
                <h4 v-if="model.person.length">Persons:</h4>
                <delete-span v-for="(person1, index) in model.person" :key="index" :name="person1.name" @deleted="model.person.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.role.length">Roles:</h4>
                <delete-span v-for="(role1, index) in model.role" :key="index" :name="role1.name" @deleted="model.role.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.metre.length">Metres:</h4>
                <delete-span v-for="(metre1, index) in model.metre" :key="index" :name="metre1.name" @deleted="model.metre.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.genre.length">Genres:</h4>
                <delete-span v-for="(genre1, index) in model.genre" :key="index" :name="genre1.name" @deleted="model.genre.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.subject.length">Subjects:</h4>
                <delete-span v-for="(subject1, index) in model.subject" :key="index" :name="subject1.name" @deleted="model.subject.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.manuscript_content.length">Manuscript Content:</h4>
                <delete-span v-for="(manu1, index) in model.manuscript_content" :key="index" :name="manu1.name" @deleted="model.manuscript_content.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.comment">Comment:</h4>
                <delete-span v-if="model.comment" :name="model.comment" @deleted="model.comment = ''; update()"></delete-span>
                <h4 v-if="model.acknowledgement.length">Acknowledgements:</h4>
                <delete-span v-for="(ack1, index) in model.acknowledgement" :key="index" :name="ack1.name" @deleted="model.acknowledgement.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.id">DBBE ID:</h4>
                <delete-span v-if="model.id" :name="model.id.name" @deleted="model.id = ''; update()"></delete-span>
                <h4 v-if="model.prev_id">Former DBBE ID:</h4>
                <delete-span v-if="model.prev_id" :name="model.prev_id.name" @deleted="model.prev_id = ''; update()"></delete-span> -->
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
                    >
                        <i class="fa fa-info-circle" />
                        More information about the text search options.
                    </a>
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
                :url="urls['occurrences_search_api']"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData"
                @loaded="onLoaded"
            >
                <span
                    slot="text"
                    slot-scope="props"
                    class="greek"
                >
                    <template v-if="props.row.title">
                        <ol type="A">
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
                        <ol>
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
                    <template v-if="props.row.palaeographical_info">
                        <em>Palaeographical info</em>
                        <ol>
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.palaeographical_info"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)"
                            />
                            <!-- eslint-enable -->
                        </ol>
                    </template>
                    <template v-if="props.row.contextual_info">
                        <em>Contextual info</em>
                        <ol>
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.contextual_info"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)"
                            />
                            <!-- eslint-enable -->
                        </ol>
                    </template>
                    <template v-if="props.row.public_comment">
                        <em v-if="isViewInternal">Public comment</em>
                        <em v-else>Comment</em>
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
                        <em>Private comment</em>
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
                <a
                    slot="id"
                    slot-scope="props"
                    :href="urls['occurrence_get'].replace('occurrence_id', props.row.id)"
                >
                    {{ props.row.id }}
                </a>
                <a
                    slot="incipit"
                    slot-scope="props"
                    :href="urls['occurrence_get'].replace('occurrence_id', props.row.id)"
                    class="greek"
                    v-html="props.row.incipit"
                />
                <a
                    v-if="props.row.manuscript"
                    slot="manuscript"
                    slot-scope="props"
                    :href="urls['manuscript_get'].replace('manuscript_id', props.row.manuscript.id)"
                >
                    {{ props.row.manuscript.name }} ({{ props.row.location }})
                </a>
                <template
                    v-if="props.row.date_floor_year && props.row.date_ceiling_year"
                    slot="date"
                    slot-scope="props"
                >
                    <template v-if="props.row.date_floor_year === props.row.date_ceiling_year">
                        {{ props.row.date_floor_year }}
                    </template>
                    <template v-else>
                        {{ props.row.date_floor_year }} - {{ props.row.date_ceiling_year }}
                    </template>
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
                        :href="urls['occurrence_edit'].replace('occurrence_id', props.row.id)"
                        class="action"
                        title="Edit"
                    >
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        :href="urls['occurrence_edit'].replace('occurrence_id', props.row.id) + '?clone=1'"
                        class="action"
                        title="Duplicate"
                    >
                        <i class="fa fa-files-o" />
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
                date_search_type: 'exact',
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
                manuscript_content: [],
                manuscript_content_op: 'or',
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
                sortable: ['id', 'incipit', 'manuscript', 'date', 'created', 'modified'],
                customFilters: ['filters'],
                requestFunction: AbstractSearch.requestFunction,
                rowClassCallback(row) {
                    return (row.public == null || row.public) ? '' : 'warning';
                },
            },
            submitModel: {
                submitType: 'occurrence',
                occurrence: {},
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
        data.schema.fields.year_from = {
            type: 'input',
            inputType: 'number',
            label: 'Year from',
            labelClasses: 'control-label',
            model: 'year_from',
            min: AbstractSearch.YEAR_MIN,
            max: AbstractSearch.YEAR_MAX,
            validator: VueFormGenerator.validators.number,
        };
        data.schema.fields.year_to = {
            type: 'input',
            inputType: 'number',
            label: 'Year to',
            labelClasses: 'control-label',
            model: 'year_to',
            min: AbstractSearch.YEAR_MIN,
            max: AbstractSearch.YEAR_MAX,
            validator: VueFormGenerator.validators.number,
        };
        data.schema.fields.date_search_type = {
            type: 'radio',
            label: 'The occurrence date interval must ... the search date interval:',
            labelClasses: 'control-label',
            model: 'date_search_type',
            values: [
                { value: 'exact', name: 'exactly match' },
                { value: 'included', name: 'be included in' },
                { value: 'overlap', name: 'overlap with' },
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
        [data.schema.fields.manuscript_content_op, data.schema.fields.manuscript_content] = this.createMultiMultiSelect(
            'Manuscript Content',
            {
                model: 'manuscript_content',
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
        data.schema.fields.dbbe = this.createMultiSelect(
            'Transcribed by DBBE',
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
        data.schema.fields.id = this.createMultiSelect('DBBE ID', { model: 'id' });
        data.schema.fields.prev_id = this.createMultiSelect('Former DBBE ID', { model: 'prev_id' });
        if (this.isViewInternal) {
            data.schema.fields.text_status = this.createMultiSelect(
                'Text Status',
                {
                    model: 'text_status',
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
                Types: {
                    depUrl: this.urls.type_deps_by_occurrence.replace('occurrence_id', this.submitModel.occurrence.id),
                    url: this.urls.type_get,
                    urlIdentifier: 'type_id',
                },
            };
        },
        tableColumns() {
            const columns = ['id', 'incipit', 'manuscript', 'date'];
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
        notEmptyFields() {
            let show = [];
            if (this.model !== undefined) {
                Object.keys(this.model).forEach(key => {
                    let value = this.model[key];
                    if (value !== undefined && Array.isArray(value) && value.length) {
                        show.push(key);
                    } else if (key !== "text_combination" && 
                                key !== "text_fields" && 
                                key !== "date_search_type" && 
                                key !== "year_from" &&
                                key !== "year_to" &&
                                !key.endsWith("_op")) {
                        show.push(key);
                    }
                });
            }
            return show;
        }
    },
    methods: {
        del(row) {
            this.submitModel.occurrence = {
                id: row.id,
                name: row.incipit,
            };
            AbstractListEdit.methods.deleteDependencies.call(this);
        },
        submitDelete() {
            this.openRequests += 1;
            this.deleteModal = false;
            window.axios.delete(this.urls.occurrence_delete.replace('occurrence_id', this.submitModel.occurrence.id))
                .then(() => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'success', message: 'Occurrence deleted successfully.' });
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'error', message: 'Something went wrong while deleting the occurrence.' });
                    console.error(error);
                });
        },
        update() {
            // Don't create a new history item
            this.noHistory = true;
            this.$refs.resultTable.refresh();
        },
        deleteOption({key, value, index}) {
            if (index === -1) {
                this.model[key] = "";
            } else {
                this.model[key].splice(index, 1);
            }
            this.update();
        }
    },
};
</script>
