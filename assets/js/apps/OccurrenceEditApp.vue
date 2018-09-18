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
                @validated="validated"
            />

            <versesPanel
                id="verses"
                ref="verses"
                header="Verses"
                :model="model.verses"
                :urls="urls"
                @validated="validated"
            />

            <typesPanel
                id="types"
                ref="types"
                header="Types"
                :model="model.types"
                :values="types"
                @validated="validated"
            />

            <personPanel
                id="persons"
                ref="persons"
                header="Persons"
                :roles="roles"
                :model="model.personRoles"
                :values="historicalPersons"
                @validated="validated"
            />

            <datePanel
                id="date"
                ref="date"
                header="Date"
                :model="model.date"
                @validated="validated"
            />

            <meterPanel
                id="meters"
                ref="meters"
                header="Meters"
                :link="{url: urls['meters_edit'], text: 'Edit meters'}"
                :model="model.meters"
                :values="meters"
                @validated="validated"
            />

            <genrePanel
                id="genres"
                ref="genres"
                header="Genres"
                :link="{url: urls['genres_edit'], text: 'Edit genres'}"
                :model="model.genres"
                :values="genres"
                @validated="validated"
            />

            <subjectPanel
                id="subjects"
                ref="subjects"
                header="Subjects"
                :link="{url: urls['keywords_edit'], text: 'Edit keywords'}"
                :model="model.subjects"
                :values="subjects"
                @validated="validated"
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

            <bibliographyPanel
                id="bibliography"
                ref="bibliography"
                header="Bibliography"
                :model="model.bibliography"
                :values="bibliographies"
                @validated="validated"
            />

            <generalOccurrencePanel
                id="general"
                ref="general"
                header="General"
                :link="{url: urls['statuses_edit'], text: 'Edit statuses'}"
                :model="model.general"
                :values="statuses"
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
                    <li><a href="#meters">Meters</a></li>
                    <li><a href="#genres">Genres</a></li>
                    <li><a href="#subjects">Subjects</a></li>
                    <li v-if="identifiers.length > 0"><a href="#identification">Identification</a></li>
                    <li><a href="#bibliography">Bibliography</a></li>
                    <li><a href="#general">General</a></li>
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

const panelComponents = require.context('../Components/Edit/Panels', false, /[/](?:BasicOccurrence|Verses|Types|Person|Date|Meter|Genre|Subject|Identification|Bibliography|GeneralOccurrence)[.]vue$/)

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
            occurrence: null,
            manuscripts: null,
            types: null,
            historicalPersons: null,
            meters: null,
            genres: null,
            subjects: null,
            bibliographies: null,
            statuses: null,
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
                meters: {meters: null},
                genres: {genres: null},
                subjects: {
                    persons: null,
                    keywords: null,
                },
                identification: {},
                bibliography: {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                },
                general: {
                    paleographicalInfo: null,
                    contextualInfo: null,
                    publicComment: null,
                    privateComment: null,
                    textStatus: null,
                    recordStatus: null,
                    dividedStatus: null,
                    public: null,
                },
            },
            forms: [
                'basic',
                'verses',
                'types',
                'persons',
                'date',
                'meters',
                'genres',
                'subjects',
                'bibliography',
                'general',
            ],
        }
        for (let identifier of data.identifiers) {
            data.model.identification[identifier.systemName] = null
        }
        if (data.identifiers.length > 0) {
            data.forms.push('identification')
        }
        for (let role of data.roles) {
            data.model.personRoles[role.systemName] = null
        }
        return data
    },
    created () {
        this.occurrence = this.data.occurrence
        this.manuscripts = this.data.manuscripts
        this.types = this.data.types
        this.historicalPersons = this.data.historicalPersons
        this.meters = this.data.meters
        this.genres = this.data.genres
        this.subjects = {
            persons: this.historicalPersons,
            keywords: this.data.keywords,
        }
        this.bibliographies = {
            books: this.data.books,
            articles: this.data.articles,
            bookChapters: this.data.bookChapters,
            onlineSources: this.data.onlineSources,
        }
        this.statuses = {
            textStatuses: this.data.textStatuses,
            recordStatuses: this.data.recordStatuses,
            dividedStatuses: this.data.dividedStatuses,
        }
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
                    foliumStartRecto: this.occurrence.foliumStartRecto,
                    foliumEnd: this.occurrence.foliumEnd,
                    foliumEndRecto: this.occurrence.foliumEndRecto,
                    unsure: this.occurrence.unsure,
                    generalLocation: this.occurrence.generalLocation,
                    alternativeFoliumStart: this.occurrence.alternativeFoliumStart,
                    alternativeFoliumStartRecto: this.occurrence.alternativeFoliumStartRecto,
                    alternativeFoliumEnd: this.occurrence.alternativeFoliumEnd,
                    alternativeFoliumEndRecto: this.occurrence.alternativeFoliumEndRecto,
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
                    this.model.personRoles[role.systemName] = this.occurrence.personRoles != null ? this.occurrence.personRoles[role.systemName] : null
                }
                this.$refs.persons.init();

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

                // Meter
                this.model.meters = {
                    meters: this.occurrence.meters,
                }

                // Genre
                this.model.genres = {
                    genres: this.occurrence.genres,
                }

                // Subject
                this.model.subjects = {
                    persons: this.occurrence.subjects.persons,
                    keywords: this.occurrence.subjects.keywords,
                }

                // Identification
                this.model.identification = {}
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.manuscript.identifications != null ? this.manuscript.identifications[identifier.systemName] : null
                }

                // Bibliography
                this.model.bibliography = {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                }
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

                // General
                this.model.general = {
                    paleographicalInfo: this.occurrence.paleographicalInfo,
                    contextualInfo: this.occurrence.contextualInfo,
                    acknowledgement: this.occurrence.acknowledgement,
                    publicComment: this.occurrence.publicComment,
                    privateComment: this.occurrence.privateComment,
                    textStatus: this.occurrence.textStatus,
                    recordStatus: this.occurrence.recordStatus,
                    dividedStatus: this.occurrence.dividedStatus,
                    public: this.occurrence.public,
                }
            }

            else {
                this.model.general.public = true
            }

            this.originalModel = JSON.parse(JSON.stringify(this.model))
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
