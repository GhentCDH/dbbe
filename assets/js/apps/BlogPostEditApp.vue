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

            <personPanel
                id="persons"
                ref="persons"
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

            <basicBlogPostPanel
                id="basic"
                ref="basic"
                header="Basic Information"
                :links="[{title: 'Blogs', reload: 'blogs', edit: urls['bibliographies_search']}]"
                :model="model.basic"
                :values="blogs"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
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
                v-if="blogPost"
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
            title="blog post"
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
            title="blog post"
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
import Vue from 'vue'

import AbstractEntityEdit from '../Components/Edit/AbstractEntityEdit'

const panelComponents = require.context('../Components/Edit/Panels', false, /[/](?:Person|BasicBlogPost|Url|GeneralBibItem|Management)[.]vue$/)

for(let key of panelComponents.keys()) {
    let compName = key.replace(/^\.\//, '').replace(/\.vue/, '')
    Vue.component(compName.charAt(0).toLowerCase() + compName.slice(1) + 'Panel', panelComponents(key).default)
}

export default {
    mixins: [ AbstractEntityEdit ],
    data() {
        let data = {
            roles: JSON.parse(this.initRoles),
            blogPost: null,
            modernPersons: null,
            blogs: null,
            model: {
                personRoles: {},
                basic: {
                    blog: null,
                    url: null,
                    title: null,
                    postDate: null,
                },
                urls: {urls: []},
                managements: {
                    managements: [],
                },
            },
            panels: [
                'persons',
                'basic',
                'urls',
                'general',
                'managements',
            ],
        }
        for (let role of data.roles) {
            // Make author a required field
            if (role.systemName == 'author') {
                role.required = true
            }
            data.model.personRoles[role.systemName] = [];
        }
        return data
    },
    created () {
        this.blogPost = this.data.blogPost;

        this.modernPersons = [];
        this.blogs = [];
        this.managements = this.data.managements;
    },
    methods: {
        loadAsync() {
            this.reload('modernPersons');
            this.reload('blogs');
        },
        setData() {
            if (this.blogPost != null) {
                // PersonRoles
                for (let role of this.roles) {
                    this.model.personRoles[role.systemName] = this.blogPost.personRoles == null ? [] : this.blogPost.personRoles[role.systemName];
                }

                // Basic info
                this.model.basic = {
                    blog: this.blogPost.blog,
                    url: this.blogPost.url,
                    title: this.blogPost.title,
                    postDate: this.blogPost.postDate,
                }

                // Urls
                this.model.urls = {
                    urls: this.blogPost.urls == null ? null : this.blogPost.urls.map(
                        function(url, index) {
                            url.tgIndex = index + 1
                            return url
                        }
                    )
                }

                // General
                this.model.general = {
                    publicComment: this.blogPost.publicComment,
                    privateComment: this.blogPost.privateComment,
                }

                // Management
                this.model.managements = {
                    managements: this.blogPost.managements,
                }
            }
        },
        save() {
            this.openRequests++
            this.saveModal = false
            if (this.blogPost == null) {
                axios.post(this.urls['blog_post_post'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['blog_post_get'].replace('blog_post_id', response.data.id)
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the blog post data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)})
                        this.openRequests--
                    })
            }
            else {
                axios.put(this.urls['blog_post_put'], this.toSave())
                    .then( (response) => {
                        window.onbeforeunload = function () {}
                        // redirect to the detail page
                        window.location = this.urls['blog_post_get']
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.saveModal = true
                        this.saveAlerts.push({type: 'error', message: 'Something went wrong while saving the blog post data.', extra: this.getErrorMessage(error), login: this.isLoginError(error)})
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
