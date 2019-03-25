<template>
    <div>
        <article
            ref="target"
            class="col-sm-9 mbottom-large"
        >
            <alert
                v-for="(item, index) in alerts"
                :key="index"
                :type="item.type"
                dismissible
                @dismissed="alerts.splice(index, 1)"
            >
                {{ item.message }}
            </alert>

            <basicOccurrencePanel
                id="basic"
                ref="basic"
                header="Basic information"
                :model="model.basic"
                :values="manuscripts"
                :clone="data.clone"
                @validated="validated"
            />

            <occurrenceVersesPanel
                id="verses"
                ref="verses"
                header="Verses"
                :model="model.verses"
                :urls="urls"
                :clone="data.clone"
                @validated="validated"
            />

            <occurrenceTypesPanel
                id="types"
                ref="types"
                header="Types"
                :model="model.types"
                :values="types"
                :clone="data.clone"
                @validated="validated"
            />

            <personPanel
                id="persons"
                ref="persons"
                header="Persons"
                :roles="roles"
                :model="model.personRoles"
                :values="historicalPersons"
                :clone="data.clone"
                @validated="validated"
            />

            <datePanel
                id="date"
                ref="date"
                header="Date"
                :model="model.date"
                :clone="data.clone"
                @validated="validated"
            />

            <metrePanel
                id="metres"
                ref="metres"
                header="Metres"
                :links="[{url: urls['metres_edit'], text: 'Edit metres'}]"
                :model="model.metres"
                :values="metres"
                :clone="data.clone"
                @validated="validated"
            />

            <genrePanel
                id="genres"
                ref="genres"
                header="Genres"
                :links="[{url: urls['genres_edit'], text: 'Edit genres'}]"
                :model="model.genres"
                :values="genres"
                :clone="data.clone"
                @validated="validated"
            />

            <subjectPanel
                id="subjects"
                ref="subjects"
                header="Subjects"
                :links="[{url: urls['keywords_subject_get'], text: 'Edit keywords'}]"
                :model="model.subjects"
                :values="subjects"
                :clone="data.clone"
                @validated="validated"
            />

            <identificationPanel
                v-if="identifiers.length > 0"
                id="identification"
                ref="identification"
                header="Identification"
                :identifiers="identifiers"
                :model="model.identification"
                :clone="data.clone"
                @validated="validated"
            />

            <imagePanel
                id="images"
                ref="images"
                header="Images"
                :model="model.images"
                :urls="urls"
                :clone="data.clone"
                @validated="validated"
            />

            <bibliographyPanel
                id="bibliography"
                ref="bibliography"
                header="Bibliography"
                :model="model.bibliography"
                :reference-type="true"
                :image="true"
                :values="bibliographies"
                :clone="data.clone"
                @validated="validated"
            />

            <generalOccurrencePanel
                id="general"
                ref="general"
                header="General"
                :links="[{url: urls['acknowledgements_edit'], text: 'Edit acknowledgements'}, {url: urls['statuses_edit'], text: 'Edit statuses'}]"
                :model="model.general"
                :values="generals"
                :clone="data.clone"
                @validated="validated"
            />

            <personPanel
                id="contributors"
                ref="contributors"
                header="Contributors"
                :roles="contributorRoles"
                :model="model.contributorRoles"
                :values="dbbePersons"
                :clone="data.clone"
                @validated="validated"
            />

            <managementPanel
                id="managements"
                ref="managements"
                header="Management collections"
                :links="[{url: urls['managements_edit'], text: 'Edit management collections'}]"
                :model="model.managements"
                :values="managements"
                :clone="data.clone"
                @validated="validated"
            />

            <btn
                id="actions"
                type="warning"
                :disabled="diff.length === 0"
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
            <btn
                :disabled="(diff.length !== 0)"
                @click="reload()"
            >
                Refresh all data
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
                    <li><a href="#basic">Basic information</a></li>
                    <li><a href="#verses">Verses</a></li>
                    <li><a href="#types">Types</a></li>
                    <li><a href="#persons">Persons</a></li>
                    <li><a href="#date">Date</a></li>
                    <li><a href="#metres">Metres</a></li>
                    <li><a href="#genres">Genres</a></li>
                    <li><a href="#subjects">Subjects</a></li>
                    <li v-if="identifiers.length > 0"><a href="#identification">Identification</a></li>
                    <li><a href="#images">Images</a></li>
                    <li><a href="#bibliography">Bibliography</a></li>
                    <li><a href="#general">General</a></li>
                    <li><a href="#contributors">Contributors</a></li>
                    <li><a href="#managements">Management collections</a></li>
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

const panelComponents = require.context('../Components/Edit/Panels', false, /[/](?:BasicOccurrence|OccurrenceVerses|OccurrenceTypes|Person|Date|Metre|Genre|Subject|Identification|Image|Bibliography|GeneralOccurrence|Management)[.]vue$/)

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
            dbbePersons: null,
            metres: null,
            genres: null,
            subjects: null,
            bibliographies: null,
            generals: null,
            model: {
                basic: {
                    incipit: null,
                    title: null,
                    manuscript: null,
                    foliumStart:  null,
                    foliumStartRecto:  null,
                    foliumEnd:  null,
                    foliumEndRecto:  null,
                    unsure:  null,
                    pageStart: null,
                    pageEnd: null,
                    generalLocation:  null,
                    alternativeFoliumStart:  null,
                    alternativeFoliumStartRecto:  null,
                    alternativeFoliumEnd:  null,
                    alternativeFoliumEndRecto:  null,
                },
                verses: {
                    verses: [],
                    numberOfVerses: null,
                },
                types: {types: null},
                personRoles: {},
                contributorRoles: {},
                date: {
                    floor: null,
                    ceiling: null,
                    exactDate: null,
                    exactYear: null,
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },
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
                    books: [],
                    articles: [],
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
            forms: [
                'basic',
                'verses',
                'types',
                'persons',
                'date',
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
            data.forms.push('identification')
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
        this.occurrence = this.data.occurrence
        this.manuscripts = this.data.manuscripts
        this.types = this.data.types
        this.historicalPersons = this.data.historicalPersons
        this.dbbePersons = this.data.dbbePersons
        this.metres = this.data.metres
        this.genres = this.data.genres
        this.subjects = {
            personSubjects: this.historicalPersons,
            keywordSubjects: this.data.keywords,
        }
        this.bibliographies = {
            books: this.data.books,
            articles: this.data.articles,
            bookChapters: this.data.bookChapters,
            onlineSources: this.data.onlineSources,
            referenceTypes: this.data.referenceTypes,
        }
        this.generals = {
            acknowledgements: this.data.acknowledgements,
            textStatuses: this.data.textStatuses,
            recordStatuses: this.data.recordStatuses,
            dividedStatuses: this.data.dividedStatuses,
            sourceStatuses: this.data.sourceStatuses,
        }
        this.managements = this.data.managements
    },
    mounted () {
        this.loadOccurrence()
        window.addEventListener('scroll', (event) => {
            this.scrollY = Math.round(window.scrollY)
        })
    },
    methods: {
        loadOccurrence() {
            if (this.occurrence != null) {
                // Basic information
                this.model.basic = {
                    incipit: this.occurrence.incipit,
                    title: this.occurrence.title,
                    manuscript: this.occurrence.manuscript,
                    foliumStart: this.occurrence.foliumStart,
                    foliumStartRecto: this.occurrence.foliumStartRecto ? true : false,
                    foliumEnd: this.occurrence.foliumEnd,
                    foliumEndRecto: this.occurrence.foliumEndRecto ? true : false,
                    unsure: this.occurrence.unsure,
                    pageStart: this.occurrence.pageStart,
                    pageEnd: this.occurrence.pageEnd,
                    generalLocation: this.occurrence.generalLocation,
                    alternativeFoliumStart: this.occurrence.alternativeFoliumStart,
                    alternativeFoliumStartRecto: this.occurrence.alternativeFoliumStartRecto ? true : false,
                    alternativeFoliumEnd: this.occurrence.alternativeFoliumEnd,
                    alternativeFoliumEndRecto: this.occurrence.alternativeFoliumEndRecto ? true : false,
                }

                // Verses
                this.model.verses = {
                    verses: this.occurrence.verses,
                    numberOfVerses: this.occurrence.numberOfVerses,
                }

                // Types
                this.model.types = {
                    types: this.occurrence.types,
                }

                // PersonRoles
                for (let role of this.roles) {
                    if (this.occurrence.personRoles[role.systemName] != null) {
                        this.model.personRoles[role.systemName] = this.occurrence.personRoles[role.systemName];
                    }
                }

                // Date
                this.model.date = {
                    floor: this.occurrence.date != null ? this.occurrence.date.floor : null,
                    ceiling: this.occurrence.date != null ? this.occurrence.date.ceiling : null,
                    exactDate: null,
                    exactYear: null,
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
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
                    this.model.identification[identifier.systemName] = this.occurrence.identifications != null ? this.occurrence.identifications[identifier.systemName] : null
                }

                // Images
                this.model.images = {
                    images: this.occurrence.images.images,
                    imageLinks: this.occurrence.images.imageLinks,
                }

                // Bibliography
                this.model.bibliography = {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                }
                if (this.occurrence.bibliography != null) {
                    for (let bib of this.occurrence.bibliography) {
                        switch (bib['type']) {
                        case 'book':
                            this.model.bibliography.books.push(bib)
                            break
                        case 'article':
                            this.model.bibliography.articles.push(bib)
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
                    if (this.occurrence.contributorRoles[role.systemName] != null) {
                        this.model.contributorRoles[role.systemName] = this.occurrence.contributorRoles[role.systemName];
                    }
                }

                // Management
                this.model.managements = {
                    managements: this.occurrence.managements,
                }
            }
            else {
                this.model.general.public = true
            }

            // Make sure a duplicated occurrence is saved as a new occurrence
            if (this.data.clone) {
                this.occurrence = null;
                this.validateForms();
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
    }
}
</script>
