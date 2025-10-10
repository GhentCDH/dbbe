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

            <a href="#"
            title="Edit"
            class="action"
            @click.prevent="updateBib(item, index)"
            >
            <i class="fa fa-pencil-square-o" />
            </a>

            <a href="#"
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
      <btn @click.native="newBib('book')">
        <i class="fa fa-plus" />&nbsp;Add a book reference
      </btn>
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

            <a href="#"
            title="Edit"
            class="action"
            @click.prevent="updateBib(item, index)"
            >
            <i class="fa fa-pencil-square-o" />
            </a>

            <a href="#"
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
      <btn @click.native="newBib('article')">
        <i class="fa fa-plus" />&nbsp;Add an article reference
      </btn>
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

            <a href="#"
            title="Edit"
            class="action"
            @click.prevent="updateBib(item, index)"
            >
            <i class="fa fa-pencil-square-o" />
            </a>

            <a href="#"
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
      <btn @click.native="newBib('bookChapter')">
        <i class="fa fa-plus" />&nbsp;Add a book chapter reference
      </btn>
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

           <a href="#"
            title="Edit"
            class="action"
            @click.prevent="updateBib(item, index)"
            >
            <i class="fa fa-pencil-square-o" />
            </a>

            <a href="#"
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
      <btn @click.native="newBib('onlineSource')">
        <i class="fa fa-plus" />&nbsp;Add an online source
      </btn>
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

            <a href="#"
            title="Edit"
            class="action"
            @click.prevent="updateBib(item, index)"
            >
            <i class="fa fa-pencil-square-o" />
            </a>

           <a href="#"
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
      <btn @click.native="newBib('blogPost')">
        <i class="fa fa-plus" />&nbsp;Add a blog post reference
      </btn>
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

            <a href="#"
            title="Edit"
            class="action"
            @click.prevent="updateBib(item, index)"
            >
            <i class="fa fa-pencil-square-o" />
            </a>

           <a href="#"
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
      <btn @click.native="newBib('phd')">
        <i class="fa fa-plus" />&nbsp;Add a PhD thesis reference
      </btn>
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

           <a href="#"
            title="Edit"
            class="action"
            @click.prevent="updateBib(item, index)"
            >
            <i class="fa fa-pencil-square-o" />
            </a>

          <a  href="#"
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
      <btn @click.native="newBib('bibVaria')">
        <i class="fa fa-plus" />&nbsp;Add a bib varia reference
      </btn>
    </div>

    <!-- Edit Bibliography Modal -->
    <modal
        :model-value="editBibModal"
        size="lg"
        auto-focus
        :backdrop="null"
        :append-to-body="appendToBody"
    >
      <template #header>
        <h4 v-if="editBib.id" class="modal-title">
          Edit bibliography
        </h4>
        <h4 v-if="!editBib.id" class="modal-title">
          Add a new bibliography item
        </h4>
      </template>

      <vue-form-generator
          v-if="editBib.type === 'book'"
          ref="editBibFormRef"
          :schema="editBookBibSchema"
          :model="editBib"
          :options="formOptions"
          @validated="validated"
      />
      <vue-form-generator
          v-if="editBib.type === 'article'"
          ref="editBibFormRef"
          :schema="editArticleBibSchema"
          :model="editBib"
          :options="formOptions"
          @validated="validated"
      />
      <vue-form-generator
          v-if="editBib.type === 'bookChapter'"
          ref="editBibFormRef"
          :schema="editBookChapterBibSchema"
          :model="editBib"
          :options="formOptions"
          @validated="validated"
      />
      <vue-form-generator
          v-if="editBib.type === 'onlineSource'"
          ref="editBibFormRef"
          :schema="editOnlineSourceBibSchema"
          :model="editBib"
          :options="formOptions"
          @validated="validated"
      />
      <vue-form-generator
          v-if="editBib.type === 'blogPost'"
          ref="editBibFormRef"
          :schema="editBlogPostBibSchema"
          :model="editBib"
          :options="formOptions"
          @validated="validated"
      />
      <vue-form-generator
          v-if="editBib.type === 'phd'"
          ref="editBibFormRef"
          :schema="editPhdBibSchema"
          :model="editBib"
          :options="formOptions"
          @validated="validated"
      />
      <vue-form-generator
          v-if="editBib.type === 'bibVaria'"
          ref="editBibFormRef"
          :schema="editBibVariaBibSchema"
          :model="editBib"
          :options="formOptions"
          @validated="validated"
      />

      <template #footer>
        <btn @click.native="editBibModal = false">Cancel</btn>
        <btn
            type="success"
            :disabled="!isValid"
            @click.native="submitBib"
        >
          {{ bibIndex > -1 ? 'Update' : 'Add' }}
        </btn>
      </template>
    </modal>

    <!-- Delete Bibliography Modal -->
    <modal
        :model-value="delBibModal"
        title="Delete bibliography"
        auto-focus
        :append-to-body="appendToBody"
    >
      <p>Are you sure you want to delete this bibliography?</p>
      <template #footer>
        <btn @click.native="delBibModal = false">Cancel</btn>
        <btn
            type="danger"
            @click.native="submitDeleteBib"
        >
          Delete
        </btn>
      </template>
    </modal>
  </panel>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue';
import Panel from '../Panel.vue';
import Alerts from "@/components/Alerts.vue";
import { Btn as btn, Modal as modal } from 'uiv';
import validatorUtil from '@/helpers/validatorUtil';
import {
  createMultiSelect,
  disableField,
  enableField,
} from '@/helpers/formFieldUtils';

const props = defineProps({
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
    default: () => ({}),
  },
  appendToBody: {
    type: Boolean,
    default: false,
  },
  keys: {
    type: Object,
    default: () => ({
      books: { field: 'book', init: false },
      articles: { field: 'article', init: false },
      bookChapters: { field: 'bookChapter', init: false },
      onlineSources: { field: 'onlineSource', init: false },
      blogPosts: { field: 'blogPost', init: false },
      phds: { field: 'phd', init: false },
      bibVarias: { field: 'bibVaria', init: false },
    }),
  },
  header: {
    type: String,
    default: '',
  },
  links: {
    type: Array,
    default: () => [],
  },
  model: {
    type: Object,
    default: () => ({}),
  },
  reloads: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['validated', 'reload']);

// Refs
const editBibFormRef = ref(null);
const editBibModal = ref(false);
const delBibModal = ref(false);
const bibIndex = ref(null);
const editBib = ref({});
const changes = ref([]);
const isValid = ref(true);
const originalModel = ref({});

// Form options
const formOptions = {
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
};

// Common field definitions
const createStartPageField = () => ({
  type: 'input',
  inputType: 'text',
  label: 'Start page',
  labelClasses: 'control-label',
  model: 'startPage',
  validator: validatorUtil.string,
});

const createEndPageField = () => ({
  type: 'input',
  inputType: 'text',
  label: 'End page',
  labelClasses: 'control-label',
  model: 'endPage',
  validator: validatorUtil.string,
});

const createRawPagesField = () => ({
  type: 'input',
  inputType: 'text',
  label: 'Raw Pages',
  labelClasses: 'control-label',
  model: 'rawPages',
  disabled: true,
  validator: validatorUtil.string,
});

const createReferenceTypeField = () => createMultiSelect('Type', {
  model: 'referenceType',
  values: props.values.referenceTypes,
  required: true,
  validator: validatorUtil.required,
});

const createImageField = () => ({
  type: 'input',
  inputType: 'text',
  label: 'Plate',
  labelClasses: 'control-label',
  model: 'image',
  validator: validatorUtil.string,
});

// Schema builder helper
const buildSchema = (mainField, hasPages = true, hasRelUrl = false) => {
  const fields = { ...mainField };

  if (hasPages) {
    fields.startPage = createStartPageField();
    fields.endPage = createEndPageField();
    fields.rawPages = createRawPagesField();
  }

  if (hasRelUrl) {
    fields.sourceLink = {
      type: 'input',
      inputType: 'text',
      disabled: 'true',
      label: 'Source link',
      labelClasses: 'control-label',
      model: 'onlineSource.url',
    };
    fields.relUrl = {
      type: 'input',
      inputType: 'text',
      label: 'Relative link',
      labelClasses: 'control-label',
      model: 'relUrl',
      validator: validatorUtil.string,
    };
  }

  if (props.referenceType) {
    fields.referenceType = createReferenceTypeField();
  }

  if (props.image) {
    fields.image = createImageField();
  }

  return { fields };
};

// Schemas
const editBookBibSchema = reactive(buildSchema({
  book: createMultiSelect('Book', {
    required: true,
    validator: validatorUtil.required,
  }, {
    customLabel: ({ id, name }) => `${id} - ${name}`,
  }),
}, true));

const editArticleBibSchema = reactive(buildSchema({
  article: createMultiSelect('Article', {
    required: true,
    validator: validatorUtil.required,
  }, {
    customLabel: ({ id, name }) => `${id} - ${name}`,
  }),
}, true));

const editBookChapterBibSchema = reactive(buildSchema({
  bookChapter: createMultiSelect('Book Chapter', {
    required: true,
    validator: validatorUtil.required,
  }, {
    customLabel: ({ id, name }) => `${id} - ${name}`,
  }),
}, true));

const editOnlineSourceBibSchema = reactive(buildSchema({
  onlineSource: createMultiSelect('Online Source', {
    required: true,
    validator: validatorUtil.required,
  }, {
    customLabel: ({ id, name }) => `${id} - ${name}`,
  }),
}, false, true));

const editBlogPostBibSchema = reactive(buildSchema({
  blogPost: createMultiSelect('Blog Post', {
    required: true,
    validator: validatorUtil.required,
  }, {
    customLabel: ({ id, name }) => `${id} - ${name}`,
  }),
}, false));

const editPhdBibSchema = reactive(buildSchema({
  phd: createMultiSelect('Phd', {
    required: true,
    validator: validatorUtil.required,
  }, {
    customLabel: ({ id, name }) => `${id} - ${name}`,
  }),
}, true));

const editBibVariaBibSchema = reactive(buildSchema({
  bibVaria: createMultiSelect('BibVaria', {
    required: true,
    validator: validatorUtil.required,
  }, {
    customLabel: ({ id, name }) => `${id} - ${name}`,
  }),
}, true));

// Computed
const fields = computed(() => ({}));

// Methods
const formatPages = (startPage = null, endPage = null, rawPages = null, prefix = '') => {
  if (startPage == null) {
    if (rawPages != null) {
      return prefix + rawPages;
    }
    return '';
  }
  if (endPage == null) {
    return prefix + startPage;
  }
  return prefix + startPage + '-' + endPage;
};

const displayBibliography = (bibliography) => {
  if (Object.keys(bibliography).length === 0) {
    return [];
  }

  const result = [];
  const types = [
    { key: 'books', field: 'book', hasPages: true },
    { key: 'articles', field: 'article', hasPages: true },
    { key: 'bookChapters', field: 'bookChapter', hasPages: true },
    { key: 'onlineSources', field: 'onlineSource', hasPages: false },
    { key: 'blogPosts', field: 'blogPost', hasPages: false },
    { key: 'phds', field: 'phd', hasPages: true },
    { key: 'bibVarias', field: 'bibVaria', hasPages: true },
  ];

  for (const type of types) {
    for (const bib of bibliography[type.key] || []) {
      let text = '';

      if (type.key === 'onlineSources') {
        text = bib.onlineSource.url;
        if (bib.relUrl != null) {
          text += '\n(Relative url: ' + bib.relUrl + ')';
        }
      } else {
        text = bib[type.field].name;
        if (type.hasPages) {
          text += formatPages(bib.startPage, bib.endPage, bib.rawPages, ': ');
        }
        text += '.';
      }

      if (bib.referenceType) {
        text += '\n(Type: ' + bib.referenceType.name + ')';
      }
      if (bib.image) {
        text += '\n(Image: ' + bib.image + ')';
      }

      result.push(text);
    }
  }

  return result;
};

const calcChanges = () => {
  changes.value = [];

  for (const key of Object.keys(props.model)) {
    if (
        JSON.stringify(props.model[key]) !== JSON.stringify(originalModel.value[key]) &&
        !(props.model[key] == null && originalModel.value[key] == null)
    ) {
      changes.value.push({
        key: 'bibliography',
        label: 'Bibliography',
        old: displayBibliography(originalModel.value),
        new: displayBibliography(props.model),
        value: props.model,
      });
      break;
    }
  }
};

const init = () => {
  originalModel.value = JSON.parse(JSON.stringify(props.model));
  enableFields();
};

const reload = (type) => {
  if (!props.reloads.includes(type)) {
    emit('reload', type);
  }
};

const enableFields = (enableKeys = null) => {
  if (enableKeys == null) {
    if (props.referenceType && props.values.referenceTypes?.length > 0) {
      enableField(editBookBibSchema.fields.referenceType);
      enableField(editArticleBibSchema.fields.referenceType);
      enableField(editBookChapterBibSchema.fields.referenceType);
      enableField(editOnlineSourceBibSchema.fields.referenceType);
      enableField(editBlogPostBibSchema.fields.referenceType);
      enableField(editPhdBibSchema.fields.referenceType);
      enableField(editBibVariaBibSchema.fields.referenceType);
    }
  } else {
    const schemaMap = {
      books: { schema: editBookBibSchema, field: 'book', values: 'books' },
      articles: { schema: editArticleBibSchema, field: 'article', values: 'articles' },
      bookChapters: { schema: editBookChapterBibSchema, field: 'bookChapter', values: 'bookChapters' },
      onlineSources: { schema: editOnlineSourceBibSchema, field: 'onlineSource', values: 'onlineSources' },
      blogPosts: { schema: editBlogPostBibSchema, field: 'blogPost', values: 'blogPosts' },
      phds: { schema: editPhdBibSchema, field: 'phd', values: 'phds' },
      bibVarias: { schema: editBibVariaBibSchema, field: 'bibVaria', values: 'bibVarias' },
    };

    for (const key of enableKeys) {
      const config = schemaMap[key];
      if (config) {
        config.schema.fields[config.field].values = props.values[config.values];
        enableField(config.schema.fields[config.field]);
      }
    }
  }
};

const disableFields = (disableKeys) => {
  const schemaMap = {
    books: { schema: editBookBibSchema, field: 'book' },
    articles: { schema: editArticleBibSchema, field: 'article' },
    bookChapters: { schema: editBookChapterBibSchema, field: 'bookChapter' },
    onlineSources: { schema: editOnlineSourceBibSchema, field: 'onlineSource' },
    blogPosts: { schema: editBlogPostBibSchema, field: 'blogPost' },
    phds: { schema: editPhdBibSchema, field: 'phd' },
    bibVarias: { schema: editBibVariaBibSchema, field: 'bibVaria' },
  };

  for (const key of disableKeys) {
    const config = schemaMap[key];
    if (config) {
      disableField(config.schema.fields[config.field]);
    }
  }
};

const validate = () => {};

const updateBib = (bibliography, index) => {
  bibIndex.value = index;
  editBib.value = JSON.parse(JSON.stringify(bibliography));
  editBibModal.value = true;
};

const delBib = (bibliography, index) => {
  bibIndex.value = index;
  editBib.value = JSON.parse(JSON.stringify(bibliography));
  delBibModal.value = true;
};

const newBib = (type) => {
  bibIndex.value = -1;
  editBib.value = { type };

  if (['article', 'book', 'bookChapter', 'phd', 'bibVaria'].includes(type)) {
    editBib.value.startPage = '';
    editBib.value.endPage = '';
  } else if (['onlineSource'].includes(type)) {
    editBib.value.relUrl = '';
  }

  editBibModal.value = true;
};

const validated = (valid, errors) => {
  isValid.value = valid;
};

const submitBib = () => {
  editBibFormRef.value?.validate();
  if (editBibFormRef.value?.errors.length === 0) {
    if (editBib.value.startPage != null) {
      editBib.value.rawPages = null;
    }

    // Edit existing bibliography
    if (bibIndex.value > -1) {
      props.model[editBib.value.type + 's'][bibIndex.value] = JSON.parse(
          JSON.stringify(editBib.value)
      );
    }
    // Add new bibliography
    else {
      props.model[editBib.value.type + 's'].push(
          JSON.parse(JSON.stringify(editBib.value))
      );
    }

    calcChanges();
    emit('validated', 0, null, { changes: changes.value });
    editBibModal.value = false;
  }
};

const submitDeleteBib = () => {
  props.model[editBib.value.type + 's'].splice(bibIndex.value, 1);
  calcChanges();
  emit('validated', 0, null, { changes: changes.value });
  delBibModal.value = false;
};

// Watch for reference types changes
watch(
    () => props.values.referenceTypes,
    (newVal) => {
      if (props.referenceType && newVal && newVal.length > 0) {
        if (editBookBibSchema.fields.referenceType) {
          editBookBibSchema.fields.referenceType.values = newVal;
        }
        if (editArticleBibSchema.fields.referenceType) {
          editArticleBibSchema.fields.referenceType.values = newVal;
        }
        if (editBookChapterBibSchema.fields.referenceType) {
          editBookChapterBibSchema.fields.referenceType.values = newVal;
        }
        if (editOnlineSourceBibSchema.fields.referenceType) {
          editOnlineSourceBibSchema.fields.referenceType.values = newVal;
        }
        if (editBlogPostBibSchema.fields.referenceType) {
          editBlogPostBibSchema.fields.referenceType.values = newVal;
        }
        if (editPhdBibSchema.fields.referenceType) {
          editPhdBibSchema.fields.referenceType.values = newVal;
        }
        if (editBibVariaBibSchema.fields.referenceType) {
          editBibVariaBibSchema.fields.referenceType.values = newVal;
        }
      }
    }
);

// Expose methods for parent component
defineExpose({
  validate,
  init,
  reload,
  enableFields,
  disableFields,
  calcChanges,
  changes,
  isValid,
});
</script>

<style scoped>
.pbottom-large {
  padding-bottom: 2rem;
}
</style>
