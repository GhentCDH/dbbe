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

            <personPanel
                id="persons"
                ref="persons"
                header="Persons"
                :roles="roles"
                :model="model.personRoles"
                :values="modernPersons"
                @validated="validated"
            />

            <basicBookPanel
                id="basic"
                ref="basic"
                header="Basic Information"
                :model="model.basic"
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
                v-if="book"
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
                    <li><a href="#persons">Persons</a></li>
                    <li><a href="#basic">Basic information</a></li>
                    <li v-if="identifiers.length > 0"><a href="#identification">Identification</a></li>
                    <li><a href="#general">General</a></li>
                    <li><a href="#managements">Management collections</a></li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <resetModal
            title="book"
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
            title="book"
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

const panelComponents = require.context('../Components/Edit/Panels', false, /[/](?:Person|BasicBook|Identification|GeneralBibItem|Management)[.]vue$/)

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
            book: null,
            modernPersons: null,
            model: {
                personRoles: {},
                basic: {
                    title: null,
                    year: null,
                    city: null,
                    editor: null,
                    publisher: null,
                    series: null,
                    volume: null,
                    totalVolumes: null,
                },
                identification: {},
                managements: {managements: null},
            },
            forms: [
                'persons',
                'basic',
                'general',
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
            data.model.personRoles[role.systemName] = null
        }
        return data
    },
    created () {
        this.book = this.data.book
        this.modernPersons = this.data.modernPersons
        this.managements = this.data.managements
    },
    mounted () {
        this.loadData()
        window.addEventListener('scroll', (event) => {
            this.scrollY = Math.round(window.scrollY)
        })
    },
    methods: {
        loadData() {
            if (this.book != null) {
                // PersonRoles
                for (let role of this.roles) {
                    this.model.personRoles[role.systemName] = this.book.personRoles == null ? [] : this.book.personRoles[role.systemName];
                }
                this.$refs.persons.init();

                // Basic info
                this.model.basic = {
                    title: this.book.title,
                    year: this.book.year,
                    city: this.book.city,
                    editor: this.book.editor,
                    publisher: this.book.publisher,
                    series: this.book.series,
                    volume: this.book.volume,
                    totalVolumes: this.book.totalVolumes,
                }

                // Identification
                this.model.identification = {}
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.book.identifications == null ? [] : this.book.identifications[identifier.systemName];
                }

                // General
                this.model.general = {
                    privateComment: this.book.privateComment,
                }

                // Management
                this.model.managements = {
                    managements: this.book.managements,
                }
            }

            this.originalModel = JSON.parse(JSON.stringify(this.model))
        },
        save() {
            this.openRequests++
            this.saveModal = false
            if (this.book == null) {
                axios.post(this.urls['book_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['book_get'].replace('book_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the book data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['book_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['book_get']
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the book data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
        },
    }
}
</script>
