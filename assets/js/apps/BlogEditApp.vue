<template>
    <div>
        <article class="col-sm-9 mbottom-large">
            <alert
                v-for="(item, index) in alerts"
                :key="index"
                :type="item.type"
                dismissible
                @dismissed="alerts.splice(index, 1)"
            >
                {{ item.message }}
            </alert>

            <basicBlogPanel
                id="basic"
                ref="basic"
                header="Basic Information"
                :model="model.basic"
                @validated="validated"
            />

            <urlPanel
                id="urls"
                ref="urls"
                header="Additional urls"
                :model="model.urls"
                @validated="validated"
            />

            <generalBibItemPanel
                id="general"
                ref="general"
                header="General"
                :model="model.general"
                @validated="validated"
            />

            <managementPanel
                id="managements"
                ref="managements"
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
        <resetModal
            title="blog"
            :show="resetModal"
            @cancel="resetModal=false"
            @confirm="reset()"
        />
        <invalidModal
            :show="invalidModal"
            @cancel="invalidModal=false"
            @confirm="invalidModal=false"
        />
        <saveModal
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

<script>
import Vue from 'vue/dist/vue.js';

import AbstractEntityEdit from '@/mixins/AbstractEntityEdit'
import axios from 'axios'
import {getErrorMessage, isLoginError} from "@/helpers/errorUtil";
import Reset from "@/Components/Edit/Modals/Reset.vue";
import Invalid from "@/Components/Edit/Modals/Invalid.vue";
import Save from "@/Components/Edit/Modals/Save.vue";

const panelComponents = import.meta.glob('../Components/Edit/Panels/{Person,BasicBlog,Url,GeneralBibItem,Management}.vue', { eager: true })

for (const path in panelComponents) {
  const component = panelComponents[path].default
  const compName = path
      .split('/')
      .pop()
      .replace(/\.vue$/, '')

  const globalName = compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel'
  Vue.component(globalName, component)
}
export default {
    mixins: [ AbstractEntityEdit ],
  components: {
    resetModal: Reset,
    invalidModal: Invalid,
    saveModal: Save
  },
    data() {
        return {
            blog: null,
            modernPersons: null,
            model: {
                basic: {
                    url: null,
                    title: null,
                    lastAccessed: null,
                },
                urls: {urls: []},
                managements: {
                    managements: [],
                },
            },
            panels: [
                'basic',
                'urls',
                'general',
                'managements',
            ],
        }
    },
    created () {
        this.blog = this.data.blog;

        this.managements = this.data.managements;
    },
    methods: {
        setData() {
            if (this.blog != null) {
                // Basic info
                this.model.basic = {
                    url: this.blog.url,
                    title: this.blog.title,
                    lastAccessed: this.blog.lastAccessed,
                }

                // Urls
                this.model.urls = {
                    urls: this.blog.urls == null ? null : this.blog.urls.map(
                        function(url, index) {
                            url.tgIndex = index + 1
                            return url
                        }
                    )
                }

                // General
                this.model.general = {
                    publicComment: this.blog.publicComment,
                    privateComment: this.blog.privateComment,
                }

                // Management
                this.model.managements = {
                    managements: this.blog.managements,
                }
            }
        },
        save() {
            this.openRequests++
            this.saveModal = false
            if (this.blog == null) {
                axios.post(this.urls['blog_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['blog_get'].replace('blog_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the blog data.', extra: getErrorMessage(error), login: isLoginError(error)})
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['blog_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['blog_get']
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the blog data.', extra: getErrorMessage(error), login: isLoginError(error)})
                        this.openRequests--
                    })
            }
        },
        reload(type) {
            this.reloadSimpleItems(type);
        },
    }
}
</script>
