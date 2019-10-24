<template>
    <panel :header="header">
        <div class="pbottom-large">
            <h3>Images</h3>
            <vue-dropzone
                id="dropzone"
                ref="dropzone"
                :options="dropzoneOptions"
                :duplicate-check="true"
                @vdropzone-success="fileAdded"
            />
            <div class="row">
                <div
                    v-for="(image, index) in model.images"
                    :key="image.id"
                    class="col-md-3"
                >
                    <div
                        class="thumbnail"
                        :class="{'bg-warning' : !(image.public), 'spinner-wrapper': !loadedImages.includes(image.id)}"
                    >
                        <div
                            v-if="!loadedImages.includes(image.id)"
                            class="spinner"
                        />
                        <a
                            v-if="!erroredImages.includes(image.id)"
                            :href="urls['image_get'].replace('image_id', image.id)"
                            data-type="image"
                            data-gallery="gallery"
                            data-toggle="lightbox"
                            :data-title="image.filename"
                        >
                            <img
                                v-if="pageLoaded"
                                :src="urls['image_get'].replace('image_id', image.id)"
                                :alt="image.filename"
                                @load="imageLoaded(image.id)"
                                @error="imageErrored(image.id)"
                            >
                        </a>
                        <span
                            v-else
                            class="text-danger"
                        >
                            <i class="fa fa-exclamation-circle"></i>
                            {{ image.filename }}
                        </span>
                        <a
                            v-if="loadedImages.includes(image.id)"
                            class="image-public"
                            @click.prevent="toggleImagePublic(index)"
                        >
                            <i
                                v-if="image.public"
                                class="fa fa-users"
                            />
                            <i
                                v-else
                                class="fa fa-user"
                            />
                        </a>
                        <a
                            v-if="loadedImages.includes(image.id)"
                            class="image-delete"
                            @click.prevent="delImage(index)"
                        >
                            <i class="fa fa-trash-o" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <h3>Image links</h3>
            <table
                v-if="model.imageLinks && model.imageLinks.length > 0"
                class="table table-striped table-bordered table-hover"
            >
                <thead>
                    <tr>
                        <th>Link</th>
                        <th>Public</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(imageLink, index) in model.imageLinks"
                        :key="index"
                    >
                        <td>{{ imageLink.url }}</td>
                        <td>
                            <i
                                v-if="imageLink.public"
                                class="fa fa-check"
                            />
                            <i
                                v-else
                                class="fa fa-times"
                            />
                        </td>
                        <td>
                            <a
                                href="#"
                                title="Edit"
                                class="action"
                                @click.prevent="updateLink(imageLink, index)"
                            >
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                href="#"
                                title="Delete"
                                class="action"
                                @click.prevent="delLink(index)"
                            >
                                <i class="fa fa-trash-o" />
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <btn @click="newLink()"><i class="fa fa-plus" />&nbsp;Add a new image link</btn>
        </div>
        <modal
            v-model="publicImageModal"
            title="Edit image public state"
            auto-focus
        >
            <alert type="alert">
                <p>This will modify the public state of this image in all occurrences. Do you wish to continue?</p>
            </alert>
            <div slot="footer">
                <btn @click="publicImageModal=false">Cancel</btn>
                <btn
                    type="alert"
                    @click="submitToggleImagePublic()"
                >
                    Update
                </btn>
            </div>
        </modal>
        <modal
            v-model="updateLinkModal"
            title="Edit image link"
            size="lg"
            auto-focus
        >
            <alert
                type="warning"
            >
                <p>This will modify the url or public state in image links with this url in all occurrences.</p>
                <p>If you don't want this to happen, create a new image link with a different url.</p>
            </alert>
            <vue-form-generator
                ref="editForm"
                :schema="editSchema"
                :model="editLink"
                :options="formOptions"
                @validated="validated"
            />
            <div slot="footer">
                <btn @click="updateLinkModal=false">Cancel</btn>
                <btn
                    type="alert"
                    @click="submitUpdateLink()"
                >
                    {{ linkIndex > -1 ? 'Update' : 'Add' }}
                </btn>
            </div>
        </modal>
        <modal
            v-model="delImageModal"
            title="Delete image"
            auto-focus
        >
            <p>Are you sure you want to delete this image?</p>
            <div slot="footer">
                <btn @click="delImageModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="submitDeleteImage()"
                >
                    Delete
                </btn>
            </div>
        </modal>
        <modal
            v-model="delLinkModal"
            title="Delete image link"
            auto-focus
        >
            <p>Are you sure you want to delete this image link?</p>
            <div slot="footer">
                <btn @click="delLinkModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="submitDeleteLink()"
                >
                    Delete
                </btn>
            </div>
        </modal>
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import vue2Dropzone from 'vue2-dropzone'

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)
Vue.component('vueDropzone', vue2Dropzone)

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    props: {
        urls: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        return {
            pageLoaded: false,
            loadedImages: [],
            erroredImages: [],
            publicImageModal: false,
            updateLinkModal: false,
            delImageModal: false,
            delLinkModal: false,
            imageIndex: null,
            linkIndex: null,
            dropzoneOptions: {
                url: this.urls['image_post'],
                maxFilesize: 10,
                dictDefaultMessage: "<i class='fa fa-upload'></i> Upload images",
            },
            editLink: {},
            editSchema: {
                fields: {
                    url: {
                        type: 'input',
                        inputType: 'url',
                        label: 'Url',
                        labelClasses: 'control-label',
                        model: 'url',
                        required: true,
                        validator: VueFormGenerator.validators.regexp,
                        pattern: '^https?:\\/\\/(www\\.)?.*$',
                    },
                    public: {
                        type: 'checkbox',
                        styleClasses: 'has-warning',
                        label: 'Public',
                        labelClasses: 'control-label',
                        model: 'public',
                    },
                },
            },
        }
    },
    mounted() {
        // Defer image loading until the application is fully loaded an thus responsive
        let self = this;
        window.addEventListener("load", function() {
            self.pageLoaded = true;
        });
    },
    methods: {
        validate() {},
        calcChanges() {
            this.changes = []
            // images
            if (
                JSON.stringify(this.model.images) !== JSON.stringify(this.originalModel.images)
                && !(this.model.images == null && this.originalModel.images == null)
            ) {
                this.changes.push({
                    'key': 'images',
                    'label': 'Images',
                    'old': this.displayImages(this.originalModel.images),
                    'new': this.displayImages(this.model.images),
                    'value': this.model.images,
                })
            }
            // image links
            if (
                JSON.stringify(this.model.imageLinks) !== JSON.stringify(this.originalModel.imageLinks)
                && !(this.model.imageLinks == null && this.originalModel.imageLinks == null)
            ) {
                this.changes.push({
                    'key': 'imageLinks',
                    'label': 'Image links',
                    'old': this.displayLinks(this.originalModel.imageLinks),
                    'new': this.displayLinks(this.model.imageLinks),
                    'value': this.model.imageLinks,
                })
            }
        },
        toggleImagePublic(index) {
            this.imageIndex = index
            this.publicImageModal = true
        },
        updateLink(link, index) {
            this.linkIndex = index
            this.editLink = JSON.parse(JSON.stringify(link))
            this.updateLinkModal = true
        },
        newLink() {
            this.linkIndex = -1
            this.editLink = {
                url: '',
                public: true,
            }
            this.updateLinkModal = true
        },
        delImage(index) {
            this.imageIndex = index
            this.delImageModal = true
        },
        delLink(index) {
            this.linkIndex = index
            this.delLinkModal = true
        },
        validated(isValid, errors) {
            this.isValid = isValid
        },
        submitToggleImagePublic() {
            this.model.images[this.imageIndex].public = !this.model.images[this.imageIndex].public
            this.calcChanges()
            this.$emit('validated', 0, null, this)
            this.publicImageModal = false
        },
        submitUpdateLink() {
            this.$refs.editForm.validate()
            if (this.$refs.editForm.errors.length == 0) {
                if (this.linkIndex > -1) {
                    // update existing
                    this.model.imageLinks[this.linkIndex] = JSON.parse(JSON.stringify(this.editLink))
                }
                else {
                    // add new
                    this.model.imageLinks.push(JSON.parse(JSON.stringify(this.editLink)))
                }
                this.calcChanges()
                this.$emit('validated', 0, null, this)
                this.updateLinkModal = false
            }
        },
        submitDeleteImage() {
            this.model.images.splice(this.imageIndex, 1)
            this.calcChanges()
            this.$emit('validated', 0, null, this)
            this.delImageModal = false
        },
        submitDeleteLink() {
            this.model.imageLinks.splice(this.linkIndex, 1)
            this.calcChanges()
            this.$emit('validated', 0, null, this)
            this.delLinkModal = false
        },
        fileAdded(file, response) {
            this.model.images.push(response)
            this.$refs.dropzone.removeFile(file)
            this.calcChanges()
            this.$emit('validated', 0, null, this)
        },
        displayImages(images) {
            // Return null if images are undefined (e.g. old values when cloning)
            if (images == null) {
                return [];
            }
            let result = []
            for (let image of images) {
                result.push(image.filename + ' (' + (image.public ? 'Public' : 'Not public') + ')')
            }
            return result
        },
        displayLinks(links) {
            // Return null if links are undefined (e.g. old values when cloning)
            if (links == null) {
                return null;
            }
            let result = []
            for (let link of links) {
                result.push(link.url + ' (' + (link.public ? 'Public' : 'Not public') + ')')
            }
            return result
        },
        imageLoaded(id) {
            this.loadedImages.push(id);
        },
        imageErrored(id) {
            this.loadedImages.push(id);
            this.erroredImages.push(id);
        },
    }
}
</script>
