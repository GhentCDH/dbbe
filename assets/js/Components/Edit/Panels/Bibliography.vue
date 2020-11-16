<template>
    <panel
        :header="header"
        :links="links"
        :reloads="reloads"
        @reload="reload"
    >
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
                        <th v-if="image">Plate</th>
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
                        <td v-if="referenceType">
                            <template v-if="item.referenceType != null">
                                {{ item.referenceType.name }}
                            </template>
                        </td>
                        <td v-if="image">
                            <template v-if="item.image != null">
                                {{ item.image }}
                            </template>
                        </td>
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
                        <th v-if="image">Plate</th>
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
                        <td v-if="referenceType">
                            <template v-if="item.referenceType != null">
                                {{ item.referenceType.name }}
                            </template>
                        </td>
                        <td v-if="image">
                            <template v-if="item.image != null">
                                {{ item.image }}
                            </template>
                        </td>
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
                        <th v-if="image">Plate</th>
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
                        <td v-if="referenceType">
                            <template v-if="item.referenceType != null">
                                {{ item.referenceType.name }}
                            </template>
                        </td>
                        <td v-if="image">
                            <template v-if="item.image != null">
                                {{ item.image }}
                            </template>
                        </td>
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
        <div class="pbottom-large">
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
                        <th v-if="image">Plate</th>
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
                        <td v-if="referenceType">
                            <template v-if="item.referenceType != null">
                                {{ item.referenceType.name }}
                            </template>
                        </td>
                        <td v-if="image">
                            <template v-if="item.image != null">
                                {{ item.image }}
                            </template>
                        </td>
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
        <div class="pbottom-large">
            <h3>Blog posts</h3>
            <table
                v-if="model.blogPosts.length > 0"
                class="table table-striped table-bordered table-hover"
            >
                <thead>
                <tr>
                    <th>Blog posts</th>
                    <th v-if="referenceType">Type</th>
                    <th v-if="image">Plate</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <tr
                    v-for="(item, index) in model.blogPosts"
                    :key="index"
                >
                    <td>{{ item.blogPost.name }}</td>
                    <td v-if="referenceType">
                        <template v-if="item.referenceType != null">
                            {{ item.referenceType.name }}
                        </template>
                    </td>
                    <td v-if="image">
                        <template v-if="item.image != null">
                            {{ item.image }}
                        </template>
                    </td>
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
            <btn @click="newBib('blogPost')"><i class="fa fa-plus" />&nbsp;Add a blog post reference</btn>
        </div>
        <div class="pbottom-large">
            <h3>PhD theses</h3>
            <table
                v-if="model.phds.length > 0"
                class="table table-striped table-bordered table-hover"
            >
                <thead>
                <tr>
                    <th>PhD theses</th>
                    <th>Start page</th>
                    <th>End page</th>
                    <th>Raw pages</th>
                    <th v-if="referenceType">Type</th>
                    <th v-if="image">Plate</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <tr
                    v-for="(item, index) in model.phds"
                    :key="index"
                >
                    <td>{{ item.phd.name }}</td>
                    <td>{{ item.startPage }}</td>
                    <td>{{ item.endPage }}</td>
                    <td>{{ item.rawPages }}</td>
                    <td v-if="referenceType">
                        <template v-if="item.referenceType != null">
                            {{ item.referenceType.name }}
                        </template>
                    </td>
                    <td v-if="image">
                        <template v-if="item.image != null">
                            {{ item.image }}
                        </template>
                    </td>
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
            <btn @click="newBib('phd')"><i class="fa fa-plus" />&nbsp;Add a PhD thesis reference</btn>
        </div>
        <div class="pbottom-large">
            <h3>Varia bibliography items</h3>
            <table
                v-if="model.bibVarias.length > 0"
                class="table table-striped table-bordered table-hover"
            >
                <thead>
                <tr>
                    <th>Bib varia</th>
                    <th>Start page</th>
                    <th>End page</th>
                    <th>Raw pages</th>
                    <th v-if="referenceType">Type</th>
                    <th v-if="image">Plate</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <tr
                    v-for="(item, index) in model.bibVarias"
                    :key="index"
                >
                    <td>{{ item.bibVaria.name }}</td>
                    <td>{{ item.startPage }}</td>
                    <td>{{ item.endPage }}</td>
                    <td>{{ item.rawPages }}</td>
                    <td v-if="referenceType">
                        <template v-if="item.referenceType != null">
                            {{ item.referenceType.name }}
                        </template>
                    </td>
                    <td v-if="image">
                        <template v-if="item.image != null">
                            {{ item.image }}
                        </template>
                    </td>
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
            <btn @click="newBib('bibVaria')"><i class="fa fa-plus" />&nbsp;Add a bib varia reference</btn>
        </div>
        <modal
            v-model="editBibModal"
            size="lg"
            auto-focus
            :backdrop="false"
            :append-to-body="appendToBody"
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
            <vue-form-generator
                v-if="editBib.type === 'blogPost'"
                ref="editBibForm"
                :schema="editBlogPostBibSchema"
                :model="editBib"
                :options="formOptions"
                @validated="validated"
            />
            <vue-form-generator
                v-if="editBib.type === 'phd'"
                ref="editBibForm"
                :schema="editPhdBibSchema"
                :model="editBib"
                :options="formOptions"
                @validated="validated"
            />
            <vue-form-generator
                v-if="editBib.type === 'bibVaria'"
                ref="editBibForm"
                :schema="editBibVariaBibSchema"
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
            :append-to-body="appendToBody"
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
        image: {
            type: Boolean,
            default: false,
        },
        values: {
            type: Object,
            default: () => {return {}}
        },
        appendToBody: {
            type: Boolean,
            default: false,
        },
        keys: {
            type: Object,
            default: () => {
                return {
                    books: {field: 'book', init: false},
                    articles: {field: 'article', init: false},
                    bookChapters: {field: 'bookChapter', init: false},
                    onlineSources: {field: 'onlineSource', init: false},
                    blogPosts: {field: 'blogPost', init: false},
                    phds: {field: 'phd', init: false},
                    bibVarias: {field: 'bibVaria', init: false},
                };
            },
        },
    },
    data() {
        let data = {
            editBookBibSchema: {
                fields: {
                    book: this.createMultiSelect(
                        'Book',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        },
                        {
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                        }
                    ),
                }
            },
            editArticleBibSchema: {
                fields: {
                    article: this.createMultiSelect(
                        'Article',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        },
                        {
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                        }
                    ),
                }
            },
            editBookChapterBibSchema: {
                fields: {
                    bookChapter: this.createMultiSelect(
                        'Book Chapter',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        },
                        {
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                        }
                    ),
                }
            },
            editOnlineSourceBibSchema: {
                fields: {
                    onlineSource: this.createMultiSelect(
                        'Online Source',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        },
                        {
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
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
            editBlogPostBibSchema: {
                fields: {
                    blogPost: this.createMultiSelect(
                        'Blog Post',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        },
                        {
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                        }
                    ),
                }
            },
            editPhdBibSchema: {
                fields: {
                    phd: this.createMultiSelect(
                        'Phd',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        },
                        {
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                        }
                    ),
                }
            },
            editBibVariaBibSchema: {
                fields: {
                    bibVaria: this.createMultiSelect(
                        'BibVaria',
                        {
                            required: true,
                            validator: VueFormGenerator.validators.required
                        },
                        {
                            customLabel: ({id, name}) => {
                                return `${id} - ${name}`
                            },
                        }
                    ),
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
        data.editPhdBibSchema.fields['startPage'] = startPageField
        data.editBibVariaBibSchema.fields['startPage'] = startPageField
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
        data.editPhdBibSchema.fields['endPage'] = endPageField
        data.editBibVariaBibSchema.fields['endPage'] = endPageField
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
        data.editPhdBibSchema.fields['rawPages'] = rawPagesField
        data.editBibVariaBibSchema.fields['rawPages'] = rawPagesField
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
            data.editBlogPostBibSchema.fields['referenceType'] = referenceTypeField
            data.editPhdBibSchema.fields['referenceType'] = referenceTypeField
            data.editBibVariaBibSchema.fields['referenceType'] = referenceTypeField
        }
        if (this.image) {
            let imageField = {
                type: 'input',
                inputType: 'text',
                label: 'Plate',
                labelClasses: 'control-label',
                model: 'image',
                validator: VueFormGenerator.validators.string,
            }
            data.editBookBibSchema.fields['image'] = imageField
            data.editArticleBibSchema.fields['image'] = imageField
            data.editBookChapterBibSchema.fields['image'] = imageField
            data.editOnlineSourceBibSchema.fields['image'] = imageField
            data.editBlogPostBibSchema.fields['image'] = imageField
            data.editPhdBibSchema.fields['image'] = imageField
            data.editBibVariaBibSchema.fields['image'] = imageField
        }
        return data
    },
    computed: {
        // Fields is not used in this panel
        fields() {
            return {};
        }
    },
    methods: {
        enableFields(enableKeys) {
            if (enableKeys == null) {
                if (this.referenceType) {
                    this.enableField(this.editBookBibSchema.fields.referenceType);
                    this.enableField(this.editArticleBibSchema.fields.referenceType);
                    this.enableField(this.editBookChapterBibSchema.fields.referenceType);
                    this.enableField(this.editOnlineSourceBibSchema.fields.referenceType);
                    this.enableField(this.editBlogPostBibSchema.fields.referenceType);
                    this.enableField(this.editPhdBibSchema.fields.referenceType);
                    this.enableField(this.editBibVariaBibSchema.fields.referenceType);
                }
            } else {
                if (enableKeys.includes('books')) {
                    this.editBookBibSchema.fields.book.values = this.values.books;
                    this.enableField(this.editBookBibSchema.fields.book);
                } else if (enableKeys.includes('articles')) {
                    this.editArticleBibSchema.fields.article.values = this.values.articles;
                    this.enableField(this.editArticleBibSchema.fields.article);
                } else if (enableKeys.includes('bookChapters')) {
                    this.editBookChapterBibSchema.fields.bookChapter.values = this.values.bookChapters;
                    this.enableField(this.editBookChapterBibSchema.fields.bookChapter);
                } else if (enableKeys.includes('onlineSources')) {
                    this.editOnlineSourceBibSchema.fields.onlineSource.values = this.values.onlineSources;
                    this.enableField(this.editOnlineSourceBibSchema.fields.onlineSource);
                } else if (enableKeys.includes('blogPosts')) {
                    this.editBlogPostBibSchema.fields.blogPost.values = this.values.blogPosts;
                    this.enableField(this.editBlogPostBibSchema.fields.blogPost);
                } else if (enableKeys.includes('phds')) {
                    this.editPhdBibSchema.fields.phd.values = this.values.phds;
                    this.enableField(this.editPhdBibSchema.fields.phd);
                } else if (enableKeys.includes('bibVarias')) {
                    this.editBibVariaBibSchema.fields.bibVaria.values = this.values.bibVarias;
                    this.enableField(this.editBibVariaBibSchema.fields.bibVaria);
                }
            }
        },
        disableFields(disableKeys) {
            if (disableKeys.includes('books')) {
                this.disableField(this.editBookBibSchema.fields.book);
            } else if (disableKeys.includes('articles')) {
                this.disableField(this.editArticleBibSchema.fields.article);
            } else if (disableKeys.includes('bookChapters')) {
                this.disableField(this.editBookChapterBibSchema.fields.bookChapter);
            } else if (disableKeys.includes('onlineSources')) {
                this.disableField(this.editOnlineSourceBibSchema.fields.onlineSource);
            } else if (disableKeys.includes('blogPosts')) {
                this.disableField(this.editBlogPostBibSchema.fields.blogPost);
            } else if (disableKeys.includes('phds')) {
                this.disableField(this.editPhdBibSchema.fields.phd);
            } else if (disableKeys.includes('bibVarias')) {
                this.disableField(this.editBibVariaBibSchema.fields.bibVaria);
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
            if (['article', 'book', 'bookChapter', 'phd', 'bibVaria'].includes(type)) {
                this.editBib.startPage = ''
                this.editBib.endPage = ''
            }
            else if (['onlineSource'].includes(type)) {
                this.editBib.relUrl = ''
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
            // Return null if bibliography is empty (e.g. old values when cloning)
            if (Object.keys(bibliography).length === 0) {
                return [];
            }
            let result = []
            for (let bib of bibliography['books']) {
                result.push(
                    bib.book.name
                        + this.formatPages(bib.startPage, bib.endPage, bib.rawPages, ': ')
                        + '.'
                        + (bib.referenceType ? '\n(Type: ' + bib.referenceType.name + ')' : '')
                        + (bib.image ? '\n(Image: ' + bib.image + ')' : '')
                )
            }
            for (let bib of bibliography['articles']) {
                result.push(
                    bib.article.name
                        + this.formatPages(bib.startPage, bib.endPage, bib.rawPages, ': ')
                        + '.'
                        + (bib.referenceType ? '\n(Type: ' + bib.referenceType.name + ')' : '')
                        + (bib.image ? '\n(Image: ' + bib.image + ')' : '')
                )
            }
            for (let bib of bibliography['bookChapters']) {
                result.push(
                    bib.bookChapter.name
                        + this.formatPages(bib.startPage, bib.endPage, bib.rawPages, ': ')
                        + '.'
                        + (bib.referenceType ? '\n(Type: ' + bib.referenceType.name + ')' : '')
                        + (bib.image ? '\n(Image: ' + bib.image + ')' : '')
                )
            }
            for (let bib of bibliography['onlineSources']) {
                result.push(
                    bib.onlineSource.url
                        + (bib.relUrl == null ? '' : '\n(Relative url: ' + bib.relUrl + ')')
                        + (bib.referenceType ? '\n(Type: ' + bib.referenceType.name + ')' : '')
                        + (bib.image ? '\n(Image: ' + bib.image + ')' : '')
                )
            }
            for (let bib of bibliography['blogPosts']) {
                result.push(
                    bib.blogPost.name
                    + '.'
                    + (bib.referenceType ? '\n(Type: ' + bib.referenceType.name + ')' : '')
                    + (bib.image ? '\n(Image: ' + bib.image + ')' : '')
                )
            }
            for (let bib of bibliography['phds']) {
                result.push(
                    bib.phd.name
                    + this.formatPages(bib.startPage, bib.endPage, bib.rawPages, ': ')
                    + '.'
                    + (bib.referenceType ? '\n(Type: ' + bib.referenceType.name + ')' : '')
                    + (bib.image ? '\n(Image: ' + bib.image + ')' : '')
                )
            }
            for (let bib of bibliography['bibVarias']) {
                result.push(
                    bib.bibVaria.name
                    + this.formatPages(bib.startPage, bib.endPage, bib.rawPages, ': ')
                    + '.'
                    + (bib.referenceType ? '\n(Type: ' + bib.referenceType.name + ')' : '')
                    + (bib.image ? '\n(Image: ' + bib.image + ')' : '')
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
