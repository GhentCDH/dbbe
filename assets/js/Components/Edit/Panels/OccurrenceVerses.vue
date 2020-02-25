<template>
    <panel :header="header">
        <vue-form-generator
            ref="generalForm"
            :schema="generalSchema"
            :model="model"
            :options="formOptions"
            @validated="validated"
        />
        <h6>Preview</h6>
        <table
            v-if="model.verses"
            class="table greek"
        >
            <tbody>
                <tr
                    v-for="(individualVerse, index) in model.verses"
                    :key="index"
                >
                    <td
                        class="line-number"
                        :data-line-number="index + 1"
                    />
                    <td class="verse">
                        <a
                            v-if="individualVerse.groupId"
                            :href="urls['verse_variant_get'].replace('verse_variant_id', individualVerse.groupId)"
                        >
                            {{ individualVerse.verse }}
                            <i class="fa fa-link pull-right"></i>
                        </a>
                        <a
                            v-else-if="individualVerse.linkVerses"
                            href="#"
                        >
                            {{ individualVerse.verse }}
                            <i class="fa fa-link pull-right"></i>
                        </a>
                        <template v-else>
                            {{ individualVerse.verse }}
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>
        <btn @click="addText()"><i class="fa fa-plus" />&nbsp;Add verses as full text</btn>
        <h6>Edit verses individually</h6>
        <draggable
            v-model="model.verses"
            @change="onVerseOrderChange"
        >
            <transition-group name="draggable">
                <div
                    v-for="(individualVerse, index) in model.verses"
                    :key="individualVerse.order"
                    class="panel panel-default draggable-item greek"
                >
                    <div class="panel-body row">
                        <div class="col-xs-1">
                            <i class="fa fa-arrows draggable-icon" />
                        </div>
                        <div class="col-xs-9">
                            {{ individualVerse.verse }}
                        </div>
                        <div class="col-xs-2 text-right">
                            <a
                                v-if="individualVerse.linkVerses || individualVerse.groupId"
                                href="#"
                                title="Display links"
                                class="action"
                                @click.prevent="displayLinks(index)"
                            >
                                <i class="fa fa-link" />
                            </a>
                            <a
                                href="#"
                                title="Edit"
                                class="action"
                                @click.prevent="editVerse(index)"
                            >
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                href="#"
                                title="Delete"
                                class="action"
                                @click.prevent="delVerse(index)"
                            >
                                <i class="fa fa-trash-o" />
                            </a>
                        </div>
                    </div>
                </div>
            </transition-group>
        </draggable>
        <btn @click="addVerse()"><i class="fa fa-plus" />&nbsp;Add a single verse</btn>
        <modal
            v-if="verse"
            v-model="linksModal"
            size="lg"
            :footer="false"
            auto-focus
        >
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <verseTable
                :link-verses="tableVerses"
                :urls="urls"
            />
            <div slot="header">
                <h4 class="modal-title">
                    <span>Linked verses for verse "<span class="greek">{{ verse.verse }}</span>" ({{ verse.index + 1 }})</span>
                </h4>
            </div>
        </modal>
        <modal
            v-model="addTextModal"
            size="lg"
            auto-focus
        >
            <vue-form-generator
                ref="addTextForm"
                :schema="addTextSchema"
                :model="textModel"
                :options="formOptions"
                @validated="addTextValidated"
            />
            <div slot="header">
                <h4 class="modal-title">
                    Add text
                </h4>
            </div>
            <div slot="footer">
                <btn @click="addTextModal=false">Cancel</btn>
                <btn
                    type="success"
                    :disabled="!addTextIsValid"
                    @click="submitAddText()"
                >
                    Add
                </btn>
            </div>
        </modal>
        <modal
            v-if="verse != null && verse.linkVerses != null"
            v-model="editVerseModal"
            size="lg"
            auto-focus
            :backdrop="false"
        >
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
            <div class="pbottom-default">
                <vue-form-generator
                    ref="editVerseForm"
                    :schema="editVerseSchema"
                    :model="verse"
                    :options="formOptions"
                />
                <btn
                    v-if="verse.groupId"
                    @click="updateText()"
                >
                    Update text
                </btn>
                <btn
                    v-if="verse.groupId"
                    @click="updateTextRemoveLink()"
                >
                    Update text and remove link(s)
                </btn>
                <btn
                    v-if="!(verse.groupId)"
                    @click="updateText()"
                >
                    Update text without linking
                </btn>
            </div>
            <h6>Linked verses</h6>
            <verseTable
                :link-verses="tableVerses"
                :linked-groups="linkedGroups"
                :linked-verses="linkedVerses"
                :urls="urls"
                :edit="true"
                @groupToggle="groupToggle"
                @verseToggle="verseToggle"
            />
            <btn
                v-if="verse.linkVerses.length !== 0"
                type="success"
                @click="updateTextSetLinks()"
            >
                Update text and update linked verses
            </btn>
            <btn
                v-else
                type="success"
                @click="updateTextSetLinks()"
            >
                Update text and create a new link group for this single verse
            </btn>
            <div class="row">
                <div class="col-xs-11">
                    <vue-form-generator
                        ref="searchVerseForm"
                        :schema="searchVerseSchema"
                        :model="search"
                        :options="formOptions"
                    />
                </div>
                <div class="col-xs-1">
                    <btn
                        :disabled="search == null || search.search == null || search.search === ''"
                        style="margin-top: 1.3em;"
                        @click="searchVerseLinks()"
                    >
                        <i class="fa fa-search" />
                    </btn>
                </div>
            </div>
            <verseTable
                v-if="linkableVerses != null"
                :link-verses="linkableVerses"
                :linked-groups="linkedGroups"
                :linked-verses="linkedVerses"
                :urls="urls"
                :edit="true"
                @groupToggle="groupToggle"
                @verseToggle="verseToggle"
            />
            <div slot="header">
                <h4 class="modal-title">
                    Edit verse
                </h4>
            </div>
            <div slot="footer">
                <btn @click="editVerseModal=false">Cancel</btn>
            </div>
        </modal>
        <modal
            v-if="verse"
            v-model="delVerseModal"
            auto-focus
        >
            Are you sure you want to delete verse "<span class="greek">{{ verse.verse }}</span>"?
            <div slot="header">
                <h4 class="modal-title">
                    Delete verse
                </h4>
            </div>
            <div slot="footer">
                <btn @click="delVerseModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="submitDelVerse()"
                >
                    Delete
                </btn>
            </div>
        </modal>
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import draggable from 'vuedraggable'

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'
import VerseTable from './Components/VerseTable'

Vue.use(VueFormGenerator)
Vue.component('draggable', draggable)
Vue.component('panel', Panel)
Vue.component('verseTable', VerseTable)

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    props: {
        urls: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        return {
            generalSchema: {
                fields: {
                    incipit: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Incipit',
                        labelClasses: 'control-label',
                        styleClasses: 'greek',
                        model: 'incipit',
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Title',
                        labelClasses: 'control-label',
                        styleClasses: 'greek',
                        model: 'title',
                        validator: VueFormGenerator.validators.string,
                    },
                    numberOfVerses: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Number of verses',
                        labelClasses: 'control-label',
                        model: 'numberOfVerses',
                        validator: VueFormGenerator.validators.number,
                        hint: 'Should be left blank if equal to the number of verses listed below. A "0" (without quotes) should be input when the number of verses is unknown.',
                    },
                },
            },
            addTextSchema: {
                fields: {
                    text: {
                        type: 'textArea',
                        label: 'Text',
                        labelClasses: 'control-label',
                        styleClasses: 'greek',
                        model: 'text',
                        rows: 10,
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            editVerseSchema: {
                fields: {
                    verse: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Verse',
                        labelClasses: 'control-label',
                        styleClasses: 'greek',
                        model: 'verse',
                        required: true,
                        validator: [
                            VueFormGenerator.validators.string,
                            VueFormGenerator.validators.required,
                        ],
                    },
                },
            },
            searchVerseSchema: {
                fields: {
                    search: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Search linkable verses',
                        labelClasses: 'control-label',
                        styleClasses: 'greek',
                        model: 'search',
                        validator: VueFormGenerator.validators.string,
                    },
                },
            },
            linksModal: false,
            addTextModal: false,
            addTextIsValid: false,
            textModel: {},
            editVerseModal: false,
            delVerseModal: false,
            verse: null,
            search: {search: null},
            linkableVerses: null,
            alerts: [],
            oldGroups: [],
        }
    },
    computed: {
        tableVerses: function() {
            if (this.verse == null || this.verse.linkVerses == null) {
                return []
            }

            let results = []

            let existing = false
            for (let verse of this.verse.linkVerses) {
                if (verse.groupId == null) {
                    results.push({
                        group: [
                            verse,
                        ],
                    })
                }
                else {
                    for (let result of results) {
                        if (verse.groupId === result['group_id']) {
                            result.group.push(verse)
                            existing = true
                            break
                        }
                    }
                    if (!existing) {
                        results.push({
                            group_id: verse.groupId,
                            group: [
                                verse,
                            ],
                        })
                    }
                }
            }

            return results
        },
        linkedGroups: function() {
            if (this.verse == null || this.verse.linkVerses == null) {
                return []
            }

            let result = []

            for (let verse of this.verse.linkVerses) {
                if (verse.groupId != null && !(result.includes(verse.groupId))) {
                    result.push(verse.groupId)
                }
            }

            return result
        },
        linkedVerses: function() {
            if (this.verse == null || this.verse.linkVerses == null) {
                return []
            }

            let result = []

            for (let verse of this.verse.linkVerses) {
                if (verse.groupId == null) {
                    result.push(verse.id)
                }
            }

            return result
        },
        maxOrder: function() {
            if (this.model.verses.length == 0) {
                return 0;
            }
            return Math.max.apply(Math, this.model.verses.map(function(v) { return v.order; }));
        },
    },
    watch: {
        'model.numberOfVerses'() {
            if (isNaN(this.model.numberOfVerses)) {
                this.model.numberOfVerses = null;
                this.$nextTick(function() {
                    this.validate();
                });
            }
        },
        'verse.verse'(newValue, oldValue) {
            if (this.editVerseModal && this.search.search === oldValue) {
                this.search.search = newValue
            }
        },
    },
    methods: {
        // validated (inherited) is only called on generalForm
        validate() {
            this.$refs.generalForm.validate()
        },
        calcChanges() {
            this.changes = []
            for (let key of Object.keys(this.model)) {
                if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key]) && !(this.model[key] == null && this.originalModel[key] == null)) {
                    switch(key) {
                    case 'incipit':
                        this.changes.push({
                            'key': 'incipit',
                            'label': 'Incipit',
                            'old': this.originalModel.incipit,
                            'new': this.model.incipit,
                            'value': this.model.incipit,
                        });
                        break;
                    case 'title':
                        this.changes.push({
                            'key': 'title',
                            'label': 'title',
                            'old': this.originalModel.title,
                            'new': this.model.title,
                            'value': this.model.title,
                        });
                        break;
                    case 'numberOfVerses':
                        this.changes.push({
                            'key': 'numberOfVerses',
                            'label': 'Number of Verses',
                            'old': this.originalModel.numberOfVerses,
                            'new': this.model.numberOfVerses,
                            'value': this.model.numberOfVerses,
                        });
                        break;
                    case 'verses':
                        this.changes.push({
                            'key': 'verses',
                            'label': 'Verses',
                            'old': this.displayVerses(this.originalModel.verses),
                            'new': this.displayVerses(this.model.verses),
                            'value': this.model.verses,
                        });
                        break;
                    }
                }
            }
        },
        validated(isValid, errors) {
            this.isValid = isValid;
            this.calcChanges();
            this.$emit('validated', isValid, this.errors, this)
        },
        addTextValidated(isValid, errors) {
            this.addTextIsValid = isValid;
        },
        setVerse(index) {
            let self = this
            return new Promise(function(resolve, reject) {
                let verse =  JSON.parse(JSON.stringify(self.model.verses[index]))
                verse['index'] = index
                if (self.model.verses[index].linkVerses != null) {
                    self.verse = JSON.parse(JSON.stringify(verse))
                    return resolve()
                }
                if (self.oldGroups[verse.groupId] != null) {
                    verse.linkVerses = JSON.parse(JSON.stringify(self.oldGroups[verse.groupId]))
                    self.verse = JSON.parse(JSON.stringify(verse))
                    return resolve()
                }
                if (verse.groupId != null) {
                    self.$parent.openRequests++
                    axios.get(self.urls['verse_variant_get'].replace('verse_variant_id', verse.groupId))
                        .then( (response) => {
                            let linkVerses = response.data
                            // remove current verse
                            if (verse.id != null) {
                                linkVerses = linkVerses.filter(linkVerse => linkVerse.id != verse.id)
                            }
                            self.oldGroups[verse.groupId] = linkVerses
                            verse.linkVerses = linkVerses
                            self.verse = JSON.parse(JSON.stringify(verse))
                            self.$parent.openRequests--
                            return resolve()
                        })
                        .catch( (error) => {
                            console.log(error)
                            self.alerts.push({type: 'error', message: 'Something went wrong while searching for linked verses.', login: self.$parent.isLoginError(error)})
                            self.$parent.openRequests--
                            return reject()
                        })
                }
                else {
                    verse.linkVerses = []
                    self.verse = JSON.parse(JSON.stringify(verse))
                    return resolve()
                }
            })
        },
        displayLinks(index) {
            this.setVerse(index)
                .then((response) => {
                    this.linksModal = true
                })
        },
        addText() {
            this.text = ''
            this.addTextModal = true
        },
        submitAddText() {
            this.$refs.addTextForm.validate()
            this.addTextIsValid = (this.$refs.addTextForm.errors.length === 0)
            if (this.addTextIsValid) {
                for (let verse of this.textModel.text.split(/\r?\n/)) {
                    this.model.verses.push({
                        id: null,
                        groupId: null,
                        verse: verse,
                        order: this.maxOrder + 1,
                    });
                }
                this.textModel = {};

                this.calcChanges()
                this.$emit('validated', 0, null, this)
                this.addTextModal = false
            }
        },
        addVerse() {
            this.verse = {
                verse: '',
                linkVerses: [],
                index: this.model.verses.length,
                order: this.model.verses.length + 1,
            }
            this.search.search = ''
            this.linkableVerses = null
            this.editVerseModal = true
        },
        editVerse(index) {
            this.setVerse(index)
                .then((response) => {
                    this.search.search = this.verse.verse
                    this.linkableVerses = null
                    this.editVerseModal = true
                })

        },
        searchVerseLinks() {
            this.$parent.openRequests++
            this.editVerseModal = false
            let url = this.urls['verse_search'] + '?verse=' + encodeURIComponent(this.search.search)
            if (this.verse.id != null) {
                url += '&id=' + this.verse.id
            }
            axios.get(url)
                .then( (response) => {
                    this.linkableVerses = response.data
                    this.editVerseModal = true
                    this.$parent.openRequests--
                })
                .catch( (error) => {
                    console.log(error)
                    this.alerts.push({type: 'error', message: 'Something went wrong while searching for linkable verses.', login: this.$parent.isLoginError(error)})
                    this.editVerseModal = true
                    this.$parent.openRequests--
                })
        },
        groupToggle(action, groupId) {
            switch (action) {
            case 'add':
                for (let linkableGroup of this.linkableVerses) {
                    if (linkableGroup['group_id'] === groupId) {
                        // data in elasticsearch result is sufficient
                        if (linkableGroup.total == null || linkableGroup.total <= linkableGroup.group.length) {
                            for (let linkableVerse of linkableGroup.group) {
                                let linkVerse = JSON.parse(JSON.stringify(linkableVerse))
                                linkVerse.groupId = linkVerse['group_id']
                                delete linkVerse['group_id']
                                this.verse.linkVerses.push(JSON.parse(JSON.stringify(linkVerse)))
                            }
                        }
                        // data in elasticsearch result is insufficient, get from cache or db
                        else {
                            if (this.oldGroups[groupId] != null) {
                                for (let linkableVerse of this.oldGroups[groupId]) {
                                    this.verse.linkVerses.push(JSON.parse(JSON.stringify(linkableVerse)))
                                }
                            }
                            else {
                                this.$parent.openRequests++
                                this.editVerseModal = false
                                axios.get(this.urls['verse_variant_get'].replace('verse_variant_id', groupId))
                                    .then( (response) => {
                                        this.oldGroups[groupId] = response.data
                                        for (let linkableVerse of this.oldGroups[groupId]) {
                                            this.verse.linkVerses.push(JSON.parse(JSON.stringify(linkableVerse)))
                                        }
                                        this.editVerseModal = true
                                        this.$parent.openRequests--
                                    })
                                    .catch( (error) => {
                                        console.log(error)
                                        this.alerts.push({type: 'error', message: 'Something went wrong while getting for linked verses.', login: this.$parent.isLoginError(error)})
                                        this.editVerseModal = true
                                        this.$parent.openRequests--
                                    })
                            }
                        }
                        break
                    }
                }
                break
            case 'remove':
                for (let i=this.verse.linkVerses.length-1; i>=0; i--) {
                    if (this.verse.linkVerses[i].groupId === groupId) {
                        this.verse.linkVerses.splice(i, 1)
                    }
                }
                break
            }
        },
        verseToggle(action, id) {
            switch (action) {
            case 'add':
                for (let linkableGroup of this.linkableVerses) {
                    if (linkableGroup['group'][0].id === id) {
                        this.verse.linkVerses.push(JSON.parse(JSON.stringify(linkableGroup['group'][0])))
                        break
                    }
                }
                break
            case 'remove':
                for (let i=this.verse.linkVerses.length-1; i>=0; i--) {
                    if (this.verse.linkVerses[i].id === id) {
                        this.verse.linkVerses.splice(i, 1)
                    }
                }
                break
            }
        },
        updateText() {
            this.$refs.editVerseForm.validate()
            this.isValid = (this.$refs.editVerseForm.errors.length === 0)
            if (this.isValid) {
                if (this.model.verses[this.verse.index] == null) {
                    if (this.verse.linkVerses.length === 0) {
                        delete this.verse.linkVerses;
                    }
                    // add new
                    this.model.verses.push(JSON.parse(JSON.stringify(this.verse)))
                }
                else {
                    // update
                    this.model.verses[this.verse.index]['verse'] = this.verse.verse
                }

                this.calcChanges()
                this.$emit('validated', 0, null, this)
                this.editVerseModal = false
            }
        },
        updateTextRemoveLink() {
            this.$refs.editVerseForm.validate()
            this.isValid = (this.$refs.editVerseForm.errors.length === 0)
            if (this.isValid) {
                // only update is possible
                this.model.verses[this.verse.index].verse = this.verse.verse
                this.model.verses[this.verse.index].groupId = null
                delete this.model.verses[this.verse.index].linkVerses

                this.calcChanges()
                this.$emit('validated', 0, null, this)
                this.editVerseModal = false
            }
        },
        updateTextSetLinks() {
            this.$refs.editVerseForm.validate()
            this.isValid = (this.$refs.editVerseForm.errors.length === 0)
            if (this.isValid) {
                if (this.model.verses[this.verse.index] == null) {
                    // add new
                    this.model.verses.push(JSON.parse(JSON.stringify(this.verse)))
                }
                else {
                    // update
                    this.model.verses[this.verse.index].verse = this.verse.verse
                    // remove linkVerses if same as original
                    if (this.verse.groupId != null && JSON.stringify(this.verse.linkVerses) === JSON.stringify(this.oldGroups[this.verse.groupId])) {
                        delete this.model.verses[this.verse.index].linkVerses
                    }
                    else {
                        this.model.verses[this.verse.index].linkVerses = JSON.parse(JSON.stringify(this.verse.linkVerses))
                    }
                }

                this.calcChanges()
                this.$emit('validated', 0, null, this)
                this.editVerseModal = false
            }
        },
        delVerse(index) {
            this.verse = JSON.parse(JSON.stringify(this.model.verses[index]))
            this.verse['index'] = index
            this.delVerseModal = true
        },
        submitDelVerse() {
            this.model.verses.splice(this.verse.index, 1)

            this.calcChanges()
            this.$emit('validated', 0, null, this)
            this.delVerseModal = false
        },
        onVerseOrderChange() {
            this.calcChanges();
            this.$emit('validated', 0, null, this);
        },
        displayVerses(verses) {
            // Return null if verses are undefined (e.g. old values when cloning)
            if (verses == null) {
                return [];
            }
            let result = []
            for (let verse of verses) {
                let display = '<span class="greek">' + verse.verse + '</span>'
                if (verse.linkVerses != null) {
                    display += ' <strong>(new linked verses)<strong>'
                }
                else if (verse.groupId != null) {
                    display += ' (linked verses)'
                }
                result.push(display)
            }
            return result
        },
    }
}
</script>
