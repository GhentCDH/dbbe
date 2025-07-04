<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />

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
                :links="[{title: 'Types', reload: 'types', edit: urls['types_search']}]"
                :model="model.types"
                :values="types"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <personPanel
                id="persons"
                ref="persons"
                header="Persons"
                :links="[{title: 'Persons', reload: 'historicalPersons', edit: urls['persons_search']}]"
                :roles="roles"
                :model="model.personRoles"
                :values="historicalPersons"
                :keys="{historicalPersons: {init: false}}"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <metrePanel
                id="metres"
                ref="metres"
                header="Metres"
                :links="[{title: 'Metres', reload: 'metres', edit: urls['metres_edit']}]"
                :model="model.metres"
                :values="metres"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <genrePanel
                id="genres"
                ref="genres"
                header="Genres"
                :links="[{title: 'Genres', reload: 'genres', edit: urls['genres_edit']}]"
                :model="model.genres"
                :values="genres"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <subjectPanel
                id="subjects"
                ref="subjects"
                header="Subjects"
                :links="[{title: 'Persons', reload: 'historicalPersons', edit: urls['persons_search']}, {title: 'Keywords', reload: 'keywordSubjects', edit: urls['keywords_subject_edit']}]"
                :model="model.subjects"
                :values="subjects"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <keywordPanel
                id="tags"
                ref="keywords"
                header="Tags"
                :links="[{title: 'Tags', reload: 'typeKeywords', edit: urls['keywords_type_edit']}]"
                :model="model.keywords"
                :values="keywords"
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

            <bibliographyPanel
                id="bibliography"
                ref="bibliography"
                header="Bibliography"
                :links="[{title: 'Books', reload: 'books', edit: urls['bibliographies_search']},{title: 'Articles', reload: 'articles', edit: urls['bibliographies_search']},{title: 'Book chapters', reload: 'bookChapters', edit: urls['bibliographies_search']},{title: 'Online sources', reload: 'onlineSources', edit: urls['bibliographies_search']},{title: 'Blog Posts', reload: 'blogPosts', edit: urls['bibliographies_search']},{title: 'Phd theses', reload: 'phds', edit: urls['bibliographies_search']},{title: 'Bib varia', reload: 'bibVarias', edit: urls['bibliographies_search']}]"
                :model="model.bibliography"
                :reference-type="true"
                :values="bibliographies"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <translationPanel
                id="translations"
                ref="translations"
                header="Translations"
                :model="model.translations"
                :values="translations"
                :reloads="reloads"
                :urls="urls"
                @validated="validated"
                @reload="reload"
            />

            <generalTypePanel
                id="general"
                ref="general"
                header="General"
                :links="[{title: 'Acknowledgements', reload: 'acknowledgements', edit: urls['acknowledgements_edit']}, {title: 'Statuses', reload: 'statuses', edit: urls['statuses_edit']}, {title: 'Occurrences', reload: 'occurrences', edit: urls['occurrences_search']}]"
                :model="model.general"
                :values="generals"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <personPanel
                id="contributors"
                ref="contributors"
                header="Contributors"
                :links="[{title: 'Persons', reload: 'dbbePersons', edit: urls['persons_search']}]"
                :roles="contributorRoles"
                :model="model.contributorRoles"
                :values="dbbePersons"
                :keys="{dbbePersons: {init: true}}"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
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
                        >Basic information</a>
                    </li>
                    <li>
                        <a
                            href="#verses"
                            :class="{'bg-danger': !($refs.verses && $refs.verses.isValid)}"
                        >Verses</a>
                    </li>
                    <li>
                        <a
                            href="#types"
                            :class="{'bg-danger': !($refs.types && $refs.types.isValid)}"
                        >Types</a>
                    </li>
                    <li>
                        <a
                            href="#persons"
                            :class="{'bg-danger': !($refs.persons && $refs.persons.isValid)}"
                        >Persons</a>
                    </li>
                    <li>
                        <a
                            href="#metres"
                            :class="{'bg-danger': !($refs.metres && $refs.metres.isValid)}"
                        >Metres</a>
                    </li>
                    <li>
                        <a
                            href="#genres"
                            :class="{'bg-danger': !($refs.genres && $refs.genres.isValid)}"
                        >Genres</a>
                    </li>
                    <li>
                        <a
                            href="#subjects"
                            :class="{'bg-danger': !($refs.subjects && $refs.subjects.isValid)}"
                        >Subjects</a>
                    </li>
                    <li>
                        <a
                            href="#tags"
                            :class="{'bg-danger': !($refs.keywords && $refs.keywords.isValid)}"
                        >Tags</a>
                    </li>
                    <li v-if="identifiers.length > 0">
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
                            href="#translations"
                            :class="{'bg-danger': !($refs.translations && $refs.translations.isValid)}"
                        >Translations</a>
                    </li>
                    <li>
                        <a
                            href="#general"
                            :class="{'bg-danger': !($refs.general && $refs.general.isValid)}"
                        >
                            General
                        </a>
                    </li>
                    <li>
                        <a
                            href="#contributors"
                            :class="{'bg-danger': !($refs.contributors && $refs.contributors.isValid)}"
                        >Contributors</a>
                    </li>
                    <li>
                        <a
                            href="#managements"
                            :class="{'bg-danger': !($refs.managements && $refs.managements.isValid)}"
                        >Management collections</a>
                    </li>
                    <li>
                    <a href="#actions">Actions</a></li>
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
import Vue from 'vue';
import axios from 'axios'

import AbstractEntityEdit from '../mixins/AbstractEntityEdit'
import {isLoginError} from "@/helpers/errorUtil";
import Reset from "@/Components/Edit/Modals/Reset.vue";
import Invalid from "@/Components/Edit/Modals/Invalid.vue";
import Save from "@/Components/Edit/Modals/Save.vue";

const panelComponents = import.meta.glob('../Components/Edit/Panels/{BasicType,TypeVerses,TypeTypes,Person,Metre,Genre,Subject,Keyword,Identification,Bibliography,Translation,GeneralType,Management}.vue', { eager: true })

for (const path in panelComponents) {
  const component = panelComponents[path].default
  const compName = path
      .split('/')
      .pop()
      .replace(/\.vue$/, '')

  // Register each component globally
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
    props: {
        initTranslationRoles: {
            type: String,
            default: '',
        },
    },
    data() {
        let data = {
            identifiers: JSON.parse(this.initIdentifiers),
            roles: JSON.parse(this.initRoles),
            contributorRoles: JSON.parse(this.initContributorRoles),
            translationRoles: JSON.parse(this.initTranslationRoles),
            type: null,
            types: null,
            dbbePersons: null,
            historicalPersons: null,
            modernPersons: null,
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
                contributorRoles: {},
                metres: {
                    metres: [],
                },
                genres: {
                    genres: [],
                },
                subjects: {
                    personSubjects: [],
                    keywordSubjects: [],
                },
                keywords: {
                    keywords: [],
                },
                identification: {},
                bibliography: {
                    articles: [],
                    blogPosts: [],
                    books: [],
                    bookChapters: [],
                    onlineSources: [],
                    phds: [],
                    bibVarias: [],
                },
                translations: {
                    translations: [],
                },
                general: {
                    criticalApparatus: null,
                    acknowledgements: [],
                    publicComment: null,
                    privateComment: null,
                    textStatus: null,
                    criticalStatus: null,
                    basedOn: null,
                    public: null,
                },
                managements: {
                    managements: [],
                },
            },
            panels: [
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
                'contributors',
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
        for (let role of data.contributorRoles) {
            data.model.contributorRoles[role.systemName] = [];
        }
        return data
    },
    created () {
        this.type = this.data.type;

        this.types = {
            types: [],
            relationTypes: this.data.typeRelationTypes,
        };
        this.historicalPersons = [];
        this.metres = this.data.metres;
        this.genres = this.data.genres;
        this.subjects = {
            historicalPersons: [],
            keywordSubjects: this.data.subjectKeywords,
        };
        this.keywords = this.data.typeKeywords;
        this.bibliographies = {
            articles: [],
            blogPosts: [],
            books: [],
            bookChapters: [],
            onlineSources: [],
            phds: [],
            bibVarias: [],
            referenceTypes: this.data.referenceTypes,
        };
        this.translations = {
            languages: this.data.languages,
            articles: [],
            blogPosts: [],
            books: [],
            bookChapters: [],
            onlineSources: [],
            phds: [],
            bibVarias: [],
            modernPersons: this.data.modernPersons,
            personRoles: this.translationRoles,
        };
        this.generals = {
            acknowledgements: this.data.acknowledgements,
            textStatuses: this.data.textStatuses,
            criticalStatuses: this.data.criticalStatuses,
            occurrences: [],
        };
        this.dbbePersons = this.data.dbbePersons;
        this.managements = this.data.managements
    },
    methods: {
        loadAsync() {
            this.reload('types');
            this.reload('historicalPersons');
            this.reload('books');
            this.reload('articles');
            this.reload('bookChapters');
            this.reload('onlineSources');
            this.reload('blogPosts');
            this.reload('occurrences');
            this.reload('phds');
            this.reload('bibVarias');
        },
        setData() {
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
                    this.model.personRoles[role.systemName] = this.type.personRoles == null ? [] : this.type.personRoles[role.systemName];
                }

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
                    this.model.identification[identifier.systemName] = this.type.identifications == null ? [] : this.type.identifications[identifier.systemName];
                }

                // Bibliography
                this.model.bibliography = {
                    articles: [],
                    blogPosts: [],
                    books: [],
                    bookChapters: [],
                    onlineSources: [],
                    phds: [],
                    bibVarias: [],
                }
                if (this.type.bibliography != null) {
                    for (let bib of this.type.bibliography) {
                        switch (bib['type']) {
                        case 'article':
                            this.model.bibliography.articles.push(bib)
                            break
                        case 'blogPost':
                            this.model.bibliography.blogPosts.push(bib)
                            break
                        case 'book':
                            this.model.bibliography.books.push(bib)
                            break
                        case 'bookChapter':
                            this.model.bibliography.bookChapters.push(bib)
                            break
                        case 'onlineSource':
                            this.model.bibliography.onlineSources.push(bib)
                            break
                        case 'phd':
                            this.model.bibliography.phds.push(bib)
                            break
                        case 'bibVaria':
                            this.model.bibliography.bibVarias.push(bib)
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
                            articles: [],
                            blogPosts: [],
                            books: [],
                            bookChapters: [],
                            onlineSources: [],
                            phds: [],
                            bibVarias: [],
                        },
                        publicComment: translation.publicComment,
                        personRoles: {},
                    }
                    if (translation.bibliography != null) {
                        for (let bib of translation.bibliography) {
                            switch (bib['type']) {
                            case 'article':
                                modelTranslation.bibliography.articles.push(bib)
                                break
                            case 'blogPost':
                                modelTranslation.bibliography.blogPosts.push(bib)
                                break
                            case 'book':
                                modelTranslation.bibliography.books.push(bib)
                                break
                            case 'bookChapter':
                                modelTranslation.bibliography.bookChapters.push(bib)
                                break
                            case 'onlineSource':
                                modelTranslation.bibliography.onlineSources.push(bib)
                                break
                            case 'phd':
                                modelTranslation.bibliography.phds.push(bib)
                                break
                            case 'bibVaria':
                                modelTranslation.bibliography.bibVarias.push(bib)
                                break
                            }
                        }
                    }
                    for (const role of this.translationRoles) {
                        modelTranslation.personRoles[role.systemName] = translation.personRoles == null ? [] : translation.personRoles[role.systemName];
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

                // ContributorRoles
                for (let role of this.contributorRoles) {
                    this.model.contributorRoles[role.systemName] = this.type.contributorRoles == null ? [] : this.type.contributorRoles[role.systemName];
                }

                // Management
                this.model.managements = {
                    managements: this.type.managements,
                }
            }

            else {
                this.model.general.public = true;
                this.model.types = {
                    relatedTypes: [],
                };
            }
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
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the type data.', login: isLoginError(error)})
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
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the type data.', login: isLoginError(error)})
                        this.openRequests--
                    })
            }
        },
        reload(type) {
            switch(type) {
            case 'types':
                this.reloadNestedItems(type, this.types);
                break;
            case 'historicalPersons':
                this.reloadItems(
                    'historicalPersons',
                    ['historicalPersons'],
                    [this.historicalPersons, this.subjects.historicalPersons],
                    this.urls['historical_persons_get']
                );
                break;
            case 'dbbePersons':
                this.reloadItems(
                    'dbbePersons',
                    ['dbbePersons'],
                    [this.dbbePersons],
                    this.urls['dbbe_persons_get']
                );
                break;
            case 'modernPersons':
                this.reloadNestedItems(type, [this.translations]);
                break;
            case 'keywordSubjects':
                this.reloadItems(
                    'keywordSubjects',
                    ['keywordSubjects'],
                    [this.subjects.keywordSubjects],
                    this.urls['keywords_subject_get']
                );
                break;
            case 'typeKeywords':
                this.reloadItems(
                    'keywords',
                    ['keywords'],
                    [this.keywords],
                    this.urls['keywords_type_get']
                );
                break;
            case 'articles':
            case 'blogPosts':
            case 'books':
            case 'bookChapters':
            case 'onlineSources':
            case 'phds':
            case 'bibVarias':
                this.reloadNestedItems(type, [this.bibliographies, this.translations]);
                break;
            case 'acknowledgements':
                this.reloadNestedItems(type, this.generals);
                break;
            case 'statuses':
                this.reloadItems(
                    'statuses',
                    ['textStatuses', 'criticalStatuses'],
                    [this.generals.textStatuses, this.generals.criticalStatuses],
                    this.urls['statuses_get'],
                    [(i) => i.type === 'type_text', (i) => i.type === 'type_critical'],
                );
                break;
            case 'occurrences':
                this.reloadNestedItems(type, this.generals);
                break;
            default:
                this.reloadSimpleItems(type);
            }
        },
    }
}
</script>
