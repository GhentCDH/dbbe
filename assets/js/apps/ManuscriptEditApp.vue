<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />

            <locatedAtPanel
                id="location"
                ref="location"
                header="Location"
                :links="[{title: 'Locations', reload: 'locations', edit: urls['locations_edit']}]"
                :model="model.locatedAt"
                :values="locations"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <contentPanel
                id="contents"
                ref="contents"
                header="Contents"
                :links="[{title: 'Contents', reload: 'contents', edit: urls['contents_edit']}]"
                :model="model.contents"
                :values="contents"
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
                :occurrence-person-roles="manuscript ? manuscript.occurrencePersonRoles : {}"
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

            <originPanel
                id="origin"
                ref="origin"
                header="Origin"
                :links="[{title: 'Origins', reload: 'origins', edit: urls['origins_edit']}]"
                :model="model.origin"
                :values="origins"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
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
                :links="[{title: 'Books', reload: 'books', edit: urls['bibliographies_search']},{title: 'Articles', reload: 'articles', edit: urls['bibliographies_search']},{title: 'Book chapters', reload: 'bookChapters', edit: urls['bibliographies_search']},{title: 'Online sources', reload: 'onlineSources', edit: urls['bibliographies_search']},{title: 'Blog Posts', reload: 'blogPosts', edit: urls['bibliographies_search']},{title: 'Phd theses', reload: 'phds', edit: urls['bibliographies_search']},{title: 'Bib varia', reload: 'bibVarias', edit: urls['bibliographies_search']}]"
                :model="model.bibliography"
                :values="bibliographies"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <generalManuscriptPanel
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
                            href="#location"
                            :class="{'bg-danger': !($refs.location && $refs.location.isValid)}"
                        >Location</a>
                    </li>
                    <li>
                        <a
                            href="#contents"
                            :class="{'bg-danger': !($refs.contents && $refs.contents.isValid)}"
                        >Contents</a>
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
                            href="#origin"
                            :class="{'bg-danger': !($refs.origin && $refs.origin.isValid)}"
                        >Origin</a>
                    </li>
                    <li>
                        <a
                            href="#occurrenceOrder"
                            :class="{'bg-danger': !($refs.occurrenceOrder && $refs.occurrenceOrder.isValid)}"
                        >Occurrence Order</a>
                    </li>
                    <li>
                        <a
                            href="#identification"
                            :class="{'bg-danger': !($refs.identification && $refs.identification.isValid)}"
                        >Identification</a>
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
import Vue from 'vue/dist/vue.js';

import AbstractEntityEdit from '../mixins/AbstractEntityEdit'
import axios from 'axios'
import {isLoginError} from "@/helpers/errorUtil";
import Reset from "@/Components/Edit/Modals/Reset.vue";
import Invalid from "@/Components/Edit/Modals/Invalid.vue";
import Save from "@/Components/Edit/Modals/Save.vue";

const panelComponents = import.meta.glob('../Components/Edit/Panels/{LocatedAt,Content,Person,Date,Origin,OccurrenceOrder,Identification,Bibliography,GeneralManuscript,Management}.vue', { eager: true })

for (const path in panelComponents) {
  const component = panelComponents[path].default
  const compName = path.split('/').pop().replace(/\.vue$/, '')
  Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel', component)
}

export default {
    mixins: [ AbstractEntityEdit ],
    components: {
      resetModal: Reset,
      invalidModal: Invalid,
      saveModal: Save
    },
    data() {
        let data = {
            identifiers: JSON.parse(this.initIdentifiers),
            roles: JSON.parse(this.initRoles),
            contributorRoles: JSON.parse(this.initContributorRoles),
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
                contents: {
                    contents: [],
                },
                personRoles: {},
                contributorRoles: {},
                dates: [],
                origin: {origin: null},
                occurrenceOrder: {occurrenceOrder: []},
                identification: {},
                bibliography: {
                    articles: [],
                    blogPosts: [],
                    books: [],
                    bookChapters: [],
                    onlineSources: [],
                    phds: [],
                    bibVarias: [],
                },
                general: {
                    acknowledgements: [],
                    publicComment: null,
                    privateComment: null,
                    illustrated: null,
                    status: null,
                    public: null,
                },
                managements: {
                    managements: [],
                },
            },
            panels: [
                'location',
                'contents',
                'persons',
                'dates',
                'origin',
                'occurrenceOrder',
                'identification',
                'bibliography',
                'general',
                'contributors',
                'managements',
            ],
        }
        for (let identifier of data.identifiers) {
            data.model.identification[identifier.systemName] = null
        }
        for (let role of data.roles) {
            data.model.personRoles[role.systemName] = [];
        }
        for (let role of data.contributorRoles) {
            data.model.contributorRoles[role.systemName] = [];
        }
        return data
    },
    created () {
        this.manuscript = this.data.manuscript;

        this.locations = [];
        this.contents = this.data.contents;
        this.historicalPersons = [];
        this.origins = this.data.origins;
        this.bibliographies = {
            articles: [],
            blogPosts: [],
            books: [],
            bookChapters: [],
            onlineSources: [],
            phds: [],
            bibVarias: [],
        };
        this.generals = {
            acknowledgements: this.data.acknowledgements,
            statuses: this.data.statuses,
        };
        this.dbbePersons = this.data.dbbePersons;
        this.managements = this.data.managements;
    },
    methods: {
        loadAsync() {
            this.reload('locations');
            this.reload('historicalPersons');
            this.reload('books');
            this.reload('articles');
            this.reload('bookChapters');
            this.reload('onlineSources');
            this.reload('blogPosts');
            this.reload('phds');
            this.reload('bibVarias');
        },
        setData() {
            if (this.manuscript != null) {
                // Located At
                this.model.locatedAt = this.manuscript.locatedAt

                // Contents
                this.model.contents = {
                    contents: this.manuscript.contents,
                }

                // PersonRoles
                for (let role of this.roles) {
                    this.model.personRoles[role.systemName] = this.manuscript.personRoles == null ? [] : this.manuscript.personRoles[role.systemName];
                }

                // Dates
                if (this.manuscript.dates != null) {
                    this.model.dates = this.manuscript.dates;
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
                    articles: [],
                    blogPosts: [],
                    books: [],
                    bookChapters: [],
                    onlineSources: [],
                    phds: [],
                    bibVarias: [],
                }
                if (this.manuscript.bibliography != null) {
                    for (let bib of this.manuscript.bibliography) {
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
                        case 'phd':
                            this.model.bibliography.phds.push(bib)
                            break
                        case 'bibVaria':
                            this.model.bibliography.bibVarias.push(bib)
                            break
                        }
                    }
                }

                // Identification
                this.model.identification = {}
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.manuscript.identifications == null ? [] : this.manuscript.identifications[identifier.systemName];
                }

                // General
                this.model.general = {
                    acknowledgements: this.manuscript.acknowledgements,
                    publicComment: this.manuscript.publicComment,
                    privateComment: this.manuscript.privateComment,
                    status: this.manuscript.status,
                    illustrated: this.manuscript.illustrated,
                    public: this.manuscript.public,
                }

                // ContributorRoles
                for (let role of this.contributorRoles) {
                    this.model.contributorRoles[role.systemName] = this.manuscript.contributorRoles == null ? [] : this.manuscript.contributorRoles[role.systemName];
                }

                // Management
                this.model.managements = {
                    managements: this.manuscript.managements,
                }
            }
            else {
                // Set defaults
                this.model.general.illustrated = false;
                this.model.general.public = true;
            }
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
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the manuscript data.', login: isLoginError(error)})
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
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the manuscript data.', login: isLoginError(error)})
                        this.openRequests--
                    })
            }
        },
        reload(type) {
            switch (type) {
            case 'articles':
            case 'blogPosts':
            case 'books':
            case 'bookChapters':
            case 'onlineSources':
            case 'phds':
            case 'bibVarias':
                this.reloadNestedItems(type, this.bibliographies);
                break;
            case 'acknowledgements':
            case 'statuses':
                this.reloadNestedItems(type, this.generals);
                break;
            default:
                this.reloadSimpleItems(type);
            }
        },
    }
}
</script>
