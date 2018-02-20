    <template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <h2>Edit Manuscript</h2>
            <!--TODO: manage locations outside of manuscripts link-->
            <alert v-for="(item, index) in alerts" :key="item.key" :type="item.type" dismissible @dismissed="alerts.splice(index, 1)">
                {{ item.message }}
            </alert>
            <div class="panel panel-default">
                <div class="panel-heading">Location</div>
                <div class="panel-body">
                    <vue-form-generator :schema="locationSchema" :model="model" :options="formOptions" ref="locationForm" @validated="validated()"></vue-form-generator>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading">Content</div>
                <div class="panel-body">
                    <vue-form-generator :schema="contentSchema" :model="model" :options="formOptions" ref="contentForm" @validated="validated()"></vue-form-generator>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading">Persons</div>
                <div class="panel-body">
                    <vue-form-generator :schema="patronsSchema" :model="model" :options="formOptions" ref="patronsForm" @validated="validated()"></vue-form-generator>
                    <div class="small" v-if="manuscript.occurrencePatrons.length > 0">
                        <p>Patron(s) provided by occurrences:</p>
                        <ul>
                            <li v-for="patron in manuscript.occurrencePatrons">
                                {{ patron.name }}
                                <ul>
                                    <li v-for="occurrence in patron.occurrences">
                                        {{ occurrence }}
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <vue-form-generator :schema="scribesSchema" :model="model" :options="formOptions" ref="scribesForm" @validated="validated()"></vue-form-generator>
                    <div class="small" v-if="manuscript.occurrenceScribes.length > 0">
                        <p>Scribe(s) provided by occurrences:</p>
                        <ul>
                            <li v-for="scribe in manuscript.occurrenceScribes">
                                {{ scribe.name }}
                                <ul>
                                    <li v-for="occurrence in scribe.occurrences">
                                        {{ occurrence }}
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <vue-form-generator :schema="relatedPersonsSchema" :model="model" :options="formOptions" ref="relatedPersonsForm" @validated="validated()"></vue-form-generator>
                    <div class="small">
                        <p>Related persons are persons that are related to this manuscript but that are not a patron or a scribe of the manuscript or of occurrences related to the manuscript.</p>
                    </div>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading">Date</div>
                <div class="panel-body">
                    <vue-form-generator :schema="dateSchema" :model="model" :options="formOptions" ref="dateForm" @validated="validated()"></vue-form-generator>
                    <div v-if="warnEstimate" class="small text-warning">
                        <p>When indicating an estimate, please add 1 year to the start year to prevent overlap. Examples:</p>
                        <ul>
                            <li>1301-1400</li>
                            <li>1476-1500</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading">Origin</div>
                <div class="panel-body">
                    <vue-form-generator :schema="originSchema" :model="model" :options="formOptions" ref="originForm" @validated="validated()"></vue-form-generator>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading">Bibliograpy</div>
                <div class="panel-body">
                    <div class="pbottom-large">
                        <h3>Books</h3>
                        <table v-if="model.bibliography.books.length > 0" class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Start page</th>
                                    <th>End page</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in model.bibliography.books">
                                    <td>{{ item.book.name }}</td>
                                    <td>{{ item.startPage }}</td>
                                    <td>{{ item.endPage }}</td>
                                    <td>
                                        <a href="#" title="Edit" class="action" @click.prevent="updateBib(item, index)"><i class="fa fa-pencil-square-o"></i></a>
                                        <a href="#" title="Delete" class="action" @click.prevent="delBib(item, index)"><i class="fa fa-trash-o"></i></a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <btn @click="newBib('book')"><i class="fa fa-plus"></i>&nbsp;Add a book reference</btn>
                    </div>
                    <div class="pbottom-large">
                        <h3>Articles</h3>
                        <table v-if="model.bibliography.articles.length > 0" class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Article</th>
                                    <th>Start page</th>
                                    <th>End page</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in model.bibliography.articles">
                                    <td>{{ item.article.name }}</td>
                                    <td>{{ item.startPage }}</td>
                                    <td>{{ item.endPage }}</td>
                                    <td>
                                        <a href="#" title="Edit" class="action" @click.prevent="updateBib(item, index)"><i class="fa fa-pencil-square-o"></i></a>
                                        <a href="#" title="Delete" class="action" @click.prevent="delBib(item, index)"><i class="fa fa-trash-o"></i></a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <btn @click="newBib('article')"><i class="fa fa-plus"></i>&nbsp;Add an article reference</btn>
                    </div>
                    <div class="pbottom-large">
                        <h3>Book chapters</h3>
                        <table v-if="model.bibliography.bookChapters.length > 0" class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Book Chapter</th>
                                    <th>Start page</th>
                                    <th>End page</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in model.bibliography.bookChapters">
                                    <td>{{ item.bookChapter.name }}</td>
                                    <td>{{ item.startPage }}</td>
                                    <td>{{ item.endPage }}</td>
                                    <td>
                                        <a href="#" title="Edit" class="action" @click.prevent="updateBib(item, index)"><i class="fa fa-pencil-square-o"></i></a>
                                        <a href="#" title="Delete" class="action" @click.prevent="delBib(item, index)"><i class="fa fa-trash-o"></i></a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <btn @click="newBib('bookChapter')"><i class="fa fa-plus"></i>&nbsp;Add a book chapter reference</btn>
                    </div>
                    <div>
                        <h3>Online sources</h3>
                        <table v-if="model.bibliography.onlineSources.length > 0" class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Online source</th>
                                    <th>Source link</th>
                                    <th>Relative link</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in model.bibliography.onlineSources">
                                    <td>{{ item.onlineSource.name }}</td>
                                    <td>{{ item.onlineSource.url }}</td>
                                    <td>{{ item.relUrl }}</td>
                                    <td>
                                        <a href="#" title="Edit" class="action" @click.prevent="updateBib(item, index)"><i class="fa fa-pencil-square-o"></i></a>
                                        <a href="#" title="Delete" class="action" @click.prevent="delBib(item, index)"><i class="fa fa-trash-o"></i></a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <btn @click="newBib('onlineSource')"><i class="fa fa-plus"></i>&nbsp;Add an online source</btn>
                    </div>
                </div>
            </div>
            <btn type="warning" :disabled="diff.length === 0" @click="resetModal=true">Reset</btn>
            <btn type="success" :disabled="(diff.length === 0) || invalidForms" @click="saveModal=true">Save changes</btn>
            <div class="loading-overlay" v-if="openRequests">
                <div class="spinner">
                </div>
            </div>
        </article>
        <modal v-model="resetModal" title="Reset manuscript" auto-focus>
            <p>Are you sure you want to reset the manuscript information?</p>
            <div slot="footer">
                <btn @click="resetModal=false">Cancel</btn>
                <btn type="danger" @click="reset()" data-action="auto-focus">Reset</btn>
            </div>
        </modal>
        <modal v-model="saveModal" title="Save manuscript" size="lg" auto-focus>
            <p>Are you sure you want to save this manuscript information?</p>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="col-md-2">Field</th>
                        <th class="col-md-5">Previous value</th>
                        <th class="col-md-5">New value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in diff">
                        <td>{{ row['label'] }}</td>
                        <template v-for="key in ['old', 'new']">
                            <td v-if="Array.isArray(row[key])">
                                <ul v-if="row[key].length > 0">
                                    <li v-for="item in row[key]">
                                        {{ getDisplay(item) }}
                                    </li>
                                </ul>
                            </td>
                            <td v-else>
                                {{ getDisplay(row[key]) }}
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
            <div slot="footer">
                <btn @click="saveModal=false">Cancel</btn>
                <btn type="success" @click="save()" data-action="auto-focus">Save</btn>
            </div>
        </modal>
        <modal v-model="editBibModal" size="lg" auto-focus>
            <vue-form-generator v-if="editBib.type === 'book'" :schema="editBookBibSchema" :model="editBib" :options="formOptions" ref="editBibForm"></vue-form-generator>
            <vue-form-generator v-if="editBib.type === 'article'" :schema="editArticleBibSchema" :model="editBib" :options="formOptions" ref="editBibForm"></vue-form-generator>
            <vue-form-generator v-if="editBib.type === 'bookChapter'" :schema="editBookChapterBibSchema" :model="editBib" :options="formOptions" ref="editBibForm"></vue-form-generator>
            <vue-form-generator v-if="editBib.type === 'onlineSource'" :schema="editOnlineSourceSchema" :model="editBib" :options="formOptions" ref="editBibForm"></vue-form-generator>
            <div slot="header">
                <h4 class="modal-title" v-if="editBib.id">Edit bibliography</h4>
                <h4 class="modal-title" v-if="!editBib.id">Add a new bibliography item</h4>
            </div>
            <div slot="footer">
                <btn @click="editBibModal=false">Cancel</btn>
                <btn type="success" :disabled="!$refs.hasOwnProperty('editBibForm') || $refs.editBibForm.errors.length > 0" @click="submitBib()">{{ editBib.id ? 'Update' : 'Add' }}</btn>
            </div>
        </modal>
        <modal v-model="delBibModal" title="Delete bibliography" auto-focus>
            Are you sure you want to delete this bibliography?
            <div slot="footer">
                <btn @click="delBibModal=false">Cancel</btn>
                <btn type="danger" @click="submitDeleteBib()">Delete</btn>
            </div>
        </modal>
    </div>
</template>

<script>
    window.axios = require('axios')

    import Vue from 'vue'
    import VueFormGenerator from 'vue-form-generator'
    import * as uiv from 'uiv'
    import VueMultiselect from 'vue-multiselect'
    import fieldMultiselectClear from '../components/formfields/fieldMultiselectClear'

    Vue.use(VueFormGenerator)
    Vue.use(uiv)

    Vue.component('multiselect', VueMultiselect)
    Vue.component('fieldMultiselectClear', fieldMultiselectClear)

    var YEAR_MIN = 1
    var YEAR_MAX = (new Date()).getFullYear()

    export default {
        props: [
            'getManuscriptUrl',
            'putManuscriptUrl',
            'initManuscript',
            'initLocations',
            'initContents',
            'initPatrons',
            'initScribes',
            'initRelatedPersons',
            'initOrigins',
            'initBooks',
            'initArticles',
            'initBookChapters',
            'initOnlineSources'
        ],
        data() {
            return {
                manuscript: {
                    occurrencePatrons: [],
                    occurrenceScribes: []
                },
                locations: [],
                contents: [],
                patrons: [],
                scribes: [],
                relatedPersons: [],
                origins: [],
                books: [],
                articles: [],
                bookChapters: [],
                onlineSources: [],
                model: {
                    city: null,
                    library: null,
                    collection: null,
                    shelf: null,
                    content: [],
                    patrons: [],
                    scribes: [],
                    relatedPersons: [],
                    same_year: false,
                    year_from: null,
                    year_to: null,
                    origin: null,
                    bibliography: {
                        books: [],
                        articles: [],
                        bookChapters: [],
                        onlineSources: [],
                    }
                },
                locationSchema: {
                    fields: {
                        city: this.createMultiSelect('City', {required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                        library: this.createMultiSelect('Library', {required: true, validator: VueFormGenerator.validators.required, dependency: 'city'}, {trackBy: 'id'}),
                        collection: this.createMultiSelect('Collection', {dependency: 'library'}, {trackBy: 'id'}),
                        shelf: {
                            type: 'input',
                            inputType: 'text',
                            label: 'Shelf Number',
                            model: 'shelf',
                            required: true,
                            validator: VueFormGenerator.validators.string
                        }
                    }
                },
                contentSchema: {
                    fields: {
                        content: this.createMultiSelect('Content', {}, {multiple: true, closeOnSelect: false, trackBy: 'id'}),
                    }
                },
                patronsSchema: {
                    fields: {
                        patrons: this.createMultiSelect('Patrons', {}, {multiple: true, closeOnSelect: false, trackBy: 'id'}),
                    }
                },
                scribesSchema: {
                    fields: {
                        scribes: this.createMultiSelect('Scribes', {}, {multiple: true, closeOnSelect: false, trackBy: 'id'}),
                    }
                },
                relatedPersonsSchema: {
                    fields: {
                        relatedPersons: this.createMultiSelect('Related Persons', {}, {multiple: true, closeOnSelect: false, trackBy: 'id'}),
                    }
                },
                dateSchema: {
                    fields: {
                        same_year: {
                            type: 'checkbox',
                            label: 'Exact year',
                            model: 'same_year',
                            default: false
                        },
                        year_from: {
                            type: 'input',
                            inputType: 'number',
                            label: 'Year from',
                            model: 'year_from',
                            min: YEAR_MIN,
                            max: YEAR_MAX,
                            validator: VueFormGenerator.validators.number
                        },
                        year_to: {
                            type: 'input',
                            inputType: 'number',
                            label: 'Year to',
                            model: 'year_to',
                            min: YEAR_MIN,
                            max: YEAR_MAX,
                            validator: VueFormGenerator.validators.number
                        }
                    }
                },
                originSchema: {
                    fields: {
                        origin: this.createMultiSelect('Origin', {}, {trackBy: 'id'})
                    }
                },
                editBookBibSchema: {
                    fields: {
                        book: this.createMultiSelect('Book', {required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                        startPage: {
                            type: 'input',
                            inputType: 'text',
                            label: 'Start page',
                            model: 'startPage',
                            validator: VueFormGenerator.validators.string
                        },
                        endPage: {
                            type: 'input',
                            inputType: 'text',
                            label: 'End page',
                            model: 'endPage',
                            validator: VueFormGenerator.validators.string
                        }
                    }
                },
                editArticleBibSchema: {
                    fields: {
                        article: this.createMultiSelect('Article', {required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                        startPage: {
                            type: 'input',
                            inputType: 'text',
                            label: 'Start page',
                            model: 'startPage',
                            validator: VueFormGenerator.validators.string
                        },
                        endPage: {
                            type: 'input',
                            inputType: 'text',
                            label: 'End page',
                            model: 'endPage',
                            validator: VueFormGenerator.validators.string
                        }
                    }
                },
                editBookChapterBibSchema: {
                    fields: {
                        bookChapter: this.createMultiSelect('Book Chapter', {required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                        startPage: {
                            type: 'input',
                            inputType: 'text',
                            label: 'Start page',
                            model: 'startPage',
                            validator: VueFormGenerator.validators.string
                        },
                        endPage: {
                            type: 'input',
                            inputType: 'text',
                            label: 'End page',
                            model: 'endPage',
                            validator: VueFormGenerator.validators.string
                        }
                    }
                },
                editOnlineSourceSchema: {
                    fields: {
                        onlineSource: this.createMultiSelect('Online Source', {required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                        relUrl: {
                            type: 'input',
                            inputType: 'text',
                            label: 'Relative link',
                            validator: VueFormGenerator.validators.string
                        }
                    }
                },
                formOptions: {
                    validateAfterLoad: true,
                    validateAfterChanged: true,
                    validationErrorClass: "has-error",
                    validationSuccessClass: "success"
                },
                openRequests: 0,
                alerts: [],
                originalModel: {},
                diff:[],
                resetModal: false,
                saveModal: false,
                invalidForms: false,
                noNewValues: true,
                editBibModal: false,
                delBibModal: false,
                bibIndex: null,
                editBib: {}
            }
        },
        mounted () {
            this.$nextTick( () => {
                this.manuscript = JSON.parse(this.initManuscript)
                this.locations = JSON.parse(this.initLocations)
                this.contents = JSON.parse(this.initContents)
                this.patrons = JSON.parse(this.initPatrons)
                this.scribes = JSON.parse(this.initScribes)
                this.relatedPersons = JSON.parse(this.initRelatedPersons)
                this.origins = JSON.parse(this.initOrigins)
                this.books = JSON.parse(this.initBooks)
                this.articles = JSON.parse(this.initArticles)
                this.bookChapters = JSON.parse(this.initBookChapters)
                this.onlineSources = JSON.parse(this.initOnlineSources)
            })
        },
        watch: {
            'manuscript': function (newValue, oldValue) {
                // Location
                this.model.city = this.manuscript.location.city
                this.model.library = this.manuscript.location.library
                this.model.collection = this.manuscript.location.collection
                this.model.shelf = this.manuscript.location.shelf

                // Content
                this.model.content = this.manuscript.content

                // People
                this.model.patrons = this.manuscript.patrons
                this.model.scribes = this.manuscript.scribes
                this.model.relatedPersons = this.manuscript.relatedPersons

                // Date
                if (this.manuscript.date != null) {
                    if (this.manuscript.date.floor != null) {
                        this.model.year_from = (new Date(this.manuscript.date.floor)).getFullYear()
                    }
                    if (this.manuscript.date.ceiling != null) {
                        this.model.year_to = (new Date(this.manuscript.date.ceiling)).getFullYear()
                    }
                }

                if (this.model.year_from === this.model.year_to && this.model.year_from != null) {
                    this.model.same_year = true
                }

                // Origin
                this.model.origin = this.manuscript.origin

                // Bibliography
                for (let id of Object.keys(this.manuscript.bibliography)) {
                    let bib = this.manuscript.bibliography[id]
                    bib['id'] = id
                    switch (bib['type']) {
                        case 'book':
                            this.model.bibliography.books.push(bib);
                            break
                        case 'article':
                            this.model.bibliography.articles.push(bib);
                            break
                        case 'bookChapter':
                            this.model.bibliography.bookChapters.push(bib);
                            break
                        case 'onlineSource':
                            this.model.bibliography.onlineSources.push(bib);
                            break
                    }
                }

                this.originalModel = JSON.parse(JSON.stringify(this.model))

                if (this.locationSchema.fields.city.values.length === 0) {
                    this.loadLocationField(this.locationSchema.fields.city)
                }

                this.$refs.locationForm.validate()
                this.$refs.contentForm.validate()
                this.$refs.patronsForm.validate()
                this.$refs.scribesForm.validate()
                this.$refs.relatedPersonsForm.validate()
                this.$refs.dateForm.validate()
                this.$refs.originForm.validate()
            },
            'locations': function (newValue, oldValue)  {
                this.loadLocationField(this.locationSchema.fields.city)
                this.enableField(this.locationSchema.fields.city)
                this.loadLocationField(this.locationSchema.fields.library)
                this.loadLocationField(this.locationSchema.fields.collection)
            },
            'contents': function (newValue, oldValue) {
                this.contentSchema.fields.content.values = this.contents
                this.enableField(this.contentSchema.fields.content)
            },
            'patrons': function (newValue, oldValue) {
                this.patronsSchema.fields.patrons.values = this.patrons
                this.enableField(this.patronsSchema.fields.patrons)
            },
            'scribes': function (newValue, oldValue) {
                this.scribesSchema.fields.scribes.values = this.scribes
                this.enableField(this.scribesSchema.fields.scribes)
            },
            'relatedPersons': function (newValue, oldValue) {
                this.relatedPersonsSchema.fields.relatedPersons.values = this.relatedPersons
                this.enableField(this.relatedPersonsSchema.fields.relatedPersons)
            },
            'origins': function (newValue, oldValue) {
                this.originSchema.fields.origin.values = this.origins
                this.enableField(this.originSchema.fields.origin)
            },
            'books': function (newValue, oldValue) {
                this.editBookBibSchema.fields.book.values = this.books
                this.enableField(this.editBookBibSchema.fields.book)
            },
            'articles': function (newValue, oldValue) {
                this.editArticleBibSchema.fields.article.values = this.articles
                this.enableField(this.editArticleBibSchema.fields.article)
            },
            'bookChapters': function (newValue, oldValue) {
                this.editBookChapterBibSchema.fields.bookChapter.values = this.bookChapters
                this.enableField(this.editBookChapterBibSchema.fields.bookChapter)
            },
            'onlineSources': function (newValue, oldValue) {
                this.editOnlineSourceSchema.fields.onlineSource.values = this.onlineSources
                this.enableField(this.editOnlineSourceSchema.fields.onlineSource)
            },
            'model.city': function (newValue, oldValue) {
                if (this.model.city == null) {
                    this.dependencyField(this.locationSchema.fields.library)
                }
                else {
                    this.loadLocationField(this.locationSchema.fields.library)
                    this.enableField(this.locationSchema.fields.library)
                }
            },
            'model.library': function (newValue, oldValue) {
                if (this.model.library == null) {
                    this.dependencyField(this.locationSchema.fields.collection)
                }
                else {
                    this.loadLocationField(this.locationSchema.fields.collection)
                    this.enableField(this.locationSchema.fields.collection)
                }
            },
            'model.same_year': function (newValue, oldValue) {
                this.dateSchema.fields.year_to.disabled = this.model.same_year
                if (this.model.year_from == null && this.model.year_to != null) {
                    this.model.year_from = this.model.year_to
                }
                else {
                    this.model.year_to = this.model.year_from
                }
                if (this.model.same_year) {
                    this.dateSchema.fields.year_to.min = YEAR_MIN
                    this.dateSchema.fields.year_from.max = YEAR_MAX
                    this.$refs.dateForm.validate()
                }
            },
            'model.year_from': function (newValue, oldValue) {
                if (this.model.same_year) {
                    this.model.year_to = this.model.year_from
                }
                if (this.model.year_from === this.model.year_to && this.model.year_from != null) {
                    this.model.same_year = true
                }
                this.$refs.dateForm.validate()
            },
            'model.year_to': function (newValue, oldValue) {
                if (this.model.year_from === this.model.year_to && this.model.year_from != null) {
                    this.model.same_year = true
                }
            }
        },
        computed: {
            warnEstimate: function() {
                if (this.model.year_from !== this.model.year_to && this.model.year_from % 25 === 0 && this.model.year_to % 25 ===0) {
                    return true
                }
                return false
            }
        },
        methods: {
            createMultiSelect(label, extra, extraSelectOptions) {
                let result = {
                    type: 'multiselectClear',
                    label: label,
                    placeholder: 'Loading',
                    // lowercase first letter + remove spaces
                    model: label.charAt(0).toLowerCase() + label.slice(1).replace(/[ ]/g, ''),
                    // Values will be loaded using a watcher
                    values: [],
                    selectOptions: {
                        optionsLimit: 10000,
                        customLabel: ({id, name}) => {
                            return name
                        },
                        showLabels: false,
                        loading: true
                    },
                    // Will be enabled when list of scribes is loaded
                    disabled: true
                }
                if (extra != null) {
                    for (let key of Object.keys(extra)) {
                        result[key] = extra[key]
                    }
                }
                if (extraSelectOptions != null) {
                    for (let key of Object.keys(extraSelectOptions)) {
                        result['selectOptions'][key] = extraSelectOptions[key]
                    }
                }
                return result
            },
            loadLocationField(field) {
                let locations = Object.values(this.locations)
                if (field.hasOwnProperty('dependency')) {
                    locations = locations.filter((location) => location[field.dependency + '_id'] === this.model[field.dependency]['id'])
                }

                let values = locations
                    .map((location) => {return {'id': location[field.model + '_id'], 'name': location[field.model + '_name']}})
                    // remove duplicates
                    .filter((location, index, self) => index === self.findIndex((l) => l.id === location.id))


                // only keep current value if it is in the list of possible values
                if (this.model[field.model] != null) {
                    if ((values.filter(v => v.id === this.model[field.model].id)).length === 0) {
                        this.model[field.model] = null
                    }
                }
                field.values = values
            },
            validated(isValid, errors) {
                for (let field of ['year_from', 'year_to']) {
                    if (isNaN(this.model[field])) {
                        this.model[field] = null
                        this.$refs.dateForm.validate()
                        return
                    }
                }
                // set year min and max values
                if (!this.model.same_year) {
                    if (this.model.year_from != null) {
                        this.dateSchema.fields.year_to.min = Math.max(YEAR_MIN, this.model.year_from)
                    }
                    else {
                        this.dateSchema.fields.year_to.min = YEAR_MIN
                    }
                    if (this.model.year_to != null) {
                        this.dateSchema.fields.year_from.max = Math.min(YEAR_MAX, this.model.year_to)
                    }
                    else {
                        this.dateSchema.fields.year_from.max = YEAR_MAX
                    }
                }
                this.invalidForms = (
                    !this.$refs.hasOwnProperty('locationForm') || this.$refs.locationForm.errors.length > 0
                    || !this.$refs.hasOwnProperty('contentForm') || this.$refs.contentForm.errors.length > 0
                    || !this.$refs.hasOwnProperty('patronsForm') || this.$refs.patronsForm.errors.length > 0
                    || !this.$refs.hasOwnProperty('scribesForm') || this.$refs.patronsForm.errors.length > 0
                    || !this.$refs.hasOwnProperty('relatedPersonsForm') || this.$refs.relatedPersonsForm.errors.length > 0
                    || !this.$refs.hasOwnProperty('dateForm') || this.$refs.dateForm.errors.length > 0
                    || !this.$refs.hasOwnProperty('originForm') || this.$refs.originForm.errors.length > 0
                )

                this.calcDiff()
            },
            disableField(field) {
                field.disabled = true
                field.placeholder = 'Loading'
                field.selectOptions.loading = true
                field.values = []
            },
            dependencyField(field) {
                this.model[field.model] = null
                field.disabled = true
                field.selectOptions.loading = false
                field.placeholder = 'Please select a ' + field.dependency + ' first'
            },
            noValuesField(field) {
                this.model[field.model] = null
                field.disabled = true
                field.selectOptions.loading = false
                field.placeholder = 'No ' + field.label.toLowerCase() + 's available'
            },
            enableField(field) {
                if (field.values.length === 0) {
                    return this.noValuesField(field)
                }

                field.selectOptions.loading = false
                field.disabled = false
                let label = field.label.toLowerCase()
                let article = ['origin'].indexOf(label) < 0 ? 'a ' : 'an '
                field.placeholder = (field.selectOptions.multiple ? 'Select ' : 'Select ' + article) + label
            },
            calcDiff() {
                if (Object.keys(this.originalModel).length === 0) {
                    return
                }
                let fields = Object.assign(
                    {},
                    this.locationSchema.fields,
                    this.contentSchema.fields,
                    this.patronsSchema.fields,
                    this.scribesSchema.fields,
                    this.relatedPersonsSchema.fields,
                    {
                        year_from: this.dateSchema.fields.year_from,
                        year_to: this.dateSchema.fields.year_to
                    },
                    this.originSchema.fields,
                    {
                        bibliography: {
                            label: 'Bibliography'
                        }
                    }
                )
                this.diff = []
                for (let key of Object.keys(fields)) {
                    if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key])) {
                        if (key === 'bibliography') {
                            this.diff.push({
                                'label': fields[key]['label'],
                                'old': this.displayBibliography(this.originalModel[key]),
                                'new': this.displayBibliography(this.model[key]),
                            })
                        }
                        else {
                            this.diff.push({
                                'label': fields[key]['label'],
                                'old': this.originalModel[key],
                                'new': this.model[key],
                            })
                        }
                    }
                }
            },
            getDisplay(item) {
                if (item == null) {
                    return null
                }
                else if (item.hasOwnProperty('name')) {
                    return item['name']
                }
                return item
            },
            toSave() {
                let result = {}
                for (let key of Object.keys(this.model)) {
                    if (['same_year'].indexOf(key) > -1) {
                        continue
                    }
                    if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key])) {
                        if (['year_from', 'year_to'].indexOf(key) > -1 && result['date'] == null) {
                            result['date'] = {
                                floor: this.model.year_from,
                                ceiling: this.model.year_to
                            }
                            continue
                        }
                        // default
                        result[key] = this.model[key]
                    }
                }
                return result
            },
            reset() {
                this.resetModal = false
                this.model = JSON.parse(JSON.stringify(this.originalModel))
            },
            save() {
                this.openRequests++
                this.saveModal = false
                axios.put(this.putManuscriptUrl, this.toSave())
                    .then( (response) => {
                        // redirect to the detail page
                        window.location = this.getManuscriptUrl
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.alerts.push({type: 'error', message: 'Something whent wrong while saving the manuscript data.'})
                        this.openRequests--
                    })
            },
            updateBib(bibliography, index) {
                this.bibIndex = index
                this.editBib = JSON.parse(JSON.stringify(bibliography))
                this.editBibModal = true
            },
            delBib(bibliography, index) {
                this.bibIndex = index
                this.editBib = JSON.parse(JSON.stringify(bibliography))
                this.delBibModal = true
            },
            newBib(type) {
                this.bibIndex = -1
                this.editBib = {
                    type: type
                }
                this.editBibModal = true
            },
            submitBib() {
                this.$refs.editBibForm.validate()
                if (this.$refs.editBibForm.errors.length == 0) {
                    if (this.bibIndex > -1) {
                        this.model.bibliography[this.editBib.type + "s"][this.bibIndex] = JSON.parse(JSON.stringify(this.editBib))
                    }
                    else {
                        this.model.bibliography[this.editBib.type + "s"].push(JSON.parse(JSON.stringify(this.editBib)))
                    }
                    this.calcDiff()
                    this.editBibModal = false
                }
            },
            submitDeleteBib() {
                this.model.bibliography[this.editBib.type + "s"].splice(this.bibIndex, 1)
                this.calcDiff()
                this.delBibModal = false
            },
            displayBibliography(bibliography) {
                let result = []
                for (let bookBibliography of bibliography['books']) {
                    result.push(bookBibliography.book.name + this.formatPages(bookBibliography.startPage, bookBibliography.endPage, ': ') + '.')
                }
                for (let articleBibliography of bibliography['articles']) {
                    result.push(articleBibliography.article.name + this.formatPages(articleBibliography.startPage, articleBibliography.endPage, ': ') + '.')
                }
                for (let bookChapterBibliography of bibliography['bookChapters']) {
                    result.push(bookChapterBibliography.bookChapter.name + this.formatPages(bookChapterBibliography.startPage, bookChapterBibliography.endPage, ': ') + '.')
                }
                for (let onlineSourceBibliography of bibliography['onlineSources']) {
                    result.push(onlineSourceBibliography.onlineSource.url + (onlineSourceBibliography.relUrl == null ? '' : onlineSourceBibliography.relUrl) + '.')
                }
                return result
            },
            formatPages(startPage = null, endPage = null, prefix = '') {
                if (startPage == null) {
                    return '';
                }
                if (endPage == null) {
                    return prefix + startPage;
                }
                return prefix + startPage + '-' + endPage;
            }
        }
    }
</script>
