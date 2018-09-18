<template>
    <div>
        <article
            class="col-sm-9 mbottom-large"
            ref="target">
            <alert
                v-for="(item, index) in alerts"
                :key="index"
                :type="item.type"
                dismissible
                @dismissed="alerts.splice(index, 1)">
                {{ item.message }}
            </alert>

            <personPanel
                id="persons"
                header="Persons"
                :roles="roles"
                :model="model.personRoles"
                :values="modernPersons"
                @validated="validated"
                ref="persons" />

            <basicBookPanel
                id="basic"
                header="Basic Information"
                :model="model.basic"
                @validated="validated"
                ref="basic" />

            <btn
                id="actions"
                type="warning"
                :disabled="diff.length === 0"
                @click="resetModal=true">
                Reset
            </btn>
            <btn
                v-if="book"
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
                class="padding-default bg-tertiary"
                :class="{stick: isSticky}"
                :style="stickyStyle">
                <h2>Quick navigation</h2>
                <ul class="linklist linklist-dark">
                    <li><a href="#persons">Persons</a></li>
                    <li><a href="#basic">Basic information</a></li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <resetModal
            title="book"
            :show="resetModal"
            @cancel="resetModal=false"
            @confirm="reset()" />
        <invalidModal
            :show="invalidModal"
            @cancel="invalidModal=false"
            @confirm="invalidModal=false" />
        <saveModal
            title="book"
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

const panelComponents = require.context('../Components/Edit/Panels', false, /[/](?:Person|BasicBook)[.]vue$/)

for(let key of panelComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '')
    Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel', panelComponents(key).default)
}

export default {
    mixins: [ AbstractEntityEdit ],
    data() {
        let data = {
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
            },
            forms: [
                'persons',
                'basic',
            ],
        }
        for (let role of data.roles) {
            data.model.personRoles[role.systemName] = null
        }
        return data
    },
    created () {
        this.book = this.data.book
        this.modernPersons = this.data.modernPersons
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
                    this.model.personRoles[role.systemName] = this.book.personRoles != null ? this.book.personRoles[role.systemName] : null
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
