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

            <personPanel
                id="persons"
                ref="persons"
                header="Persons"
                :links="[{title: 'Persons', reload: 'modernPersons', edit: urls['persons_search']}]"
                :roles="roles"
                :model="model.personRoles"
                :values="modernPersons"
                :keys="{modernPersons: {init: false}}"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <basicBookChapterPanel
                id="basic"
                ref="basic"
                header="Basic Information"
                :links="[{title: 'Books', reload: 'books', edit: urls['bibliographies_search']}]"
                :model="model.basic"
                :values="books"
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

            <generalBibItemPanel
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
                v-if="bookChapter"
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
                            href="#persons"
                            :class="{'bg-danger': !($refs.persons && $refs.persons.isValid)}"
                        >Persons</a>
                    </li>
                    <li>
                        <a
                            href="#basic"
                            :class="{'bg-danger': !($refs.basic && $refs.basic.isValid)}"
                        >Basic information</a>
                    </li>
                    <li v-if="identifiers.length > 0">
                        <a
                            href="#identification"
                            :class="{'bg-danger': !($refs.identification && $refs.identification.isValid)}"
                        >Identification</a>
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
            title="book chapter"
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
            title="book chapter"
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

const panelComponents = require.context('../Components/Edit/Panels', false, /[/](?:Person|BasicBookChapter|Identification|GeneralBibItem|Management)[.]vue$/);

for(let key of panelComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '');
    Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel', panelComponents(key).default)
}

export default {
    mixins: [ AbstractEntityEdit ],
    data() {
        let data = {
            identifiers: JSON.parse(this.initIdentifiers),
            roles: JSON.parse(this.initRoles),
            bookChapter: null,
            modernPersons: null,
            books: null,
            model: {
                personRoles: {},
                basic: {
                    title: null,
                    book: null,
                    startPage: null,
                    endPage: null,
                    rawPages: null,
                },
                identification: {},
                managements: {managements: null},
            },
            panels: [
                'persons',
                'basic',
                'general',
                'managements',
            ],
        };
        for (let identifier of data.identifiers) {
            data.model.identification[identifier.systemName] = null;
        }
        if (data.identifiers.length > 0) {
            data.panels.push('identification')
        }
        for (let role of data.roles) {
            data.model.personRoles[role.systemName] = null
        }
        return data
    },
    created () {
        this.bookChapter = this.data.bookChapter;

        this.modernPersons = [];
        this.books = [];
        this.managements = this.data.managements;
    },
    methods: {
        loadAsync() {
            this.reload('modernPersons');
            this.reload('books');
        },
        setData() {
            if (this.bookChapter != null) {
                // PersonRoles
                for (let role of this.roles) {
                    this.model.personRoles[role.systemName] = this.bookChapter.personRoles == null ? [] : this.bookChapter.personRoles[role.systemName];
                }

                // Basic info
                this.model.basic = {
                    title: this.bookChapter.title,
                    book: this.bookChapter.book,
                    startPage: this.bookChapter.startPage,
                    endPage: this.bookChapter.endPage,
                    rawPages: this.bookChapter.rawPages,
                };

                // Identification
                this.model.identification = {};
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.bookChapter.identifications == null ? [] : this.bookChapter.identifications[identifier.systemName];
                }

                // General
                this.model.general = {
                    publicComment: this.bookChapter.publicComment,
                    privateComment: this.bookChapter.privateComment,
                };

                // Management
                this.model.managements = {
                    managements: this.bookChapter.managements,
                }
            }
        },
        save() {
            this.openRequests++;
            this.saveModal = false;
            if (this.bookChapter == null) {
                axios.post(this.urls['book_chapter_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {};
                        // redirect to the detail page
                        window.location = this.urls['book_chapter_get'].replace('book_chapter_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error);
                        this.saveModal = true;
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the book chapter data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)});
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['book_chapter_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {};
                        // redirect to the detail page
                        window.location = this.urls['book_chapter_get']
                    })
                    .catch( (error) => {
                        console.log(error);
                        this.saveModal = true;
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the book chapter data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)});
                        this.openRequests--
                    })
            }
        },
        reload(type) {
            this.reloadSimpleItems(type);
        },
    }
}
</script>
