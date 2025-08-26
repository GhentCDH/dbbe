<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts
          v-for="(item, index) in alerts"
          :key="index"
          :type="item.type"
          dismissible
          @dismissed="alerts.splice(index, 1)"
      >
        {{ item.message }}
      </Alerts>

      <BasicType
          id="basic"
          ref="basicRef"
          header="Basic information"
          :model="model.basic"
          @validated="validated"
      />

      <TypeVerses
          id="verses"
          ref="versesRef"
          header="Verses"
          :model="model.verses"
          :urls="urls"
          @validated="validated"
      />

      <TypeTypes
          id="types"
          ref="typesRef"
          header="Types"
          :links="[{title: 'Types', reload: 'types', edit: urls['types_search']}]"
          :model="model.types"
          :values="types"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <Person
          id="persons"
          ref="personsRef"
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

      <Metre
          id="metres"
          ref="metresRef"
          header="Metres"
          :links="[{title: 'Metres', reload: 'metres', edit: urls['metres_edit']}]"
          :model="model.metres"
          :values="metres"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <Genre
          id="genres"
          ref="genresRef"
          header="Genres"
          :links="[{title: 'Genres', reload: 'genres', edit: urls['genres_edit']}]"
          :model="model.genres"
          :values="genres"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <Subject
          id="subjects"
          ref="subjectsRef"
          header="Subjects"
          :links="[{title: 'Persons', reload: 'historicalPersons', edit: urls['persons_search']}, {title: 'Keywords', reload: 'keywordSubjects', edit: urls['keywords_subject_edit']}]"
          :model="model.subjects"
          :values="subjects"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <Keyword
          id="tags"
          ref="keywordsRef"
          header="Tags"
          :links="[{title: 'Tags', reload: 'typeKeywords', edit: urls['keywords_type_edit']}]"
          :model="model.keywords"
          :values="keywords"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <Identification
          v-if="identifiers.length > 0"
          id="identification"
          ref="identificationRef"
          header="Identification"
          :identifiers="identifiers"
          :model="model.identification"
          @validated="validated"
      />

      <Bibliography
          id="bibliography"
          ref="bibliographyRef"
          header="Bibliography"
          :links="[{title: 'Books', reload: 'books', edit: urls['bibliographies_search']},{title: 'Articles', reload: 'articles', edit: urls['bibliographies_search']},{title: 'Book chapters', reload: 'bookChapters', edit: urls['bibliographies_search']},{title: 'Online sources', reload: 'onlineSources', edit: urls['bibliographies_search']},{title: 'Blog Posts', reload: 'blogPosts', edit: urls['bibliographies_search']},{title: 'Phd theses', reload: 'phds', edit: urls['bibliographies_search']},{title: 'Bib varia', reload: 'bibVarias', edit: urls['bibliographies_search']}]"
          :model="model.bibliography"
          :reference-type="true"
          :values="bibliographies"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <Translation
          id="translations"
          ref="translationsRef"
          header="Translations"
          :model="model.translations"
          :values="translations"
          :reloads="reloads"
          :urls="urls"
          @validated="validated"
          @reload="reload"
      />

      <GeneralType
          id="general"
          ref="generalRef"
          header="General"
          :links="[{title: 'Acknowledgements', reload: 'acknowledgements', edit: urls['acknowledgements_edit']}, {title: 'Statuses', reload: 'statuses', edit: urls['statuses_edit']}, {title: 'Occurrences', reload: 'occurrences', edit: urls['occurrences_search']}]"
          :model="model.general"
          :values="generals"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <Person
          id="contributors"
          ref="contributorsRef"
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

      <Management
          id="managements"
          ref="managementsRef"
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
          @click="resetModal = true"
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
                :class="{'bg-danger': !(basicRef && basicRef.isValid)}"
            >Basic information</a>
          </li>
          <li>
            <a
                href="#verses"
                :class="{'bg-danger': !(versesRef && versesRef.isValid)}"
            >Verses</a>
          </li>
          <li>
            <a
                href="#types"
                :class="{'bg-danger': !(typesRef && typesRef.isValid)}"
            >Types</a>
          </li>
          <li>
            <a
                href="#persons"
                :class="{'bg-danger': !(personsRef && personsRef.isValid)}"
            >Persons</a>
          </li>
          <li>
            <a
                href="#metres"
                :class="{'bg-danger': !(metresRef && metresRef.isValid)}"
            >Metres</a>
          </li>
          <li>
            <a
                href="#genres"
                :class="{'bg-danger': !(genresRef && genresRef.isValid)}"
            >Genres</a>
          </li>
          <li>
            <a
                href="#subjects"
                :class="{'bg-danger': !(subjectsRef && subjectsRef.isValid)}"
            >Subjects</a>
          </li>
          <li>
            <a
                href="#tags"
                :class="{'bg-danger': !(keywordsRef && keywordsRef.isValid)}"
            >Tags</a>
          </li>
          <li v-if="identifiers.length > 0">
            <a
                href="#identification"
                :class="{'bg-danger': !(identificationRef && identificationRef.isValid)}"
            >Identification</a>
          </li>
          <li>
            <a
                href="#bibliography"
                :class="{'bg-danger': !(bibliographyRef && bibliographyRef.isValid)}"
            >Bibliography</a>
          </li>
          <li>
            <a
                href="#translations"
                :class="{'bg-danger': !(translationsRef && translationsRef.isValid)}"
            >Translations</a>
          </li>
          <li>
            <a
                href="#general"
                :class="{'bg-danger': !(generalRef && generalRef.isValid)}"
            >
              General
            </a>
          </li>
          <li>
            <a
                href="#contributors"
                :class="{'bg-danger': !(contributorsRef && contributorsRef.isValid)}"
            >Contributors</a>
          </li>
          <li>
            <a
                href="#managements"
                :class="{'bg-danger': !(managementsRef && managementsRef.isValid)}"
            >Management collections</a>
          </li>
          <li>
            <a href="#actions">Actions</a>
          </li>
        </ul>
      </nav>
    </aside>
    <Reset
        title="type"
        :show="resetModal"
        @cancel="resetModal = false"
        @confirm="reset()"
    />
    <Invalid
        :show="invalidModal"
        @cancel="invalidModal = false"
        @confirm="invalidModal = false"
    />
    <Save
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

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import axios from 'axios'

import { isLoginError } from "@/helpers/errorUtil"
import Reset from "@/components/Edit/Modals/Reset.vue"
import Invalid from "@/components/Edit/Modals/Invalid.vue"
import Save from "@/components/Edit/Modals/Save.vue"
import BasicType from "@/components/Edit/Panels/BasicType.vue"
import TypeVerses from "@/components/Edit/Panels/TypeVerses.vue"
import TypeTypes from "@/components/Edit/Panels/TypeTypes.vue"
import Person from "@/components/Edit/Panels/Person.vue"
import Metre from "@/components/Edit/Panels/Metre.vue"
import Genre from "@/components/Edit/Panels/Genre.vue"
import Subject from "@/components/Edit/Panels/Subject.vue"
import Keyword from "@/components/Edit/Panels/Keyword.vue"
import Identification from "@/components/Edit/Panels/Identification.vue"
import Bibliography from "@/components/Edit/Panels/Bibliography.vue"
import Translation from "@/components/Edit/Panels/Translation.vue"
import GeneralType from "@/components/Edit/Panels/GeneralType.vue"
import Management from "@/components/Edit/Panels/Management.vue"
import Alerts from "@/components/Alerts.vue"
import {usePanelValidation} from "@/composables/editAppComposables/usePanelValidation";
import {useModelDiff} from "@/composables/editAppComposables/useModelDiff";
import {useStickyNav} from "@/composables/editAppComposables/useStickyNav";
import {useSaveModel} from "@/composables/editAppComposables/useSaveModel";

const props = defineProps({
  initUrls: {
    type: String,
    default: '',
  },
  initData: {
    type: String,
    default: '',
  },
  initIdentifiers: {
    type: String,
    default: '',
  },
  initRoles: {
    type: String,
    default: '',
  },
  initContributorRoles: {
    type: String,
    default: '',
  },
  initTranslationRoles: {
    type: String,
    default: '',
  },
})

// Template refs
const basicRef = ref(null)
const versesRef = ref(null)
const typesRef = ref(null)
const personsRef = ref(null)
const metresRef = ref(null)
const genresRef = ref(null)
const subjectsRef = ref(null)
const keywordsRef = ref(null)
const identificationRef = ref(null)
const bibliographyRef = ref(null)
const translationsRef = ref(null)
const generalRef = ref(null)
const contributorsRef = ref(null)
const managementsRef = ref(null)
const anchor = ref(null)

// Parse props
const identifiers = JSON.parse(props.initIdentifiers)
const roles = JSON.parse(props.initRoles)
const contributorRoles = JSON.parse(props.initContributorRoles)
const translationRoles = JSON.parse(props.initTranslationRoles)
const urls = JSON.parse(props.initUrls)
const data = JSON.parse(props.initData)

// Reactive data
const type = ref(null)
const types = ref(null)
const dbbePersons = ref(null)
const historicalPersons = ref(null)
const modernPersons = ref(null)
const metres = ref(null)
const genres = ref(null)
const subjects = ref(null)
const keywords = ref(null)
const bibliographies = ref({
  books: [],
  articles: [],
  bookChapters: [],
  onlineSources: [],
  blogPosts: [],
  phds: [],
  bibVarias: [],
  referenceTypes: [],
})
const translations = ref(null)
const generals = ref(null)
const managements = ref(null)

// Helper functions for creating models
const createPersonRolesModel = () => {
  const personRolesModel = {}
  for (let role of roles) {
    personRolesModel[role.systemName] = []
  }
  return personRolesModel
}

const createContributorRolesModel = () => {
  const contributorRolesModel = {}
  for (let role of contributorRoles) {
    contributorRolesModel[role.systemName] = []
  }
  return contributorRolesModel
}

const createIdentificationModel = () => {
  const identificationModel = {}
  for (let identifier of identifiers) {
    identificationModel[identifier.systemName] = null
  }
  return identificationModel
}

// Reactive model
const model = reactive({
  basic: {
    incipit: null,
    title_GR: null,
    title_LA: null,
  },
  verses: {
    verses: '',
    numberOfVerses: null,
  },
  types: { types: null },
  personRoles: createPersonRolesModel(),
  contributorRoles: createContributorRolesModel(),
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
  identification: createIdentificationModel(),
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
})

// Create panels array
const createPanelsArray = () => {
  const basePanels = [
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
  ]

  if (identifiers.length > 0) {
    const keywordsIndex = basePanels.indexOf('keywords')
    basePanels.splice(keywordsIndex + 1, 0, 'identification')
  }

  return basePanels
}

const panels = createPanelsArray()

const alerts = ref([])
const originalModel = ref({})
const resetModal = ref(false)
const invalidModal = ref(false)
const reloads = ref([])

// Computed for panel refs
const panelRefs = computed(() => ({
  basic: basicRef.value,
  verses: versesRef.value,
  types: typesRef.value,
  persons: personsRef.value,
  metres: metresRef.value,
  genres: genresRef.value,
  subjects: subjectsRef.value,
  keywords: keywordsRef.value,
  identification: identificationRef.value,
  bibliography: bibliographyRef.value,
  translations: translationsRef.value,
  general: generalRef.value,
  contributors: contributorsRef.value,
  managements: managementsRef.value,
}))

const setData = () => {
  type.value = data.type
  types.value = {
    types: [],
    relationTypes: data.typeRelationTypes,
  }
  historicalPersons.value = []
  metres.value = data.metres
  genres.value = data.genres
  subjects.value = {
    historicalPersons: [],
    keywordSubjects: data.subjectKeywords,
  }
  keywords.value = data.typeKeywords
  bibliographies.value = {
    articles: [],
    bookChapters: [],
    onlineSources: [],
    blogPosts: [],
    books: [],
    phds: [],
    bibVarias: [],
    referenceTypes: data.referenceTypes,
  }
  translations.value = {
    languages: data.languages,
    articles: [],
    blogPosts: [],
    books: [],
    bookChapters: [],
    onlineSources: [],
    phds: [],
    bibVarias: [],
    modernPersons: data.modernPersons,
    personRoles: translationRoles,
  }
  generals.value = {
    acknowledgements: data.acknowledgements,
    textStatuses: data.textStatuses,
    criticalStatuses: data.criticalStatuses,
    occurrences: [],
  }
  dbbePersons.value = data.dbbePersons
  managements.value = data.managements

  if (type.value != null) {
    model.basic = {
      incipit: type.value.incipit,
      title_GR: type.value.title_GR,
      title_LA: type.value.title_LA,
    }

    model.verses = {
      verses: type.value.verses,
      numberOfVerses: type.value.numberOfVerses,
    }

    model.types = {
      relatedTypes: type.value.relatedTypes || [],
    }

    for (let role of roles) {
      model.personRoles[role.systemName] = type.value.personRoles == null ? [] : type.value.personRoles[role.systemName]
    }

    model.metres = {
      metres: type.value.metres,
    }

    model.genres = {
      genres: type.value.genres,
    }

    model.subjects = {
      personSubjects: type.value.subjects.persons,
      keywordSubjects: type.value.subjects.keywords,
    }

    model.keywords = {
      keywords: type.value.keywords,
    }

    model.identification = {}
    for (let identifier of identifiers) {
      model.identification[identifier.systemName] = type.value.identifications == null ? [] : type.value.identifications[identifier.systemName]
    }

    model.bibliography = {
      articles: [],
      blogPosts: [],
      books: [],
      bookChapters: [],
      onlineSources: [],
      phds: [],
      bibVarias: [],
    }
    if (type.value.bibliography != null) {
      for (let bib of type.value.bibliography) {
        switch (bib['type']) {
          case 'article':
            model.bibliography.articles.push(bib)
            break
          case 'blogPost':
            model.bibliography.blogPosts.push(bib)
            break
          case 'book':
            model.bibliography.books.push(bib)
            break
          case 'bookChapter':
            model.bibliography.bookChapters.push(bib)
            break
          case 'onlineSource':
            model.bibliography.onlineSources.push(bib)
            break
          case 'phd':
            model.bibliography.phds.push(bib)
            break
          case 'bibVaria':
            model.bibliography.bibVarias.push(bib)
            break
        }
      }
    }

    model.translations = {
      translations: []
    }

    for (let translation of type.value.translations) {
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
      for (const role of translationRoles) {
        modelTranslation.personRoles[role.systemName] = translation.personRoles == null ? [] : translation.personRoles[role.systemName]
      }
      model.translations.translations.push(modelTranslation)
    }

    // General
    model.general = {
      criticalApparatus: type.value.criticalApparatus,
      acknowledgements: type.value.acknowledgements,
      publicComment: type.value.publicComment,
      privateComment: type.value.privateComment,
      textStatus: type.value.textStatus,
      criticalStatus: type.value.criticalStatus,
      basedOn: type.value.basedOn,
      public: type.value.public,
    }

    // ContributorRoles
    for (let role of contributorRoles) {
      model.contributorRoles[role.systemName] = type.value.contributorRoles == null ? [] : type.value.contributorRoles[role.systemName]
    }

    // Management
    model.managements = {
      managements: type.value.managements,
    }
  } else {
    model.general.public = true
    model.types = {
      relatedTypes: [],
    }
  }
}

// Load async data
const loadAsync = () => {
  reload('types', types.value)
  reload('historicalPersons',historicalPersons.validateAfterLoad)
  reload('occurrences')
  reload('books',bibliographies.value)
  reload('articles',bibliographies.value)
  reload('bookChapters',bibliographies.value)
  reload('onlineSources',bibliographies.value)
  reload('blogPosts',bibliographies.value)
  reload('phds',bibliographies.value)
  reload('bibVarias',bibliographies.value)
}

const {
  invalidPanels,
  validateForms,
  checkInvalidPanels,
} = usePanelValidation(panelRefs, panels)

const {
  diff,
  calcDiff,
} = useModelDiff(panelRefs, panels)

const {
  scrollY,
  isSticky,
  stickyStyle,
  initScrollListener,
} = useStickyNav(anchor)

const {
  saveModal,
  saveAlerts,
  openRequests,
  postUpdatedModel,
  putUpdatedModel
} = useSaveModel(urls)

// Methods
const save = () => {
  openRequests.value++
  saveModal.value = false
  if (type.value == null) {
    postUpdatedModel('type', toSave())
  } else {
    putUpdatedModel('type', toSave())

  }
}

const reload = (reloadType,items=[]) => {
  switch (reloadType) {
    case 'types':
      reloadNestedItems(reloadType, types.value)
      break
    case 'historicalPersons':
      reloadItems(
          'historicalPersons',
          ['historicalPersons'],
          [historicalPersons.value, subjects.value.historicalPersons],
          urls['historical_persons_get']
      )
      break
    case 'dbbePersons':
      reloadItems(
          'dbbePersons',
          ['dbbePersons'],
          [dbbePersons.value],
          urls['dbbe_persons_get']
      )
      break
    case 'modernPersons':
      reloadNestedItems(reloadType, [translations.value])
      break
    case 'keywordSubjects':
      reloadItems(
          'keywordSubjects',
          ['keywordSubjects'],
          [subjects.value.keywordSubjects],
          urls['keywords_subject_get']
      )
      break
    case 'typeKeywords':
      reloadItems(
          'keywords',
          ['keywords'],
          [keywords.value],
          urls['keywords_type_get']
      )
      break
    case 'articles':
    case 'blogPosts':
    case 'books':
    case 'bookChapters':
    case 'onlineSources':
    case 'phds':
    case 'bibVarias':
      reloadNestedItems(reloadType, [bibliographies.value, translations.value])
      break
    case 'acknowledgements':
      reloadNestedItems(reloadType, generals.value)
      break
    case 'statuses':
      reloadItems(
          'statuses',
          ['textStatuses', 'criticalStatuses'],
          [generals.value.textStatuses, generals.value.criticalStatuses],
          urls['statuses_get'],
          [(i) => i.type === 'type_text', (i) => i.type === 'type_critical'],
      )
      break
    case 'occurrences':
      reloadNestedItems(reloadType, generals.value)
      break
    default:
      reloadSimpleItems(reloadType, items)
  }
}

const validated = (isValid, errors) => {
  invalidPanels.value = false
  for (let panel of panels) {
    const panelRef = panelRefs.value[panel]
    if (panelRef && !panelRef.isValid) {
      invalidPanels.value = true
      break
    }
  }

  calcDiff()
}


const toSave = () => {
  let result = {}
  for (let diffItem of diff.value) {
    if ('keyGroup' in diffItem) {
      if (!(diffItem.keyGroup in result)) {
        result[diffItem.keyGroup] = {}
      }
      result[diffItem.keyGroup][diffItem.key] = diffItem.value
    } else {
      result[diffItem.key] = diffItem.value
    }
  }
  return result
}

const reset = () => {
  resetModal.value = false
  Object.assign(model, JSON.parse(JSON.stringify(originalModel.value)))
  nextTick(() => {
    validateForms()
  })
}

const saveButton = () => {
  validateForms()
  if (invalidPanels.value) {
    invalidModal.value = true
  } else {
    saveModal.value = true
  }
}

const cancelSave = () => {
  saveModal.value = false
  saveAlerts.value = []
}

const reloadSimpleItems = (reloadType,items) => {
  reloadItems(
      reloadType,
      [reloadType],
      [items], // Note: Using eval as in original - consider a better approach
      urls[reloadType.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get'] // convert camel case to snake case
  )
}

// parent can either be an array of multiple parents or a single parent
const reloadNestedItems = (reloadType, parent) => {
  reloadItems(
      reloadType,
      [reloadType],
      Array.isArray(parent) ? parent.map(p => p[reloadType]) : [parent[reloadType]],
      urls[reloadType.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get'] // convert camel case to snake case
  )
}

const reloadItems = (reloadType, keys, items, url, filters) => {
  // Be careful to mutate the existing array and not create a new one
  for (let panel of panels) {
    const panelRef = panelRefs.value[panel]
    if (panelRef) {
      panelRef.disableFields(keys)
    }
  }
  reloads.value.push(reloadType)
  axios.get(url)
      .then((response) => {
        for (let i = 0; i < items.length; i++) {
          let responseData = []
          if (filters == null || filters[i] == null) {
            // Copy data
            responseData = response.data.filter(() => true)
          } else {
            responseData = response.data.filter(filters[i])
          }
          while (items[i].length) {
            items[i].splice(0, 1)
          }
          while (responseData.length) {
            items[i].push(responseData.shift())
          }
        }
        for (let panel of panels) {
          const panelRef = panelRefs.value[panel]
          if (panelRef) {
            panelRef.enableFields(keys)
          }
        }
        let typeIndex = reloads.value.indexOf(reloadType)
        if (typeIndex > -1) {
          reloads.value.splice(typeIndex, 1)
        }
      })
      .catch((error) => {
        alerts.value.push({
          type: 'error',
          message: 'Something went wrong while loading data.',
          login: isLoginError(error)
        })
        // Note: $notify might need to be replaced with your notification system
        console.log(error)
      })
}

// Lifecycle
onMounted(() => {
  initScrollListener()
  setData()
  originalModel.value = JSON.parse(JSON.stringify(model))

  // Initialize panels after model is updated
  nextTick(() => {
    if (!data.clone) {
      for (let panel of panels) {
        const panelRef = panelRefs.value[panel]
        if (panelRef) {
          panelRef.init()
        }
      }
    }
  })

  // Load some (slower) data asynchronously
  loadAsync()
})
</script>