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

            <BasicBibVaria
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
                v-if="bibVaria"
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
            title="bib varia"
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
            title="bib varia"
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
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import axios from 'axios'

import { getErrorMessage, isLoginError } from '@/helpers/errorUtil'
import Reset from '@/Components/Edit/Modals/Reset.vue'
import Invalid from '@/Components/Edit/Modals/Invalid.vue'
import Save from '@/Components/Edit/Modals/Save.vue'
import Person from "@/Components/Edit/Panels/Person.vue";
import BasicBibVaria from "@/Components/Edit/Panels/BasicBibVaria.vue";
import Identification from "@/Components/Edit/Panels/Identification.vue";
import GeneralBibItem from "@/Components/Edit/Panels/GeneralBibItem.vue";
import Management from "@/Components/Edit/Panels/Management.vue";
import Url from "@/Components/Edit/Panels/Url.vue";
import {usePanelValidation} from "@/composables/editAppComposables/usePanelValidation";
import {useModelDiff} from "@/composables/editAppComposables/useModelDiff";
import {useSaveModel} from "@/composables/editAppComposables/useSaveModel";
import {useStickyNav} from "@/composables/editAppComposables/useStickyNav";
import {disablePanels, enablePanels, updateItems} from "@/helpers/panelUtil";
import Alerts from "@/Components/Alerts.vue";
import panel from "@/Components/Edit/Panel.vue";

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

const basicRef = ref(null)
const urlsRef = ref(null)
const generalRef = ref(null)
const managementsRef = ref(null)
const personsRef = ref(null)
const identificationRef = ref(null)
const anchor = ref(null)

const urls = JSON.parse(props.initUrls)
const data = JSON.parse(props.initData)
const identifiers = JSON.parse(props.initIdentifiers)
const roles = JSON.parse(props.initRoles)

const managements = ref([])
const modernPersons = ref([])

const personRoles = {}
for (let role of roles) {
  personRoles[role.systemName] = []
}

const model = reactive({
  personRoles: personRoles,
  basic: { title: null, year: null, city: null, institution: null },
  urls: { urls: [] },
  identification: {},
  managements: { managements: [] }
})

const panels = ['persons', 'basic', 'urls', 'general', 'managements']

const alerts = ref([])
const bibVaria = ref(null)

const reloads = ref([])
const resetModal = ref(false)
const originalModel = ref({})
const invalidModal = ref(false)

const panelRefs = computed(() => ({
  basic: basicRef.value,
  urls: urlsRef.value,
  general: generalRef.value,
  persons: personsRef.value,
  identification: identificationRef.value,
  managements: managementsRef.value,
}))


identifiers.forEach(id => model.identification[id.systemName] = null)
if (identifiers.length > 0) panels.push('identification')
roles.forEach(role => model.personRoles[role.systemName] = [])

const { invalidPanels, validateForms, checkInvalidPanels } = usePanelValidation(panelRefs, panels)
const { diff, calcDiff } = useModelDiff(panelRefs, panels)
const { saveModal, saveAlerts, openRequests, postUpdatedModel, putUpdatedModel } = useSaveModel(urls)
const { scrollY, isSticky, stickyStyle, initScrollListener } = useStickyNav(anchor)

const setData = () => {
  bibVaria.value = data.bibVaria
  modernPersons.value = []
  managements.value = data.managements
  if (bibVaria.value) {
    roles.forEach(role => {
      model.personRoles[role.systemName] = bibVaria.value.personRoles == null ? [] : bibVaria.value.personRoles[role.systemName]

    })

    Object.assign(model.basic , {
      title: bibVaria.value.title,
      year: bibVaria.value.year,
      city: bibVaria.value.city,
      institution: bibVaria.value.institution
    })

    model.urls.urls = bibVaria.value.urls?.map((url, index) => ({ ...url, tgIndex: index + 1 })) || null

    identifiers.forEach(id => {
      model.identification[id.systemName] = bibVaria.value.identifications?.[id.systemName] || []
    })

    model.general = {
      publicComment: bibVaria.value.publicComment,
      privateComment: bibVaria.value.privateComment
    }

    model.managements.managements = bibVaria.value.managements
  }
}

const validated=()=> {
  checkInvalidPanels()
  calcDiff()
}


const toSave = ()=> {
  const result = {}
  diff.value.forEach(change => {
    if (change.keyGroup) {
      result[change.keyGroup] = result[change.keyGroup] || {}
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
  if (bibVaria.value == null) {
    postUpdatedModel('bib_varia',toSave());
  } else {
    putUpdatedModel('bib_varia',toSave());
  }
}

const saveButton = ()=> {
  validateForms()
  if (invalidPanels.value) {
    invalidModal.value = true
  } else {
    saveModal.value = true
  }
}

const cancelSave=()=> {
  saveModal.value = false
  saveAlerts.value = []
}

const reset = ()=> {
  resetModal.value = false
  Object.assign(model, JSON.parse(JSON.stringify(originalModel.value)))
  nextTick(() => validateForms())
}

const reload=(type, items)=> {
  reloadSimpleItems(type, items)
}

const reloadSimpleItems=(type, items) =>{
  reloadItems(type, [type], [items], urls[type.replace(/([A-Z])/g, '_$1').toLowerCase() + '_get'])
}

const reloadItems =(type, keys, items, url, filters) => {
  disablePanels(panelRefs, panels, keys)
  reloads.value.push(type)
  axios.get(url)
      .then(response => {
        updateItems(items, response.data, filters)
        enablePanels(panelRefs, panels, keys)
        reloads.value.splice(reloads.value.indexOf(type), 1)
      })
      .catch(error => {
        alerts.value.push({ type: 'error', message: 'Something went wrong while loading data.', login: isLoginError(error) })
        console.log(error)
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
  reload('modernPersons',modernPersons.value)

})
</script>
