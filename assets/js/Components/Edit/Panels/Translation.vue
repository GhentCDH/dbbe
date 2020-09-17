<template>
    <panel :header="header">
        <table
            v-if="model.translations.length > 0"
            class="table table-striped table-bordered table-hover"
        >
            <thead>
                <tr>
                    <th>Language</th>
                    <th>Text</th>
                    <th>Bibliography</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(item, index) in model.translations"
                    :key="index"
                >
                    <td>{{ item.language.name }}</td>
                    <td class="preserve-newlines">{{ item.text }}</td>
                    <td>
                        <ul v-if="displayBibliography(item.bibliography).length > 1">
                            <li
                                v-for="(bibItem, bibIndex) in displayBibliography(item.bibliography)"
                                :key="bibIndex"
                            >
                                {{ bibItem }}
                            </li>
                        </ul>
                        <template v-else-if="displayBibliography(item.bibliography).length == 1">
                            {{ displayBibliography(item.bibliography)[0] }}
                        </template>
                    </td>
                    <td>
                        <a
                            href="#"
                            title="Edit"
                            class="action"
                            @click.prevent="update(item, index)"
                        >
                            <i class="fa fa-pencil-square-o" />
                        </a>
                        <a
                            href="#"
                            title="Delete"
                            class="action"
                            @click.prevent="del(item, index)"
                        >
                            <i class="fa fa-trash-o" />
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>
        <btn @click="add()"><i class="fa fa-plus" />&nbsp;Add a translation</btn>
        <modal
            v-model="editModal"
            size="lg"
            auto-focus
            :backdrop="false"
        >
            <vue-form-generator
                ref="editForm"
                :schema="schema"
                :model="editModel"
                :options="formOptions"
                @validated="validated"
            />
            <bibliographyPanel
                id="translationBibliography"
                ref="translationBibliography"
                header="Bibliography"
                :links="[{title: 'Books', reload: 'books', edit: urls['bibliographies_search']},{title: 'Articles', reload: 'articles', edit: urls['bibliographies_search']},{title: 'Book chapters', reload: 'bookChapters', edit: urls['bibliographies_search']},{title: 'Online sources', reload: 'onlineSources', edit: urls['bibliographies_search']},{title: 'Blog Posts', reload: 'blogPosts', edit: urls['bibliographies_search']},{title: 'Phds', reload: 'phds', edit: urls['bibliographies_search']},{title: 'Bib varia', reload: 'bibVarias', edit: urls['bibliographies_search']}]"
                :model="editModel.bibliography"
                :values="values"
                :reloads="reloads"
                :append-to-body="true"
                @validated="calcChanges"
                @reload="reload"
            />
            <div slot="header">
                <h4
                    v-if="editModel.index == null"
                    class="modal-title"
                >
                    Add a new translation
                </h4>
                <h4
                    v-else
                    class="modal-title"
                >
                    Edit translation
                </h4>
            </div>
            <div slot="footer">
                <btn @click="editModal=false">Cancel</btn>
                <btn
                    type="success"
                    :disabled="!isValid"
                    @click="submitEdit()"
                >
                    {{ editModel.index == null ? 'Add' : 'Update' }}
                </btn>
            </div>
        </modal>
        <modal
            v-model="delModal"
            title="Delete translation"
            auto-focus
            :append-to-body="true"
        >
            <p>Are you sure you want to delete this translation?</p>
            <div slot="footer">
                <btn @click="delModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="submitDelete()"
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
        values: {
            type: Object,
            default: () => {return {}}
        },
        urls: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        return {
            editModal: false,
            editModel: {
                bibliography: {
                    articles: [],
                    blogPosts: [],
                    books: [],
                    bookChapters: [],
                    onlineSources: [],
                    phds: [],
                    bibVarias: [],
                },
            },
            delModal: false,
            schema: {
                fields: {
                    text: {
                        type: 'textArea',
                        label: 'Text',
                        labelClasses: 'control-label',
                        model: 'text',
                        rows: 10,
                        required: true,
                        validator: VueFormGenerator.validators.string,
                    },
                    language: this.createMultiSelect(
                        'Language',
                        {
                            values: this.values.languages,
                            required: true,
                            validator: VueFormGenerator.validators.required,
                        }
                    ),
                }
            },
        }
    },
    methods: {
        enableFields(enableKeys) {
            if (enableKeys == null) {
                this.enableField(this.schema.fields.language);
            } else if (enableKeys.includes('articles')
                || enableKeys.includes('blogPosts')
                || enableKeys.includes('books')
                || enableKeys.includes('bookChapters')
                || enableKeys.includes('onlineSources')
                || enableKeys.includes('phds')
                || enableKeys.includes('bibVarias')
            ) {
                this.$refs.translationBibliography.enableFields(enableKeys);
            }
        },
        disableFields(disableKeys) {
            if (disableKeys.includes('articles')
                || disableKeys.includes('blogPosts')
                || disableKeys.includes('books')
                || disableKeys.includes('bookChapters')
                || disableKeys.includes('onlineSources')
                || disableKeys.includes('phds')
                || disableKeys.includes('bibVarias')
            ) {
                this.$refs.translationBibliography.disableFields(disableKeys);
            }
        },
        validate() {},
        calcChanges() {
            this.changes = []
            for (let key of Object.keys(this.model)) {
                if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key]) && !(this.model[key] == null && this.originalModel[key] == null)) {
                    // translations is regarded as a single item
                    this.changes.push({
                        'key': 'translations',
                        'label': 'Translations',
                        'old': this.displayTranslations(this.originalModel.translations),
                        'new': this.displayTranslations(this.model.translations),
                        'value': this.model.translations,
                    })
                    break
                }
            }
        },
        add() {
            this.editModel = {
                bibliography: {
                    articles: [],
                    blogPosts: [],
                    books: [],
                    bookChapters: [],
                    onlineSources: [],
                    phds: [],
                    bibVarias: [],
                },
            }
            this.editModal = true
        },
        update(item, index) {
            this.editModel = JSON.parse(JSON.stringify(item))
            this.editModel.index = index
            this.editModal = true
        },
        del(item, index) {
            this.editModel = JSON.parse(JSON.stringify(item))
            this.editModel.index = index
            this.delModal = true
        },
        submitEdit() {
            this.$refs.editForm.validate()
            if (this.$refs.editForm.errors.length == 0) {
                // Edit existing bibliography
                if (this.editModel.index != null) {
                    let index = this.editModel.index
                    delete this.editModel.index
                    // remove null properties
                    for (let key of Object.keys(this.editModel)) {
                        if (this.editModel[key] == null) {
                            delete this.editModel[key]
                        }
                    }
                    this.model.translations[index] = JSON.parse(JSON.stringify(this.editModel))
                }
                // Add new bibliography
                else {
                    this.model.translations.push(JSON.parse(JSON.stringify(this.editModel)))
                }
                this.calcChanges()
                this.$emit('validated', 0, null, this)
                this.editModal = false
            }
        },
        submitDelete() {
            this.model.translations.splice(this.editModel.index, 1)
            this.calcChanges()
            this.$emit('validated', 0, null, this)
            this.delModal = false
        },
        displayTranslations(translations) {
            let result = []
            for (let t of translations) {
                result.push(
                    t.text
                        + '\nLanguage: ' + t.language.name
                        + (this.displayBibliography(t.bibliography).length > 0 ? '\nBibliography:\n' + this.displayBibliography(t.bibliography).join('\n') : '')
                )
            }
            return result
        },
        displayBibliography(bibliography) {
            let result = []
            for (let bib of bibliography['articles']) {
                result.push(
                    bib.article.name
                    + this.formatPages(bib.startPage, bib.endPage, ': ')
                    + '.'
                )
            }
            for (let bib of bibliography['blogPosts']) {
                result.push(
                    bib.blogPost.name
                    + '.'
                )
            }
            for (let bib of bibliography['books']) {
                result.push(
                    bib.book.name
                        + this.formatPages(bib.startPage, bib.endPage, ': ')
                        + '.'
                )
            }
            for (let bib of bibliography['bookChapters']) {
                result.push(
                    bib.bookChapter.name
                        + this.formatPages(bib.startPage, bib.endPage, ': ')
                        + '.'
                )
            }
            for (let bib of bibliography['onlineSources']) {
                result.push(
                    bib.onlineSource.url
                        + (bib.relUrl == null ? '' : bib.relUrl)
                        + '.'
                )
            }
            for (let bib of bibliography['phds']) {
                result.push(
                    bib.phd.name
                    + this.formatPages(bib.startPage, bib.endPage, ': ')
                    + '.'
                )
            }
            for (let bib of bibliography['bibVarias']) {
                result.push(
                    bib.bibVaria.name
                    + this.formatPages(bib.startPage, bib.endPage, ': ')
                    + '.'
                )
            }
            return result
        },
        formatPages(startPage = null, endPage = null, prefix = '') {
            if (startPage == null) {
                return ''
            }
            if (endPage == null || startPage === endPage) {
                return prefix + startPage
            }
            return prefix + startPage + '-' + endPage
        },
    }
}
</script>
