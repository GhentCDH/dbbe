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

      <LocatedAtPanel
          id="location"
          ref="locationRef"
          header="Location"
          :links="[{title: 'Locations', reload: 'locations', edit: urls['locations_edit']}]"
          :model="model.locatedAt"
          :values="locations"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <ContentPanel
          id="contents"
          ref="contentsRef"
          header="Contents"
          :links="[{title: 'Contents', reload: 'contents', edit: urls['contents_edit']}]"
          :model="model.contents"
          :values="contents"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <PersonPanel
          id="persons"
          ref="personsRef"
          header="Persons"
          :links="[{title: 'Persons', reload: 'historicalPersons', edit: urls['persons_search']}]"
          :roles="roles"
          :model="model.personRoles"
          :values="historicalPersons"
          :occurrence-person-roles="manuscript ? manuscript.occurrencePersonRoles : {}"
          :keys="{historicalPersons: {init: false}}"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <DatePanel
          id="dates"
          ref="datesRef"
          header="Dates"
          :model="model.dates"
          :config="{'completed at': {limit: 1, type: 'date'}}"
          @validated="validated"
      />

      <OriginPanel
          id="origin"
          ref="originRef"
          header="Origin"
          :links="[{title: 'Origins', reload: 'origins', edit: urls['origins_edit']}]"
          :model="model.origin"
          :values="origins"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <OccurrenceOrderPanel
          id="occurrenceOrder"
          ref="occurrenceOrderRef"
          header="Occurrence Order"
          :model="model.occurrenceOrder"
          @validated="validated"
      />

      <IdentificationPanel
          id="identification"
          ref="identificationRef"
          header="Identification"
          :identifiers="identifiers"
          :model="model.identification"
          @validated="validated"
      />

      <BibliographyPanel
          id="bibliography"
          ref="bibliographyRef"
          header="Bibliography"
          :links="[
            {title: 'Books', reload: 'books', edit: urls['bibliographies_search']},
            {title: 'Articles', reload: 'articles', edit: urls['bibliographies_search']},
            {title: 'Book chapters', reload: 'bookChapters', edit: urls['bibliographies_search']},
            {title: 'Online sources', reload: 'onlineSources', edit: urls['bibliographies_search']},
            {title: 'Blog Posts', reload: 'blogPosts', edit: urls['bibliographies_search']},
            {title: 'Phd theses', reload: 'phds', edit: urls['bibliographies_search']},
            {title: 'Bib varia', reload: 'bibVarias', edit: urls['bibliographies_search']}
          ]"
          :model="model.bibliography"
          :values="bibliographies"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <GeneralManuscriptPanel
          id="general"
          ref="generalRef"
          header="General"
          :links="[
            {title: 'Acknowledgements', reload: 'acknowledgements', edit: urls['acknowledgements_edit']},
            {title: 'Statuses', reload: 'statuses', edit: urls['statuses_edit']}
          ]"
          :model="model.general"
          :values="generals"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <PersonPanel
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

      <ManagementPanel
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
          @click.native="resetModal = true"
      >
        Reset
      </btn>
      <btn
          v-if="manuscript"
          type="success"
          :disabled="(diff.length === 0)"
          @click.native="saveButton()"
      >
        Save changes
      </btn>
      <btn
          v-else
          type="success"
          :disabled="(diff.length === 0)"
          @click.native="saveButton()"
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
                href="#location"
                :class="{'bg-danger': !(locationRef && locationRef.isValid)}"
            >Location</a>
          </li>
          <li>
            <a
                href="#contents"
                :class="{'bg-danger': !(contentsRef && contentsRef.isValid)}"
            >Contents</a>
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
                href="#origin"
                :class="{'bg-danger': !(originRef && originRef.isValid)}"
            >Origin</a>
          </li>
          <li>
            <a
                href="#occurrenceOrder"
                :class="{'bg-danger': !(occurrenceOrderRef && occurrenceOrderRef.isValid)}"
            >Occurrence Order</a>
          </li>
          <li>
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
        title="manuscript"
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
        title="manuscript"
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
import LocatedAtPanel from "@/components/Edit/Panels/LocatedAt.vue"
import ContentPanel from "@/components/Edit/Panels/Content.vue"
import PersonPanel from "@/components/Edit/Panels/Person.vue"
import DatePanel from "@/components/Edit/Panels/Date.vue"
import OriginPanel from "@/components/Edit/Panels/Origin.vue"
import OccurrenceOrderPanel from "@/components/Edit/Panels/OccurrenceOrder.vue"
import IdentificationPanel from "@/components/Edit/Panels/Identification.vue"
import BibliographyPanel from "@/components/Edit/Panels/Bibliography.vue"
import GeneralManuscriptPanel from "@/components/Edit/Panels/GeneralManuscript.vue"
import ManagementPanel from "@/components/Edit/Panels/Management.vue"
import Alerts from "@/components/Alerts.vue"
import { disablePanels, enablePanels, updateItems } from "@/helpers/panelUtil"
import { useErrorAlert } from "@/composables/useErrorAlert"
import { usePanelValidation } from "@/composables/editAppComposables/usePanelValidation"
import { useModelDiff } from "@/composables/editAppComposables/useModelDiff"
import { useStickyNav } from "@/composables/editAppComposables/useStickyNav"
import { useSaveModel } from "@/composables/editAppComposables/useSaveModel"
import { isLoginError } from "@/helpers/errorUtil"

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

// Template refs
const locationRef = ref(null)
const contentsRef = ref(null)
const personsRef = ref(null)
const datesRef = ref(null)
const originRef = ref(null)
const occurrenceOrderRef = ref(null)
const identificationRef = ref(null)
const bibliographyRef = ref(null)
const generalRef = ref(null)
const contributorsRef = ref(null)
const managementsRef = ref(null)
const anchor = ref(null)

// Parse initial props
const identifiers = JSON.parse(props.initIdentifiers)
const roles = JSON.parse(props.initRoles)
const contributorRoles = JSON.parse(props.initContributorRoles)
const urls = JSON.parse(props.initUrls)
const data = JSON.parse(props.initData)

// Data refs
const manuscript = ref(null)
const locations = ref([])
const contents = ref(null)
const historicalPersons = ref([])
const origins = ref(null)
const bibliographies = ref({
  articles: [],
  blogPosts: [],
  books: [],
  bookChapters: [],
  onlineSources: [],
  phds: [],
  bibVarias: [],
})
const generals = ref(null)
const dbbePersons = ref([])
const managements = ref([])

// Helper functions for model creation
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
  locatedAt: {
    location: {
      id: null,
      regionWithParents: null,
      institution: null,
      collection: null,
    },
    shelf: null,
    extra: null,
  },
  contents: {
    contents: [],
  },
  personRoles: createPersonRolesModel(),
  contributorRoles: createContributorRolesModel(),
  dates: [],
  origin: { origin: null },
  occurrenceOrder: { occurrenceOrder: [] },
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
  general: {
    acknowledgements: [],
    publicComment: null,
    privateComment: null,
    illustrated: null,
    status: null,
    public: null,
  },
  managements: {
    managements: [],
  },
})

const panels = [
  'location',
  'contents',
  'persons',
  'dates',
  'origin',
  'occurrenceOrder',
  'identification',
  'bibliography',
  'general',
  'contributors',
  'managements',
]

// State refs
const alerts = ref([])
const originalModel = ref({})
const resetModal = ref(false)
const invalidModal = ref(false)
const reloads = ref([])

// Composables
const handleError = useErrorAlert(alerts)

const panelRefs = computed(() => ({
  location: locationRef.value,
  contents: contentsRef.value,
  persons: personsRef.value,
  dates: datesRef.value,
  origin: originRef.value,
  occurrenceOrder: occurrenceOrderRef.value,
  identification: identificationRef.value,
  bibliography: bibliographyRef.value,
  general: generalRef.value,
  contributors: contributorsRef.value,
  managements: managementsRef.value,
}))


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
const setData = () => {
  manuscript.value = data.manuscript
  locations.value = []
  contents.value = data.contents
  historicalPersons.value = []
  origins.value = data.origins
  bibliographies.value = {
    articles: [],
    blogPosts: [],
    books: [],
    bookChapters: [],
    onlineSources: [],
    phds: [],
    bibVarias: [],
  }
  generals.value = {
    acknowledgements: data.acknowledgements,
    statuses: data.statuses,
  }
  dbbePersons.value = data.dbbePersons
  managements.value = data.managements

  if (manuscript.value != null) {
    // Located At
    model.locatedAt = manuscript.value.locatedAt

    // Contents
    model.contents = {
      contents: manuscript.value.contents,
    }

    // PersonRoles
    for (let role of roles) {
      model.personRoles[role.systemName] = manuscript.value.personRoles == null ? [] : manuscript.value.personRoles[role.systemName]
    }

    // Dates
    if (manuscript.value.dates != null) {
      model.dates = manuscript.value.dates
    }

    // Origin
    model.origin = {
      origin: manuscript.value.origin,
    }

    // Occurrence order
    model.occurrenceOrder = {
      occurrenceOrder: manuscript.value.occurrences,
    }

    // Bibliography
    model.bibliography = {
      articles: [],
      blogPosts: [],
      books: [],
      bookChapters: [],
      onlineSources: [],
      phds: [],
      bibVarias: [],
    }
    if (manuscript.value.bibliography != null) {
      for (let bib of manuscript.value.bibliography) {
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

    // Identification
    model.identification = {}
    for (let identifier of identifiers) {
      model.identification[identifier.systemName] = manuscript.value.identifications == null ? [] : manuscript.value.identifications[identifier.systemName]
    }

    // General
    model.general = {
      acknowledgements: manuscript.value.acknowledgements,
      publicComment: manuscript.value.publicComment,
      privateComment: manuscript.value.privateComment,
      status: manuscript.value.status,
      illustrated: manuscript.value.illustrated,
      public: manuscript.value.public,
    }

    // ContributorRoles
    for (let role of contributorRoles) {
      model.contributorRoles[role.systemName] = manuscript.value.contributorRoles == null ? [] : manuscript.value.contributorRoles[role.systemName]
    }

    // Management
    model.managements = {
      managements: manuscript.value.managements,
    }
  } else {
    // Set defaults
    model.general.illustrated = false
    model.general.public = true
  }
}

const loadAsync = () => {
  reload('locations',locations.value)
  reload('historicalPersons',historicalPersons.value)
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
  if (manuscript.value == null) {
    postUpdatedModel('manuscript', toSave())
  } else {
    putUpdatedModel('manuscript', toSave())
  }
}

const reload = (type, items) => {
  const bibliographyTypes = ['articles', 'blogPosts', 'books', 'bookChapters', 'onlineSources', 'phds', 'bibVarias']
  const generalTypes = ['acknowledgements', 'statuses']

  if (bibliographyTypes.includes(type)) {
    reloadNestedItems(type, bibliographies.value)
    return
  }

  if (generalTypes.includes(type)) {
    reloadNestedItems(type, generals.value)
    return
  }

  if (items === undefined) {
    const itemsMap = {
      origins: origins.value,
      locations: locations.value,
      historicalPersons: historicalPersons.value,
      dbbePersons: dbbePersons.value,
      managements: managements.value,
      contents: contents.value,
    }

    items = itemsMap[type]
    if (items === undefined) {
      console.warn(`No items reference found for type: ${type}`)
      return
    }
  }

  reloadSimpleItems(type, items)
}

const validated = (isValid, errors) => {
  checkInvalidPanels()
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

const reloadSimpleItems = (type,items) => {
  if (items) {
    reloadItems(
        type,
        [type],
        [items],
        urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get'] // convert camel case to snake case
    )
  }
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
      .catch(handleError('Something went wrong while loading the data'))
}

// Lifecycle
onMounted(() => {
  initScrollListener()
  setData()
  originalModel.value = JSON.parse(JSON.stringify(model))

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

  loadAsync()
})
</script>