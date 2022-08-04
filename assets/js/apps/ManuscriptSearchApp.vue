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
                <h4 v-if="model.city">City:</h4>
                <delete-span v-if="model.city" :name="model.city.name" @deleted="model.city = ''; update()"></delete-span>
                <h4 v-if="model.library">Library:</h4>
                <delete-span v-if="model.library" :name="model.library.name" @deleted="model.library = ''; update()"></delete-span>
                <h4 v-if="model.collection">Collection:</h4>
                <delete-span v-if="model.collection" :name="model.collection.name" @deleted="model.collection = ''; update()"></delete-span>
                <h4 v-if="model.shelf">Shelf number:</h4>
                <delete-span v-if="model.shelf" :name="model.shelf.name" @deleted="model.shelf = ''; update()"></delete-span>
                <h4 v-if="model.diktyon">Diktyon (Pinakes):</h4>
                <delete-span v-if="model.diktyon" :name="model.diktyon.name" @deleted="model.diktyon = ''; update()"></delete-span>
                <h4 v-if="model.content.length">Content:</h4>
                <delete-span v-for="(content1, index) in model.content" :key="index" :name="content1.name" @deleted="model.content.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.person.length">Persons:</h4>
                <delete-span v-for="(person1, index) in model.person" :key="index" :name="person1.name" @deleted="model.person.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.role.length">Roles:</h4>
                <delete-span v-for="(role1, index) in model.role" :key="index" :name="role1.name" @deleted="model.role.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.origin.length">Origins:</h4>
                <delete-span v-for="(origin1, index) in model.origin" :key="index" :name="origin1.name" @deleted="model.origin.splice(index, 1); update()"></delete-span>
                <h4 v-if="model.comment">Comment:</h4>
                <delete-span v-if="model.comment" :name="model.comment" @deleted="model.comment = ''; update()"></delete-span>
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
                :url="urls['manuscripts_search_api']"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData"
                @loaded="onLoaded"
            >
                <template
                    slot="comment"
                    slot-scope="props"
                >
                    <template v-if="props.row.public_comment">
                        <em v-if="isEditor">Public</em>
                        <ol>
                            <li
                                v-for="(item, index) in props.row.public_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)"
                            />
                        </ol>
                    </template>
                    <template v-if="props.row.private_comment">
                        <em>Private</em>
                        <ol>
                            <li
                                v-for="(item, index) in props.row.private_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)"
                            />
                        </ol>
                    </template>
                </template>
                <a
                    slot="name"
                    slot-scope="props"
                    :href="urls['manuscript_get'].replace('manuscript_id', props.row.id)"
                >
                    {{ props.row.name }}
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
                    v-if="props.row.content"
                    slot="content"
                    slot-scope="props"
                >
                    <!-- set displayContent using a v-for -->
                    <!-- eslint-disable-next-line max-len -->
                    <template v-for="(displayContent, index) in [props.row.content.filter((content) => content['display'])]">
                        <ul
                            v-if="displayContent.length > 1"
                            :key="index"
                        >
                            <li
                                v-for="(content, contentIndex) in displayContent"
                                :key="contentIndex"
                            >
                                {{ content.name }}
                            </li>
                        </ul>
                        <template v-else>
                            {{ displayContent[0].name }}
                        </template>
                    </template>
                </template>
                <template
                    slot="occ"
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
                        :href="urls['manuscript_edit'].replace('manuscript_id', props.row.id)"
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
                date_search_type: 'exact',
                person: [],
                role: [],
                content: [],
                content_op: 'or',
                origin: [],
                origin_op: 'or',
                acknowledgement: [],
                acknowledgement_op: 'or',
            },
            schema: {
                fields: {},
            },
            tableOptions: {
                headings: {
                    comment: 'Comment (matching lines only)',
                },
                filterable: false,
                orderBy: {
                    column: 'name',
                },
                perPage: 25,
                perPageValues: [25, 50, 100],
                sortable: ['name', 'date', 'occ', 'created', 'modified'],
                customFilters: ['filters'],
                requestFunction: AbstractSearch.requestFunction,
                rowClassCallback(row) {
                    return (row.public == null || row.public) ? '' : 'warning';
                },
            },
            submitModel: {
                submitType: 'manuscript',
                manuscript: {},
            },
            defaultOrdering: 'name',
        };

        // Add fields
        data.schema.fields.city = this.createMultiSelect('City');
        data.schema.fields.library = this.createMultiSelect('Library', { dependency: 'city' });
        data.schema.fields.collection = this.createMultiSelect('Collection', { dependency: 'library' });
        data.schema.fields.shelf = this.createMultiSelect('Shelf number', { model: 'shelf', dependency: 'collection' });
        // Diktyon identifier
        for (const identifier of JSON.parse(this.initIdentifiers)) {
            if (identifier.systemName === 'diktyon') {
                data.schema.fields[identifier.systemName] = this.createMultiSelect(
                    identifier.name,
                    {
                        model: identifier.systemName,
                    },
                );
            }
        }
        data.schema.fields.year_from = {
            type: 'input',
            inputType: 'number',
            label: 'Year from',
            model: 'year_from',
            min: AbstractSearch.YEAR_MIN,
            max: AbstractSearch.YEAR_MAX,
            validator: VueFormGenerator.validators.number,
        };
        data.schema.fields.year_to = {
            type: 'input',
            inputType: 'number',
            label: 'Year to',
            model: 'year_to',
            min: AbstractSearch.YEAR_MIN,
            max: AbstractSearch.YEAR_MAX,
            validator: VueFormGenerator.validators.number,
        };
        data.schema.fields.date_search_type = {
            type: 'radio',
            label: 'The manuscript date interval must ... the search date interval:',
            labelClasses: 'control-label',
            model: 'date_search_type',
            values: [
                { value: 'exact', name: 'exactly match' },
                { value: 'included', name: 'be included in' },
                { value: 'overlap', name: 'overlap with' },
            ],
        };
        [data.schema.fields.content_op, data.schema.fields.content] = this.createMultiMultiSelect('Content');
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
        [data.schema.fields.origin_op, data.schema.fields.origin] = this.createMultiMultiSelect('Origin');
        data.schema.fields.comment = {
            type: 'input',
            inputType: 'text',
            label: 'Comment',
            model: 'comment',
            validator: VueFormGenerator.validators.string,
        };
        [data.schema.fields.acknowledgement_op, data.schema.fields.acknowledgement] = this.createMultiMultiSelect(
            'Acknowledgements',
            {
                model: 'acknowledgement',
            },
        );

        // Add identifier fields (without Diktyon (added above))
        for (const identifier of JSON.parse(this.initIdentifiers)) {
            if (identifier.systemName !== 'diktyon') {
                data.schema.fields[identifier.systemName] = this.createMultiSelect(
                    identifier.name,
                    {
                        model: identifier.systemName,
                    },
                );
            }
        }

        // Add view internal only fields
        if (this.isViewInternal) {
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
                    depUrl: this.urls.occurrence_deps_by_manuscript.replace(
                        'manuscript_id',
                        this.submitModel.manuscript.id,
                    ),
                    url: this.urls.occurrence_get,
                    urlIdentifier: 'occurrence_id',
                },
            };
        },
        tableColumns() {
            const columns = ['name', 'date', 'content'];
            if (this.commentSearch) {
                columns.unshift('comment');
            }
            if (this.isViewInternal) {
                columns.push('occ');
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
            this.submitModel.manuscript = row;
            AbstractListEdit.methods.deleteDependencies.call(this);
        },
        submitDelete() {
            this.openRequests += 1;
            this.deleteModal = false;
            window.axios.delete(this.urls.manuscript_delete.replace('manuscript_id', this.submitModel.manuscript.id))
                .then((_response) => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'success', message: 'Manuscript deleted successfully.' });
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'error', message: 'Something went wrong while deleting the manuscript.' });
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
