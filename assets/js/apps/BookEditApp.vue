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
            <Person
                id="persons"
                ref="personsRef"
                header="Persons"
                :links="[{title: 'Persons', reload: 'modernPersons', edit: urls['persons_search']}]"
                :roles="roles"
                :model="model.personRoles"
                :values="modernPersons"
                :keys="{modernPersons: {init: false}}"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <BasicBook
                id="basic"
                ref="basicRef"
                header="Basic Information"
                :links="[{title: 'Book clusters', reload: 'bookClusters', edit: urls['book_clusters_edit']}, {title: 'Book series', reload: 'bookSeriess', edit: urls['book_seriess_edit']}]"
                :model="model.basic"
                :values="clustersAndSeries"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <Url
                id="urls"
                ref="urlsRef"
                header="Urls"
                :model="model.urls"
                @validated="validated"
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

            <GeneralBibItem
                id="general"
                ref="generalRef"
                header="General"
                :model="model.general"
                @validated="validated"
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
                v-if="book"
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
                            href="#persons"
                            :class="{'bg-danger': !($refs.persons && $refs.persons.isValid)}"
                        >Persons</a>
                    </li>
                    <li>
                        <a
                            href="#basic"
                            :class="{'bg-danger': !($refs.basic && $refs.basic.isValid)}"
                        >Basic information</a>
                    </li>
                    <li>
                        <a
                            href="#urls"
                            :class="{'bg-danger': !($refs.urls && $refs.urls.isValid)}"
                        >Urls</a>
                    </li>
                    <li v-if="identifiers.length > 0">
                        <a
                            href="#identification"
                            :class="{'bg-danger': !($refs.identification && $refs.identification.isValid)}"
                        >Identification</a>
                    </li>
                    <li>
                        <a
                            href="#general"
                            :class="{'bg-danger': !($refs.general && $refs.general.isValid)}"
                        >General</a>
                    </li>
                    <li>
                        <a
                            href="#managements"
                            :class="{'bg-danger': !($refs.managements && $refs.managements.isValid)}"
                        >Management collections</a>
                    </li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <Reset
            title="book"
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
            title="book"
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

import Reset from '@/Components/Edit/Modals/Reset.vue'
import Invalid from '@/Components/Edit/Modals/Invalid.vue'
import Save from '@/Components/Edit/Modals/Save.vue'

import { getErrorMessage, isLoginError } from '@/helpers/errorUtil'
import { disablePanels, enablePanels, updateItems } from '@/helpers/panelUtil'
import { usePanelValidation } from '@/composables/usePanelValidation'
import { useModelDiff } from '@/composables/useModelDiff'
import { useStickyNav } from '@/composables/useStickyNav'
import { useSaveModel } from '@/composables/useSaveModel'
import Person from "@/Components/Edit/Panels/Person.vue";
import BasicBook from "@/Components/Edit/Panels/BasicBook.vue";
import Url from "@/Components/Edit/Panels/Url.vue";
import Identification from "@/Components/Edit/Panels/Identification.vue";
import GeneralBibItem from "@/Components/Edit/Panels/GeneralBibItem.vue";
import Management from "@/Components/Edit/Panels/Management.vue";
import Alerts from "@/Components/Alerts.vue";
const props = defineProps({
  initUrls: {
    type: String,
    default: '',
  },
  initData: {
    type: String,
    default: '',
  },
  initRoles: {
    type: String,
    default: '',
  },
  initIdentifiers: {
    type: String,
    default: '',
  },
})
// Refs for panels
const personsRef = ref(null)
const basicRef = ref(null)
const urlsRef = ref(null)
const generalRef = ref(null)
const identificationRef = ref(null)
const managementsRef = ref(null)
const anchor = ref(null)

// Props data
const urls = JSON.parse(props.initUrls)
const data = JSON.parse(props.initData)
const identifiers = JSON.parse(props.initIdentifiers)
const roles = JSON.parse(props.initRoles)

// State
const book = ref(null)
const modernPersons = ref([])
const clustersAndSeries = reactive({ bookClusters: [], bookSeriess: [] })
const managements = ref([])

const personRoles = {}
roles.forEach(role => personRoles[role.systemName] = [])

const model = reactive({
  personRoles: personRoles,
  basic: {
    bookCluster: null,
    volume: null,
    totalVolumes: null,
    title: null,
    year: null,
    forthcoming: null,
    city: null,
    editor: null,
    publisher: null,
    bookSeries: null,
    seriesVolume: null,
  },
  urls: { urls: [] },
  identification: {},
  general: {},
  managements: { managements: [] },
})


// UI State
const alerts = ref([])
const resetModal = ref(false)
const invalidModal = ref(false)
const reloads = ref([])
const originalModel = ref({})

const panelRefs = computed(() => ({
  persons: personsRef.value,
  basic: basicRef.value,
  urls: urlsRef.value,
  general: generalRef.value,
  identification: identificationRef.value,
  managements: managementsRef.value,
}))

identifiers.forEach(identifier => {
  model.identification[identifier.systemName] = null
})

const panels = ['persons', 'basic', 'urls', 'general', 'managements']
if (identifiers.length > 0) {
  panels.push('identification')
}


// Hooks
const { invalidPanels, validateForms, checkInvalidPanels } = usePanelValidation(panelRefs, panels)
const { diff, calcDiff } = useModelDiff(panelRefs, panels)
const { saveModal, saveAlerts, openRequests, postUpdatedModel, putUpdatedModel } = useSaveModel(urls)
const { scrollY, isSticky, stickyStyle, initScrollListener } = useStickyNav(anchor)

// Set initial data
const setData = () => {
  book.value = data.book
  modernPersons.value = []
  clustersAndSeries.bookClusters = []
  clustersAndSeries.bookSeriess = []
  managements.value = data.managements

  if (book.value !== null) {
    roles.forEach(role => {
      model.personRoles[role.systemName] = book.value.personRoles == null ? [] : book.value.personRoles[role.systemName]

    })

    Object.assign(model.basic, {
      bookCluster: book.value.bookCluster,
      volume: book.value.volume,
      totalVolumes: book.value.totalVolumes,
      title: book.value.title,
      year: book.value.year,
      forthcoming: book.value.forthcoming,
      city: book.value.city,
      editor: book.value.editor,
      publisher: book.value.publisher,
      bookSeries: book.value.bookSeries,
      seriesVolume: book.value.seriesVolume,
    })

    model.urls.urls = book.value.urls?.map((url, i) => ({ ...url, tgIndex: i + 1 })) ?? []

    identifiers.forEach(identifier => {
      model.identification[identifier.systemName] = book.value.identifications?.[identifier.systemName] ?? []
    })

    model.general = {
      publicComment: book.value.publicComment,
      privateComment: book.value.privateComment,
    }

    model.managements.managements = book.value.managements
  }
}

const toSave = () => {
  const result = {}
  diff.value.forEach(change => {
    if ('keyGroup' in change) {
      result[change.keyGroup] ||= {}
      result[change.keyGroup][change.key] = change.value
    } else {
      result[change.key] = change.value
    }
  })
  return result
}

const save = () => {
  openRequests.value++
  saveModal.value = false
  if (!book.value) {
    postUpdatedModel('book', toSave())
  } else {
    putUpdatedModel('book', toSave())
  }
}

const validated = () => {
  checkInvalidPanels()
  calcDiff()
}

const reset = () => {
  resetModal.value = false
  Object.assign(model, JSON.parse(JSON.stringify(originalModel.value)))
  nextTick(() => validateForms())
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

const reload = (type, items) => {
  if (['bookClusters', 'bookSeriess'].includes(type)) {
    reloadNestedItems(type, clustersAndSeries)
  } else {
    reloadSimpleItems(type, items)
  }
}

const reloadSimpleItems = (type, items) => {
  const url = urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get']
  reloadItems(type, [type], [items], url)
}

const reloadNestedItems = (type, parent) => {
  const items = Array.isArray(parent) ? parent.map(p => p[type]) : [parent[type]]
  const url = urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get']
  reloadItems(type, [type], items, url)
}

const reloadItems = (type, keys, items, url, filters = null) => {
  disablePanels(panelRefs, panels, keys)
  reloads.value.push(type)

  axios.get(url)
      .then(res => {
        updateItems(items, res.data, filters)
        enablePanels(panelRefs, panels, keys)
        const index = reloads.value.indexOf(type)
        if (index > -1) reloads.value.splice(index, 1)
      })
      .catch(err => {
        alerts.value.push({ type: 'error', message: 'Something went wrong while loading data.', login: isLoginError(err) })
        console.error(err)
      })
}

onMounted(() => {
  initScrollListener()
  setData()
  originalModel.value = JSON.parse(JSON.stringify(model))

  nextTick(() => {
    if (!data.clone) {
      for (const panel of panels) {
        panelRefs.value[panel]?.init?.()
      }
    }
  })

  reload('modernPersons', modernPersons.value)
  reload('bookClusters', clustersAndSeries.bookClusters)
  reload('bookSeriess', clustersAndSeries.bookSeriess)
})
</script>
