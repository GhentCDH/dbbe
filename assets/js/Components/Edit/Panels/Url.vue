<template>
    <panel :header="header">
        <draggable
            v-if="model.urls && model.urls.length"
            v-model="model.urls"
            @change="onOrderChange"
        >
            <transition-group name="draggable">
                <div
                    v-for="(url, index) in model.urls"
                    :key="url.tgIndex"
                    class="panel panel-default draggable-item"
                >
                    <div class="panel-body row">
                        <div class="col-xs-1">
                            <i class="fa fa-arrows draggable-icon" />
                        </div>
                        <div class="col-xs-9">
                            <strong>Url</strong> {{ url.url }}
                            <br />
                            <strong>Title</strong> {{ url.title }}
                        </div>
                        <div class="col-xs-2 text-right">
                            <a
                                href="#"
                                title="Edit"
                                class="action"
                                @click.prevent="edit(index)"
                            >
                                <i class="fa fa-pencil-square-o" />
                            </a>
                            <a
                                href="#"
                                title="Delete"
                                class="action"
                                @click.prevent="del(index)"
                            >
                                <i class="fa fa-trash-o" />
                            </a>
                        </div>
                    </div>
                </div>
            </transition-group>
        </draggable>
        <btn @click="add()"><i class="fa fa-plus" />&nbsp;Add a url</btn>
        <modal append-to-body
            v-model="editModal"
            size="lg"
            auto-focus
            :backdrop="false"
        >
            <div class="pbottom-default">
                <vue-form-generator
                    ref="editForm"
                    :schema="editSchema"
                    :model="editModel"
                    :options="formOptions"
                />
            </div>
            <div slot="header">
                <h4 class="modal-title">
                    <template v-if="editModel.index">
                        Edit url
                    </template>
                    <template v-else>
                        Add url
                    </template>
                </h4>
            </div>
            <div slot="footer">
                <btn @click="editModal=false">Cancel</btn>
                <btn
                    type="success"
                    :disabled="!isValid"
                    @click="submit()"
                >
                    {{ editModel.index != null ? 'Update' : 'Add' }}
                </btn>
            </div>
        </modal>
        <modal append-to-body
            v-model="delModal"
            title="Delete url"
            auto-focus
        >
            <p>Are you sure you want to delete this url?</p>
            <div slot="footer">
                <btn @click="delModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="submitDelete()"
                >
                    Delete
                </btn>
            </div>
        </modal>
    </panel>
</template>
<script>
import Vue from 'vue'
import draggable from 'vuedraggable'

import AbstractPanelForm from '../AbstractPanelForm'
import Panel from '../Panel'
import VueFormGenerator from "vue-form-generator";

Vue.component('panel', Panel)
Vue.component('draggable', draggable)

export default {
    mixins: [
        AbstractPanelForm,
    ],
    props: {
        asSlot: {
            type: Boolean,
            default: false,
        },
    },
    computed: {
        fields() {
            return {
                occurrenceOrder: {
                    label: 'Occurrence Order',
                },
            }
        }
    },
    data() {
        return {
            delModal: false,
            editModal: false,
            editModel: {
                index: null,
                id: null,
                url: null,
                title: null,
            },
            editSchema: {
                fields: {
                    url: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Url',
                        labelClasses: 'control-label',
                        model: 'url',
                        required: true,
                        validator: [
                            VueFormGenerator.validators.url,
                            VueFormGenerator.validators.required,
                        ],
                    },
                    title: {
                        type: 'input',
                        inputType: 'text',
                        label: 'URL title',
                        labelClasses: 'control-label',
                        model: 'title',
                        validator: [
                            VueFormGenerator.validators.string,
                        ],
                    },
                },
            },
        }
    },
    methods: {
        add() {
            this.editModel.id = null
            this.editModel.url = null
            this.editModel.title = null
            this.editModel.index = null
            this.editModel.tgIndex = this.maxTgIndex() + 1

            this.editModal = true
        },
        edit(index) {
            this.editModel.id = this.model.urls[index].id
            this.editModel.url = this.model.urls[index].url
            this.editModel.title = this.model.urls[index].title
            this.editModel.index = index
            this.editModel.tgIndex = this.model.urls[index].tgIndex

            this.editModal = true
        },
        del(index) {
            this.editModel.index = index;
            this.delModal = true;
        },
        submit() {
            this.$refs.editForm.validate();
            if (this.$refs.editForm.errors.length === 0) {
                // Prepare item to be saved
                let item = {
                    id: this.editModel.id,
                    url: this.editModel.url,
                    title: this.editModel.title,
                    tgIndex: this.editModel.tgIndex,
                }

                if (this.editModel.index != null) {
                    this.model.urls[this.editModel.index] = item
                }
                else if (this.model.urls) {
                    this.model.urls.push(item)
                }
                else {
                    this.model.urls = [item]
                }
                this.calcChanges();
                this.$emit('validated', 0, null, this);
                if (this.asSlot) {
                    this.$parent.$parent.slotUpdated()
                }
                this.editModal = false
            }
        },
        submitDelete() {
            this.model.urls.splice(this.editModel.index, 1);
            if (this.model.urls.length === 0) {
                this.model.urls = null;
            }
            this.calcChanges();
            this.$emit('validated', 0, null, this);
            if (this.asSlot) {
                this.$parent.$parent.slotUpdated()
            }
            this.delModal = false;
        },
        validate() {
            this.calcChanges()
        },
        calcChanges() {
            if (JSON.stringify(this.model.urls) !== JSON.stringify(this.originalModel.urls) && !(this.model.urls == null && this.originalModel.urls == null)) {
                this.changes = [{
                    'key': 'urls',
                    'label': 'Urls',
                    'old': this.displayUrls(this.originalModel.urls),
                    'new': this.displayUrls(this.model.urls),
                    'value': this.model.urls,
                }]
            }
            else {
                this.changes = []
            }
        },
        displayUrls(urls) {
            if (urls == null) {
                return null
            }
            const displays = []
            for (let url of urls) {
                let display = '<strong>Url</strong> ' + url.url
                if (url.title) {
                    display += '<br /><strong>Title</strong> ' + url.title
                }
                displays.push(display)
            }
            return displays
        },
        onOrderChange() {
            this.calcChanges()
            this.$emit('validated')
            if (this.asSlot) {
                this.$parent.$parent.slotUpdated()
            }
        },
        maxTgIndex: function() {
            if (this.model.urls == null || this.model.urls.length == 0) {
                return 0;
            }
            return Math.max.apply(Math, this.model.urls.map(u => u.tgIndex));
        },
    }
}
</script>