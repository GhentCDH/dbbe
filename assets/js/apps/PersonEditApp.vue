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

            <datePanel
                id="bornDate"
                header="Born Date"
                :model="model.bornDate"
                key-group="bornDate"
                group-label="Born"
                @validated="validated"
                ref="bornDate" />

            <datePanel
                id="deathDate"
                header="Death Date"
                key-group="deathDate"
                group-label="Death"
                :model="model.deathDate"
                @validated="validated"
                ref="deathDate" />

            <identificationPanel
                id="identification"
                header="Identification"
                :identifiers="identifiers"
                :model="model.identification"
                @validated="validated"
                ref="identification" />

            <officePanel
                id="offices"
                header="Offices"
                :link="{url: urls['offices_edit'], text: 'Edit offices'}"
                :model="model.offices"
                :values="offices"
                @validated="validated"
                ref="offices" />

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
                v-if="person"
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
                    <li><a href="#bornDate">Born Date</a></li>
                    <li><a href="#deathDate">Death Date</a></li>
                    <li><a href="#identification">Identification</a></li>
                    <li><a href="#offices">Offices</a></li>
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
    if (['Basic', 'Date', 'Identification', 'Office', 'GeneralPerson'].includes(compName)) {
        Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel', panelComponents(key).default)
    }
}

export default {
    mixins: [ AbstractEntityEdit ],
    data() {
        let data = {
            person: null,
            offices: null,
            model: {
                basic: {
                    firstName: null,
                    lastName: null,
                    extra: null,
                    unprocessed: null,
                    historical: null,
                    modern: null,
                },
                bornDate: {
                    floor: null,
                    ceiling: null,
                    exactDate: null,
                    exactYear: null,
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },
                deathDate: {
                    floor: null,
                    ceiling: null,
                    exactDate: null,
                    exactYear: null,
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },
                identification: {},
                offices: {offices: null},
                general: {
                    publicComment: null,
                    privateComment: null,
                    public: null,
                },
            },
            forms: [
                'basic',
                'bornDate',
                'deathDate',
                'identification',
                'offices',
                'general',
            ],
        }
        for (let identifier of JSON.parse(this.initIdentifiers)) {
            data.model.identification[identifier.systemName] = null
        }
        return data
    },
    created () {
        this.person = this.data.person
        this.offices = this.data.offices
    },
    mounted () {
        this.loadPerson()
        window.addEventListener('scroll', (event) => {
            this.scrollY = Math.round(window.scrollY)
        })
    },
    methods: {
        loadPerson() {
            if (this.person != null) {
                // Basic info
                this.model.basic = {
                    firstName: this.person.firstName,
                    lastName: this.person.lastName,
                    extra: this.person.extra,
                    unprocessed: this.person.unprocessed,
                    historical: this.person.historical,
                    modern: this.person.modern,
                }

                // Born date
                this.model.bornDate = {
                    floor: this.person.bornDate != null ? this.person.bornDate.floor : null,
                    ceiling: this.person.bornDate != null ? this.person.bornDate.ceiling : null,
                    exactDate: null,
                    exactYear: null,
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                }

                // Death date
                this.model.deathDate = {
                    floor: this.person.deathDate != null ? this.person.deathDate.floor : null,
                    ceiling: this.person.deathDate != null ? this.person.deathDate.ceiling : null,
                    exactDate: null,
                    exactYear: null,
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                }

                // Identification
                this.model.identification = {}
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.person.identifications != null ? this.person.identifications[identifier.systemName] : null
                }

                // Identification
                this.model.offices = {
                    offices: this.person.office
                }

                // General
                this.model.general = {
                    publicComment: this.person.publicComment,
                    privateComment: this.person.privateComment,
                    public: this.person.public,
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
            if (this.person == null) {
                axios.post(this.urls['person_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['person_get'].replace('person_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the person data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['person_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['person_get']
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the person data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
        },
    }
}
</script>
