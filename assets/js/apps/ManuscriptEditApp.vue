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

            <locatedAtPanel
                id="location"
                header="Location"
                :link="{url: getLocationsUrl, text: 'Edit locations'}"
                :model="model.locatedAt"
                :values="locations"
                @validated="validated"
                ref="locatedAt" />

            <contentPanel
                id="content"
                header="Content"
                :link="{url: getContentsUrl, text: 'Edit contents'}"
                :model="model.content"
                :values="contents"
                @validated="validated"
                ref="content" />

            <personPanel
                id="persons"
                header="Persons"
                :model="model.person"
                :values="persons"
                @validated="validated"
                ref="persons"
                :occurrence-patrons="manuscript ? manuscript.occurrencePatrons : []"
                :occurrence-scribes="manuscript ? manuscript.occurrenceScribes : []" />

            <datePanel
                id="date"
                header="Date"
                :model="model.date"
                @validated="validated"
                ref="date" />

            <originPanel
                id="origin"
                header="Origin"
                :link="{url: getOriginsUrl, text: 'Edit origins'}"
                :model="model.origin"
                :values="origins"
                @validated="validated"
                ref="origin" />

            <bibliographyPanel
                id="bibliography"
                header="Bibliography"
                :model="model.bibliography"
                :values="bibliographies"
                @validated="validated"
                ref="bibliography" />

            <generalPanel
                id="general"
                header="General"
                :link="{url: getStatusesUrl, text: 'Edit statuses'}"
                :model="model.general"
                :values="statuses"
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
                v-if="manuscript"
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
                    <li><a href="#location">Location</a></li>
                    <li><a href="#content">Content</a></li>
                    <li><a href="#persons">Persons</a></li>
                    <li><a href="#date">Date</a></li>
                    <li><a href="#origin">Origin</a></li>
                    <li><a href="#bibliography">Bibliography</a></li>
                    <li><a href="#general">General</a></li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <modal
            v-model="resetModal"
            title="Reset manuscript"
            auto-focus>
            <p>Are you sure you want to reset the manuscript information?</p>
            <div slot="footer">
                <btn @click="resetModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="reset()"
                    data-action="auto-focus">
                    Reset
                </btn>
            </div>
        </modal>
        <modal
            v-model="invalidModal"
            title="Invalid fields"
            auto-focus>
            <p>There are invalid input fields. Please correct them.</p>
            <div slot="footer">
                <btn
                    @click="invalidModal=false"
                    data-action="auto-focus">
                    OK
                </btn>
            </div>
        </modal>
        <modal
            v-model="saveModal"
            title="Save manuscript"
            size="lg"
            auto-focus>
            <p>Are you sure you want to save this manuscript information?</p>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="col-md-2">Field</th>
                        <th class="col-md-5">Previous value</th>
                        <th class="col-md-5">New value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in diff"
                        :key="row.key">
                        <td>{{ row['label'] }}</td>
                        <template v-for="key in ['old', 'new']">
                            <td
                                v-if="Array.isArray(row[key])"
                                :key="key">
                                <ul v-if="row[key].length > 0">
                                    <li
                                        v-for="(item, index) in row[key]"
                                        :key="index">
                                        {{ getDisplay(item) }}
                                    </li>
                                </ul>
                            </td>
                            <td
                                v-else
                                :key="key">
                                {{ getDisplay(row[key]) }}
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
            <div slot="footer">
                <btn @click="saveModal=false">Cancel</btn>
                <btn
                    type="success"
                    @click="save()"
                    data-action="auto-focus">
                    Save
                </btn>
            </div>
        </modal>
    </div>
</template>

<script>
window.axios = require('axios')

import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import * as uiv from 'uiv'
import VueMultiselect from 'vue-multiselect'

import fieldMultiselectClear from '../Components/FormFields/fieldMultiselectClear'

Vue.use(VueFormGenerator)
Vue.use(uiv)

Vue.component('multiselect', VueMultiselect)
Vue.component('fieldMultiselectClear', fieldMultiselectClear)

let panelComponents = require.context('../Components/Edit/Panels', false, /[.]vue$/)
let components = {}
for(let key of panelComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '')
    components[compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel'] = panelComponents(key).default
}

export default {
    components: components,
    props: {
        getManuscriptUrl: {
            type: String,
            default: '',
        },
        postManuscriptUrl: {
            type: String,
            default: '',
        },
        putManuscriptUrl: {
            type: String,
            default: '',
        },
        getLocationsUrl: {
            type: String,
            default: '',
        },
        getContentsUrl: {
            type: String,
            default: '',
        },
        getOriginsUrl: {
            type: String,
            default: '',
        },
        getStatusesUrl: {
            type: String,
            default: '',
        },
        initManuscript: {
            type: String,
            default: '',
        },
        initLocations: {
            type: String,
            default: '',
        },
        initContents: {
            type: String,
            default: '',
        },
        initPatrons: {
            type: String,
            default: '',
        },
        initScribes: {
            type: String,
            default: '',
        },
        initRelatedPersons: {
            type: String,
            default: '',
        },
        initOrigins: {
            type: String,
            default: '',
        },
        initBooks: {
            type: String,
            default: '',
        },
        initArticles: {
            type: String,
            default: '',
        },
        initBookChapters: {
            type: String,
            default: '',
        },
        initOnlineSources: {
            type: String,
            default: '',
        },
        initStatuses: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            manuscript: this.initManuscript ? JSON.parse(this.initManuscript) : null,
            locations: JSON.parse(this.initLocations),
            contents: JSON.parse(this.initContents),
            persons: {
                patrons: JSON.parse(this.initPatrons),
                scribes: JSON.parse(this.initScribes),
                relatedPersons: JSON.parse(this.initRelatedPersons),
            },
            origins: JSON.parse(this.initOrigins),
            bibliographies: {
                books: JSON.parse(this.initBooks),
                articles: JSON.parse(this.initArticles),
                bookChapters: JSON.parse(this.initBookChapters),
                onlineSources: JSON.parse(this.initOnlineSources),
            },
            statuses: JSON.parse(this.initStatuses),
            model: {
                locatedAt: {
                    location: {
                        id: null,
                        regionWithParents: null,
                        institution: null,
                        collection: null,
                    },
                    shelf: null,
                },
                content: {content: null},
                person: {
                    patrons: [],
                    scribes: [],
                    relatedPersons: [],
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
                origin: {origin: null},
                bibliography: {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                },
                general: {
                    diktyon: null,
                    publicComment: null,
                    privateComment: null,
                    illustrated: null,
                    status: null,
                    public: null,
                },
            },
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            openRequests: 0,
            alerts: [],
            originalModel: {},
            diff:[],
            resetModal: false,
            invalidModal: false,
            saveModal: false,
            invalidForms: false,
            scrollY: null,
            isSticky: false,
            stickyStyle: {},
        }
    },
    watch: {
        'manuscript': function (newValue, oldValue) {
            this.loadManuscript()
        },
        scrollY(newValue) {
            let anchor = this.$refs.anchor.getBoundingClientRect()
            if (anchor.top < 30) {
                this.isSticky = true
                this.stickyStyle = {
                    width: anchor.width + 'px',
                }
            }
            else {
                this.isSticky = false
                this.stickyStyle = {}
            }
        },
    },
    mounted () {
        this.loadManuscript()
        window.addEventListener('scroll', (event) => {
            this.scrollY = Math.round(window.scrollY)
        })
    },
    methods: {
        loadManuscript() {
            if (this.manuscript != null) {
                this.model.locatedAt = this.manuscript.locatedAt
                this.model.content = {
                    content: this.manuscript.content,
                }
                this.model.person = {
                    patrons: this.manuscript.patrons,
                    scribes: this.manuscript.scribes,
                    relatedPersons: this.manuscript.relatedPersons,
                }

                // Date
                this.model.date = {
                    floor: this.manuscript.date != null ? this.manuscript.date.floor : null,
                    ceiling: this.manuscript.date != null ? this.manuscript.date.ceiling : null,
                    exactDate: null,
                    exactYear: null,
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                }

                // Origin
                this.model.origin = {
                    origin: this.manuscript.origin,
                }

                // Bibliography
                this.model.bibliography = {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                }
                for (let id of Object.keys(this.manuscript.bibliography)) {
                    let bib = this.manuscript.bibliography[id]
                    bib['id'] = id
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
                    diktyon: this.manuscript.diktyon,
                    publicComment: this.manuscript.publicComment,
                    privateComment: this.manuscript.privateComment,
                    status: this.manuscript.status,
                    illustrated: this.manuscript.illustrated,
                    public: this.manuscript.public,
                }
            }

            else {
                this.model.general.public = true
            }

            this.originalModel = JSON.parse(JSON.stringify(this.model))
        },
        validateForms() {
            this.$refs.locatedAt.validate()
            this.$refs.content.validate()
            this.$refs.persons.validate()
            this.$refs.date.validate()
            this.$refs.origin.validate()
            this.$refs.general.validate()
        },
        validated(isValid, errors) {
            this.invalidForms = (
                !this.$refs.locatedAt.isValid
                || !this.$refs.content.isValid
                || !this.$refs.persons.isValid
                || !this.$refs.date.isValid
                || !this.$refs.origin.isValid
                || !this.$refs.bibliography.isValid
                || !this.$refs.general.isValid
            )

            this.calcDiff()
        },
        calcDiff() {
            this.diff = []
                .concat(this.$refs.locatedAt.changes)
                .concat(this.$refs.content.changes)
                .concat(this.$refs.persons.changes)
                .concat(this.$refs.date.changes)
                .concat(this.$refs.origin.changes)
                .concat(this.$refs.bibliography.changes)
                .concat(this.$refs.general.changes)

            if (this.diff.length !== 0) {
                window.onbeforeunload = function(e) {
                    let dialogText = 'There are unsaved changes.'
                    e.returnValue = dialogText
                    return dialogText
                }
            }
        },
        getDisplay(item) {
            if (item == null) {
                return null
            }
            else if (item.hasOwnProperty('name')) {
                return item['name']
            }
            return item
        },
        toSave() {
            let result = {}
            for (let diff of this.diff) {
                if ('keyGroup' in diff) {
                    if (!(diff.keyGroup in result)) {
                        result[diff.keyGroup] = {}
                    }
                    result[diff.keyGroup][diff.key] = diff.value
                }
                else {
                    result[diff.key] = diff.value
                }
            }
            console.log(result)
            return result
        },
        reset() {
            this.resetModal = false
            this.model = JSON.parse(JSON.stringify(this.originalModel))
        },
        save() {
            this.openRequests++
            this.saveModal = false
            if (this.manuscript == null) {
                axios.post(this.postManuscriptUrl, this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.getManuscriptUrl.replace('manuscript_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.alerts.push({type: 'error', message: 'Something whent wrong while saving the manuscript data.'})
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.putManuscriptUrl, this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.getManuscriptUrl
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.alerts.push({type: 'error', message: 'Something whent wrong while saving the manuscript data.'})
                        this.openRequests--
                    })
            }
        },
        saveButton() {
            this.validateForms()
            if (this.invalidForms) {
                this.invalidModal = true
            }
            else {
                this.saveModal = true
            }
        },
        reload() {
            window.onbeforeunload = function () {}
            window.location.reload(true)
        },
    }
}
</script>
