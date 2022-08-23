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
                :url="urls['bibliographies_search_api']"
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
                <template
                    slot="type"
                    slot-scope="props"
                >
                    {{ props.row.type.name }}
                </template>
                <template
                    slot="author"
                    slot-scope="props"
                >
                    <!-- view internal -->
                    <template
                        v-if="props.row.author && props.row.author.length > 0"
                    >
                        <ul
                            v-if="props.row.author.length > 1"
                        >
                            <li
                                v-for="(author, index) in props.row.author"
                                :key="index"
                            >
                                <!-- eslint-disable max-len -->
                                <a
                                    :href="urls['person_get'].replace('person_id', author.id)"
                                    :class="{'bg-warning': !props.row.author_public || props.row.author_public.filter(auth => auth.id === author.id).length === 0}"
                                >
                                    <!-- eslint-enable max-len -->
                                    {{ author.name }}
                                </a>
                            </li>
                        </ul>
                        <template v-else>
                            <!-- eslint-disable max-len -->
                            <a
                                :href="urls['person_get'].replace('person_id', props.row.author[0].id)"
                                :class="{'bg-warning': !props.row.author_public || props.row.author_public.length === 0}"
                            >
                                <!-- eslint-enable max-len -->
                                {{ props.row.author[0].name }}
                            </a>
                        </template>
                    </template>
                    <!-- no view internal -->
                    <template
                        v-else-if="props.row.author_public && props.row.author_public.length > 0"
                    >
                        <ul
                            v-if="props.row.author_public.length > 1"
                        >
                            <li
                                v-for="(author, index) in props.row.author_public"
                                :key="index"
                            >
                                <a :href="urls['person_get'].replace('person_id', author.id)">
                                    {{ author.name }}
                                </a>
                            </li>
                        </ul>
                        <template v-else>
                            <a :href="urls['person_get'].replace('person_id', props.row.author_public[0].id)">
                                {{ props.row.author_public[0].name }}
                            </a>
                        </template>
                    </template>
                </template>
                <!-- eslint-disable max-len -->
                <a
                    slot="title"
                    slot-scope="props"
                    :href="urls[types[props.row.type.id] + '_get'].replace(types[props.row.type.id] + '_id', props.row.id)"
                    v-html="greekFont(formatTitle(props.row.title))"
                />
                <!-- eslint-enable max-len -->
                <template
                    slot="actions"
                    slot-scope="props"
                >
                    <!-- eslint-disable max-len -->
                    <a
                        v-if="urls[types[props.row.type.id] + '_edit']"
                        :href="urls[types[props.row.type.id] + '_edit'].replace(types[props.row.type.id] + '_id', props.row.id)"
                        class="action"
                        title="Edit"
                    >
                        <!-- eslint-enable max-len -->
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <!-- eslint-disable max-len -->
                    <a
                        v-else-if="urls[types[props.row.type.id] + 's_edit']"
                        :href="urls[types[props.row.type.id] + 's_edit'].replace(types[props.row.type.id] + '_id', props.row.id)"
                        class="action"
                        title="Edit"
                    >
                        <!-- eslint-enable max-len -->
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        v-if="types[props.row.type.id] === 'book' || types[props.row.type.id] === 'journal'"
                        href="#"
                        class="action"
                        title="Merge"
                        @click.prevent="merge(props.row)"
                    >
                        <i class="fa fa-compress" />
                    </a>
                    <a
                        v-if="urls[types[props.row.type.id] + '_delete']"
                        href="#"
                        class="action"
                        title="Delete"
                        @click.prevent="del(props.row)"
                    >
                        <i class="fa fa-trash-o" />
                    </a>
                    <!-- eslint-disable max-len -->
                    <a
                        v-else-if="urls[types[props.row.type.id] + 's_edit']"
                        :href="urls[types[props.row.type.id] + 's_edit'].replace(types[props.row.type.id] + '_id', props.row.id)"
                        class="action"
                        title="Delete"
                    >
                        <!-- eslint-enable max-len -->
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
            :schema="mergeSchema"
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
                <tbody v-if="mergeModel.submitType === 'book'">
                    <tr>
                        <td>Book cluster</td>
                        <!-- eslint-disable-next-line max-len -->
                        <td>{{ (mergeModel.primaryFull.bookCluster != null ? mergeModel.primaryFull.bookCluster.title : null) || (mergeModel.secondaryFull.bookCluster != null ? mergeModel.secondaryFull.bookCluster.title : null) }}</td>
                    </tr>
                    <tr>
                        <td>Volume</td>
                        <td>{{ mergeModel.primaryFull.volume || mergeModel.secondaryFull.volume }}</td>
                    </tr>
                    <tr>
                        <td>Total Volumes</td>
                        <td>{{ mergeModel.primaryFull.totalVolumes || mergeModel.secondaryFull.totalVolumes }}</td>
                    </tr>
                    <tr>
                        <td>Title</td>
                        <td>{{ mergeModel.primaryFull.title || mergeModel.secondaryFull.title }}</td>
                    </tr>
                    <tr>
                        <td>Year</td>
                        <td>{{ mergeModel.primaryFull.year || mergeModel.secondaryFull.year }}</td>
                    </tr>
                    <tr>
                        <td>City</td>
                        <td>{{ mergeModel.primaryFull.city || mergeModel.secondaryFull.city }}</td>
                    </tr>
                    <tr>
                        <td>Person roles</td>
                        <!-- eslint-disable-next-line max-len -->
                        <td>{{ formatPersonRoles(mergeModel.primaryFull.personRoles || mergeModel.secondaryFull.personRoles) }}</td>
                    </tr>
                    <tr>
                        <td>Publisher</td>
                        <td>{{ mergeModel.primaryFull.publisher || mergeModel.secondaryFull.publisher }}</td>
                    </tr>
                    <tr>
                        <td>Book series</td>
                        <!-- eslint-disable-next-line max-len -->
                        <td>{{ (mergeModel.primaryFull.bookSeries != null ? mergeModel.primaryFull.bookSeries.title : null) || (mergeModel.secondaryFull.bookSeries != null ? mergeModel.secondaryFull.bookSeries.title : null) }}</td>
                    </tr>
                    <tr>
                        <td>Series volume</td>
                        <td>{{ mergeModel.primaryFull.seriesVolume || mergeModel.secondaryFull.seriesVolume }}</td>
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
                        <td>Acknowledgements</td>
                        <!-- eslint-disable-next-line max-len -->
                        <td>{{ mergeModel.primaryFull.acknowledgements || mergeModel.secondaryFull.acknowledgements }}</td>
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
                <tbody v-else-if="mergeModel.submitType === 'journal'">
                    <tr>
                        <td>Title</td>
                        <td>{{ mergeModel.primaryFull.name }}</td>
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

// used for deleteDependencies
import AbstractListEdit from '../Components/Edit/AbstractListEdit';

import fieldRadio from '../Components/FormFields/fieldRadio.vue';
import ActiveFilters from '../Components/Search/ActiveFilters.vue';

import SharedSearch from '../Components/Search/SharedSearch';
import PersistentConfig from '../Components/Shared/PersistentConfig';

Vue.component('FieldRadio', fieldRadio);

export default {
    components: { ActiveFilters },
    mixins: [
        PersistentConfig('BibliographySearchConfig'),
        AbstractField,
        AbstractSearch,
        AbstractListEdit, // merge functionality
        SharedSearch,
    ],
    data() {
        const data = {
            model: {
                title_type: 'any',
                person: [],
                role: [],
                comment_mode: ['greek'],
            },
            books: null,
            journals: null,
            schema: {
                fields: {},
            },
            tableOptions: {
                headings: {
                    comment: 'Comment (matching lines only)',
                    author: 'Author(s)',
                },
                columnsClasses: {
                    author: 'no-wrap',
                },
                filterable: false,
                orderBy: {
                    column: 'title',
                },
                perPage: 25,
                perPageValues: [25, 50, 100],
                sortable: ['type', 'author', 'title'],
                customFilters: ['filters'],
                requestFunction: AbstractSearch.requestFunction,
                rowClassCallback(row) {
                    return (row.public == null || row.public) ? '' : 'warning';
                },
            },
            mergeSchema: {
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
                submitType: null,
                primary: null,
                primaryFull: null,
                secondary: null,
                secondaryFull: null,
            },
            submitModel: {
                submitType: null,
                article: {},
                blog_post: {},
                book: {},
                book_chapter: {},
                online_source: {},
                phd: {},
                bib_varia: {},
            },
            defaultOrdering: 'title',
            types: {
                0: 'article',
                1: 'book',
                2: 'book_chapter',
                3: 'online_source',
                4: 'journal',
                5: 'book_cluster',
                6: 'book_series',
                7: 'blog',
                8: 'blog_post',
                9: 'phd',
                10: 'bib_varia',
            },
        };

        // Add fields
        data.schema.fields.type = this.createMultiSelect('Type');
        data.schema.fields.title = {
            type: 'input',
            inputType: 'text',
            label: 'Title',
            model: 'title',
        };
        data.schema.fields.title_type = {
            type: 'checkboxes',
            styleClasses: 'field-checkboxes-labels-only field-checkboxes-lg',
            label: 'Title search options:',
            model: 'title_type',
            parentModel: 'title',
            values: [
                { value: 'all', name: 'all', toggleGroup: 'all_any_phrase' },
                { value: 'any', name: 'any', toggleGroup: 'all_any_phrase' },
                { value: 'phrase', name: 'consecutive words', toggleGroup: 'all_any_phrase' },
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
        data.schema.fields.comment_mode = this.createLanguageToggle('comment');
        data.schema.fields.comment = {
            type: 'input',
            inputType: 'text',
            label: 'Comment',
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

        // Add view internal only fields
        if (this.isViewInternal) {
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
            const depUrls = {};
            switch (this.submitModel.submitType) {
            case 'article':
            case 'book':
            case 'book_chapter':
            case 'online_source':
            case 'blog_post':
            case 'phd':
            case 'bib_varia':
                depUrls.Manuscripts = {
                    depUrl: this.urls[`manuscript_deps_by_${this.submitModel.submitType}`]
                        .replace(`${this.submitModel.submitType}_id`, this.submitModel[this.submitModel.submitType].id),
                    url: this.urls.manuscript_get,
                    urlIdentifier: 'manuscript_id',
                };
                depUrls.Occurrences = {
                    depUrl: this.urls[`occurrence_deps_by_${this.submitModel.submitType}`]
                        .replace(`${this.submitModel.submitType}_id`, this.submitModel[this.submitModel.submitType].id),
                    url: this.urls.occurrence_get,
                    urlIdentifier: 'occurrence_id',
                };
                depUrls.Types = {
                    depUrl: this.urls[`type_deps_by_${this.submitModel.submitType}`]
                        .replace(`${this.submitModel.submitType}_id`, this.submitModel[this.submitModel.submitType].id),
                    url: this.urls.type_get,
                    urlIdentifier: 'type_id',
                };
                depUrls.Persons = {
                    depUrl: this.urls[`person_deps_by_${this.submitModel.submitType}`]
                        .replace(`${this.submitModel.submitType}_id`, this.submitModel[this.submitModel.submitType].id),
                    url: this.urls.person_get,
                    urlIdentifier: 'person_id',
                };
                if (this.submitModel.submitType === 'book') {
                    depUrls['Book chapters'] = {
                        depUrl: this.urls.book_chapter_deps_by_book.replace('book_id', this.submitModel.book.id),
                        url: this.urls.book_chapter_get,
                        urlIdentifier: 'book_chapter_id',
                    };
                }
                break;
            case 'blog':
                depUrls['Blog posts'] = {
                    depUrl: this.urls.blog_post_deps_by_blog.replace('blog_id', this.submitModel.blog.id),
                    url: this.urls.blog_post_get,
                    urlIdentifier: 'blog_post_id',
                };
                break;
            default:
                throw new Error('Unknown submit type');
            }
            return depUrls;
        },
        tableColumns() {
            const columns = ['type', 'author', 'title'];
            if (this.commentSearch) {
                columns.unshift('comment');
            }
            if (this.isViewInternal) {
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
                let url = '';
                if (this.mergeModel.submitType === 'book') {
                    url = this.urls.book_get.replace('book_id', this.mergeModel.primary.id);
                } else if (this.mergeModel.submitType === 'journal') {
                    url = this.urls.journal_get.replace('journal_id', this.mergeModel.primary.id);
                }
                window.axios.get(url)
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
                let url = '';
                if (this.mergeModel.submitType === 'book') {
                    url = this.urls.book_get.replace('book_id', this.mergeModel.secondary.id);
                } else if (this.mergeModel.submitType === 'journal') {
                    url = this.urls.journal_get.replace('journal_id', this.mergeModel.secondary.id);
                }
                window.axios.get(url)
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
            this.mergeModel.submitType = this.types[row.type.id];
            this.openRequests += 1;
            if (this.types[row.type.id] === 'book') {
                window.axios.get(this.urls.books_get)
                    .then((response) => {
                        this.books = response.data;
                        this.openRequests -= 1;
                        this.mergeModel.primary = JSON.parse(
                            JSON.stringify(
                                this.books.filter((book) => book.id === row.id)[0],
                            ),
                        );
                        this.mergeModel.secondary = null;
                        this.mergeSchema.fields.primary.values = this.books;
                        this.mergeSchema.fields.secondary.values = this.books;
                        this.enableField(this.mergeSchema.fields.primary);
                        this.enableField(this.mergeSchema.fields.secondary);
                        this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel));
                        this.mergeModal = true;
                    })
                    .catch((error) => {
                        this.openRequests -= 1;
                        this.alerts.push({
                            type: 'error',
                            message: 'Something went wrong while getting the book data.',
                            login: this.isLoginError(error),
                        });
                        console.error(error);
                    });
            } else if (this.types[row.type.id] === 'journal') {
                window.axios.get(this.urls.journals_get)
                    .then((response) => {
                        this.journals = response.data;
                        this.openRequests -= 1;
                        this.mergeModel.primary = JSON.parse(
                            JSON.stringify(
                                this.journals.filter((journal) => journal.id === row.id)[0],
                            ),
                        );
                        this.mergeModel.secondary = null;
                        this.mergeSchema.fields.primary.values = this.journals;
                        this.mergeSchema.fields.secondary.values = this.journals;
                        this.enableField(this.mergeSchema.fields.primary);
                        this.enableField(this.mergeSchema.fields.secondary);
                        this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel));
                        this.mergeModal = true;
                    })
                    .catch((error) => {
                        this.openRequests -= 1;
                        this.alerts.push({
                            type: 'error',
                            message: 'Something went wrong while getting the journal data.',
                            login: this.isLoginError(error),
                        });
                        console.error(error);
                    });
            }
        },
        del(row) {
            this.submitModel.submitType = this.types[row.type.id];
            this.submitModel[this.types[row.type.id]] = row;
            if (Array.isArray(this.submitModel[this.types[row.type.id]].title)) {
                // eslint-disable-next-line max-len
                this.submitModel[this.types[row.type.id]].name = this.submitModel[this.types[row.type.id]].original_title;
            } else {
                this.submitModel[this.types[row.type.id]].name = this.submitModel[this.types[row.type.id]].title;
            }
            AbstractListEdit.methods.deleteDependencies.call(this);
        },
        submitMerge() {
            this.mergeModal = false;
            this.openRequests += 1;
            let url = '';
            if (this.mergeModel.submitType === 'book') {
                url = this.urls.book_merge
                    .replace('primary_id', this.mergeModel.primary.id)
                    .replace('secondary_id', this.mergeModel.secondary.id);
            } else if (this.mergeModel.submitType === 'journal') {
                url = this.urls.journal_merge
                    .replace('primary_id', this.mergeModel.primary.id)
                    .replace('secondary_id', this.mergeModel.secondary.id);
            }
            window.axios.put(url)
                .then((_response) => {
                    this.update();
                    this.mergeAlerts = [];
                    this.alerts.push({
                        type: 'success',
                        message: 'Merge successful.',
                    });
                    this.openRequests -= 1;
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.mergeModal = true;
                    this.mergeAlerts.push({
                        type: 'error',
                        message: `Something went wrong while merging the ${this.mergeModel.submitType}s.`,
                        login: this.isLoginError(error),
                    });
                    console.error(error);
                });
        },
        submitDelete() {
            this.openRequests += 1;
            this.deleteModal = false;
            window.axios.delete(
                this.urls[`${this.submitModel.submitType}_delete`]
                    .replace(`${this.submitModel.submitType}_id`, this.submitModel[this.submitModel.submitType].id),
            )
                .then((_response) => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests -= 1;
                    this.alerts.push({
                        type: 'success',
                        // eslint-disable-next-line max-len
                        message: `${this.submitModel.submitType.replace(/^\w/, (c) => c.toUpperCase())} deleted successfully.`,
                    });
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push({
                        type: 'error',
                        message: `Something went wrong while deleting the ${this.submitModel.submitType}.`,
                    });
                    console.error(error);
                });
        },
        formatTitle(title) {
            if (Array.isArray(title)) {
                return title[0];
            }

            return title;
        },
        formatPersonRoles(personRoles) {
            if (personRoles == null) {
                return null;
            }
            const result = [];
            for (const key of Object.keys(personRoles)) {
                const rolePersons = [];
                for (const person of personRoles[key]) {
                    rolePersons.push(person.name);
                }
                result.push(`${key.charAt(0).toUpperCase() + key.substr(1)}(s): ${rolePersons.join(', ')}`);
            }
            return result.join('<br />');
        },
    },
};
</script>
