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
                                v-html="greekFont(item)" />
                        </ol>
                    </template>
                    <template v-if="props.row.private_comment">
                        <em>Private</em>
                        <ol>
                            <li
                                v-for="(item, index) in props.row.private_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)" />
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
                    v-html="greekFont(formatTitle(props.row.title))" />
                <template
                    slot="actions"
                    slot-scope="props">
                    <a
                        v-if="urls[types[props.row.type.id] + '_edit']"
                        :href="urls[types[props.row.type.id] + '_edit'].replace(types[props.row.type.id] + '_id', props.row.id)"
                        class="action"
                        title="Edit">
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        v-else-if="urls[types[props.row.type.id] + 's_edit']"
                        :href="urls[types[props.row.type.id] + 's_edit'].replace(types[props.row.type.id] + '_id', props.row.id)"
                        class="action"
                        title="Edit"
                    >
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
                        @click.prevent="del(props.row)">
                        <i class="fa fa-trash-o" />
                    </a>
                    <a
                        v-else-if="urls[types[props.row.type.id] + 's_edit']"
                        :href="urls[types[props.row.type.id] + 's_edit'].replace(types[props.row.type.id] + '_id', props.row.id)"
                        class="action"
                        title="Delete"
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
                        <td>{{ formatPersonRoles(mergeModel.primaryFull.personRoles || mergeModel.secondaryFull.personRoles) }}</td>
                    </tr>
                    <tr>
                        <td>Publisher</td>
                        <td>{{ mergeModel.primaryFull.publisher || mergeModel.secondaryFull.publisher }}</td>
                    </tr>
                    <tr>
                        <td>Series</td>
                        <td>{{ mergeModel.primaryFull.series || mergeModel.secondaryFull.series }}</td>
                    </tr>
                    <tr>
                        <td>Volume</td>
                        <td>{{ mergeModel.primaryFull.volume || mergeModel.secondaryFull.volume }}</td>
                    </tr>
                    <tr>
                        <td>Total Volumes</td>
                        <td>{{ mergeModel.primaryFull.totalVolumes || mergeModel.secondaryFull.totalVolumes }}</td>
                    </tr>
                    <tr
                        v-for="identifier in identifiers"
                        :key="identifier.systemName"
                    >
                        <td>{{ identifier.name }}</td>
                        <td>
                            {{
                                (mergeModel.primaryFull.identifications != null ? mergeModel.primaryFull.identifications[identifier.systemName] : null)
                                    || (mergeModel.secondaryFull.identifications != null ? mergeModel.secondaryFull.identifications[identifier.systemName] : null)
                            }}
                        </td>
                    </tr>
                    <tr>
                        <td>Acknowledgements</td>
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
        AbstractListEdit, // merge functionality
    ],
    data() {
        let data = {
            model: {
                title_type: 'any',
            },
            books: null,
            journals: null,
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
            mergeSchema: {
                fields: {
                    primary: this.createMultiSelect(
                        'Primary',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        },
                        {
                            customLabel: ({id, name}) => {
                                return '[' + id + '] ' + name
                            },
                        }
                    ),
                    secondary: this.createMultiSelect(
                        'Secondary',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        },
                        {
                            customLabel: ({id, name}) => {
                                return '[' + id + '] ' + name
                            },
                        }
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
                4: 'journal',
            }
        }

        // Add identifier fields
        for (let identifier of JSON.parse(this.initIdentifiers)) {
            data.schema.fields[identifier.systemName] = this.createMultiSelect(identifier.name, {model: identifier.systemName})
        }

        // Add view internal only fields
        if (this.isViewInternal) {
            data.schema.fields['management'] = this.createMultiSelect(
                'Management collection',
                {
                    model: 'management',
                    styleClasses: 'has-warning',
                }
            )
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
                'Manuscripts': {
                    depUrl: this.urls['manuscript_deps_by_' + this.submitModel.submitType].replace(this.submitModel.submitType + '_id', this.submitModel[this.submitModel.submitType].id),
                    url: this.urls['manuscript_get'],
                    urlIdentifier: 'manuscript_id',
                },
                'Occurrences': {
                    depUrl: this.urls['occurrence_deps_by_' + this.submitModel.submitType].replace(this.submitModel.submitType + '_id', this.submitModel[this.submitModel.submitType].id),
                    url: this.urls['occurrence_get'],
                    urlIdentifier: 'occurrence_id',
                },
                'Types': {
                    depUrl: this.urls['type_deps_by_' + this.submitModel.submitType].replace(this.submitModel.submitType + '_id', this.submitModel[this.submitModel.submitType].id),
                    url: this.urls['type_get'],
                    urlIdentifier: 'type_id',
                },
                'Persons': {
                    depUrl: this.urls['person_deps_by_' + this.submitModel.submitType].replace(this.submitModel.submitType + '_id', this.submitModel[this.submitModel.submitType].id),
                    url: this.urls['person_get'],
                    urlIdentifier: 'person_id',
                },
            }
        },
        tableColumns() {
            let columns = ['type', 'title']
            if (this.commentSearch) {
                columns.unshift('comment')
            }
            if (this.isViewInternal) {
                columns.push('actions')
                columns.push('c')
            }
            return columns
        },
    },
    watch: {
        'mergeModel.primary'() {
            if (this.mergeModel.primary == null) {
                this.mergeModel.primaryFull = null;
            }
            else {
                this.mergeModal = false;
                this.openRequests++;
                let url = '';
                if (this.mergeModel.submitType === 'book') {
                    url = this.urls['book_get'].replace('book_id', this.mergeModel.primary.id);
                } else if (this.mergeModel.submitType === 'journal') {
                    url = this.urls['journal_get'].replace('journal_id', this.mergeModel.primary.id);
                }
                axios.get(url)
                    .then((response) => {
                        this.mergeModel.primaryFull = response.data;
                        this.mergeModal = true;
                        this.openRequests--;
                    })
                    .catch((error) => {
                        this.mergeModal = true;
                        this.openRequests--;
                        this.alerts.push({
                            type: 'error',
                            message: 'Something went wrong while getting the person data.',
                            login: this.isLoginError(error)
                        });
                        console.log(error);
                    })
            }
        },
        'mergeModel.secondary'() {
            if (this.mergeModel.secondary == null) {
                this.mergeModel.secondaryFull = null;
            }
            else {
                this.mergeModal = false;
                this.openRequests++;
                let url = '';
                if (this.mergeModel.submitType === 'book') {
                    url = this.urls['book_get'].replace('book_id', this.mergeModel.secondary.id);
                } else if (this.mergeModel.submitType === 'journal') {
                    url = this.urls['journal_get'].replace('journal_id', this.mergeModel.secondary.id);
                }
                axios.get(url)
                    .then( (response) => {
                        this.mergeModel.secondaryFull = response.data;
                        this.mergeModal = true;
                        this.openRequests--;
                    })
                    .catch( (error) => {
                        this.mergeModal = true;
                        this.openRequests--;
                        this.alerts.push({type: 'error', message: 'Something went wrong while getting the person data.', login: this.isLoginError(error)});
                        console.log(error);
                    })
            }
        },
    },
    methods: {
        merge(row) {
            this.mergeModel.submitType = this.types[row.type.id];
            this.openRequests++;
            if (this.types[row.type.id] === 'book') {
                axios.get(this.urls['books_get'])
                    .then((response) => {
                        this.books = response.data;
                        this.openRequests--;
                        this.mergeModel.primary = JSON.parse(JSON.stringify(this.books.filter(book => book.id === row.id)[0]));
                        this.mergeModel.secondary = null;
                        this.mergeSchema.fields.primary.values = this.books;
                        this.mergeSchema.fields.secondary.values = this.books;
                        this.enableField(this.mergeSchema.fields.primary);
                        this.enableField(this.mergeSchema.fields.secondary);
                        this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel));
                        this.mergeModal = true;
                    })
                    .catch((error) => {
                        this.openRequests--
                        this.alerts.push({
                            type: 'error',
                            message: 'Something went wrong while getting the book data.',
                            login: this.isLoginError(error)
                        })
                        console.log(error)
                    });
            } else if (this.types[row.type.id] === 'journal') {
                axios.get(this.urls['journals_get'])
                    .then((response) => {
                        this.journals = response.data;
                        this.openRequests--;
                        this.mergeModel.primary = JSON.parse(JSON.stringify(this.journals.filter(journal => journal.id === row.id)[0]));
                        this.mergeModel.secondary = null;
                        this.mergeSchema.fields.primary.values = this.journals;
                        this.mergeSchema.fields.secondary.values = this.journals;
                        this.enableField(this.mergeSchema.fields.primary);
                        this.enableField(this.mergeSchema.fields.secondary);
                        this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel));
                        this.mergeModal = true;
                    })
                    .catch((error) => {
                        this.openRequests--
                        this.alerts.push({
                            type: 'error',
                            message: 'Something went wrong while getting the journal data.',
                            login: this.isLoginError(error)
                        })
                        console.log(error)
                    });
            }
        },
        del(row) {
            this.submitModel.submitType = this.types[row.type.id]
            this.submitModel[this.types[row.type.id]] = row
            if (Array.isArray(this.submitModel[this.types[row.type.id]].title)) {
                this.submitModel[this.types[row.type.id]].name = this.submitModel[this.types[row.type.id]].original_title
            }
            else {
                this.submitModel[this.types[row.type.id]].name = this.submitModel[this.types[row.type.id]].title
            }
            AbstractListEdit.methods.deleteDependencies.call(this)
        },
        submitMerge() {
            this.mergeModal = false;
            this.openRequests++;
            let url = '';
            if (this.mergeModel.submitType === 'book') {
                url = this.urls['book_merge'].replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id);
            } else if (this.mergeModel.submitType === 'journal') {
                url = this.urls['journal_merge'].replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id);
            }
            axios.put(url)
                .then( (response) => {
                    this.update();
                    this.mergeAlerts = [];
                    this.alerts.push({type: 'success', message: 'Merge successful.'});
                    this.openRequests--;
                })
                .catch( (error) => {
                    this.openRequests--;
                    this.mergeModal = true;
                    this.mergeAlerts.push({type: 'error', message: 'Something went wrong while merging the ' + this.mergeModel.submitType + 's.', login: this.isLoginError(error)});
                    console.log(error);
                })
        },
        submitDelete() {
            this.openRequests++
            this.deleteModal = false
            axios.delete(this.urls[this.submitModel.submitType + '_delete'].replace(this.submitModel.submitType + '_id', this.submitModel[this.submitModel.submitType].id))
                .then((response) => {
                    // Don't create a new history item
                    this.noHistory = true
                    this.$refs.resultTable.refresh()
                    this.openRequests--
                    this.alerts.push({type: 'success', message: this.submitModel.submitType.replace(/^\w/, c => c.toUpperCase()) + ' deleted successfully.'})
                })
                .catch((error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while deleting the ' + this.submitModel.submitType + '.'})
                    console.log(error)
                })
        },
        update() {
            // Don't create a new history item
            this.noHistory = true;
            this.$refs.resultTable.refresh();
        },
        formatTitle(title) {
            if (Array.isArray(title)) {
                return title[0]
            }
            else {
                return title
            }
        },
        formatPersonRoles(personRoles) {
            if (personRoles == null) {
                return null;
            }
            let result = [];
            for (let key of Object.keys(personRoles)) {
                let rolePersons = [];
                for (let person of personRoles[key]) {
                    rolePersons.push(person.name);
                }
                result.push(key.charAt(0).toUpperCase() + key.substr(1) + '(s): ' + rolePersons.join(', '));
            }
            return result.join('<br />');
        },
    }
}
</script>
