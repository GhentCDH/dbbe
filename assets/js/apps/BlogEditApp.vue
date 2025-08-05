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

      <BasicBlog
          id="basic"
          ref="basic"
          header="Basic Information"
          :model="model.basic"
          @validated="validated"
      />

      <Url
          id="urls"
          ref="urls_ref"
          header="Additional urls"
          :model="model.urls"
          @validated="validated"
      />

      <GeneralBibItem
          id="general"
          ref="general"
          header="General"
          :model="model.general"
          @validated="validated"
      />

      <Management
          id="managements"
          ref="managements_ref"
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
          v-if="blog"
          type="success"
          :disabled="(diff.length === 0)"
          @click="saveAllChanges()"
      >
        Save changes
      </btn>
      <btn
          v-else
          type="success"
          :disabled="(diff.length === 0)"
          @click="saveAllChanges()"
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
                :class="{'bg-danger': !($refs.basic && $refs.basic.isValid)}"
            >Basic information</a>
          </li>
          <li>
            <a
                href="#urls"
                :class="{'bg-danger': !($refs.urls && $refs.urls.isValid)}"
            >Urls</a>
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
        title="blog"
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
        title="blog"
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
import { ref, reactive, onMounted, nextTick, watch} from 'vue'
import axios from 'axios'
import { getErrorMessage, isLoginError } from "@/helpers/errorUtil"
import Reset from "@/Components/Edit/Modals/Reset.vue"
import Invalid from "@/Components/Edit/Modals/Invalid.vue"
import Save from "@/Components/Edit/Modals/Save.vue"
import { useEntityEdit } from '@/composables/useEntityEdit'
import BasicBlog from "@/Components/Edit/Panels/BasicBlog.vue";
import Url from "@/Components/Edit/Panels/Url.vue";
import GeneralBibItem from "@/Components/Edit/Panels/GeneralBibItem.vue";
import Alerts from "@/Components/Alerts.vue";
import Management from "@/Components/Edit/Panels/Management.vue";

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

// Use the entity edit composable
const {
  urls,
  data,
  openRequests,
  alerts,
  saveAlerts,
  originalModel,
  diff,
  resetModal,
  invalidModal,
  calcDiff,
  saveModal,
  invalidPanels,
  scrollY,
  isSticky,
  stickyStyle,
  reloads,
  initScroll,
  loadAsync,
  toSave,
  validateForms,
  reset,
  saveButton,
  cancelSave,
  reloadSimpleItems
  } = useEntityEdit(props)

const blog = ref(null)
const modernPersons = ref(null)
const managements = ref(null)

const model = reactive({
  basic: {
    url: null,
    title: null,
    lastAccessed: null,
  },
  urls: { urls: [] },
  managements: {
    managements: [],
  },
})

const activePanels = ref([
  'basic',
  'urls',
  'general',
  'managements',
])

// Template refs
const basic = ref(null)
const urls_ref = ref(null)
const general = ref(null)
const managements_ref = ref(null)
const anchor = ref(null)

const setData = () => {
  if (blog.value != null) {
    model.basic = {
      url: blog.value.url,
      title: blog.value.title,
      lastAccessed: blog.value.lastAccessed,
    }

    model.urls = {
      urls: blog.value.urls == null ? null : blog.value.urls.map(
          function(url, index) {
            url.tgIndex = index + 1
            return url
          }
      )
    }

    model.general = {
      publicComment: blog.value.publicComment,
      privateComment: blog.value.privateComment,
    }

    model.managements = {
      managements: blog.value.managements,
    }
  }
}

const save = () => {
  openRequests.value++
  saveModal.value = false
  if (blog.value == null) {
    axios.post(urls.value['blog_post'], toSave())
        .then((response) => {
          window.onbeforeunload = function () {}
          window.location = urls.value['blog_get'].replace('blog_id', response.data.id)
        })
        .catch((error) => {
          console.log(error)
          saveModal.value = true
          saveAlerts.value.push({
            type: 'error',
            message: 'Something went wrong while saving the blog data.',
            extra: getErrorMessage(error),
            login: isLoginError(error)
          })
          openRequests.value--
        })
  } else {
    axios.put(urls.value['blog_put'], toSave())
        .then((response) => {
          window.onbeforeunload = function () {}
          window.location = urls.value['blog_get']
        })
        .catch((error) => {
          console.log(error)
          saveModal.value = true
          saveAlerts.value.push({
            type: 'error',
            message: 'Something went wrong while saving the blog data.',
            extra: getErrorMessage(error),
            login: isLoginError(error)
          })
          openRequests.value--
        })
  }
}

const reload = (type) => {
  reloadSimpleItems(type)
}

onMounted(() => {
  blog.value = data.value.blog
  managements.value = data.value.managements

  initScroll(anchor)
  setData()
  originalModel.value = JSON.parse(JSON.stringify(model))

  nextTick(() => {
    if (!data.value.clone) {
      const refs = {
        basic: basic.value,
        urls: urls_ref.value,
        general: general.value,
        managements: managements_ref.value
      }

      for (let panel of activePanels.value) {
        if (refs[panel] && refs[panel].init) {
          refs[panel].init()
        }
      }}
  })

  loadAsync()
})

watch(scrollY, () => {
  if (anchor.value) {
    let anchorRect = anchor.value.getBoundingClientRect()
    if (anchorRect.top < 30) {
      isSticky.value = true
      stickyStyle.value = {
        width: anchorRect.width + 'px',
      }
    } else {
      isSticky.value = false
      stickyStyle.value = {}
    }
  }
})

const validated = (isValid, errors) => {
  invalidPanels.value = false
  const refs = {
    basic: basic.value,
    urls: urls_ref.value,
    general: general.value,
    managements: managements_ref.value
  }

  for (let panel of activePanels.value) {
    if (refs[panel] && !refs[panel].isValid) {
      invalidPanels.value = true
      break
    }
  }

  calcDiff(refs, activePanels.value)
}


const saveAllChanges = () => {
  const refs = {
    basic: basic.value,
    urls: urls_ref.value,
    general: general.value,
    managements: managements_ref.value
  }
  saveButton(refs, activePanels.value)
}


</script>