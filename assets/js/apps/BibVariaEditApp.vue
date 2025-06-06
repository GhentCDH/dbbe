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

            <basicBibVariaPanel
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
                v-if="bibVaria"
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
            title="bib varia"
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
            title="bib varia"
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
import axios from 'axios'

import AbstractEntityEdit from '@/mixins/AbstractEntityEdit'
import {getErrorMessage, isLoginError} from "@/helpers/errorUtil";
import Reset from "@/Components/Edit/Modals/Reset.vue";
import Invalid from "@/Components/Edit/Modals/Invalid.vue";
import Save from "@/Components/Edit/Modals/Save.vue";

const panelComponents = import.meta.glob('../Components/Edit/Panels/{Person,BasicBibVaria,Url,Identification,GeneralBibItem,Management}.vue', { eager: true })

for (const path in panelComponents) {
  const component = panelComponents[path].default
  const compName = path
      .split('/')
      .pop()
      .replace(/\.vue$/, '')

  const globalName = compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel'
  Vue.component(globalName, component)
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
            bibVaria: null,
            modernPersons: null,
            model: {
                personRoles: {},
                basic: {
                    title: null,
                    year: null,
                    city: null,
                    institution: null,
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
        };
        for (let identifier of data.identifiers) {
            data.model.identification[identifier.systemName] = null;
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
        this.bibVaria = this.data.bibVaria;

        this.modernPersons = [];
        this.managements = this.data.managements;
    },
    methods: {
        loadAsync() {
            this.reload('modernPersons');
        },
        setData() {
            if (this.bibVaria != null) {
                // PersonRoles
                for (let role of this.roles) {
                    this.model.personRoles[role.systemName] = this.bibVaria.personRoles == null ? [] : this.bibVaria.personRoles[role.systemName];
                }

                // Basic info
                this.model.basic = {
                    title: this.bibVaria.title,
                    year: this.bibVaria.year,
                    city: this.bibVaria.city,
                    institution: this.bibVaria.institution,
                };

                // Urls
                this.model.urls = {
                    urls: this.bibVaria.urls == null ? null : this.bibVaria.urls.map(
                        function(url, index) {
                            url.tgIndex = index + 1
                            return url
                        }
                    )
                }

                // Identification
                this.model.identification = {};
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.bibVaria.identifications == null ? [] : this.bibVaria.identifications[identifier.systemName];
                }

                // General
                this.model.general = {
                    publicComment: this.bibVaria.publicComment,
                    privateComment: this.bibVaria.privateComment,
                };

                // Management
                this.model.managements = {
                    managements: this.bibVaria.managements,
                }
            }
        },
        save() {
            this.openRequests++;
            this.saveModal = false;
            if (this.bibVaria == null) {
                axios.post(this.urls['bib_varia_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {};
                        // redirect to the detail page
                        window.location = this.urls['bib_varia_get'].replace('bib_varia_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error);
                        this.saveModal = true;
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the bib varia data.', extra: getErrorMessage(error), login: isLoginError(error)});
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['bib_varia_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {};
                        // redirect to the detail page
                        window.location = this.urls['bib_varia_get']
                    })
                    .catch( (error) => {
                        console.log(error);
                        this.saveModal = true;
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the bib varia data.', extra: getErrorMessage(error), login: isLoginError(error)});
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
