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

            <basicPhdPanel
                id="basic"
                ref="basic"
                header="Basic Information"
                :model="model.basic"
                @validated="validated"
            />

            <urlPanel
                id="urls"
                ref="urls"
                header="Urls"
                :model="model.urls"
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
                v-if="phd"
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
                    <li>
                        <a
                            href="#urls"
                            :class="{'bg-danger': !($refs.urls && $refs.urls.isValid)}"
                        >Urls</a>
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
            title="PhD thesis"
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
            title="PhD thesis"
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
import Vue from 'vue';
import axios from 'axios'

import AbstractEntityEdit from '../mixins/AbstractEntityEdit'
import {enableField} from "@/helpers/formFieldUtils";
import {getErrorMessage, isLoginError} from "@/helpers/errorUtil";
import Reset from "@/Components/Edit/Modals/Reset.vue";
import Invalid from "@/Components/Edit/Modals/Invalid.vue";
import Save from "@/Components/Edit/Modals/Save.vue";

const panelComponents = import.meta.glob('../Components/Edit/Panels/{Person,BasicPhd,Url,Identification,GeneralBibItem,Management}.vue', { eager: true })

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
            phd: null,
            modernPersons: null,
            clustersAndSeries: null,
            model: {
                personRoles: {},
                basic: {
                    title: null,
                    year: null,
                    forthcoming: null,
                    city: null,
                    institution: null,
                    volume: null,
                },
                urls: {urls: []},
                identification: {},
                managements: {
                    managements: [],
                },
            },
            panels: [
                'persons',
                'basic',
                'urls',
                'general',
                'managements',
            ],
        }
        for (let identifier of data.identifiers) {
            data.model.identification[identifier.systemName] = null
        }
        if (data.identifiers.length > 0) {
            data.panels.push('identification')
        }
        for (let role of data.roles) {
            data.model.personRoles[role.systemName] = [];
        }
        return data
    },
    created () {
        this.phd = this.data.phd;

        this.modernPersons = [];
        this.managements = this.data.managements;
    },
    methods: {
        // Override to make sure forthcoming is set
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model));
            if (this.model.forthcoming == null) {
                this.model.forthcoming = false;
            }
            enableField();
        },
        loadAsync() {
            this.reload('modernPersons');
        },
        setData() {
            if (this.phd != null) {
                // PersonRoles
                for (let role of this.roles) {
                    this.model.personRoles[role.systemName] = this.phd.personRoles == null ? [] : this.phd.personRoles[role.systemName];
                }

                // Basic info
                this.model.basic = {
                    title: this.phd.title,
                    year: this.phd.year,
                    forthcoming: this.phd.forthcoming,
                    city: this.phd.city,
                    institution: this.phd.institution,
                    volume: this.phd.volume,
                }

                // Urls
                this.model.urls = {
                    urls: this.phd.urls == null ? null : this.phd.urls.map(
                        function(url, index) {
                            url.tgIndex = index + 1
                            return url
                        }
                    )
                }

                // Identification
                this.model.identification = {}
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.phd.identifications == null ? [] : this.phd.identifications[identifier.systemName];
                }

                // General
                this.model.general = {
                    publicComment: this.phd.publicComment,
                    privateComment: this.phd.privateComment,
                }

                // Management
                this.model.managements = {
                    managements: this.phd.managements,
                }
            }
        },
        save() {
            this.openRequests++
            this.saveModal = false
            if (this.phd == null) {
                axios.post(this.urls['phd_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['phd_get'].replace('phd_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the PhD thesis data.', extra: getErrorMessage(error), login: isLoginError(error)})
                        this.openRequests--
                    })
            }
            else {
                console.log('putting');
                axios.put(this.urls['phd_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['phd_get']
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the PhD thesis data.', extra: getErrorMessage(error), login: isLoginError(error)})
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
