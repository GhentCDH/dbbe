<template>
    <panel :header="header">
        <div class="panel panel-default">
            <div class="panel-heading">Bibliograpy</div>
            <div class="panel-body">
                <div class="pbottom-large">
                    <h3>Books</h3>
                    <table
                        v-if="model.books.length > 0"
                        class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Start page</th>
                                <th>End page</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, index) in model.books"
                                :key="index">
                                <td>{{ item.book.name }}</td>
                                <td>{{ item.startPage }}</td>
                                <td>{{ item.endPage }}</td>
                                <td>
                                    <a
                                        href="#"
                                        title="Edit"
                                        class="action"
                                        @click.prevent="updateBib(item, index)">
                                        <i class="fa fa-pencil-square-o" />
                                    </a>
                                    <a
                                        href="#"
                                        title="Delete"
                                        class="action"
                                        @click.prevent="delBib(item, index)">
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
                        class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Article</th>
                                <th>Start page</th>
                                <th>End page</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, index) in model.articles"
                                :key="index">
                                <td>{{ item.article.name }}</td>
                                <td>{{ item.startPage }}</td>
                                <td>{{ item.endPage }}</td>
                                <td>
                                    <a
                                        href="#"
                                        title="Edit"
                                        class="action"
                                        @click.prevent="updateBib(item, index)">
                                        <i class="fa fa-pencil-square-o" />
                                    </a>
                                    <a
                                        href="#"
                                        title="Delete"
                                        class="action"
                                        @click.prevent="delBib(item, index)">
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
                        class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Book Chapter</th>
                                <th>Start page</th>
                                <th>End page</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, index) in model.bookChapters"
                                :key="index">
                                <td>{{ item.bookChapter.name }}</td>
                                <td>{{ item.startPage }}</td>
                                <td>{{ item.endPage }}</td>
                                <td>
                                    <a
                                        href="#"
                                        title="Edit"
                                        class="action"
                                        @click.prevent="updateBib(item, index)">
                                        <i class="fa fa-pencil-square-o" />
                                    </a>
                                    <a
                                        href="#"
                                        title="Delete"
                                        class="action"
                                        @click.prevent="delBib(item, index)">
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
                        class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Online source</th>
                                <th>Source link</th>
                                <th>Relative link</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, index) in model.onlineSources"
                                :key="index">
                                <td>{{ item.onlineSource.name }}</td>
                                <td>{{ item.onlineSource.url }}</td>
                                <td>{{ item.relUrl }}</td>
                                <td>
                                    <a
                                        href="#"
                                        title="Edit"
                                        class="action"
                                        @click.prevent="updateBib(item, index)">
                                        <i class="fa fa-pencil-square-o" />
                                    </a>
                                    <a
                                        href="#"
                                        title="Delete"
                                        class="action"
                                        @click.prevent="delBib(item, index)">
                                        <i class="fa fa-trash-o" />
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <btn @click="newBib('onlineSource')"><i class="fa fa-plus" />&nbsp;Add an online source</btn>
                </div>
            </div>
        </div>
        <modal
            v-model="editBibModal"
            size="lg"
            auto-focus>
            <vue-form-generator
                v-if="editBib.type === 'book'"
                :schema="editBookBibSchema"
                :model="editBib"
                :options="formOptions"
                ref="editBibForm"
                @validated="bibFormValidated" />
            <vue-form-generator
                v-if="editBib.type === 'article'"
                :schema="editArticleBibSchema"
                :model="editBib"
                :options="formOptions"
                ref="editBibForm"
                @validated="bibFormValidated" />
            <vue-form-generator
                v-if="editBib.type === 'bookChapter'"
                :schema="editBookChapterBibSchema"
                :model="editBib"
                :options="formOptions"
                ref="editBibForm"
                @validated="bibFormValidated" />
            <vue-form-generator
                v-if="editBib.type === 'onlineSource'"
                :schema="editOnlineSourceSchema"
                :model="editBib"
                :options="formOptions"
                ref="editBibForm"
                @validated="bibFormValidated" />
            <div slot="header">
                <h4
                    class="modal-title"
                    v-if="editBib.id">
                    Edit bibliography
                </h4>
                <h4
                    class="modal-title"
                    v-if="!editBib.id">
                    Add a new bibliography item
                </h4>
            </div>
            <div slot="footer">
                <btn @click="editBibModal=false">Cancel</btn>
                <btn
                    type="success"
                    :disabled="invalidBibForm"
                    @click="submitBib()">
                    {{ bibIndex > -1 ? 'Update' : 'Add' }}
                </btn>
            </div>
        </modal>
        <modal
            v-model="delBibModal"
            title="Delete bibliography"
            auto-focus>
            <p>Are you sure you want to delete this bibliography?</p>
            <div slot="footer">
                <btn @click="delBibModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="submitDeleteBib()">
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

import Abstract from '../Abstract'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)

export default {
    mixins: [ Abstract ],
    props: {
        values: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        return {
            editBookBibSchema: {
                fields: {
                    book: this.createMultiSelect('Book', {values: this.values.books, required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                    startPage: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Start page',
                        model: 'startPage',
                        validator: VueFormGenerator.validators.string
                    },
                    endPage: {
                        type: 'input',
                        inputType: 'text',
                        label: 'End page',
                        model: 'endPage',
                        validator: VueFormGenerator.validators.string
                    }
                }
            },
            editArticleBibSchema: {
                fields: {
                    article: this.createMultiSelect('Article', {values: this.values.articles, required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                    startPage: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Start page',
                        model: 'startPage',
                        validator: VueFormGenerator.validators.string
                    },
                    endPage: {
                        type: 'input',
                        inputType: 'text',
                        label: 'End page',
                        model: 'endPage',
                        validator: VueFormGenerator.validators.string
                    }
                }
            },
            editBookChapterBibSchema: {
                fields: {
                    bookChapter: this.createMultiSelect('Book Chapter', {values: this.values.bookChapters, required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                    startPage: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Start page',
                        model: 'startPage',
                        validator: VueFormGenerator.validators.string
                    },
                    endPage: {
                        type: 'input',
                        inputType: 'text',
                        label: 'End page',
                        model: 'endPage',
                        validator: VueFormGenerator.validators.string
                    }
                }
            },
            editOnlineSourceSchema: {
                fields: {
                    onlineSource: this.createMultiSelect('Online Source', {values: this.values.onlineSources, required: true, validator: VueFormGenerator.validators.required}, {trackBy: 'id'}),
                    sourceLink: {
                        type: 'input',
                        inputType: 'text',
                        disabled: 'true',
                        label: 'Source link',
                        model: 'onlineSource.url'
                    },
                    relUrl: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Relative link',
                        model: 'relUrl',
                        validator: VueFormGenerator.validators.string
                    }
                }
            },
            editBibModal: false,
            delBibModal: false,
            bibIndex: null,
            editBib: {},
            invalidBibForm: true,
        }
    },
    computed: {
        fields() {
            return Object.assign(
                {},
                this.editBookBibSchema.fields,
                this.editArticleBibSchema.fields,
                this.editBookChapterBibSchema.fields,
                this.editOnlineSourceSchema.fields
            )
        }
    },
    watch: {
        values() {
            this.enableFields()
        },
        model() {
            this.enableFields()
        }
    },
    methods: {
        enableFields() {
            this.enableField(this.editBookBibSchema.fields.book)
            this.enableField(this.editArticleBibSchema.fields.article)
            this.enableField(this.editBookChapterBibSchema.fields.bookChapter)
            this.enableField(this.editOnlineSourceSchema.fields.onlineSource)
        },
        calcChanges() {
            this.changes = []
            if (this.originalModel == null) {
                return
            }
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
            this.editBibModal = true
        },
        bibFormValidated(isValid, errors) {
            this.invalidBibForm = !isValid
        },
        submitBib() {
            this.$refs.editBibForm.validate()
            if (this.$refs.editBibForm.errors.length == 0) {
                if (this.bibIndex > -1) {
                    this.model[this.editBib.type + "s"][this.bibIndex] = JSON.parse(JSON.stringify(this.editBib))
                }
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
            for (let bookBibliography of bibliography['books']) {
                result.push(bookBibliography.book.name + this.formatPages(bookBibliography.startPage, bookBibliography.endPage, ': ') + '.')
            }
            for (let articleBibliography of bibliography['articles']) {
                result.push(articleBibliography.article.name + this.formatPages(articleBibliography.startPage, articleBibliography.endPage, ': ') + '.')
            }
            for (let bookChapterBibliography of bibliography['bookChapters']) {
                result.push(bookChapterBibliography.bookChapter.name + this.formatPages(bookChapterBibliography.startPage, bookChapterBibliography.endPage, ': ') + '.')
            }
            for (let onlineSourceBibliography of bibliography['onlineSources']) {
                result.push(onlineSourceBibliography.onlineSource.url + (onlineSourceBibliography.relUrl == null ? '' : onlineSourceBibliography.relUrl) + '.')
            }
            return result
        },
        formatPages(startPage = null, endPage = null, prefix = '') {
            if (startPage == null) {
                return '';
            }
            if (endPage == null) {
                return prefix + startPage;
            }
            return prefix + startPage + '-' + endPage;
        },
    }
}
</script>
