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

            <basicPanel
                id="basic"
                header="Basic Information"
                :model="model.basic"
                @validated="validated"
                ref="basic" />

            <identificationPanel
                id="identification"
                header="Identification"
                :model="model.identification"
                @validated="validated"
                ref="identification" />

            <occupationPanel
                id="occupation"
                header="Occupations"
                :link="{url: urls['occupations_edit'], text: 'Edit occupations'}"
                :model="model.occupation"
                :values="occupations"
                @validated="validated"
                ref="occupation" />

            <generalPersonPanel
                id="general"
                header="General"
                :model="model.general"
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
                v-if="occurrence"
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
                    <li><a href="#basic">Basic Information</a></li>
                    <li><a href="#identification">Identification</a></li>
                    <li><a href="#occupation">Occupations</a></li>
                    <li><a href="#general">General</a></li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <resetModal
            title="person"
            :show="resetModal"
            @cancel="resetModal=false"
            @confirm="reset()" />
        <invalidModal
            :show="invalidModal"
            @cancel="invalidModal=false"
            @confirm="invalidModal=false" />
        <saveModal
            title="person"
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
    if (['Basic', 'Identification', 'Occupation', 'GeneralPerson'].includes(compName)) {
        Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel', panelComponents(key).default)
    }
}

export default {
    mixins: [ AbstractEntityEdit ],
    data() {
        return {
            occurrence: null,
            persons: null,
            bibliographies: null,
            statuses: null,
            model: {
                person: {
                    patrons: [],
                    scribes: [],
                },
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
                bibliography: {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                },
                general: {
                    publicComment: null,
                    privateComment: null,
                    textStatus: null,
                    recordStatus: null,
                    public: null,
                },
            },
            forms: [
                'persons',
                'date',
                'bibliography',
                'general',
            ],
        }
    },
    created () {
        this.occurrence = this.data.occurrence
        this.persons = {
            patrons: this.data.patrons,
            scribes: this.data.scribes,
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
                // Person
                this.model.person = {
                    patrons: this.occurrence.patrons,
                    scribes: this.occurrence.scribes,
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
                    publicComment: this.occurrence.publicComment,
                    privateComment: this.occurrence.privateComment,
                    textStatus: this.occurrence.textStatus,
                    recordStatus: this.occurrence.recordStatus,
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
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the occurrence data.', login: true})
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
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the occurrence data.', login: true})
                        this.openRequests--
                    })
            }
        },
    }
}
</script>
