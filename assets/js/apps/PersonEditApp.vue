<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alert
                v-for="(item, index) in alerts"
                :key="index"
                :type="item.type"
                dismissible
                @dismissed="alerts.splice(index, 1)"
            >
                {{ item.message }}
            </alert>

            <basicPersonPanel
                id="basic"
                ref="basic"
                header="Basic Information"
                :links="[
                    {title:'Self designations', reload: 'selfDesignations', edit: urls['self_designations_edit']},
                    {title: 'Offices', reload: 'offices', edit: urls['offices_edit']},
                    {title: 'Origins', reload: 'origins', edit: urls['origins_edit']},
                ]"
                :model="model.basic"
                :values="{selfDesignations: selfDesignations, offices: offices, origins: origins}"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <datePanel
                id="dates"
                ref="dates"
                header="Dates"
                :model="model.dates"
                :config="{'born': {limit: 1, type: 'date'}, 'died': {limit: 1, type: 'date'}, 'attested': {limit: 0, type: 'dateOrInterval'}}"
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
                :links="[{title: 'Books', reload: 'books', edit: urls['bibliographies_search']},{title: 'Articles', reload: 'articles', edit: urls['bibliographies_search']},{title: 'Book chapters', reload: 'bookChapters', edit: urls['bibliographies_search']},{title: 'Online sources', reload: 'onlineSources', edit: urls['bibliographies_search']}]"
                :model="model.bibliography"
                :values="bibliographies"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <generalPersonPanel
                id="general"
                ref="general"
                header="General"
                :model="model.general"
                @validated="validated"
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
                v-if="person"
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
                            href="#basic"
                            :class="{'bg-danger': !($refs.basic && $refs.basic.isValid)}"
                        >Basic Information</a>
                    </li>
                    <li>
                        <a
                            href="#dates"
                            :class="{'bg-danger': !($refs.dates && $refs.dates.isValid)}"
                        >Dates</a>
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
                            href="#managements"
                            :class="{'bg-danger': !($refs.managements && $refs.managements.isValid)}"
                        >Management collections</a>
                    </li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <resetModal
            title="person"
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
            title="person"
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

const panelComponents = require.context('../Components/Edit/Panels', false, /[/](?:BasicPerson|Date|Identification|Office|Bibliography|GeneralPerson|Management)[.]vue$/);

for(let key of panelComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '');
    Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel', panelComponents(key).default)
}

export default {
    mixins: [ AbstractEntityEdit ],
    data() {
        let data = {
            identifiers: JSON.parse(this.initIdentifiers),
            person: null,
            offices: null,
            origins: null,
            selfDesignations: null,
            bibliographies: null,
            model: {
                basic: {
                    firstName: null,
                    lastName: null,
                    selfDesignations: null,
                    origin: null,
                    extra: null,
                    unprocessed: null,
                    historical: null,
                    modern: null,
                    dbbe: null,
                },
                dates: [],
                identification: {},
                offices: {offices: null},
                bibliography: {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                },
                general: {
                    publicComment: null,
                    privateComment: null,
                    public: null,
                },
                managements: {managements: null},
            },
            panels: [
                'basic',
                'dates',
                'identification',
                'bibliography',
                'general',
                'managements',
            ],
        };
        for (let identifier of data.identifiers) {
            data.model.identification[identifier.systemName] = null;
        }
        return data
    },
    created () {
        this.person = this.data.person;

        this.offices = this.data.offices;
        this.origins = this.data.origins;
        this.selfDesignations = this.data.selfDesignations;
        this.bibliographies = {
            books: [],
            articles: [],
            bookChapters: [],
            onlineSources: [],
        };
        this.managements = this.data.managements
    },
    methods: {
        loadAsync() {
            this.reload('books');
            this.reload('articles');
            this.reload('bookChapters');
            this.reload('onlineSources');
        },
        setData() {
            if (this.person != null) {
                // Basic info
                this.model.basic = {
                    firstName: this.person.firstName,
                    lastName: this.person.lastName,
                    selfDesignations: this.person.selfDesignations,
                    offices: this.person.officesWithParents,
                    origin: this.person.origin,
                    extra: this.person.extra,
                    unprocessed: this.person.unprocessed,
                    historical: this.person.historical,
                    modern: this.person.modern,
                    dbbe: this.person.dbbe,
                };

                // Dates
                this.model.dates = this.person.dates;

                // Identification
                this.model.identification = {};
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.person.identifications == null ? [] : this.person.identifications[identifier.systemName];
                }

                // Bibliography
                this.model.bibliography = {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                };
                if (this.person.bibliography != null) {
                    for (let bib of this.person.bibliography) {
                        switch (bib['type']) {
                        case 'book':
                            this.model.bibliography.books.push(bib);
                            break;
                        case 'article':
                            this.model.bibliography.articles.push(bib);
                            break;
                        case 'bookChapter':
                            this.model.bibliography.bookChapters.push(bib);
                            break;
                        case 'onlineSource':
                            this.model.bibliography.onlineSources.push(bib);
                            break
                        }
                    }
                }

                // General
                this.model.general = {
                    publicComment: this.person.publicComment,
                    privateComment: this.person.privateComment,
                    public: this.person.public,
                };

                // Management
                this.model.managements = {
                    managements: this.person.managements,
                }
            }
            else {
                this.model.general.public = true
            }
        },
        save() {
            this.openRequests++;
            this.saveModal = false;
            if (this.person == null) {
                axios.post(this.urls['person_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {};
                        // redirect to the detail page
                        window.location = this.urls['person_get'].replace('person_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error);
                        this.saveModal = true;
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the person data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)});
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['person_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {};
                        // redirect to the detail page
                        window.location = this.urls['person_get']
                    })
                    .catch( (error) => {
                        console.log(error);
                        this.saveModal = true;
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the person data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)});
                        this.openRequests--
                    })
            }
        },
        reload(type) {
            switch (type) {
            case 'books':
            case 'articles':
            case 'bookChapters':
            case 'onlineSources':
                this.reloadNestedItems(type, this.bibliographies);
                break;
            default:
                this.reloadSimpleItems(type);
            }
        },
    }
}
</script>
