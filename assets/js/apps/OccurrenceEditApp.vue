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

      <OccurrenceVerses
          id="verses"
          ref="versesRef"
          header="Verses"
          :model="model.verses"
          :urls="urls"
          @validated="validated"
      />

      <BasicOccurrence
          id="basic"
          ref="basicRef"
          header="Basic information"
          :links="[{title: 'Manuscripts', reload: 'manuscripts', edit: urls['manuscripts_search']}]"
          :model="model.basic"
          :values="manuscripts"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <OccurrenceTypes
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

      <Date
          id="dates"
          ref="datesRef"
          header="Dates"
          :model="model.dates"
          :config="{'completed at': {limit: 1, type: 'date'}}"
          @validated="validated"
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

      <Identification
          v-if="identifiers.length > 0"
          id="identification"
          ref="identificationRef"
          header="Identification"
          :identifiers="identifiers"
          :model="model.identification"
          @validated="validated"
      />

      <ImagePanel
          id="images"
          ref="imagesRef"
          header="Images"
          :model="model.images"
          :urls="urls"
          @validated="validated"
      />

      <Bibliography
          id="bibliography"
          ref="bibliographyRef"
          header="Bibliography"
          :links="[
          {title: 'Books', reload: 'books', edit: urls['bibliographies_search']},
          {title: 'Articles', reload: 'articles', edit: urls['bibliographies_search']},
          {title: 'Book chapters', reload: 'bookChapters', edit: urls['bibliographies_search']},
          {title: 'Online sources', reload: 'onlineSources', edit: urls['bibliographies_search']},
          {title: 'Blog Posts', reload: 'blogPosts', edit: urls['bibliographies_search']},
          {title: 'PhD theses', reload: 'phds', edit: urls['bibliographies_search']},
          {title: 'Bib varia', reload: 'bibVarias', edit: urls['bibliographies_search']}
        ]"
          :model="model.bibliography"
          :reference-type="true"
          :image="true"
          :values="bibliographies"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <GeneralOccurrence
          id="general"
          ref="generalRef"
          header="General"
          :links="[{title: 'Acknowledgements', reload: 'acknowledgements', edit: urls['acknowledgements_edit']}, {title: 'Statuses', reload: 'statuses', edit: urls['statuses_edit']}]"
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
          :disabled="data.clone ? JSON.stringify(originalModel) !== JSON.stringify(model) : diff.length === 0"
          @click="resetModal = true"
      >
        Reset
      </btn>
      <btn
          v-if="occurrence"
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
                href="#verses"
                :class="{'bg-danger': !(versesRef && versesRef.isValid)}"
            >Verses</a>
          </li>
          <li>
            <a
                href="#basic"
                :class="{'bg-danger': !(basicRef && basicRef.isValid)}"
            >Basic information</a>
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
                href="#dates"
                :class="{'bg-danger': !(datesRef && datesRef.isValid)}"
            >Dates</a>
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
          <li v-if="identifiers.length > 0">
            <a
                href="#identification"
                :class="{'bg-danger': !(identificationRef && identificationRef.isValid)}"
            >Identification</a>
          </li>
          <li>
            <a
                href="#images"
                :class="{'bg-danger': !(imagesRef && imagesRef.isValid)}"
            >Images</a>
          </li>
          <li>
            <a
                href="#bibliography"
                :class="{'bg-danger': !(bibliographyRef && bibliographyRef.isValid)}"
            >Bibliography</a>
          </li>
          <li>
            <a
                href="#general"
                :class="{'bg-danger': !(generalRef && generalRef.isValid)}"
            >General</a>
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
          <li><a href="#actions">Actions</a></li>
        </ul>
      </nav>
    </aside>
    <Reset
        title="occurrence"
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
        title="occurrence"
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
import { ref, reactive, onMounted, nextTick, computed } from 'vue'
import axios from 'axios'
import Reset from "@/components/Edit/Modals/Reset.vue"
import Invalid from "@/components/Edit/Modals/Invalid.vue"
import Save from "@/components/Edit/Modals/Save.vue"
import OccurrenceVerses from "@/components/Edit/Panels/OccurrenceVerses.vue"
import BasicOccurrence from "@/components/Edit/Panels/BasicOccurrence.vue"
import OccurrenceTypes from "@/components/Edit/Panels/OccurrenceTypes.vue"
import Person from "@/components/Edit/Panels/Person.vue"
import Date from "@/components/Edit/Panels/Date.vue"
import Metre from "@/components/Edit/Panels/Metre.vue"
import Genre from "@/components/Edit/Panels/Genre.vue"
import Subject from "@/components/Edit/Panels/Subject.vue"
import Identification from "@/components/Edit/Panels/Identification.vue"
import ImagePanel from "@/components/Edit/Panels/Image.vue"
import Bibliography from "@/components/Edit/Panels/Bibliography.vue"
import GeneralOccurrence from "@/components/Edit/Panels/GeneralOccurrence.vue"
import Management from "@/components/Edit/Panels/Management.vue"
import Alerts from "@/components/Alerts.vue"
import { disablePanels, enablePanels, updateItems } from "@/helpers/panelUtil"
import { useErrorAlert } from "@/composables/useErrorAlert"
import { usePanelValidation } from "@/composables/editAppComposables/usePanelValidation"
import { useModelDiff } from "@/composables/editAppComposables/useModelDiff"
import { useStickyNav } from "@/composables/editAppComposables/useStickyNav"
import { useSaveModel } from "@/composables/editAppComposables/useSaveModel"

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
})

const versesRef = ref(null)
const basicRef = ref(null)
const typesRef = ref(null)
const personsRef = ref(null)
const datesRef = ref(null)
const metresRef = ref(null)
const genresRef = ref(null)
const subjectsRef = ref(null)
const identificationRef = ref(null)
const imagesRef = ref(null)
const bibliographyRef = ref(null)
const generalRef = ref(null)
const contributorsRef = ref(null)
const managementsRef = ref(null)
const anchor = ref(null)

const identifiers = JSON.parse(props.initIdentifiers)
const roles = JSON.parse(props.initRoles)
const contributorRoles = JSON.parse(props.initContributorRoles)
const urls = JSON.parse(props.initUrls)
const data = JSON.parse(props.initData)

const occurrence = ref(null)
const manuscripts = ref([])
const types = ref([])
const historicalPersons = ref([])
const metres = ref(null)
const genres = ref(null)
const subjects = ref({
  historicalPersons: [],
  keywordSubjects: [],
})
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
const generals = ref(null)
const dbbePersons = ref([])
const managements = ref([])

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

const model = reactive({
  verses: {
    incipit: null,
    title: null,
    verses: [],
    numberOfVerses: null,
  },
  basic: {
    manuscript: null,
    foliumStart: null,
    foliumStartRecto: null,
    foliumEnd: null,
    foliumEndRecto: null,
    unsure: null,
    pageStart: null,
    pageEnd: null,
    generalLocation: null,
    oldLocation: null,
    alternativeFoliumStart: null,
    alternativeFoliumStartRecto: null,
    alternativeFoliumEnd: null,
    alternativeFoliumEndRecto: null,
    alternativePageStart: null,
    alternativePageEnd: null,
  },
  types: {
    types: [],
  },
  personRoles: createPersonRolesModel(),
  contributorRoles: createContributorRolesModel(),
  dates: [],
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
  identification: createIdentificationModel(),
  images: {
    images: [],
    imageLinks: [],
  },
  bibliography: {
    articles: [],
    blogPosts: [],
    books: [],
    bookChapters: [],
    onlineSources: [],
    phds: [],
    bibVarias: [],
  },
  general: {
    palaeographicalInfo: null,
    contextualInfo: null,
    acknowledgements: [],
    publicComment: null,
    privateComment: null,
    textStatus: null,
    recordStatus: null,
    dividedStatus: null,
    sourceStatus: null,
    public: null,
  },
  managements: {
    managements: [],
  },
})

const createPanelsArray = () => {
  const basePanels = [
    'verses',
    'basic',
    'types',
    'persons',
    'dates',
    'metres',
    'genres',
    'subjects',
    'images',
    'bibliography',
    'general',
    'contributors',
    'managements',
  ]

  if (identifiers.length > 0) {
    const imageIndex = basePanels.indexOf('images')
    basePanels.splice(imageIndex, 0, 'identification')
  }

  return basePanels
}

const panels = createPanelsArray()

const alerts = ref([])
const originalModel = ref({})
const resetModal = ref(false)
const invalidModal = ref(false)
const reloads = ref([])

const handleError = useErrorAlert(alerts)

const panelRefs = computed(() => {
  const refs = {
    verses: versesRef.value,
    basic: basicRef.value,
    types: typesRef.value,
    persons: personsRef.value,
    dates: datesRef.value,
    metres: metresRef.value,
    genres: genresRef.value,
    subjects: subjectsRef.value,
    images: imagesRef.value,
    bibliography: bibliographyRef.value,
    general: generalRef.value,
    contributors: contributorsRef.value,
    managements: managementsRef.value,
  }

  if (identifiers.length > 0) {
    refs.identification = identificationRef.value
  }

  return refs
})

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

const setData = () => {
  types.value = []
  manuscripts.value = []
  occurrence.value = data.occurrence
  metres.value = data.metres
  genres.value = data.genres
  subjects.value = {
    historicalPersons: [],
    keywordSubjects: data.keywords,
  }
  bibliographies.value = {
    books: [],
    articles: [],
    bookChapters: [],
    onlineSources: [],
    blogPosts: [],
    phds: [],
    bibVarias: [],
    referenceTypes: data.referenceTypes,
  }
  generals.value = {
    acknowledgements: data.acknowledgements,
    textStatuses: data.textStatuses,
    recordStatuses: data.recordStatuses,
    dividedStatuses: data.dividedStatuses,
    sourceStatuses: data.sourceStatuses,
  }
  dbbePersons.value = data.dbbePersons
  managements.value = data.managements

  if (occurrence.value != null) {
    model.verses = {
      incipit: occurrence.value.incipit,
      title: occurrence.value.title,
      verses: occurrence.value.verses != null ? occurrence.value.verses : [],
      numberOfVerses: occurrence.value.numberOfVerses,
    }
    Object.assign(model.basic, {
      manuscript: occurrence.value.manuscript,
      foliumStart: occurrence.value.foliumStart,
      foliumStartRecto: occurrence.value.foliumStartRecto,
      foliumEnd: occurrence.value.foliumEnd,
      foliumEndRecto: occurrence.value.foliumEndRecto,
      unsure: occurrence.value.unsure,
      pageStart: occurrence.value.pageStart,
      pageEnd: occurrence.value.pageEnd,
      generalLocation: occurrence.value.generalLocation,
      oldLocation: occurrence.value.oldLocation,
      alternativeFoliumStart: occurrence.value.alternativeFoliumStart,
      alternativeFoliumStartRecto: occurrence.value.alternativeFoliumStartRecto,
      alternativeFoliumEnd: occurrence.value.alternativeFoliumEnd,
      alternativeFoliumEndRecto: occurrence.value.alternativeFoliumEndRecto,
      alternativePageStart: occurrence.value.alternativePageStart,
      alternativePageEnd: occurrence.value.alternativePageEnd,
    })

    model.types = {
      types: occurrence.value.types,
    }

    for (let role of roles) {
      model.personRoles[role.systemName] = occurrence.value.personRoles == null ? [] : occurrence.value.personRoles[role.systemName]
    }

    if (occurrence.value.dates != null) {
      model.dates = occurrence.value.dates
    }

    model.metres = {
      metres: occurrence.value.metres,
    }

    model.genres = {
      genres: occurrence.value.genres,
    }

    model.subjects = {
      personSubjects: occurrence.value.subjects.persons,
      keywordSubjects: occurrence.value.subjects.keywords,
    }

    model.identification = {}
    for (let identifier of identifiers) {
      model.identification[identifier.systemName] = occurrence.value.identifications == null ? [] : occurrence.value.identifications[identifier.systemName]
    }

    model.images = {
      images: occurrence.value.images.images,
      imageLinks: occurrence.value.images.imageLinks,
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
    if (occurrence.value.bibliography != null) {
      for (let bib of occurrence.value.bibliography) {
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

    model.general = {
      palaeographicalInfo: occurrence.value.palaeographicalInfo,
      contextualInfo: occurrence.value.contextualInfo,
      acknowledgements: occurrence.value.acknowledgements,
      publicComment: occurrence.value.publicComment,
      privateComment: occurrence.value.privateComment,
      textStatus: occurrence.value.textStatus,
      recordStatus: occurrence.value.recordStatus,
      dividedStatus: occurrence.value.dividedStatus,
      sourceStatus: occurrence.value.sourceStatus,
      public: occurrence.value.public,
    }

    for (let role of contributorRoles) {
      model.contributorRoles[role.systemName] = occurrence.value.contributorRoles == null ? [] : occurrence.value.contributorRoles[role.systemName]
    }

    model.managements = {
      managements: occurrence.value.managements,
    }
  } else {
    model.general.public = true
    model.verses = {
      verses: [],
    }
  }
}

const loadAsync = () => {
  reload('manuscripts', manuscripts.value)
  reload('types', types.value)
  reload('historicalPersons', historicalPersons.value)
  reload('books',bibliographies.value)
  reload('articles',bibliographies.value)
  reload('bookChapters',bibliographies.value)
  reload('onlineSources',bibliographies.value)
  reload('blogPosts',bibliographies.value)
  reload('phds',bibliographies.value)
  reload('bibVarias',bibliographies.value)
}

const save = () => {
  openRequests.value++
  saveModal.value = false
  if (occurrence.value == null || data.clone) {
    postUpdatedModel('occurrence', toSave())
  } else {
    putUpdatedModel('occurrence', toSave())
  }
}

const reload = (type, items=[]) => {
  const simpleRefMappings = {
    manuscripts: [manuscripts.value],
    types: [types.value],
    metres: [metres.value],
    genres: [genres.value],
    managements: [managements.value],
    dbbePersons: [dbbePersons.value]
  }

  if (simpleRefMappings[type]) {
    reloadItems(
        type,
        [type],
        simpleRefMappings[type],
        urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get']
    )
    return
  }

  switch (type) {
    case 'historicalPersons':
      reloadItems(
          'historicalPersons',
          ['historicalPersons'],
          [historicalPersons.value, subjects.value.historicalPersons],
          urls['historical_persons_get']
      )
      break
    case 'keywordSubjects':
      reloadItems(
          'keywordSubjects',
          ['keywordSubjects'],
          [subjects.value.keywordSubjects],
          urls['keywords_subject_get']
      )
      break
    case 'articles':
    case 'blogPosts':
    case 'books':
    case 'bookChapters':
    case 'onlineSources':
    case 'phds':
    case 'bibVarias':
      reloadNestedItems(type, bibliographies.value)
      break
    case 'acknowledgements':
      reloadNestedItems(type, generals.value)
      break
    case 'statuses':
      reloadItems(
          'statuses',
          ['textStatuses', 'recordStatuses', 'dividedStatuses', 'sourceStatuses'],
          [generals.value.textStatuses, generals.value.recordStatuses, generals.value.dividedStatuses, generals.value.sourceStatuses],
          urls['statuses_get'],
          [(i) => i.type === 'occurrence_text', (i) => i.type === 'occurrence_record', (i) => i.type === 'occurrence_divided', (i) => i.type === 'occurrence_source']
      )
      break
    default:
      reloadItems(
          type,
          [type],
          [items],
          urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get']
      )
  }
}
const validated = (isValid, errors) => {
  checkInvalidPanels()
  calcDiff()
}

const toSave = () => {
  let result = {}

  if (data.clone) {
    const clone = {
      ...model.verses,
      ...model.basic,
      ...model.types,
      ...model.general,
      ...model.managements,
      ...model.dates && { dates: model.dates },
      ...model.metres && { metres: model.metres.metres },
      ...model.genres && { genres: model.genres.genres },
      ...model.personRoles,
      ...model.contributorRoles,
      ...model.subjects,
      ...model.images,
      ...model.identification,
      bibliography: model.bibliography || {
        books: [],
        articles: [],
        bookChapters: [],
        onlineSources: [],
        blogPosts: [],
        phds: [],
        bibVarias: []
      }
    };
    return clone
  }

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

const reloadNestedItems = (type, parent) => {
      reloadItems(
      type,
      [type],
      Array.isArray(parent) ? parent.map(p => p[type]) : [parent[type]],
      urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get'] // convert camel case to snake case
  )
}

const reloadItems = (type, keys, items, url, filters) => {
  disablePanels(panelRefs, panels, keys)
  reloads.value.push(type)
  axios.get(url)
      .then((response) => {
        updateItems(items, response.data, filters)
        enablePanels(panelRefs, panels, keys)
        let typeIndex = reloads.value.indexOf(type)
        if (typeIndex > -1) {
          reloads.value.splice(typeIndex, 1)
        }
      })
      .catch(handleError('Something went wrong while loading data.'))
}

onMounted(() => {
  initScrollListener()
  setData()
  originalModel.value = JSON.parse(JSON.stringify(model))
  nextTick(() => {
      for (let panel of panels) {
        const panelRef = panelRefs.value[panel]
        if (panelRef) {
          panelRef.init()
      }
    }
  })

  loadAsync()
})
</script>