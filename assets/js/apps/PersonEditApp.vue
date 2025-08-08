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

      <BasicPerson
          id="basic"
          ref="basicRef"
          header="Basic Information"
          :links="[
          {title:'Self designations', reload: 'selfDesignations', edit: urls['self_designations_edit']},
          {title: 'Offices', reload: 'offices', edit: urls['offices_edit']},
          {title: 'Origins', reload: 'origins', edit: urls['origins_edit']},
        ]"
          :model="model.basic"
          :values="{selfDesignations: selfDesignations, offices: offices, origins: origins}"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <Date
          id="dates"
          ref="datesRef"
          header="Dates"
          :model="model.dates"
          :config="{'born': {limit: 1, type: 'date'}, 'died': {limit: 1, type: 'date'}, 'attested': {limit: 0, type: 'dateOrInterval'}}"
          @validated="validated"
      />

      <Identification
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

      <GeneralPerson
          id="general"
          ref="generalRef"
          header="General"
          :links="[{title: 'Acknowledgements', reload: 'acknowledgements', edit: urls['acknowledgements_edit']}]"
          :model="model.general"
          :values="generals"
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
          @click="resetModal=true"
      >
        Reset
      </btn>
      <btn
          v-if="person"
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
                :class="{'bg-danger': !(panelRefs.basic && panelRefs.basic.isValid)}"
            >Basic Information</a>
          </li>
          <li>
            <a
                href="#dates"
                :class="{'bg-danger': !(panelRefs.dates && panelRefs.dates.isValid)}"
            >Dates</a>
          </li>
          <li>
            <a
                href="#identification"
                :class="{'bg-danger': !(panelRefs.identification && panelRefs.identification.isValid)}"
            >Identification</a>
          </li>
          <li>
            <a
                href="#bibliography"
                :class="{'bg-danger': !(panelRefs.bibliography && panelRefs.bibliography.isValid)}"
            >Bibliography</a>
          </li>
          <li>
            <a
                href="#general"
                :class="{'bg-danger': !(panelRefs.general && panelRefs.general.isValid)}"
            >General</a>
          </li>
          <li>
            <a
                href="#managements"
                :class="{'bg-danger': !(panelRefs.managements && panelRefs.managements.isValid)}"
            >Management collections</a>
          </li>
          <li><a href="#actions">Actions</a></li>
        </ul>
      </nav>
    </aside>
    <Reset
        title="person"
        :show="resetModal"
        @cancel="resetModal=false"
        @confirm="reset()"
    />
    <Invalid
        :show="invalidModal"
        @cancel="invalidModal=false"
        @confirm="invalidModal=false"
    />
    <Save
        title="person"
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
import Reset from "@/Components/Edit/Modals/Reset.vue"
import Invalid from "@/Components/Edit/Modals/Invalid.vue"
import Save from "@/Components/Edit/Modals/Save.vue"
import BasicPerson from "@/Components/Edit/Panels/BasicPerson.vue"
import Date from "@/Components/Edit/Panels/Date.vue"
import Identification from "@/Components/Edit/Panels/Identification.vue"
import Bibliography from "@/Components/Edit/Panels/Bibliography.vue"
import GeneralPerson from "@/Components/Edit/Panels/GeneralPerson.vue"
import Management from "@/Components/Edit/Panels/Management.vue"
import Alerts from "@/Components/Alerts.vue"
import { disablePanels, enablePanels, updateItems } from "@/helpers/panelUtil"
import { useErrorAlert } from "@/composables/useErrorAlert"
import { usePanelValidation } from "@/composables/usePanelValidation"
import { useModelDiff } from "@/composables/useModelDiff"
import { useStickyNav } from "@/composables/useStickyNav"
import { useSaveModel } from "@/composables/useSaveModel"

// Props
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

// Refs for panels
const basicRef = ref(null)
const datesRef = ref(null)
const identificationRef = ref(null)
const bibliographyRef = ref(null)
const generalRef = ref(null)
const managementsRef = ref(null)
const anchor = ref(null)

// Parse initial data
const urls = JSON.parse(props.initUrls)
const data = JSON.parse(props.initData)
const identifiers = JSON.parse(props.initIdentifiers)

// Reactive data
const person = ref(null)
const offices = ref(null)
const origins = ref(null)
const selfDesignations = ref(null)
const bibliographies = ref({
  articles: [],
  blogPosts: [],
  books: [],
  bookChapters: [],
  onlineSources: [],
  phds: [],
  bibVarias: [],
})
const managements = ref(null)
const generals = ref(null)

// Create identification model based on identifiers
const createIdentificationModel = () => {
  const identificationModel = {}
  for (let identifier of identifiers) {
    identificationModel[identifier.systemName] = null
  }
  return identificationModel
}

const model = reactive({
  basic: {
    firstName: null,
    lastName: null,
    selfDesignations: [],
    offices: [],
    origin: null,
    extra: null,
    unprocessed: null,
    historical: null,
    modern: null,
    dbbe: null,
  },
  dates: [],
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
    public: null,
  },
  managements: {
    managements: [],
  },
})

const panels = [
  'basic',
  'dates',
  'identification',
  'bibliography',
  'general',
  'managements',
]

const alerts = ref([])
const originalModel = ref({})
const resetModal = ref(false)
const invalidModal = ref(false)
const reloads = ref([])

const handleError = useErrorAlert(alerts)

const panelRefs = computed(() => ({
  basic: basicRef.value,
  dates: datesRef.value,
  identification: identificationRef.value,
  bibliography: bibliographyRef.value,
  general: generalRef.value,
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

const setData = () => {
  person.value = data.person
  offices.value = data.offices
  origins.value = data.origins
  selfDesignations.value = data.selfDesignations
  managements.value = data.managements
  generals.value = {
    acknowledgements: data.acknowledgements,
  }

  if (person.value != null) {
    // Basic info
    model.basic = {
      firstName: person.value.firstName,
      lastName: person.value.lastName,
      selfDesignations: person.value.selfDesignations,
      offices: person.value.officesWithParents,
      origin: person.value.origin,
      extra: person.value.extra,
      unprocessed: person.value.unprocessed,
      historical: person.value.historical,
      modern: person.value.modern,
      dbbe: person.value.dbbe,
    }

    // Dates
    model.dates = person.value.dates

    // Identification
    model.identification = {}
    for (let identifier of identifiers) {
      model.identification[identifier.systemName] = person.value.identifications == null ? [] : person.value.identifications[identifier.systemName]
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
    if (person.value.bibliography != null) {
      for (let bib of person.value.bibliography) {
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

    // General
    model.general = {
      acknowledgements: person.value.acknowledgements,
      publicComment: person.value.publicComment,
      privateComment: person.value.privateComment,
      public: person.value.public,
    }

    // Management
    model.managements = {
      managements: person.value.managements,
    }
  } else {
    model.general.public = true
  }
}

const loadAsync = () => {
  reload('books')
  reload('articles')
  reload('bookChapters')
  reload('onlineSources')
  reload('blogPosts')
  reload('phds')
  reload('bibVarias')
}

const save = () => {
  openRequests.value++
  saveModal.value = false
  if (person.value == null) {
    postUpdatedModel('person', toSave())
  } else {
    putUpdatedModel('person', toSave())
  }
}

const reload = (type) => {
  switch (type) {
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
    default:
      reloadSimpleItems(type)
  }
}

const validated = (isValid, errors) => {
  checkInvalidPanels()
  calcDiff(panelRefs, panels)
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

const reloadSimpleItems = (type) => {
  reloadItems(
      type,
      [type],
      [data[type]],
      urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get'] // convert camel case to snake case
  )
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