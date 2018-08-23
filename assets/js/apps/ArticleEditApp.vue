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

            <basicArticlePanel
                id="basic"
                header="Basic Information"
                :model="model.basic"
                :values="journals"
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
                v-if="article"
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
                    <li><a href="#persons">Persons</a></li>
                    <li><a href="#basic">Basic information</a></li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <resetModal
            title="article"
            :show="resetModal"
            @cancel="resetModal=false"
            @confirm="reset()" />
        <invalidModal
            :show="invalidModal"
            @cancel="invalidModal=false"
            @confirm="invalidModal=false" />
        <saveModal
            title="article"
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

const panelComponents = require.context('../Components/Edit/Panels', false, /[Person|BasicArticle][.]vue$/)

for(let key of panelComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '')
    Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel', panelComponents(key).default)
}

export default {
    mixins: [ AbstractEntityEdit ],
    props: {
        initRoles: {
            type: String,
            default: '',
        },
    },
    data() {
        let data = {
            article: null,
            modernPersons: null,
            roles: JSON.parse(this.initRoles),
            model: {
                personRoles: {},
                basic: {
                    title: null,
                    journal: null,
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
        this.article = this.data.article
        this.modernPersons = this.data.modernPersons
        this.journals = this.data.journals
    },
    mounted () {
        this.loadArticle()
        window.addEventListener('scroll', (event) => {
            this.scrollY = Math.round(window.scrollY)
        })
    },
    methods: {
        loadArticle() {
            if (this.article != null) {
                // PersonRoles
                this.model.personRoles = {}
                for (let role of this.roles) {
                    this.model.personRoles[role.systemName] = this.article.personRoles != null ? this.article.personRoles[role.systemName] : null
                }

                // Basic info
                this.model.basic = {
                    title: this.article.title,
                    journal: this.article.journal,
                }
            }

            this.originalModel = JSON.parse(JSON.stringify(this.model))
        },
        save() {
            this.openRequests++
            this.saveModal = false
            if (this.article == null) {
                axios.post(this.urls['article_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['article_get'].replace('article_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the article data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['article_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['article_get']
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the article data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
        },
    }
}
</script>
