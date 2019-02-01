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

            <basicTypePanel
                id="basic"
                ref="basic"
                header="Basic information"
                :model="model.basic"
                @validated="validated"
            />

            <typeVersesPanel
                id="verses"
                ref="verses"
                header="Verses"
                :model="model.verses"
                :urls="urls"
                @validated="validated"
            />

            <typeTypesPanel
                id="types"
                ref="types"
                header="Types"
                :model="model.types"
                :values="types"
                @validated="validated"
            />

            <personPanel
                id="persons"
                ref="persons"
                header="Persons"
                :roles="roles"
                :model="model.personRoles"
                :values="historicalPersons"
                @validated="validated"
            />

            <metrePanel
                id="metres"
                ref="metres"
                header="Metres"
                :link="{url: urls['metres_edit'], text: 'Edit metres'}"
                :model="model.metres"
                :values="metres"
                @validated="validated"
            />

            <genrePanel
                id="genres"
                ref="genres"
                header="Genres"
                :link="{url: urls['genres_edit'], text: 'Edit genres'}"
                :model="model.genres"
                :values="genres"
                @validated="validated"
            />

            <subjectPanel
                id="subjects"
                ref="subjects"
                header="Subjects"
                :link="{url: urls['keywords_subject_edit'], text: 'Edit subject keywords'}"
                :model="model.subjects"
                :values="subjects"
                @validated="validated"
            />

            <keywordPanel
                id="keywords"
                ref="keywords"
                header="Keywords"
                :link="{url: urls['keywords_type_edit'], text: 'Edit type keywords'}"
                :model="model.keywords"
                :values="keywords"
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

            <bibliographyPanel
                id="bibliography"
                ref="bibliography"
                header="Bibliography"
                :model="model.bibliography"
                :reference-type="true"
                :values="bibliographies"
                @validated="validated"
            />

            <translationPanel
                id="translations"
                ref="translations"
                header="Translations"
                :model="model.translations"
                :values="translations"
                @validated="validated"
            />

            <generalTypePanel
                id="general"
                ref="general"
                header="General"
                :link="{url: urls['statuses_edit'], text: 'Edit statuses'}"
                :model="model.general"
                :values="generals"
                @validated="validated"
            />

            <managementPanel
                id="managements"
                ref="managements"
                header="Management collections"
                :link="{url: urls['managements_edit'], text: 'Edit management collections'}"
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
                v-if="type"
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
                    <li><a href="#basic">Basic information</a></li>
                    <li><a href="#verses">Verses</a></li>
                    <li><a href="#types">Types</a></li>
                    <li><a href="#persons">Persons</a></li>
                    <li><a href="#metres">Metres</a></li>
                    <li><a href="#genres">Genres</a></li>
                    <li><a href="#subjects">Subjects</a></li>
                    <li><a href="#keywords">Keywords</a></li>
                    <li v-if="identifiers.length > 0"><a href="#identification">Identification</a></li>
                    <li><a href="#bibliography">Bibliography</a></li>
                    <li><a href="#translations">Translations</a></li>
                    <li><a href="#general">General</a></li>
                    <li><a href="#managements">Management collections</a></li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <resetModal
            title="type"
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
            title="type"
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

const panelComponents = require.context('../Components/Edit/Panels', false, /[/](?:BasicType|TypeVerses|TypeTypes|Person|Metre|Genre|Subject|Keyword|Identification|Bibliography|Translation|GeneralType|Management)[.]vue$/)

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
            type: null,
            manuscripts: null,
            types: null,
            historicalPersons: null,
            metres: null,
            genres: null,
            subjects: null,
            keywords: null,
            bibliographies: null,
            translations: null,
            generals: null,
            model: {
                basic: {
                    incipit: null,
                    title_GR: null,
                    title_LA: null,
                },
                verses: {
                    verses: '',
                    numberOfVerses: null,
                },
                types: {types: null},
                personRoles: {},
                metres: {metres: null},
                genres: {genres: null},
                subjects: {
                    persons: null,
                    keywords: null,
                },
                keywords: {
                    keywords: null,
                },
                identification: {},
                bibliography: {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                },
                translations: {
                    translations: [],
                },
                general: {
                    criticalApparatus: null,
                    acknowledgements: null,
                    publicComment: null,
                    privateComment: null,
                    textStatus: null,
                    criticalStatus: null,
                    basedOn: null,
                    public: null,
                },
                managements: {managements: null},
            },
            forms: [
                'basic',
                'verses',
                'types',
                'persons',
                'metres',
                'genres',
                'subjects',
                'keywords',
                'bibliography',
                'translations',
                'general',
                'managements',
            ],
        }
        for (let identifier of data.identifiers) {
            data.model.identification[identifier.systemName] = null
            if (identifier.extra) {
                data.model.identification[identifier.systemName + '_extra'] = null
            }
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
        this.type = this.data.type
        this.manuscripts = this.data.manuscripts
        this.types = {
            types: this.data.types,
            relationTypes: this.data.typeRelationTypes,
        }
        this.historicalPersons = this.data.historicalPersons
        this.metres = this.data.metres
        this.genres = this.data.genres
        this.subjects = {
            personSubjects: this.historicalPersons,
            keywordSubjects: this.data.subjectKeywords,
        }
        this.keywords = this.data.typeKeywords,
        this.bibliographies = {
            books: this.data.books,
            articles: this.data.articles,
            bookChapters: this.data.bookChapters,
            onlineSources: this.data.onlineSources,
            referenceTypes: this.data.referenceTypes,
        }
        this.translations = {
            languages: this.data.languages,
            books: this.data.books,
            articles: this.data.articles,
            bookChapters: this.data.bookChapters,
            onlineSources: this.data.onlineSources,
        }
        this.generals = {
            acknowledgements: this.data.acknowledgements,
            textStatuses: this.data.textStatuses,
            criticalStatuses: this.data.criticalStatuses,
            occurrences: this.data.occurrences,
        }
        this.managements = this.data.managements
    },
    mounted () {
        this.loadType()
        window.addEventListener('scroll', (event) => {
            this.scrollY = Math.round(window.scrollY)
        })
    },
    methods: {
        loadType() {
            if (this.type != null) {
                // Basic information
                this.model.basic = {
                    incipit: this.type.incipit,
                    title_GR: this.type.title_GR,
                    title_LA: this.type.title_LA,
                }

                // Verses
                this.model.verses = {
                    verses: this.type.verses,
                    numberOfVerses: this.type.numberOfVerses,
                }

                // Types
                this.model.types = {
                    relatedTypes: this.type.relatedTypes || [],
                }

                // PersonRoles
                for (let role of this.roles) {
                    this.model.personRoles[role.systemName] = this.type.personRoles != null ? this.type.personRoles[role.systemName] : null
                }
                this.$refs.persons.init();

                // Metre
                this.model.metres = {
                    metres: this.type.metres,
                }

                // Genre
                this.model.genres = {
                    genres: this.type.genres,
                }

                // Subject
                this.model.subjects = {
                    personSubjects: this.type.subjects.persons,
                    keywordSubjects: this.type.subjects.keywords,
                }

                // Keyword
                this.model.keywords = {
                    keywords: this.type.keywords,
                }

                // Identification
                this.model.identification = {}
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.type.identifications != null ? this.type.identifications[identifier.systemName] : null
                    if (identifier.extra) {
                        this.model.identification[identifier.systemName + '_extra'] = this.type.identifications != null ? this.type.identifications[identifier.systemName + '_extra'] : null
                    }
                }

                // Bibliography
                this.model.bibliography = {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                }
                if (this.type.bibliography != null) {
                    for (let bib of this.type.bibliography) {
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
                }

                // Translations
                this.model.translations = {
                    translations: []
                }

                for (let translation of this.type.translations) {
                    let modelTranslation = {
                        id: translation.id,
                        text: translation.text,
                        language: translation.language,
                        bibliography: {
                            books: [],
                            articles: [],
                            bookChapters: [],
                            onlineSources: [],
                        }
                    }
                    if (translation.bibliography != null) {
                        for (let bib of translation.bibliography) {
                            switch (bib['type']) {
                            case 'book':
                                modelTranslation.bibliography.books.push(bib)
                                break
                            case 'article':
                                modelTranslation.bibliography.articles.push(bib)
                                break
                            case 'bookChapter':
                                modelTranslation.bibliography.bookChapters.push(bib)
                                break
                            case 'onlineSource':
                                modelTranslation.bibliography.onlineSources.push(bib)
                                break
                            }
                        }
                    }
                    this.model.translations.translations.push(modelTranslation)
                }

                // General
                this.model.general = {
                    criticalApparatus: this.type.criticalApparatus,
                    acknowledgements: this.type.acknowledgements,
                    publicComment: this.type.publicComment,
                    privateComment: this.type.privateComment,
                    textStatus: this.type.textStatus,
                    criticalStatus: this.type.criticalStatus,
                    basedOn: this.type.basedOn,
                    public: this.type.public,
                }

                // Management
                this.model.managements = {
                    managements: this.type.managements,
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
            if (this.type == null) {
                axios.post(this.urls['type_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['type_get'].replace('type_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the type data.', login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['type_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['type_get']
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the type data.', login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
        },
    }
}
</script>
