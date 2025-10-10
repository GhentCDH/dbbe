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
        <th>Translator(s)</th>
        <th>Comments</th>
        <th>Actions</th>
      </tr>
      </thead>
      <tbody>
      <tr
          v-for="(item, index) in model.translations"
          :key="index"
      >
        <td>{{ item.language?.name }}</td>
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
          <template v-else-if="displayBibliography(item.bibliography).length === 1">
            {{ displayBibliography(item.bibliography)[0] }}
          </template>
        </td>
        <td>
          <ul v-if="item.personRoles?.translator?.length > 1">
            <li
                v-for="(translator, trIndex) in item.personRoles.translator"
                :key="trIndex"
            >
              {{ translator.name }}
            </li>
          </ul>
          <template v-else-if="item.personRoles?.translator?.length === 1">
            {{ item.personRoles.translator[0].name }}
          </template>
        </td>
        <td>{{ item.publicComment }}</td>
        <td>

          <a href="#"
          title="Edit"
          class="action"
          @click.prevent="update(item, index)"
          >
          <i class="fa fa-pencil-square-o" />
          </a>

          <a href="#"
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
      <template #header>
        <h4 class="modal-title">
          {{ editModel.index == null ? 'Add a new translation' : 'Edit translation' }}
        </h4>
      </template>

      <vue-form-generator
          ref="editForm"
          :schema="schema"
          :model="editModel"
          :options="formOptions"
          @validated="validated"
      />
      <Bibliography
          id="translationBibliography"
          ref="translationBibliography"
          header="Bibliography"
          :links="[
            {title: 'Books', reload: 'books', edit: urls['bibliographies_search']},
            {title: 'Articles', reload: 'articles', edit: urls['bibliographies_search']},
            {title: 'Book chapters', reload: 'bookChapters', edit: urls['bibliographies_search']},
            {title: 'Online sources', reload: 'onlineSources', edit: urls['bibliographies_search']},
            {title: 'Blog Posts', reload: 'blogPosts', edit: urls['bibliographies_search']},
            {title: 'Phds', reload: 'phds', edit: urls['bibliographies_search']},
            {title: 'Bib varia', reload: 'bibVarias', edit: urls['bibliographies_search']}
          ]"
          :model="editModel.bibliography"
          :values="values"
          :reloads="reloads"
          :append-to-body="true"
          @validated="calcChanges"
          @reload="reload"
      />
      <Person
          id="translators"
          ref="translators"
          header="Translators"
          :links="[{title: 'Persons', reload: 'modernPersons', edit: urls['persons_search']}]"
          :roles="values?.personRoles"
          :model="editModel.personRoles"
          :values="values?.modernPersons"
          :keys="{modernPersons: {init: true}}"
          :reloads="reloads"
          @validated="calcChanges"
          @reload="reload"
      />

      <template #footer>
        <btn @click="editModal = false">Cancel</btn>
        <btn
            type="success"
            :disabled="!isValid"
            @click="submitEdit()"
        >
          {{ editModel.index == null ? 'Add' : 'Update' }}
        </btn>
      </template>
    </modal>
    <modal
        v-model="delModal"
        title="Delete translation"
        auto-focus
    >
      <p>Are you sure you want to delete this translation?</p>
      <template #footer>
        <btn @click="delModal = false">Cancel</btn>
        <btn
            type="danger"
            @click="submitDelete()"
        >
          Delete
        </btn>
      </template>
    </modal>
  </panel>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue'
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils'
import Panel from '../Panel.vue'
import { calcChanges as calcChangesHelper } from "@/helpers/modelChangeUtil"
import validatorUtil from "@/helpers/validatorUtil"
import Bibliography from "@/components/Edit/Panels/Bibliography.vue"
import Person from "@/components/Edit/Panels/Person.vue"

const props = defineProps({
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
  values: {
    type: Object,
    default: () => ({}),
  },
  urls: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['validated', 'reload'])

// Refs
const editForm = ref(null)
const translationBibliography = ref(null)
const translators = ref(null)

// State
const editModal = ref(false)
const delModal = ref(false)
const isValid = ref(true)
const originalModel = ref({})
const changes = ref([])

// Create initial personRoles
const createPersonRoles = () => {
  const roles = {}
  if (props.values?.personRoles) {
    for (const role of props.values.personRoles) {
      roles[role.systemName] = []
    }
  }
  return roles
}

const editModel = reactive({
  bibliography: {
    articles: [],
    blogPosts: [],
    books: [],
    bookChapters: [],
    onlineSources: [],
    phds: [],
    bibVarias: [],
  },
  personRoles: createPersonRoles(),
})

// Schema
const schema = ref({
  fields: {
    language: createMultiSelect(
        'Language',
        {
          values: props.values?.languages || [],
          required: true,
          validator: validatorUtil.required,
        },
    ),
    text: {
      type: 'textArea',
      label: 'Text',
      labelClasses: 'control-label',
      model: 'text',
      rows: 10,
      required: true,
      validator: validatorUtil.string,
    },
    publicComment: {
      type: 'textArea',
      label: 'Public comment',
      labelClasses: 'control-label',
      model: 'publicComment',
      rows: 4,
      validator: validatorUtil.string,
    },
  },
})

const formOptions = ref({
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
})

const fields = computed(() => schema.value.fields)

// Watch for language changes
watch(() => props.values?.languages, (newVal) => {
  if (schema.value.fields.language) {
    schema.value.fields.language.values = newVal || []
  }
}, { deep: true })

// Methods
const init = () => {
  originalModel.value = JSON.parse(JSON.stringify(props.model))
  enableFields()
}

const reload = (type) => {
  if (!props.reloads.includes(type)) {
    emit('reload', type)
  }
}

const enableFields = (enableKeys) => {
  if (enableKeys == null) {
    enableField(schema.value.fields.language)
    translators.value?.enableFields(['modernPersons'])
  } else {
    if (
        enableKeys.includes('articles') ||
        enableKeys.includes('blogPosts') ||
        enableKeys.includes('books') ||
        enableKeys.includes('bookChapters') ||
        enableKeys.includes('onlineSources') ||
        enableKeys.includes('phds') ||
        enableKeys.includes('bibVarias')
    ) {
      translationBibliography.value?.enableFields(enableKeys)
    }
    if (enableKeys.includes('modernPersons')) {
      translators.value?.enableFields(enableKeys)
    }
  }
}

const disableFields = (disableKeys) => {
  if (
      disableKeys.includes('articles') ||
      disableKeys.includes('blogPosts') ||
      disableKeys.includes('books') ||
      disableKeys.includes('bookChapters') ||
      disableKeys.includes('onlineSources') ||
      disableKeys.includes('phds') ||
      disableKeys.includes('bibVarias')
  ) {
    translationBibliography.value?.disableFields(disableKeys)
  }
  if (disableKeys.includes('modernPersons')) {
    translators.value?.disableFields(disableKeys)
  }
}

const validate = () => {
  // Empty implementation as in original
}

const calcChanges = () => {
  changes.value = []
  for (const key of Object.keys(props.model)) {
    if (
        JSON.stringify(props.model[key]) !== JSON.stringify(originalModel.value[key]) &&
        !(props.model[key] == null && originalModel.value[key] == null)
    ) {
      // translations is regarded as a single item
      changes.value.push({
        key: 'translations',
        label: 'Translations',
        old: displayTranslations(originalModel.value.translations),
        new: displayTranslations(props.model.translations),
        value: props.model.translations,
      })
      break
    }
  }
}

const add = () => {
  Object.assign(editModel, {
    bibliography: {
      articles: [],
      blogPosts: [],
      books: [],
      bookChapters: [],
      onlineSources: [],
      phds: [],
      bibVarias: [],
    },
    personRoles: createPersonRoles(),
    index: undefined,
    language: undefined,
    text: undefined,
    publicComment: undefined,
  })
  editModal.value = true
}

const update = (item, index) => {
  Object.assign(editModel, JSON.parse(JSON.stringify(item)))
  editModel.index = index
  editModal.value = true
}

const del = (item, index) => {
  Object.assign(editModel, JSON.parse(JSON.stringify(item)))
  editModel.index = index
  delModal.value = true
}

const submitEdit = () => {
  editForm.value?.validate()
  if (editForm.value?.errors.length === 0) {
    // Edit existing translation
    if (editModel.index != null) {
      const index = editModel.index
      const modelCopy = JSON.parse(JSON.stringify(editModel))
      delete modelCopy.index

      // Remove null properties
      for (const key of Object.keys(modelCopy)) {
        if (modelCopy[key] == null) {
          delete modelCopy[key]
        }
      }
      props.model.translations[index] = modelCopy
    }
    // Add new translation
    else {
      const modelCopy = JSON.parse(JSON.stringify(editModel))
      delete modelCopy.index
      props.model.translations.push(modelCopy)
    }
    calcChanges()
    emit('validated', 0, null)
    editModal.value = false
  }
}

const submitDelete = () => {
  props.model.translations.splice(editModel.index, 1)
  calcChanges()
  emit('validated', 0, null)
  delModal.value = false
}

const displayTranslations = (translations) => {
  const result = []
  for (const t of translations) {
    result.push(
        t.text +
        '\nLanguage: ' + t.language.name +
        (displayBibliography(t.bibliography).length > 0 ? '\nBibliography:\n' + displayBibliography(t.bibliography).join('\n') : '') +
        (t.personRoles?.translator?.length > 0 ? '\nTranslator(s):\n' + t.personRoles.translator.map(tr => tr.name).join('\n') : '') +
        ((t.publicComment != null && t.publicComment !== '') ? '\nPublic comment:\n' + t.publicComment : '')
    )
  }
  return result
}

const displayBibliography = (bibliography) => {
  const result = []

  for (const bib of bibliography.articles || []) {
    result.push(
        bib.article.name +
        formatPages(bib.startPage, bib.endPage, ': ') +
        '.'
    )
  }
  for (const bib of bibliography.blogPosts || []) {
    result.push(bib.blogPost.name + '.')
  }
  for (const bib of bibliography.books || []) {
    result.push(
        bib.book.name +
        formatPages(bib.startPage, bib.endPage, ': ') +
        '.'
    )
  }
  for (const bib of bibliography.bookChapters || []) {
    result.push(
        bib.bookChapter.name +
        formatPages(bib.startPage, bib.endPage, ': ') +
        '.'
    )
  }
  for (const bib of bibliography.onlineSources || []) {
    result.push(
        bib.onlineSource.url +
        (bib.relUrl == null ? '' : bib.relUrl) +
        '.'
    )
  }
  for (const bib of bibliography.phds || []) {
    result.push(
        bib.phd.name +
        formatPages(bib.startPage, bib.endPage, ': ') +
        '.'
    )
  }
  for (const bib of bibliography.bibVarias || []) {
    result.push(
        bib.bibVaria.name +
        formatPages(bib.startPage, bib.endPage, ': ') +
        '.'
    )
  }

  return result
}

const formatPages = (startPage = null, endPage = null, prefix = '') => {
  if (startPage == null) {
    return ''
  }
  if (endPage == null || startPage === endPage) {
    return prefix + startPage
  }
  return prefix + startPage + '-' + endPage
}

const validated = (isValidValue) => {
  isValid.value = isValidValue
  changes.value = calcChangesHelper(props.model, originalModel.value, fields.value)
  emit('validated', isValidValue, null)
}

// Expose for parent component
defineExpose({
  init,
  reload,
  enableFields,
  disableFields,
  validate,
  isValid,
  changes,
})
</script>