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

            <BasicPhd
                id="basic"
                ref="basicRef"
                header="Basic Information"
                :model="model.basic"
                @validated="validated"
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
                v-if="phd"
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
                            :class="{'bg-danger': !(panelRefs.persons && panelRefs.persons.isValid)}"
                        >Persons</a>
                    </li>
                    <li>
                        <a
                            href="#basic"
                            :class="{'bg-danger': !(panelRefs.basic && panelRefs.basic.isValid)}"
                        >Basic information</a>
                    </li>
                    <li>
                        <a
                            href="#urls"
                            :class="{'bg-danger': !(panelRefs.urls && panelRefs.urls.isValid)}"
                        >Urls</a>
                    </li>
                    <li v-if="identifiers.length > 0">
                        <a
                            href="#identification"
                            :class="{'bg-danger': !(panelRefs.identification && panelRefs.identification.isValid)}"
                        >Identification</a>
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
            title="PhD thesis"
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
            title="PhD thesis"
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

import Reset from "@/Components/Edit/Modals/Reset.vue"
import Invalid from "@/Components/Edit/Modals/Invalid.vue"
import Save from "@/Components/Edit/Modals/Save.vue"
import Person from "@/Components/Edit/Panels/Person.vue"
import BasicPhd from "@/Components/Edit/Panels/BasicPhd.vue"
import Url from "@/Components/Edit/Panels/Url.vue"
import Identification from "@/Components/Edit/Panels/Identification.vue"
import GeneralBibItem from "@/Components/Edit/Panels/GeneralBibItem.vue"
import Management from "@/Components/Edit/Panels/Management.vue"
import Alerts from "@/Components/Alerts.vue"

import { useStickyNav } from '@/composables/useStickyNav'
import { useModelDiff } from '@/composables/useModelDiff'
import { usePanelValidation } from '@/composables/usePanelValidation'
import { useSaveModel } from '@/composables/useSaveModel'
import { updateItems, disablePanels, enablePanels } from '@/helpers/panelUtil'
import { useErrorAlert } from '@/composables/useErrorAlert'

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

const personsRef = ref(null)
const basicRef = ref(null)
const urlsRef = ref(null)
const identificationRef = ref(null)
const generalRef = ref(null)
const managementsRef = ref(null)
const anchor = ref(null)

const urls = JSON.parse(props.initUrls)
const data = JSON.parse(props.initData)
const roles = JSON.parse(props.initRoles)
const identifiers = JSON.parse(props.initIdentifiers)

const phd = ref(data.phd)
const modernPersons = ref([])
const managements = ref([])

const personRoles = {}
for (let role of roles) {
  personRoles[role.systemName] = []
}

const model = reactive({
  personRoles: personRoles,
  basic: {
    title: null,
    year: null,
    forthcoming: null,
    city: null,
    institution: null,
    volume: null,
  },
  urls: { urls: [] },
  identification: {},
  general: {
    publicComment: null,
    privateComment: null,
  },
  managements: {
    managements: [],
  }
})

identifiers.forEach(identifier => model.identification[identifier.systemName] = null)

const panels = [
  'persons',
  'basic',
  'urls',
  'general',
  'managements',
  ...(identifiers.length > 0 ? ['identification'] : [])
]

const alerts = ref([])
const originalModel = ref({})
const reloads = ref([])
const resetModal = ref(false)
const invalidModal = ref(false)

const panelRefs = computed(() => ({
  persons: personsRef.value,
  basic: basicRef.value,
  urls: urlsRef.value,
  identification: identificationRef.value,
  general: generalRef.value,
  managements: managementsRef.value,
}))


const { invalidPanels, validateForms, checkInvalidPanels } = usePanelValidation(panelRefs, panels)

const { diff, calcDiff } = useModelDiff(panelRefs, panels)

const { isSticky, stickyStyle, initScrollListener } = useStickyNav(anchor)
const {
  saveModal,
  saveAlerts,
  openRequests,
  postUpdatedModel,
  putUpdatedModel
} = useSaveModel(urls)


const setData = () => {
  managements.value=data.managements
  modernPersons.value=[]
  if (!phd.value) return
  model.basic = { ...phd.value }
  model.urls.urls = phd.value.urls?.map((url, i) => ({ ...url, tgIndex: i + 1 })) ?? []
  model.identification = {}
  identifiers.forEach(identifier => {
    model.identification[identifier.systemName] = phd.value.identifications?.[identifier.systemName] ?? []
  })
  model.general = {
    publicComment: phd.value.publicComment,
    privateComment: phd.value.privateComment,
  }
  model.managements = { managements: phd.value.managements }
  roles.forEach(role => {
    model.personRoles[role.systemName] = phd.value.personRoles == null? [] : phd.value.personRoles[role.systemName]
  })

}

const reset = () => {
  resetModal.value = false
  Object.assign(model, JSON.parse(JSON.stringify(originalModel.value)))
  nextTick(validateForms)
}

const save = () => {
  openRequests.value++
  saveModal.value = false
  const data = toSave()
  if (!phd.value) {
    postUpdatedModel('phd', data)
  } else {
    putUpdatedModel('phd', data)
  }
}

const validated = () => {
  checkInvalidPanels()
  calcDiff(panelRefs, panels)
}

const toSave = () => {
  const result = {}
  for (const change of diff.value) {
    if (change.keyGroup) {
      result[change.keyGroup] ??= {}
      result[change.keyGroup][change.key] = change.value
    } else {
      result[change.key] = change.value
    }
  }
  return result
}

const saveButton = () => {
  validateForms()
  invalidPanels.value ? invalidModal.value = true : saveModal.value = true
}

const cancelSave = () => {
  saveModal.value = false
  saveAlerts.value = []
}

const handleError = useErrorAlert(alerts)

const reload = (type, items) => {
  reloadItems(type, [type], [items], urls[type.replace(/([A-Z])/g, '_$1').toLowerCase() + '_get'])
}

const reloadItems = (type, keys, items, url, filters) => {
  disablePanels(panelRefs, panels, keys)
  reloads.value.push(type)
  axios.get(url).then(response => {
    updateItems(items, response.data, filters)
    enablePanels(panelRefs, panels, keys)
    reloads.value = reloads.value.filter(r => r !== type)
  }).catch(handleError('Something went wrong while loading data.'))
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
  reload('modernPersons',modernPersons.value)
})
</script>