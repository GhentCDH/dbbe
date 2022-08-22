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
            <active-filters
                :filters="notEmptyFields"
                class="active-filters"
                @resetFilters="resetAllFilters"
                @deletedActiveFilter="deleteActiveFilter"
            />
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
                :url="urls['persons_search_api']"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData"
                @loaded="onLoaded"
            >
                <template
                    slot="h__self_designation"
                >
                    (Self) designation
                </template>
                <template
                    slot="comment"
                    slot-scope="props"
                >
                    <template v-if="props.row.public_comment">
                        <em v-if="isEditor">Public</em>
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
                <template
                    slot="name"
                    slot-scope="props"
                >
                    <a
                        v-if="props.row.name.constructor !== Array"
                        :href="urls['person_get'].replace('person_id', props.row.id)"
                    >
                        {{ props.row.name }}
                    </a>
                    <template v-else>
                        <!-- eslint-disable vue/no-v-html -->
                        <a
                            v-if="props.row.name.length === 1"
                            :href="urls['person_get'].replace('person_id', props.row.id)"
                            v-html="props.row.name[0]"
                        />
                        <!-- eslint-enable -->
                        <ul v-else>
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.name"
                                :key="index"
                                v-html="item"
                            />
                            <!-- eslint-enable -->
                        </ul>
                    </template>
                </template>
                <template
                    v-if="hasIdentification(props.row)"
                    slot="identification"
                    slot-scope="props"
                >
                    {{ formatIdentification(props.row) }}
                </template>
                <template
                    v-if="props.row.self_designation"
                    slot="self_designation"
                    slot-scope="props"
                >
                    <ul v-if="props.row.self_designation.length > 1">
                        <li
                            v-for="(self_designation, index) in props.row.self_designation"
                            :key="index"
                            class="greek"
                        >
                            {{ self_designation.name }}
                        </li>
                    </ul>
                    <template v-else>
                        <span class="greek">{{ props.row.self_designation[0].name }}</span>
                    </template>
                </template>
                <template
                    v-if="props.row.office"
                    slot="office"
                    slot-scope="props"
                >
                    <!-- set displayContent using a v-for -->
                    <!-- eslint-disable-next-line max-len -->
                    <template v-for="(displayOffice, index) in [props.row.office.filter((office) => office['display'])]">
                        <ul
                            v-if="displayOffice.length > 1"
                            :key="index"
                        >
                            <li
                                v-for="(office, officeIndex) in displayOffice"
                                :key="officeIndex"
                            >
                                {{ office.name }}
                            </li>
                        </ul>
                        <template v-else>
                            {{ displayOffice[0].name }}
                        </template>
                    </template>
                </template>
                <!-- eslint-disable max-len -->
                <template
                    v-if="props.row.born_date_floor_year || props.row.born_date_ceiling_year || props.row.death_date_floor_year || props.row.death_date_ceiling_year"
                    slot="date"
                    slot-scope="props"
                >
                    {{ formatInterval(props.row.born_date_floor_year, props.row.born_date_ceiling_year, props.row.death_date_floor_year, props.row.death_date_ceiling_year) }}
                    <!-- eslint-enable max-len -->
                </template>
                <template
                    v-if="props.row.death_date_floor_year && props.row.death_date_ceiling_year"
                    slot="deathdate"
                    slot-scope="props"
                >
                    <template v-if="props.row.death_date_floor_year === props.row.death_date_ceiling_year">
                        {{ props.row.death_date_floor_year }}
                    </template>
                    <template v-else>
                        {{ props.row.death_date_floor_year }} - {{ props.row.death_date_ceiling_year }}
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
                        :href="urls['person_edit'].replace('person_id', props.row.id)"
                        class="action"
                        title="Edit"
                    >
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        href="#"
                        class="action"
                        title="Merge"
                        @click.prevent="merge(props.row)"
                    >
                        <i class="fa fa-compress" />
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
        <mergeModal
            :show="mergeModal"
            :schema="mergePersonSchema"
            :merge-model="mergeModel"
            :original-merge-model="originalMergeModel"
            :alerts="mergeAlerts"
            @cancel="cancelMerge()"
            @reset="resetMerge()"
            @confirm="submitMerge()"
            @dismiss-alert="mergeAlerts.splice($event, 1)"
        >
            <table
                v-if="mergeModel.primaryFull && mergeModel.secondaryFull"
                slot="preview"
                class="table table-striped table-hover"
            >
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>First Name</td>
                        <td>{{ mergeModel.primaryFull.firstName || mergeModel.secondaryFull.firstName }}</td>
                    </tr>
                    <tr>
                        <td>Last Name</td>
                        <td>{{ mergeModel.primaryFull.lastName || mergeModel.secondaryFull.lastName }}</td>
                    </tr>
                    <tr>
                        <td>Extra</td>
                        <td>{{ mergeModel.primaryFull.extra || mergeModel.secondaryFull.extra }}</td>
                    </tr>
                    <tr>
                        <td>Unprocessed</td>
                        <!-- eslint-disable-next-line max-len -->
                        <td>{{ (mergeModel.primaryFull.firstName || mergeModel.secondaryFull.firstName || mergeModel.primaryFull.lastName || mergeModel.secondaryFull.lastName || mergeModel.primary.extra || mergeModel.secondary.extra) ? '' : mergeModel.primary.unprocessed || mergeModel.secondary.unprocessed }}</td>
                    </tr>
                    <tr>
                        <td>Historical</td>
                        <td>{{ mergeModel.primaryFull.historical ? 'Yes' : 'No' }}</td>
                    </tr>
                    <tr>
                        <td>Modern</td>
                        <td>{{ mergeModel.primaryFull.modern ? 'Yes' : 'No' }}</td>
                    </tr>
                    <tr>
                        <td>Born Date</td>
                        <!-- eslint-disable-next-line max-len -->
                        <td>{{ formatMergeDate(mergeModel.primaryFull.dates, mergeModel.secondaryFull.dates, 'born') }}</td>
                    </tr>
                    <tr>
                        <td>Death Date</td>
                        <!-- eslint-disable-next-line max-len -->
                        <td>{{ formatMergeDate(mergeModel.primaryFull.dates, mergeModel.secondaryFull.dates, 'died') }}</td>
                    </tr>
                    <tr
                        v-for="identifier in identifiers"
                        :key="identifier.systemName"
                    >
                        <td>{{ identifier.name }}</td>
                        <td>
                            {{
                                // eslint-disable-next-line max-len
                                (mergeModel.primaryFull.identifications != null ? mergeModel.primaryFull.identifications[identifier.systemName] : null)
                                    // eslint-disable-next-line max-len
                                    || (mergeModel.secondaryFull.identifications != null ? mergeModel.secondaryFull.identifications[identifier.systemName] : null)
                            }}
                        </td>
                    </tr>
                    <tr>
                        <td>(Self) designation</td>
                        <!-- eslint-disable-next-line max-len -->
                        <td>{{ formatObjectArray(mergeModel.primaryFull.selfDesignations) || formatObjectArray(mergeModel.secondaryFull.selfDesignations) }}</td>
                    </tr>
                    <tr>
                        <td>Offices</td>
                        <!-- eslint-disable-next-line max-len -->
                        <td>{{ formatObjectArray(mergeModel.primaryFull.officesWithParents) || formatObjectArray(mergeModel.secondaryFull.officesWithParents) }}</td>
                    </tr>
                    <tr>
                        <td>Public comment</td>
                        <td>{{ mergeModel.primaryFull.publicComment || mergeModel.secondaryFull.publicComment }}</td>
                    </tr>
                    <tr>
                        <td>Private comment</td>
                        <td>{{ mergeModel.primaryFull.privateComment || mergeModel.secondaryFull.privateComment }}</td>
                    </tr>
                </tbody>
            </table>
        </mergeModal>
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

// used for deleteDependencies, mergeModal
import AbstractListEdit from '../Components/Edit/AbstractListEdit';

import fieldRadio from '../Components/FormFields/fieldRadio.vue';
import ActiveFilters from '../Components/Search/ActiveFilters.vue';

import SharedSearch from '../Components/Search/SharedSearch';
import PersistentConfig from '../Components/Shared/PersistentConfig';

Vue.component('FieldRadio', fieldRadio);

export default {
    components: { ActiveFilters },
    mixins: [
        PersistentConfig('PersonSearchConfig'),
        AbstractField,
        AbstractSearch,
        AbstractListEdit, // merge functionality
        SharedSearch,
    ],
    props: {
        initPersons: {
            type: String,
            default: '',
        },
    },
    data() {
        const data = {
            model: {
                date_search_type: 'exact',
                role: [],
                role_op: 'or',
                office: [],
                office_op: 'or',
                self_designation: [],
                self_designation_op: 'or',
                origin: [],
                origin_op: 'or',
            },
            persons: null,
            schema: {
                fields: {},
            },
            tableOptions: {
                headings: {
                    comment: 'Comment (matching lines only)',
                },
                columnsClasses: {
                    name: 'no-wrap',
                },
                filterable: false,
                orderBy: {
                    column: 'name',
                },
                perPage: 25,
                perPageValues: [25, 50, 100],
                sortable: ['name', 'date', 'created', 'modified'],
                customFilters: ['filters'],
                requestFunction: AbstractSearch.requestFunction,
                rowClassCallback(row) {
                    return (row.public == null || row.public) ? '' : 'warning';
                },
            },
            mergePersonSchema: {
                fields: {
                    primary: this.createMultiSelect(
                        'Primary',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required,
                        },
                        {
                            customLabel: ({ id, name }) => `[${id}] ${name}`,
                        },
                    ),
                    secondary: this.createMultiSelect(
                        'Secondary',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required,
                        },
                        {
                            customLabel: ({ id, name }) => `[${id}] ${name}`,
                        },
                    ),
                },
            },
            mergeModel: {
                submitType: 'persons',
                primary: null,
                primaryFull: null,
                secondary: null,
                secondaryFull: null,
            },
            submitModel: {
                submitType: 'person',
                person: {},
            },
            defaultOrdering: 'name',
        };

        // Add fields
        data.schema.fields.name = {
            type: 'input',
            inputType: 'text',
            label: 'Name',
            model: 'name',
        };
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
            label: 'The person date interval must ... the search date interval:',
            labelClasses: 'control-label',
            model: 'date_search_type',
            values: [
                { value: 'exact', name: 'exactly match' },
                { value: 'included', name: 'be included in' },
                { value: 'overlap', name: 'overlap with' },
            ],
        };
        [data.schema.fields.role_op, data.schema.fields.role] = this.createMultiMultiSelect('Role');
        [data.schema.fields.office_op, data.schema.fields.office] = this.createMultiMultiSelect('Office');
        [data.schema.fields.self_designation_op, data.schema.fields.self_designation] = this.createMultiMultiSelect(
            '(Self) designation',
            {
                model: 'self_designation',
            },
            {
                internalSearch: false,
                onSearch: this.greekSearch,
            },
        );
        [data.schema.fields.origin_op, data.schema.fields.origin] = this.createMultiMultiSelect(
            'Provenance',
            {
                model: 'origin',
            },
        );
        data.schema.fields.comment = {
            type: 'input',
            inputType: 'text',
            label: 'Comment',
            model: 'comment',
            validator: VueFormGenerator.validators.string,
        };

        const idList = [];
        for (const identifier of JSON.parse(this.initIdentifiers)) {
            idList.push(this.createMultiSelect(
                identifier.name,
                {
                    model: identifier.systemName,
                },
            ));
        }

        data.schema.groups = [
            {
                styleClasses: 'collapsible collapsed',
                legend: 'External identifiers',
                fields: idList,
            },
        ];

        // Add view internal only fields
        if (this.isViewInternal) {
            data.schema.fields.historical = this.createMultiSelect(
                'Historical',
                {
                    styleClasses: 'has-warning',
                },
                {
                    customLabel: ({ _id, name }) => (name === 'true' ? 'Historical only' : 'Non-historical only'),
                },
            );
            data.schema.fields.modern = this.createMultiSelect(
                'Modern',
                {
                    styleClasses: 'has-warning',
                },
                {
                    customLabel: ({ _id, name }) => (name === 'true' ? 'Modern only' : 'Non-modern only'),
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
                Manuscripts: {
                    depUrl: this.urls.manuscript_deps_by_person.replace('person_id', this.submitModel.person.id),
                    url: this.urls.manuscript_get,
                    urlIdentifier: 'manuscript_id',
                },
                Occurrences: {
                    depUrl: this.urls.occurrence_deps_by_person.replace('person_id', this.submitModel.person.id),
                    url: this.urls.occurrence_get,
                    urlIdentifier: 'occurrence_id',
                },
                Types: {
                    depUrl: this.urls.type_deps_by_person.replace('person_id', this.submitModel.person.id),
                    url: this.urls.type_get,
                    urlIdentifier: 'type_id',
                },
                Articles: {
                    depUrl: this.urls.article_deps_by_person.replace('person_id', this.submitModel.person.id),
                    url: this.urls.article_get,
                    urlIdentifier: 'article_id',
                },
                Books: {
                    depUrl: this.urls.book_deps_by_person.replace('person_id', this.submitModel.person.id),
                    url: this.urls.book_get,
                    urlIdentifier: 'book_id',
                },
                'Book chapters': {
                    depUrl: this.urls.book_chapter_deps_by_person.replace('person_id', this.submitModel.person.id),
                    url: this.urls.book_chapter_get,
                    urlIdentifier: 'book_chapter_id',
                },
                Contents: {
                    depUrl: this.urls.content_deps_by_person.replace('person_id', this.submitModel.person.id),
                    url: this.urls.contents_edit,
                    urlIdentifier: 'content_id',
                },
                'Blog posts': {
                    depUrl: this.urls.blog_post_deps_by_person.replace('person_id', this.submitModel.person.id),
                    url: this.urls.blog_post_get,
                    urlIdentifier: 'blog_post_id',
                },
                'PhD theses': {
                    depUrl: this.urls.phd_deps_by_person.replace('person_id', this.submitModel.person.id),
                    url: this.urls.phd_get,
                    urlIdentifier: 'phd_id',
                },
                'Bib varia': {
                    depUrl: this.urls.bib_varia_deps_by_person.replace('person_id', this.submitModel.person.id),
                    url: this.urls.bib_varia_get,
                    urlIdentifier: 'bib_varia_id',
                },
            };
        },
        tableColumns() {
            const columns = ['name', 'identification', 'self_designation', 'office', 'date'];
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
    watch: {
        'mergeModel.primary': function () {
            if (this.mergeModel.primary == null) {
                this.mergeModel.primaryFull = null;
            } else {
                this.mergeModal = false;
                this.openRequests += 1;
                window.axios.get(this.urls.person_get.replace('person_id', this.mergeModel.primary.id))
                    .then((response) => {
                        this.mergeModel.primaryFull = response.data;
                        this.mergeModal = true;
                        this.openRequests -= 1;
                    })
                    .catch((error) => {
                        this.mergeModal = true;
                        this.openRequests -= 1;
                        this.alerts.push({
                            type: 'error',
                            message: 'Something went wrong while getting the person data.',
                            login: this.isLoginError(error),
                        });
                        console.error(error);
                    });
            }
        },
        'mergeModel.secondary': function () {
            if (this.mergeModel.secondary == null) {
                this.mergeModel.secondaryFull = null;
            } else {
                this.mergeModal = false;
                this.openRequests += 1;
                window.axios.get(this.urls.person_get.replace('person_id', this.mergeModel.secondary.id))
                    .then((response) => {
                        this.mergeModel.secondaryFull = response.data;
                        this.mergeModal = true;
                        this.openRequests -= 1;
                    })
                    .catch((error) => {
                        this.mergeModal = true;
                        this.openRequests -= 1;
                        this.alerts.push({
                            type: 'error',
                            message: 'Something went wrong while getting the person data.',
                            login: this.isLoginError(error),
                        });
                        console.error(error);
                    });
            }
        },
    },
    methods: {
        merge(row) {
            this.openRequests += 1;
            window.axios.get(this.urls.persons_get)
                .then((response) => {
                    this.persons = response.data;
                    this.openRequests -= 1;
                    this.mergeModel.primary = JSON.parse(
                        JSON.stringify(
                            this.persons.filter((person) => person.id === row.id)[0],
                        ),
                    );
                    this.mergeModel.secondary = null;
                    this.mergePersonSchema.fields.primary.values = this.persons;
                    this.mergePersonSchema.fields.secondary.values = this.persons;
                    this.enableField(this.mergePersonSchema.fields.primary);
                    this.enableField(this.mergePersonSchema.fields.secondary);
                    this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel));
                    this.mergeModal = true;
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push({
                        type: 'error',
                        message: 'Something went wrong while getting the person data.',
                        login: this.isLoginError(error),
                    });
                    console.error(error);
                });
        },
        del(row) {
            this.submitModel.person = {
                id: row.id,
                name: row.original_name == null ? row.name : row.original_name,
            };
            AbstractListEdit.methods.deleteDependencies.call(this);
        },
        submitMerge() {
            this.mergeModal = false;
            this.openRequests += 1;
            window.axios.put(
                this.urls.person_merge
                    .replace('primary_id', this.mergeModel.primary.id)
                    .replace('secondary_id', this.mergeModel.secondary.id),
            )
                .then((_response) => {
                    this.update();
                    this.mergeAlerts = [];
                    this.alerts.push({ type: 'success', message: 'Merge successful.' });
                    this.openRequests -= 1;
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.mergeModal = true;
                    this.mergeAlerts.push({
                        type: 'error',
                        message: 'Something went wrong while merging the persons.',
                        login: this.isLoginError(error),
                    });
                    console.error(error);
                });
        },
        submitDelete() {
            this.openRequests += 1;
            this.deleteModal = false;
            window.axios.delete(this.urls.person_delete.replace('person_id', this.submitModel.person.id))
                .then((_response) => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'success', message: 'Person deleted successfully.' });
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'error', message: 'Something went wrong while deleting the person.' });
                    console.error(error);
                });
        },
        formatMergeDate(primary, secondary, type) {
            if (primary.filter((d) => d.type === type).length === 1) {
                return this.formatPersonDate(primary.filter((d) => d.type === type)[0].date);
            }
            if (secondary.filter((d) => d.type === type).length === 1) {
                return this.formatPersonDate(secondary.filter((d) => d.type === type)[0].date);
            }
            return null;
        },
        formatPersonDate(date) {
            if (date == null || date.floor == null || date.ceiling == null) {
                return null;
            }
            return `${date.floor} - ${date.ceiling}`;
        },
        formatInterval(bornFloor, bornCeiling, deathFloor, deathCeiling) {
            const born = bornFloor === bornCeiling ? bornFloor : `${bornFloor}-${bornCeiling}`;
            const death = deathFloor === deathCeiling ? deathFloor : `${deathFloor}-${deathCeiling}`;
            return born === death ? born : `(${born}) - (${death})`;
        },
        formatObjectArray(objects) {
            if (objects == null || objects.length === 0) {
                return null;
            }
            return objects.map((object) => object.name).join(', ');
        },
        hasIdentification(person) {
            for (const identifier of this.identifiers) {
                if (person[identifier.systemName] != null && person[identifier.systemName].length > 0) {
                    return true;
                }
            }
            return false;
        },
        formatIdentification(person) {
            const result = [];
            for (const identifier of this.identifiers) {
                if (person[identifier.systemName] != null && person[identifier.systemName].length > 0) {
                    result.push(`${identifier.name}: ${person[identifier.systemName].join(', ')}`);
                }
            }
            return result.join(' - ');
        },
        greekSearch(searchQuery) {
            this.schema.fields.self_designation.values = this.schema.fields.self_designation.originalValues.filter(
                (option) => this.removeGreekAccents(option.name).includes(this.removeGreekAccents(searchQuery)),
            );
        },
    },
};
</script>
