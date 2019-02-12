<template>
    <div>
        <article
            ref="target"
            class="col-sm-9 mbottom-large"
        >
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />

            <locatedAtPanel
                id="location"
                ref="locatedAt"
                header="Location"
                :links="[{url: urls['locations_edit'], text: 'Edit locations'}]"
                :model="model.locatedAt"
                :values="locations"
                @validated="validated"
            />

            <contentPanel
                id="content"
                ref="content"
                header="Content"
                :links="[{url: urls['contents_edit'], text: 'Edit contents'}]"
                :model="model.content"
                :values="contents"
                @validated="validated"
            />

            <personPanel
                id="persons"
                ref="persons"
                header="Persons"
                :roles="roles"
                :model="model.personRoles"
                :values="historicalPersons"
                :occurrence-person-roles="manuscript ? manuscript.occurrencePersonRoles : {}"
                @validated="validated"
            />

            <datePanel
                id="date"
                ref="date"
                header="Date"
                :model="model.date"
                @validated="validated"
            />

            <originPanel
                id="origin"
                ref="origin"
                header="Origin"
                :links="[{url: urls['origins_edit'], text: 'Edit origins'}]"
                :model="model.origin"
                :values="origins"
                @validated="validated"
            />

            <occurrenceOrderPanel
                id="occurrenceOrder"
                ref="occurrenceOrder"
                header="Occurrence Order"
                :model="model.occurrenceOrder"
                @validated="validated"
            />

            <identificationPanel
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

            <generalManuscriptPanel
                id="general"
                ref="general"
                header="General"
                :links="[{url: urls['statuses_edit'], text: 'Edit statuses'}]"
                :model="model.general"
                :values="statuses"
                @validated="validated"
            />

            <managementPanel
                id="managements"
                ref="managements"
                header="Management collections"
                :links="[{url: urls['managements_edit'], text: 'Edit management collections'}]"
                :model="model.managements"
                :values="managements"
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
                v-if="manuscript"
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
                    <li><a href="#location">Location</a></li>
                    <li><a href="#content">Content</a></li>
                    <li><a href="#persons">Persons</a></li>
                    <li><a href="#date">Date</a></li>
                    <li><a href="#origin">Origin</a></li>
                    <li><a href="#occurrenceOrder">Occurrence Order</a></li>
                    <li><a href="#identification">Identification</a></li>
                    <li><a href="#bibliography">Bibliography</a></li>
                    <li><a href="#general">General</a></li>
                    <li><a href="#managements">Management collections</a></li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <resetModal
            title="manuscript"
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
            title="manuscript"
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

const panelComponents = require.context('../Components/Edit/Panels', false, /[/](?:LocatedAt|Content|Person|Date|Origin|OccurrenceOrder|Identification|Bibliography|GeneralManuscript|Management)[.]vue$/)

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
            manuscript: null,
            locations: null,
            contents: null,
            historicalPersons: null,
            origins: null,
            bibliographies: null,
            statuses: null,
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
                managements: {managements: null},
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
                'managements',
            ],
        }
        for (let identifier of data.identifiers) {
            data.model.identification[identifier.systemName] = null
            if (identifier.extra) {
                data.model.identification[identifier.systemName + '_extra'] = null
            }
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
        this.managements = this.data.managements
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
                for (let role of this.roles) {
                    this.model.personRoles[role.systemName] = this.manuscript.personRoles != null ? this.manuscript.personRoles[role.systemName] : null
                }
                this.$refs.persons.init();

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
                    if (identifier.extra) {
                        this.model.identification[identifier.systemName + '_extra'] = this.manuscript.identifications != null ? this.manuscript.identifications[identifier.systemName + '_extra'] : null
                    }
                }

                // General
                this.model.general = {
                    publicComment: this.manuscript.publicComment,
                    privateComment: this.manuscript.privateComment,
                    status: this.manuscript.status,
                    illustrated: this.manuscript.illustrated,
                    public: this.manuscript.public,
                }

                // Management
                this.model.managements = {
                    managements: this.manuscript.managements,
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
