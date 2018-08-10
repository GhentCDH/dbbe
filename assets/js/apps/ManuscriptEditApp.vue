<template>
    <div>
        <article
            class="col-sm-9 mbottom-large"
            ref="target">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />

            <locatedAtPanel
                id="location"
                header="Location"
                :link="{url: urls['locations_edit'], text: 'Edit locations'}"
                :model="model.locatedAt"
                :values="locations"
                @validated="validated"
                ref="locatedAt" />

            <contentPanel
                id="content"
                header="Content"
                :link="{url: urls['contents_edit'], text: 'Edit contents'}"
                :model="model.content"
                :values="contents"
                @validated="validated"
                ref="content" />

            <personPanel
                id="persons"
                header="Persons"
                :roles="roles"
                :model="model.personRoles"
                :values="historicalPersons"
                :occurrence-person-roles="manuscript ? manuscript.occurrencePersonRoles : []"
                @validated="validated"
                ref="persons" />

            <datePanel
                id="date"
                header="Date"
                :model="model.date"
                @validated="validated"
                ref="date" />

            <originPanel
                id="origin"
                header="Origin"
                :link="{url: urls['origins_edit'], text: 'Edit origins'}"
                :model="model.origin"
                :values="origins"
                @validated="validated"
                ref="origin" />

            <occurrenceOrderPanel
                id="occurrenceOrder"
                header="Occurrence Order"
                :model="model.occurrenceOrder"
                @validated="validated"
                ref="occurrenceOrder" />

            <identificationPanel
                id="identification"
                header="Identification"
                :identifiers="identifiers"
                :model="model.identification"
                @validated="validated"
                ref="identification" />

            <bibliographyPanel
                id="bibliography"
                header="Bibliography"
                :model="model.bibliography"
                :values="bibliographies"
                @validated="validated"
                ref="bibliography" />

            <generalManuscriptPanel
                id="general"
                header="General"
                :link="{url: urls['statuses_edit'], text: 'Edit statuses'}"
                :model="model.general"
                :values="statuses"
                @validated="validated"
                ref="general" />

            <btn
                id="actions"
                type="warning"
                :disabled="diff.length === 0"
                @click="resetModal=true">
                Reset
            </btn>
            <btn
                v-if="manuscript"
                type="success"
                :disabled="(diff.length === 0)"
                @click="saveButton()">
                Save changes
            </btn>
            <btn
                v-else
                type="success"
                :disabled="(diff.length === 0)"
                @click="saveButton()">
                Save
            </btn>
            <btn
                :disabled="(diff.length !== 0)"
                @click="reload()">
                Refresh all data
            </btn>
            <div
                class="loading-overlay"
                v-if="openRequests">
                <div class="spinner" />
            </div>
        </article>
        <aside class="col-sm-3 inpage-nav-container xs-hide">
            <div ref="anchor" />
            <nav
                v-scrollspy
                role="navigation"
                :class="isSticky ? 'stick padding-default bg-tertiary' : 'padding-default bg-tertiary'"
                :style="stickyStyle">
                <h2>Quick navigation</h2>
                <ul class="linklist linklist-dark">
                    <li><a href="#location">Location</a></li>
                    <li><a href="#content">Content</a></li>
                    <li><a href="#persons">Persons</a></li>
                    <li><a href="#date">Date</a></li>
                    <li><a href="#origin">Origin</a></li>
                    <li><a href="#occurrenceOrder">Occurrence Order</a></li>
                    <li><a href="#identification">Identification</a></li>
                    <li><a href="#bibliography">Bibliography</a></li>
                    <li><a href="#general">General</a></li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <resetModal
            title="manuscript"
            :show="resetModal"
            @cancel="resetModal=false"
            @confirm="reset()" />
        <invalidModal
            :show="invalidModal"
            @cancel="invalidModal=false"
            @confirm="invalidModal=false" />
        <saveModal
            title="manuscript"
            :show="saveModal"
            :diff="diff"
            :alerts="saveAlerts"
            @cancel="cancelSave()"
            @confirm="save()"
            @dismiss-alert="saveAlerts.splice($event, 1)" />
    </div>
</template>

<script>
import Vue from 'vue'

import AbstractEntityEdit from '../Components/Edit/AbstractEntityEdit'

const panelComponents = require.context('../Components/Edit/Panels', false, /[.]vue$/)

for(let key of panelComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '')
    if (['LocatedAt', 'Content', 'Person', 'Date', 'Origin', 'OccurrenceOrder', 'Identification', 'Bibliography', 'GeneralManuscript'].includes(compName)) {
        Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel', panelComponents(key).default)
    }
}

export default {
    mixins: [ AbstractEntityEdit ],
    props: {
        initRoles: {
            type: String,
            default: '',
        },
    },
    data() {
        let data = {
            manuscript: null,
            locations: null,
            contents: null,
            historicalPersons: null,
            origins: null,
            bibliographies: null,
            statuses: null,
            roles: JSON.parse(this.initRoles),
            model: {
                locatedAt: {
                    location: {
                        id: null,
                        regionWithParents: null,
                        institution: null,
                        collection: null,
                    },
                    shelf: null,
                    extra: null,
                },
                content: {content: null},
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
                origin: {origin: null},
                occurrenceOrder: {occurrenceOrder: []},
                identification: {},
                bibliography: {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                },
                general: {
                    publicComment: null,
                    privateComment: null,
                    illustrated: null,
                    status: null,
                    public: null,
                },
            },
            forms: [
                'locatedAt',
                'content',
                'persons',
                'date',
                'origin',
                'occurrenceOrder',
                'identification',
                'bibliography',
                'general',
            ],
        }
        for (let identifier of JSON.parse(this.initIdentifiers)) {
            data.model.identification[identifier.systemName] = null
        }
        for (let role of data.roles) {
            data.model.personRoles[role.systemName] = null
        }
        return data
    },
    created () {
        this.manuscript = this.data.manuscript
        this.locations = this.data.locations
        this.contents = this.data.contents
        this.historicalPersons = this.data.historicalPersons
        this.origins = this.data.origins
        this.bibliographies = {
            books: this.data.books,
            articles: this.data.articles,
            bookChapters: this.data.bookChapters,
            onlineSources: this.data.onlineSources,
        }
        this.statuses = this.data.statuses
    },
    mounted () {
        this.loadManuscript()
        window.addEventListener('scroll', (event) => {
            this.scrollY = Math.round(window.scrollY)
        })
    },
    methods: {
        loadManuscript() {
            if (this.manuscript != null) {
                // Located At
                this.model.locatedAt = this.manuscript.locatedAt

                // Content
                this.model.content = {
                    content: this.manuscript.content,
                }

                // PersonRoles
                this.model.identification = {}
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.manuscript.identifications != null ? this.manuscript.identifications[identifier.systemName] : null
                }
                this.model.personRoles = {}
                for (let role of this.roles) {
                    this.model.personRoles[role.systemName] = this.manuscript.personRoles != null ? this.manuscript.personRoles[role.systemName] : null
                }

                // Date
                this.model.date = {
                    floor: this.manuscript.date != null ? this.manuscript.date.floor : null,
                    ceiling: this.manuscript.date != null ? this.manuscript.date.ceiling : null,
                    exactDate: null,
                    exactYear: null,
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                }

                // Origin
                this.model.origin = {
                    origin: this.manuscript.origin,
                }

                // Occurrence order
                this.model.occurrenceOrder = {
                    occurrenceOrder: this.manuscript.occurrences,
                }

                // Bibliography
                this.model.bibliography = {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                }
                if (this.manuscript.bibliography != null) {
                    for (let bib of this.manuscript.bibliography) {
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

                // Identification
                this.model.identification = {}
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.manuscript.identifications != null ? this.manuscript.identifications[identifier.systemName] : null
                }

                // General
                this.model.general = {
                    publicComment: this.manuscript.publicComment,
                    privateComment: this.manuscript.privateComment,
                    status: this.manuscript.status,
                    illustrated: this.manuscript.illustrated,
                    public: this.manuscript.public,
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
            if (this.manuscript == null) {
                axios.post(this.urls['manuscript_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['manuscript_get'].replace('manuscript_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the manuscript data.', login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['manuscript_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['manuscript_get']
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the manuscript data.', login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
        },
    }
}
</script>
