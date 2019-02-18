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

            <basicPersonPanel
                id="basic"
                ref="basic"
                header="Basic Information"
                :links="[
                    {url: urls['self_designations_edit'], text: 'Edit (self) designations'},
                    {url: urls['offices_edit'], text: 'Edit offices'},
                    {url: urls['origins_edit'], text: 'Edit origins'},
                ]"
                :model="model.basic"
                :values="{selfDesignations: selfDesignations, offices: offices, origins: origins}"
                @validated="validated"
            />

            <datePanel
                id="bornDate"
                ref="bornDate"
                header="Date of birth"
                :model="model.bornDate"
                :invalid-combo="invalidDateCombo"
                invalid-combo-text="Date of birth must be earlier than date of death."
                key-group="bornDate"
                group-label="Born"
                @validated="validated"
            />

            <datePanel
                id="deathDate"
                ref="deathDate"
                header="Date of death"
                :model="model.deathDate"
                :invalid-combo="invalidDateCombo"
                invalid-combo-text="Date of birth must be earlier than date of death."
                key-group="deathDate"
                group-label="Death"
                @validated="validated"
            />

            <datePanel
                id="attestedStartDate"
                ref="attestedStartDate"
                header="Attested date or start of attested interval"
                :model="model.attestedStartDate"
                :invalid-combo="invalidAttestedDateCombo"
                invalid-combo-text="Attested start date must be earlier than attested end date."
                key-group="attestedStartDate"
                group-label="Attested date or start of attested interval"
                @validated="validated"
            />

            <datePanel
                id="attestedEndDate"
                ref="attestedEndDate"
                header="End of attested interval"
                :model="model.attestedEndDate"
                :invalid-combo="invalidAttestedDateCombo"
                invalid-combo-text="Attested start date must be earlier than attested end date."
                key-group="attestedEndDate"
                group-label="Attested end"
                @validated="validated"
            />

            <identificationPanel
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
                :values="bibliographies"
                @validated="validated"
            />

            <generalPersonPanel
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
                v-if="person"
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
                    <li><a href="#basic">Basic Information</a></li>
                    <li><a href="#bornDate">Date of birth</a></li>
                    <li><a href="#deathDate">Date of death</a></li>
                    <li><a href="#attestedStartDate">Attested date or interval</a></li>
                    <li><a href="#identification">Identification</a></li>
                    <li><a href="#bibliography">Bibliography</a></li>
                    <li><a href="#general">General</a></li>
                    <li><a href="#managements">Management collections</a></li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <resetModal
            title="person"
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
            title="person"
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

const panelComponents = require.context('../Components/Edit/Panels', false, /[/](?:BasicPerson|Date|Identification|Office|Bibliography|GeneralPerson|Management)[.]vue$/);

for(let key of panelComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '');
    Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel', panelComponents(key).default)
}

export default {
    mixins: [ AbstractEntityEdit ],
    data() {
        let data = {
            identifiers: JSON.parse(this.initIdentifiers),
            person: null,
            offices: null,
            origins: null,
            selfDesignations: null,
            bibliographies: null,
            model: {
                basic: {
                    firstName: null,
                    lastName: null,
                    selfDesignations: null,
                    origin: null,
                    extra: null,
                    unprocessed: null,
                    historical: null,
                    modern: null,
                    dbbe: null,
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
                attestedStartDate: {
                    floor: null,
                    ceiling: null,
                    exactDate: null,
                    exactYear: null,
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                },
                attestedEndDate: {
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
                bibliography: {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                },
                general: {
                    publicComment: null,
                    privateComment: null,
                    public: null,
                },
                managements: {managements: null},
            },
            invalidDateCombo: false,
            invalidAttestedDateCombo: false,
            forms: [
                'basic',
                'bornDate',
                'deathDate',
                'attestedStartDate',
                'attestedEndDate',
                'identification',
                'bibliography',
                'general',
                'managements',
            ],
        };
        for (let identifier of data.identifiers) {
            data.model.identification[identifier.systemName] = null;
            if (identifier.extra) {
                data.model.identification[identifier.systemName + '_extra'] = null
            }
        }
        return data
    },
    watch: {
        'model.bornDate.floorYear'() {this.validateDate()},
        'model.bornDate.floorDayMonth'() {this.validateDate()},
        'model.bornDate.ceilingYear'() {this.validateDate()},
        'model.bornDate.ceilingDayMonth'() {this.validateDate()},
        'model.deathDate.floorYear'() {this.validateDate()},
        'model.deathDate.floorDayMonth'() {this.validateDate()},
        'model.deathDate.ceilingYear'() {this.validateDate()},
        'model.deathDate.ceilingDayMonth'() {this.validateDate()},
        'model.attestedStartDate.floorYear'() {this.validateAttestedDate()},
        'model.attestedStartDate.floorDayMonth'() {this.validateAttestedDate()},
        'model.attestedStartDate.ceilingYear'() {this.validateAttestedDate()},
        'model.attestedStartDate.ceilingDayMonth'() {this.validateAttestedDate()},
        'model.attestedEndDate.floorYear'() {this.validateAttestedDate()},
        'model.attestedEndDate.floorDayMonth'() {this.validateAttestedDate()},
        'model.attestedEndDate.ceilingYear'() {this.validateAttestedDate()},
        'model.attestedEndDate.ceilingDayMonth'() {this.validateAttestedDate()},
    },
    created () {
        this.person = this.data.person;
        this.offices = this.data.offices;
        this.origins = this.data.origins;
        this.selfDesignations = this.data.selfDesignations;
        this.bibliographies = {
            books: this.data.books,
            articles: this.data.articles,
            bookChapters: this.data.bookChapters,
            onlineSources: this.data.onlineSources,
        };
        this.managements = this.data.managements
    },
    mounted () {
        this.loadPerson();
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
                    selfDesignations: this.person.selfDesignations,
                    offices: this.person.officesWithParents,
                    origin: this.person.origin,
                    extra: this.person.extra,
                    unprocessed: this.person.unprocessed,
                    historical: this.person.historical,
                    modern: this.person.modern,
                    dbbe: this.person.dbbe,
                };

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
                };

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
                };

                // Attested start date
                this.model.attestedStartDate = {
                    floor: this.person.attestedStartDate != null ? this.person.attestedStartDate.floor : null,
                    ceiling: this.person.attestedStartDate != null ? this.person.attestedStartDate.ceiling : null,
                    exactDate: null,
                    exactYear: null,
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                };

                // Attested end date
                this.model.attestedEndDate = {
                    floor: this.person.attestedEndDate != null ? this.person.attestedEndDate.floor : null,
                    ceiling: this.person.attestedEndDate != null ? this.person.attestedEndDate.ceiling : null,
                    exactDate: null,
                    exactYear: null,
                    floorYear: null,
                    floorDayMonth: null,
                    ceilingYear: null,
                    ceilingDayMonth: null,
                };

                // Identification
                this.model.identification = {};
                for (let identifier of this.identifiers) {
                    this.model.identification[identifier.systemName] = this.person.identifications != null ? this.person.identifications[identifier.systemName] : null;
                    if (identifier.extra) {
                        this.model.identification[identifier.systemName + '_extra'] = this.person.identifications != null ? this.person.identifications[identifier.systemName + '_extra'] : null
                    }
                }

                // Bibliography
                this.model.bibliography = {
                    books: [],
                    articles: [],
                    bookChapters: [],
                    onlineSources: [],
                };
                if (this.person.bibliography != null) {
                    for (let bib of this.person.bibliography) {
                        switch (bib['type']) {
                        case 'book':
                            this.model.bibliography.books.push(bib);
                            break;
                        case 'article':
                            this.model.bibliography.articles.push(bib);
                            break;
                        case 'bookChapter':
                            this.model.bibliography.bookChapters.push(bib);
                            break;
                        case 'onlineSource':
                            this.model.bibliography.onlineSources.push(bib);
                            break
                        }
                    }
                }

                // General
                this.model.general = {
                    publicComment: this.person.publicComment,
                    privateComment: this.person.privateComment,
                    public: this.person.public,
                };

                // Management
                this.model.managements = {
                    managements: this.person.managements,
                }
            }
            else {
                this.model.general.public = true
            }

            this.originalModel = JSON.parse(JSON.stringify(this.model))
        },
        validateDate() {
            this.invalidDateCombo = (
                (this.getFloorDate(this.model.bornDate) > this.getFloorDate(this.model.deathDate))
                || (this.getCeilingDate(this.model.bornDate) > this.getCeilingDate(this.model.deathDate))
            );
            // revalidate both born and death form
            Vue.nextTick(function () {
                this.$refs.bornDate.validate();
                this.$refs.deathDate.validate();
            }, this);
        },
        validateAttestedDate(){
            this.invalidAttestedDateCombo = (
                (this.getFloorDate(this.model.attestedStartDate) > this.getFloorDate(this.model.attestedEndDate))
                || (this.getCeilingDate(this.model.attestedStartDate) > this.getCeilingDate(this.model.attestedEndDate))
            );
            // revalidate both attested start and end form
            Vue.nextTick(function() {
                this.$refs.attestedStartDate.validate();
                this.$refs.deathDate.validate();
            }, this);
        },
        getCeilingDate(date) {
            if (date.ceilingDayMonth == null) {
                date.ceilingDayMonth = '31/12';
            }
            return date.ceilingYear + date.ceilingDayMonth.substring(3,5) + date.ceilingDayMonth.substring(0,2);
        },
        getFloorDate(date) {
            if (date.floorDayMonth == null) {
                date.floorDayMonth = '01/01';
            }
            return date.floorYear + date.floorDayMonth.substring(3,5) + date.floorDayMonth.substring(0,2);
        },
        save() {
            this.openRequests++;
            this.saveModal = false;
            if (this.person == null) {
                axios.post(this.urls['person_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {};
                        // redirect to the detail page
                        window.location = this.urls['person_get'].replace('person_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error);
                        this.saveModal = true;
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the person data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)});
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['person_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {};
                        // redirect to the detail page
                        window.location = this.urls['person_get']
                    })
                    .catch( (error) => {
                        console.log(error);
                        this.saveModal = true;
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the person data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)});
                        this.openRequests--
                    })
            }
        },
    }
}
</script>
