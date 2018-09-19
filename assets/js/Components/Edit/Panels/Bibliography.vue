<template>
    <panel :header="header">
        <div class="pbottom-large">
            <h3>Books</h3>
            <table
                v-if="model.books.length > 0"
                class="table table-striped table-bordered table-hover"
            >
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Start page</th>
                        <th>End page</th>
                        <th>Raw pages</th>
                        <th v-if="referenceType">Type</th>
                        <th>Remark</th>
                        <th>Note</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, index) in model.books"
                        :key="index"
                    >
                        <td>{{ item.book.name }}</td>
                        <td>{{ item.startPage }}</td>
                        <td>{{ item.endPage }}</td>
                        <td>{{ item.rawPages }}</td>
                        <td v-if="referenceType">{{ item.referenceType.name }}</td>
                        <td>{{ item.sourceRemark }}</td>
                        <td>{{ item.note }}</td>
                        <td>
                            <a
                                href="#"
                                title="Edit"
                                class="action"
                                @click.prevent="updateBib(item, index)"
                            >
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                href="#"
                                title="Delete"
                                class="action"
                                @click.prevent="delBib(item, index)"
                            >
                                <i class="fa fa-trash-o" />
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <btn @click="newBib('book')"><i class="fa fa-plus" />&nbsp;Add a book reference</btn>
        </div>
        <div class="pbottom-large">
            <h3>Articles</h3>
            <table
                v-if="model.articles.length > 0"
                class="table table-striped table-bordered table-hover"
            >
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Start page</th>
                        <th>End page</th>
                        <th>Raw pages</th>
                        <th v-if="referenceType">Type</th>
                        <th>Remark</th>
                        <th>Note</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, index) in model.articles"
                        :key="index"
                    >
                        <td>{{ item.article.name }}</td>
                        <td>{{ item.startPage }}</td>
                        <td>{{ item.endPage }}</td>
                        <td>{{ item.rawPages }}</td>
                        <td v-if="referenceType">{{ item.referenceType.name }}</td>
                        <td>{{ item.sourceRemark }}</td>
                        <td>{{ item.note }}</td>
                        <td>
                            <a
                                href="#"
                                title="Edit"
                                class="action"
                                @click.prevent="updateBib(item, index)"
                            >
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                href="#"
                                title="Delete"
                                class="action"
                                @click.prevent="delBib(item, index)"
                            >
                                <i class="fa fa-trash-o" />
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <btn @click="newBib('article')"><i class="fa fa-plus" />&nbsp;Add an article reference</btn>
        </div>
        <div class="pbottom-large">
            <h3>Book chapters</h3>
            <table
                v-if="model.bookChapters.length > 0"
                class="table table-striped table-bordered table-hover"
            >
                <thead>
                    <tr>
                        <th>Book Chapter</th>
                        <th>Start page</th>
                        <th>End page</th>
                        <th>Raw pages</th>
                        <th v-if="referenceType">Type</th>
                        <th>Remark</th>
                        <th>Note</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, index) in model.bookChapters"
                        :key="index"
                    >
                        <td>{{ item.bookChapter.name }}</td>
                        <td>{{ item.startPage }}</td>
                        <td>{{ item.endPage }}</td>
                        <td>{{ item.rawPages }}</td>
                        <td v-if="referenceType">{{ item.referenceType.name }}</td>
                        <td>{{ item.sourceRemark }}</td>
                        <td>{{ item.note }}</td>
                        <td>
                            <a
                                href="#"
                                title="Edit"
                                class="action"
                                @click.prevent="updateBib(item, index)"
                            >
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                href="#"
                                title="Delete"
                                class="action"
                                @click.prevent="delBib(item, index)"
                            >
                                <i class="fa fa-trash-o" />
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <btn @click="newBib('bookChapter')"><i class="fa fa-plus" />&nbsp;Add a book chapter reference</btn>
        </div>
        <div>
            <h3>Online sources</h3>
            <table
                v-if="model.onlineSources.length > 0"
                class="table table-striped table-bordered table-hover"
            >
                <thead>
                    <tr>
                        <th>Online source</th>
                        <th>Source link</th>
                        <th>Relative link</th>
                        <th v-if="referenceType">Type</th>
                        <th>Remark</th>
                        <th>Note</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, index) in model.onlineSources"
                        :key="index"
                    >
                        <td>{{ item.onlineSource.name }}</td>
                        <td>{{ item.onlineSource.url }}</td>
                        <td>{{ item.relUrl }}</td>
                        <td v-if="referenceType">{{ item.referenceType.name }}</td>
                        <td>{{ item.sourceRemark }}</td>
                        <td>{{ item.note }}</td>
                        <td>
                            <a
                                href="#"
                                title="Edit"
                                class="action"
                                @click.prevent="updateBib(item, index)"
                            >
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                href="#"
                                title="Delete"
                                class="action"
                                @click.prevent="delBib(item, index)"
                            >
                                <i class="fa fa-trash-o" />
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <btn @click="newBib('onlineSource')"><i class="fa fa-plus" />&nbsp;Add an online source</btn>
        </div>
        <modal
            v-model="editBibModal"
            size="lg"
            auto-focus
        >
            <vue-form-generator
                v-if="editBib.type === 'book'"
                ref="editBibForm"
                :schema="editBookBibSchema"
                :model="editBib"
                :options="formOptions"
                @validated="validated"
            />
            <vue-form-generator
                v-if="editBib.type === 'article'"
                ref="editBibForm"
                :schema="editArticleBibSchema"
                :model="editBib"
                :options="formOptions"
                @validated="validated"
            />
            <vue-form-generator
                v-if="editBib.type === 'bookChapter'"
                ref="editBibForm"
                :schema="editBookChapterBibSchema"
                :model="editBib"
                :options="formOptions"
                @validated="validated"
            />
            <vue-form-generator
                v-if="editBib.type === 'onlineSource'"
                ref="editBibForm"
                :schema="editOnlineSourceBibSchema"
                :model="editBib"
                :options="formOptions"
                @validated="validated"
            />
            <div slot="header">
                <h4
                    v-if="editBib.id"
                    class="modal-title"
                >
                    Edit bibliography
                </h4>
                <h4
                    v-if="!editBib.id"
                    class="modal-title"
                >
                    Add a new bibliography item
                </h4>
            </div>
            <div slot="footer">
                <btn @click="editBibModal=false">Cancel</btn>
                <btn
                    type="success"
                    :disabled="!isValid"
                    @click="submitBib()"
                >
                    {{ bibIndex > -1 ? 'Update' : 'Add' }}
                </btn>
            </div>
        </modal>
        <modal
            v-model="delBibModal"
            title="Delete bibliography"
            auto-focus
        >
            <p>Are you sure you want to delete this bibliography?</p>
            <div slot="footer">
                <btn @click="delBibModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="submitDeleteBib()"
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

import VueMultiselect from 'vue-multiselect'
import fieldMultiselectClear from '../../FormFields/fieldMultiselectClear'

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    props: {
        referenceType: {
            type: Boolean,
            default: false,
        },
        values: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        let data = {
            editBookBibSchema: {
                fields: {
                    book: this.createMultiSelect(
                        'Book',
                        {
                            values: this.values.books,
                            required: true,
                            validator: VueFormGenerator.validators.required
                        }
                    ),
                }
            },
            editArticleBibSchema: {
                fields: {
                    article: this.createMultiSelect(
                        'Article',
                        {
                            values: this.values.articles,
                            required: true,
                            validator: VueFormGenerator.validators.required
                        }
                    ),
                }
            },
            editBookChapterBibSchema: {
                fields: {
                    bookChapter: this.createMultiSelect(
                        'Book Chapter',
                        {
                            values: this.values.bookChapters,
                            required: true,
                            validator: VueFormGenerator.validators.required
                        }
                    ),
                }
            },
            editOnlineSourceBibSchema: {
                fields: {
                    onlineSource: this.createMultiSelect(
                        'Online Source',
                        {
                            values: this.values.onlineSources,
                            required: true,
                            validator: VueFormGenerator.validators.required
                        }
                    ),
                    sourceLink: {
                        type: 'input',
                        inputType: 'text',
                        disabled: 'true',
                        label: 'Source link',
                        labelClasses: 'control-label',
                        model: 'onlineSource.url',
                    },
                    relUrl: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Relative link',
                        labelClasses: 'control-label',
                        model: 'relUrl',
                        validator: VueFormGenerator.validators.string,
                    }
                }
            },
            editBibModal: false,
            delBibModal: false,
            bibIndex: null,
            editBib: {},
        }
        let startPageField = {
            type: 'input',
            inputType: 'text',
            label: 'Start page',
            labelClasses: 'control-label',
            model: 'startPage',
            validator: VueFormGenerator.validators.string,
        }
        data.editBookBibSchema.fields['startPage'] = startPageField
        data.editArticleBibSchema.fields['startPage'] = startPageField
        data.editBookChapterBibSchema.fields['startPage'] = startPageField
        let endPageField = {
            type: 'input',
            inputType: 'text',
            label: 'End page',
            labelClasses: 'control-label',
            model: 'endPage',
            validator: VueFormGenerator.validators.string,
        }
        data.editBookBibSchema.fields['endPage'] = endPageField
        data.editArticleBibSchema.fields['endPage'] = endPageField
        data.editBookChapterBibSchema.fields['endPage'] = endPageField
        let rawPagesField = {
            type: 'input',
            inputType: 'text',
            label: 'Raw Pages',
            labelClasses: 'control-label',
            model: 'rawPages',
            disabled: true,
            validator: VueFormGenerator.validators.string,
        }
        data.editBookBibSchema.fields['rawPages'] = rawPagesField
        data.editArticleBibSchema.fields['rawPages'] = rawPagesField
        data.editBookChapterBibSchema.fields['rawPages'] = rawPagesField
        if (this.referenceType) {
            let referenceTypeField = this.createMultiSelect('Type', {
                model: 'referenceType',
                values: this.values.referenceTypes,
                required: true,
                validator: VueFormGenerator.validators.required,
            })
            data.editBookBibSchema.fields['referenceType'] = referenceTypeField
            data.editArticleBibSchema.fields['referenceType'] = referenceTypeField
            data.editBookChapterBibSchema.fields['referenceType'] = referenceTypeField
            data.editOnlineSourceBibSchema.fields['referenceType'] = referenceTypeField
        }
        let sourceRemarkField = {
            type: 'input',
            inputType: 'text',
            label: 'Source Remark',
            labelClasses: 'control-label',
            model: 'sourceRemark',
            validator: VueFormGenerator.validators.string,
        }
        data.editBookBibSchema.fields['sourceRemark'] = sourceRemarkField
        data.editArticleBibSchema.fields['sourceRemark'] = sourceRemarkField
        data.editBookChapterBibSchema.fields['sourceRemark'] = sourceRemarkField
        data.editOnlineSourceBibSchema.fields['sourceRemark'] = sourceRemarkField
        let noteField = {
            type: 'input',
            inputType: 'text',
            label: 'Note',
            labelClasses: 'control-label',
            model: 'note',
            validator: VueFormGenerator.validators.string,
        }
        data.editBookBibSchema.fields['note'] = noteField
        data.editArticleBibSchema.fields['note'] = noteField
        data.editBookChapterBibSchema.fields['note'] = noteField
        data.editOnlineSourceBibSchema.fields['note'] = noteField
        return data
    },
    computed: {
        fields() {
            return Object.assign(
                {},
                this.editBookBibSchema.fields,
                this.editArticleBibSchema.fields,
                this.editBookChapterBibSchema.fields,
                this.editOnlineSourceBibSchema.fields
            )
        }
    },
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
            this.enableFields()
        },
        enableFields() {
            this.enableField(this.editBookBibSchema.fields.book)
            this.enableField(this.editArticleBibSchema.fields.article)
            this.enableField(this.editBookChapterBibSchema.fields.bookChapter)
            this.enableField(this.editOnlineSourceBibSchema.fields.onlineSource)
            if (this.referenceType) {
                this.enableField(this.editBookBibSchema.fields.referenceType)
                this.enableField(this.editArticleBibSchema.fields.referenceType)
                this.enableField(this.editBookChapterBibSchema.fields.referenceType)
                this.enableField(this.editOnlineSourceBibSchema.fields.referenceType)
            }
        },
        validate() {},
        calcChanges() {
            this.changes = []
            for (let key of Object.keys(this.model)) {
                if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key]) && !(this.model[key] == null && this.originalModel[key] == null)) {
                    // bibliography is regarded as a single item
                    this.changes.push({
                        'key': 'bibliography',
                        'label': 'Bibliography',
                        'old': this.displayBibliography(this.originalModel),
                        'new': this.displayBibliography(this.model),
                        'value': this.model,
                    })
                    break
                }
            }
        },
        updateBib(bibliography, index) {
            this.bibIndex = index
            this.editBib = JSON.parse(JSON.stringify(bibliography))
            this.editBibModal = true
        },
        delBib(bibliography, index) {
            this.bibIndex = index
            this.editBib = JSON.parse(JSON.stringify(bibliography))
            this.delBibModal = true
        },
        newBib(type) {
            this.bibIndex = -1
            this.editBib = {
                type: type
            }
            if (type === 'onlineSource') {
                this.editBib.relUrl = ''
            }
            else {
                this.editBib.startPage = ''
                this.editBib.endPage = ''
            }
            this.editBibModal = true
        },
        validated(isValid, errors) {
            this.isValid = isValid
        },
        submitBib() {
            this.$refs.editBibForm.validate()
            if (this.$refs.editBibForm.errors.length == 0) {
                if (this.editBib.startPage != null) {
                    this.editBib.rawPages = null
                }
                // Edit existing bibliography
                if (this.bibIndex > -1) {
                    this.model[this.editBib.type + "s"][this.bibIndex] = JSON.parse(JSON.stringify(this.editBib))
                }
                // Add new bibliography
                else {
                    this.model[this.editBib.type + "s"].push(JSON.parse(JSON.stringify(this.editBib)))
                }
                this.calcChanges()
                this.$emit('validated', 0, null, this)
                this.editBibModal = false
            }
        },
        submitDeleteBib() {
            this.model[this.editBib.type + "s"].splice(this.bibIndex, 1)
            this.calcChanges()
            this.$emit('validated', 0, null, this)
            this.delBibModal = false
        },
        displayBibliography(bibliography) {
            let result = []
            for (let bib of bibliography['books']) {
                result.push(
                    bib.book.name
                        + this.formatPages(bib.startPage, bib.endPage, bib.rawPages, ': ')
                        + '.'
                        + (bib.referenceType ? '\n(Type: ' + bib.referenceType.name + ')' : '')
                        + (bib.sourceRemark ? '\n(Remark: ' + bib.sourceRemark + ')' : '')
                        + (bib.note ? '\n(Note: ' + bib.note + ')' : '')
                )
            }
            for (let bib of bibliography['articles']) {
                result.push(
                    bib.article.name
                        + this.formatPages(bib.startPage, bib.endPage, bib.rawPages, ': ')
                        + '.'
                        + (bib.referenceType ? '\n(Type: ' + bib.referenceType.name + ')' : '')
                        + (bib.sourceRemark ? '\n(Remark: ' + bib.sourceRemark + ')' : '')
                        + (bib.note ? '\n(Note: ' + bib.note + ')' : '')
                )
            }
            for (let bib of bibliography['bookChapters']) {
                result.push(
                    bib.bookChapter.name
                        + this.formatPages(bib.startPage, bib.endPage, bib.rawPages, ': ')
                        + '.'
                        + (bib.referenceType ? '\n(Type: ' + bib.referenceType.name + ')' : '')
                        + (bib.sourceRemark ? '\n(Remark: ' + bib.sourceRemark + ')' : '')
                        + (bib.note ? '\n(Note: ' + bib.note + ')' : '')
                )
            }
            for (let bib of bibliography['onlineSources']) {
                result.push(
                    bib.onlineSource.url
                        + (bib.relUrl == null ? '' : bib.relUrl)
                        + '.'
                        + (bib.referenceType ? '\n(Type: ' + bib.referenceType.name + ')' : '')
                        + (bib.sourceRemark ? '\n(Remark: ' + bib.sourceRemark + ')' : '')
                        + (bib.note ? '\n(Note: ' + bib.note + ')' : '')
                )
            }
            return result
        },
        formatPages(startPage = null, endPage = null, rawPages = null, prefix = '') {
            if (startPage == null) {
                if (rawPages != null) {
                    return prefix + rawPages
                }
                else {
                    return ''
                }
            }
            if (endPage == null) {
                return prefix + startPage
            }
            return prefix + startPage + '-' + endPage
        },
    }
}
</script>
