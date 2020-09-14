<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />

            <occurrenceVersesPanel
                id="verses"
                ref="verses"
                header="Verses"
                :model="model.verses"
                :urls="urls"
                @validated="validated"
            />

            <basicOccurrencePanel
                id="basic"
                ref="basic"
                header="Basic information"
                :links="[{title: 'Manuscripts', reload: 'manuscripts', edit: urls['manuscripts_search']}]"
                :model="model.basic"
                :values="manuscripts"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <occurrenceTypesPanel
                id="types"
                ref="types"
                header="Types"
                :links="[{title: 'Types', reload: 'types', edit: urls['types_search']}]"
                :model="model.types"
                :values="types"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <personPanel
                id="persons"
                ref="persons"
                header="Persons"
                :links="[{title: 'Persons', reload: 'historicalPersons', edit: urls['persons_search']}]"
                :roles="roles"
                :model="model.personRoles"
                :values="historicalPersons"
                :keys="{historicalPersons: {init: false}}"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <datePanel
                id="dates"
                ref="dates"
                header="Dates"
                :model="model.dates"
                :config="{'completed at': {limit: 1, type: 'date'}}"
                @validated="validated"
            />

            <metrePanel
                id="metres"
                ref="metres"
                header="Metres"
                :links="[{title: 'Metres', reload: 'metres', edit: urls['metres_edit']}]"
                :model="model.metres"
                :values="metres"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <genrePanel
                id="genres"
                ref="genres"
                header="Genres"
                :links="[{title: 'Genres', reload: 'genres', edit: urls['genres_edit']}]"
                :model="model.genres"
                :values="genres"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <subjectPanel
                id="subjects"
                ref="subjects"
                header="Subjects"
                :links="[{title: 'Persons', reload: 'historicalPersons', edit: urls['persons_search']}, {title: 'Keywords', reload: 'keywordSubjects', edit: urls['keywords_subject_edit']}]"
                :model="model.subjects"
                :values="subjects"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <identificationPanel
                v-if="identifiers.length > 0"
                id="identification"
                ref="identification"
                header="Identification"
                :identifiers="identifiers"
                :model="model.identification"
                @validated="validated"
            />

            <imagePanel
                id="images"
                ref="images"
                header="Images"
                :model="model.images"
                :urls="urls"
                @validated="validated"
            />

            <bibliographyPanel
                id="bibliography"
                ref="bibliography"
                header="Bibliography"
                :links="[{title: 'Books', reload: 'books', edit: urls['bibliographies_search']},{title: 'Articles', reload: 'articles', edit: urls['bibliographies_search']},{title: 'Book chapters', reload: 'bookChapters', edit: urls['bibliographies_search']},{title: 'Online sources', reload: 'onlineSources', edit: urls['bibliographies_search']},{title: 'Blog Posts', reload: 'blogPosts', edit: urls['bibliographies_search']}]"
                :model="model.bibliography"
                :reference-type="true"
                :image="true"
                :values="bibliographies"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <generalOccurrencePanel
                id="general"
                ref="general"
                header="General"
                :links="[{title: 'Acknowledgements', reload: 'acknowledgements', edit: urls['acknowledgements_edit']}, {title: 'Statuses', reload: 'statuses', edit: urls['statuses_edit']}]"
                :model="model.general"
                :values="generals"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <personPanel
                id="contributors"
                ref="contributors"
                header="Contributors"
                :links="[{title: 'Persons', reload: 'dbbePersons', edit: urls['persons_search']}]"
                :roles="contributorRoles"
                :model="model.contributorRoles"
                :values="dbbePersons"
                :keys="{dbbePersons: {init: true}}"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <managementPanel
                id="managements"
                ref="managements"
                header="Management collections"
                :links="[{title: 'Management collections', reload: 'managements', edit: urls['managements_edit']}]"
                :model="model.managements"
                :values="managements"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <btn
                id="actions"
                type="warning"
                :disabled="data.clone ? JSON.stringify(originalModel) !== JSON.stringify(model) : diff.length === 0"
                @click="resetModal=true"
            >
                Reset
            </btn>
            <btn
                v-if="occurrence"
                type="success"
                :disabled="(diff.length === 0)"
                @click="saveButton()"
            >
                Save changes
            </btn>
            <btn
                v-else
                type="success"
                :disabled="(diff.length === 0)"
                @click="saveButton()"
            >
                Save
            </btn>
            <div
                v-if="openRequests"
                class="loading-overlay"
            >
                <div class="spinner" />
            </div>
        </article>
        <aside class="col-sm-3 inpage-nav-container xs-hide">
            <div ref="anchor" />
            <nav
                v-scrollspy
                role="navigation"
                class="padding-default bg-tertiary"
                :class="{stick: isSticky}"
                :style="stickyStyle"
            >
                <h2>Quick navigation</h2>
                <ul class="linklist linklist-dark">
                    <li>
                        <a
                            href="#verses"
                            :class="{'bg-danger': !($refs.verses && $refs.verses.isValid)}"
                        >Verses</a>
                    </li>
                    <li>
                        <a
                            href="#basic"
                            :class="{'bg-danger': !($refs.basic && $refs.basic.isValid)}"
                        >Basic information</a>
                    </li>
                    <li>
                        <a
                            href="#types"
                            :class="{'bg-danger': !($refs.types && $refs.types.isValid)}"
                        >Types</a>
                    </li>
                    <li>
                        <a
                            href="#persons"
                            :class="{'bg-danger': !($refs.persons && $refs.persons.isValid)}"
                        >Persons</a>
                    </li>
                    <li>
                        <a
                            href="#dates"
                            :class="{'bg-danger': !($refs.dates && $refs.dates.isValid)}"
                        >Dates</a>
                    </li>
                    <li>
                        <a
                            href="#metres"
                            :class="{'bg-danger': !($refs.metres && $refs.metres.isValid)}"
                        >Metres</a>
                    </li>
                    <li>
                        <a
                            href="#genres"
                            :class="{'bg-danger': !($refs.genres && $refs.genres.isValid)}"
                        >Genres</a>
                    </li>
                    <li>
                        <a
                            href="#subjects"
                            :class="{'bg-danger': !($refs.subjects && $refs.subjects.isValid)}"
                        >Subjects</a>
                    </li>
                    <li v-if="identifiers.length > 0">
                        <a
                            href="#identification"
                            :class="{'bg-danger': !($refs.identification && $refs.identification.isValid)}"
                        >Identification</a>
                    </li>
                    <li>
                        <a
                            href="#images"
                            :class="{'bg-danger': !($refs.images && $refs.images.isValid)}"
                        >Images</a>
                    </li>
                    <li>
                        <a
                            href="#bibliography"
                            :class="{'bg-danger': !($refs.bibliography && $refs.bibliography.isValid)}"
                        >Bibliography</a>
                    </li>
                    <li>
                        <a
                            href="#general"
                            :class="{'bg-danger': !($refs.general && $refs.general.isValid)}"
                        >General</a>
                    </li>
                    <li>
                        <a
                            href="#contributors"
                            :class="{'bg-danger': !($refs.contributors && $refs.contributors.isValid)}"
                        >Contributors</a>
                    </li>
                    <li>
                        <a
                            href="#managements"
                            :class="{'bg-danger': !($refs.managements && $refs.managements.isValid)}"
                        >Management collections</a>
                    </li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <resetModal
            title="occurrence"
            :show="resetModal"
            @cancel="resetModal=false"
            @confirm="reset()"
        />
        <invalidModal
            :show="invalidModal"
            @cancel="invalidModal=false"
            @confirm="invalidModal=false"
        />
        <saveModal
            title="occurrence"
            :show="saveModal"
            :diff="diff"
            :alerts="saveAlerts"
            @cancel="cancelSave()"
            @confirm="save()"
            @dismiss-alert="saveAlerts.splice($event, 1)"
        />
    </div>
</template>

<script>
import Vue from 'vue'

import AbstractEntityEdit from '../Components/Edit/AbstractEntityEdit'

const panelComponents = require.context('../Components/Edit/Panels', false, /[/](?:OccurrenceVerses|BasicOccurrence|OccurrenceTypes|Person|Date|Metre|Genre|Subject|Identification|Image|Bibliography|GeneralOccurrence|Management)[.]vue$/)

for(let key of panelComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '')
    Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel', panelComponents(key).default)
}

export default {
    mixins: [ AbstractEntityEdit ],
    data() {
        let data = {
            identifiers: JSON.parse(this.initIdentifiers),
            roles: JSON.parse(this.initRoles),
            contributorRoles: JSON.parse(this.initContributorRoles),
            occurrence: null,
            manuscripts: null,
            types: null,
            historicalPersons: null,
            metres: null,
            genres: null,
            subjects: null,
            bibliographies: null,
            generals: null,
            dbbePersons: null,
            managements: null,
            model: {
                verses: {
                    incipit: null,
                    title: null,
                    verses: [],
                    numberOfVerses: null,
                },
                basic: {
                    manuscript: null,
                    foliumStart:  null,
                    foliumStartRecto:  null,
                    foliumEnd:  null,
                    foliumEndRecto:  null,
                    unsure:  null,
                    pageStart: null,
                    pageEnd: null,
                    generalLocation:  null,
                    oldLocation:  null,
                    alternativeFoliumStart:  null,
                    alternativeFoliumStartRecto:  null,
                    alternativeFoliumEnd:  null,
                    alternativeFoliumEndRecto:  null,
                    alternativePageStart: null,
                    alternativePageEnd: null,
                },
                types: {types: null},
                personRoles: {},
                contributorRoles: {},
                dates: [],
                metres: {metres: null},
                genres: {genres: null},
                subjects: {
                    personSubjects: null,
                    keywordSubjects: null,
                },
                identification: {},
                images: {
                    images: [],
                    imageLinks: [],
                },
                bibliography: {
                    articles: [],
                    blogPosts: [],
                    books: [],
                    bookChapters: [],
                    onlineSources: [],
                },
                general: {
                    palaeographicalInfo: null,
                    contextualInfo: null,
                    acknowledgements: null,
                    publicComment: null,
                    privateComment: null,
                    textStatus: null,
                    recordStatus: null,
                    dividedStatus: null,
                    sourceStatus: null,
                    public: null,
                },
                managements: {managements: null},
            },
            panels: [
                'verses',
                'basic',
                'types',
                'persons',
                'dates',
                'metres',
                'genres',
                'subjects',
                'images',
                'bibliography',
                'general',
                'contributors',
                'managements',
            ],
        }
        for (let identifier of data.identifiers) {
            data.model.identification[identifier.systemName] = null
        }
        if (data.identifiers.length > 0) {
            data.panels.push('identification')
        }
        for (let role of data.roles) {
            data.model.personRoles[role.systemName] = []
        }
        for (let role of data.contributorRoles) {
            data.model.contributorRoles[role.systemName] = []
        }
        return data
    },
    created () {
        this.occurrence = this.data.occurrence;

        this.manuscripts = [];
        this.types = [];
        this.historicalPersons = [];
        this.metres = this.data.metres;
        this.genres = this.data.genres;
        this.subjects = {
            historicalPersons: [],
            keywordSubjects: this.data.keywords,
        };
        this.bibliographies = {
            books: [],
            articles: [],
            bookChapters: [],
            onlineSources: [],
            blogPosts: [],
            referenceTypes: this.data.referenceTypes,
        };
        this.generals = {
            acknowledgements: this.data.acknowledgements,
            textStatuses: this.data.textStatuses,
            recordStatuses: this.data.recordStatuses,
            dividedStatuses: this.data.dividedStatuses,
            sourceStatuses: this.data.sourceStatuses,
        };
        this.dbbePersons = this.data.dbbePersons;
        this.managements = this.data.managements;
    },
    mounted() {
        // AbstractEntityEdit mounted hook is called first

        // Make sure a duplicated occurrence is saved as a new occurrence
        this.$nextTick(() => {
            if (this.data.clone) {
                this.occurrence = null;
                this.validateForms();
                this.calcAllChanges();
                for (let panel of this.panels) {
                    this.$refs[panel].enableFields();
                }
            }
        });
    },
    methods: {
        loadAsync() {
            this.reload('manuscripts');
            this.reload('types');
            this.reload('historicalPersons');
            this.reload('books');
            this.reload('articles');
            this.reload('bookChapters');
            this.reload('onlineSources');
            this.reload('blogPosts');
        },
        setData() {
            if (this.occurrence != null) {
                // Verses
                this.model.verses = {
                    incipit: this.occurrence.incipit,
                    title: this.occurrence.title,
                    verses: this.occurrence.verses != null ? this.occurrence.verses : [],
                    numberOfVerses: this.occurrence.numberOfVerses,
                }

                // Basic information
                this.model.basic = {
                    manuscript: this.occurrence.manuscript,
                    foliumStart: this.occurrence.foliumStart,
                    foliumStartRecto: this.occurrence.foliumStartRecto,
                    foliumEnd: this.occurrence.foliumEnd,
                    foliumEndRecto: this.occurrence.foliumEndRecto,
                    unsure: this.occurrence.unsure,
                    pageStart: this.occurrence.pageStart,
                    pageEnd: this.occurrence.pageEnd,
                    generalLocation: this.occurrence.generalLocation,
                    oldLocation: this.occurrence.oldLocation,
                    alternativeFoliumStart: this.occurrence.alternativeFoliumStart,
                    alternativeFoliumStartRecto: this.occurrence.alternativeFoliumStartRecto,
                    alternativeFoliumEnd: this.occurrence.alternativeFoliumEnd,
                    alternativeFoliumEndRecto: this.occurrence.alternativeFoliumEndRecto,
                    alternativePageStart: this.occurrence.alternativePageStart,
                    alternativePageEnd: this.occurrence.alternativePageEnd,
                }

                // Types
                this.model.types = {
                    types: this.occurrence.types,
                }

                // PersonRoles
                for (let role of this.roles) {
                    this.model.personRoles[role.systemName] = this.occurrence.personRoles == null ? [] : this.occurrence.personRoles[role.systemName];
                }

                // Dates
                if (this.occurrence.dates != null) {
                    this.model.dates = this.occurrence.dates;
                }

                // Metre
                this.model.metres = {
                    metres: this.occurrence.metres,
                }

                // Genre
                this.model.genres = {
                    genres: this.occurrence.genres,
                }

                // Subject
                this.model.subjects = {
                    personSubjects: this.occurrence.subjects.persons,
                    keywordSubjects: this.occurrence.subjects.keywords,
                }

                // Identification
                this.model.identification = {}
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.occurrence.identifications == null ? [] : this.occurrence.identifications[identifier.systemName];
                }

                // Images
                this.model.images = {
                    images: this.occurrence.images.images,
                    imageLinks: this.occurrence.images.imageLinks,
                }

                // Bibliography
                this.model.bibliography = {
                    articles: [],
                    blogPosts: [],
                    books: [],
                    bookChapters: [],
                    onlineSources: [],
                }
                if (this.occurrence.bibliography != null) {
                    for (let bib of this.occurrence.bibliography) {
                        switch (bib['type']) {
                        case 'article':
                            this.model.bibliography.articles.push(bib)
                            break
                        case 'blogPost':
                            this.model.bibliography.blogPosts.push(bib)
                            break
                        case 'book':
                            this.model.bibliography.books.push(bib)
                            break
                        case 'bookChapter':
                            this.model.bibliography.bookChapters.push(bib)
                            break
                        case 'onlineSource':
                            this.model.bibliography.onlineSources.push(bib)
                            break
                        }
                    }
                }

                // General
                this.model.general = {
                    palaeographicalInfo: this.occurrence.palaeographicalInfo,
                    contextualInfo: this.occurrence.contextualInfo,
                    acknowledgements: this.occurrence.acknowledgements,
                    publicComment: this.occurrence.publicComment,
                    privateComment: this.occurrence.privateComment,
                    textStatus: this.occurrence.textStatus,
                    recordStatus: this.occurrence.recordStatus,
                    dividedStatus: this.occurrence.dividedStatus,
                    sourceStatus: this.occurrence.sourceStatus,
                    public: this.occurrence.public,
                }

                // ContributorRoles
                for (let role of this.contributorRoles) {
                    this.model.contributorRoles[role.systemName] = this.occurrence.contributorRoles == null ? [] : this.occurrence.contributorRoles[role.systemName];
                }

                // Management
                this.model.managements = {
                    managements: this.occurrence.managements,
                }
            }
            else {
                this.model.general.public = true;
                this.model.verses = {
                    verses: [],
                };
            }
        },
        save() {
            this.openRequests++
            this.saveModal = false
            if (this.occurrence == null) {
                axios.post(this.urls['occurrence_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['occurrence_get'].replace('occurrence_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the occurrence data.', login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['occurrence_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['occurrence_get']
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the occurrence data.', login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
        },
        reload(type) {
            switch(type) {
            case 'historicalPersons':
                this.reloadItems(
                    'historicalPersons',
                    ['historicalPersons'],
                    [this.historicalPersons, this.subjects.historicalPersons],
                    this.urls['historical_persons_get']
                );
                break;
            case 'keywordSubjects':
                this.reloadItems(
                    'keywordSubjects',
                    ['keywordSubjects'],
                    [this.subjects.keywordSubjects],
                    this.urls['keywords_subject_get']
                );
                break;
            case 'articles':
            case 'blogPosts':
            case 'books':
            case 'bookChapters':
            case 'onlineSources':
                this.reloadNestedItems(type, this.bibliographies);
                break;
            case 'acknowledgements':
                this.reloadNestedItems(type, this.generals);
                break;
            case 'statuses':
                this.reloadItems(
                    'statuses',
                    ['textStatuses', 'recordStatuses', 'dividedStatuses', 'sourceStatuses'],
                    [this.generals.textStatuses, this.generals.recordStatuses, this.generals.dividedStatuses, this.generals.sourceStatuses],
                    this.urls['statuses_get'],
                    [(i) => i.type === 'occurrence_text', (i) => i.type === 'occurrence_record', (i) => i.type === 'occurrence_divided', (i) => i.type === 'occurrence_source'],
                );
                break;
            default:
                this.reloadSimpleItems(type);
            }
        },
    }
}
</script>
