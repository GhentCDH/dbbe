<template>
    <div v-if="linkVerses != null && linkVerses.length > 0">
        <table
            v-if="linkVerses.length > 1"
            class="table table-bordered"
        >
            <thead>
                <tr>
                    <th class="col-xs-4">Verse</th>
                    <th class="col-xs-1">Line</th>
                    <th class="col-xs-3">Occurrence</th>
                    <th class="col-xs-1">Occ. loc.</th>
                    <th :class="edit ? 'col-xs-2' : 'col-xs-3'">Manuscript</th>
                    <th
                        v-if="edit"
                        class="col-xs-1"
                    >
                        (Un)link
                    </th>
                </tr>
            </thead>
        </table>
        <table
            v-for="(linkGroup, groupIndex) in linkVerses"
            :key="groupIndex"
            class="table table-bordered"
        >
            <thead v-if="linkVerses.length == 1">
                <tr>
                    <th class="col-xs-4">Verse</th>
                    <th class="col-xs-1">Line</th>
                    <th class="col-xs-3">Occurrence</th>
                    <th class="col-xs-1">Occ. loc.</th>
                    <th :class="edit ? 'col-xs-2' : 'col-xs-3'">Manuscript</th>
                    <th
                        v-if="edit"
                        class="col-xs-1"
                    >
                        (Un)link
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(linkVerse, index) in linkGroup.group"
                    :key="index"
                >
                    <!-- eslint-disable vue/no-v-html -->
                    <td
                        class="col-xs-4 greek"
                        v-html="linkVerse.highlight_verse || linkVerse.verse"
                    />
                    <!-- eslint-enable -->
                    <td class="col-xs-1">{{ linkVerse.order +1 }}</td>
                    <td class="col-xs-3 greek">
                        <a :href="urls['occurrence_get'].replace('occurrence_id', linkVerse.occurrence.id).replace(/[\d]+$/, linkVerse.occurrence.id)">
                            [{{ linkVerse.occurrence.id }}] {{ linkVerse.occurrence.name }}
                        </a>
                    </td>
                    <td class="col-xs-1">{{ linkVerse.occurrence.location }}</td>
                    <td :class="edit ? 'col-xs-2' : 'col-xs-3'">
                        <a :href="urls['manuscript_get'].replace('manuscript_id', linkVerse.manuscript.id)">
                            {{ linkVerse.manuscript.name }}
                        </a>
                    </td>
                    <!-- only display one button for each group -->
                    <!-- add additional row if group is larger than results displayed -->
                    <td
                        v-if="edit && index == 0"
                        :rowspan="(linkGroup.total != null && linkGroup.total > linkGroup.group.length) ? linkGroup.group.length + 1 : linkGroup.group.length"
                        class="col-xs-1"
                    >
                        <!-- grouped results -->
                        <template v-if="linkGroup['group_id'] != null">
                            <btn
                                v-if="(linkedGroups.includes(linkGroup['group_id']))"
                                class="danger"
                                @click="$emit('groupToggle', 'remove', linkGroup['group_id'])"
                            >
                                <i class="fa fa-minus" />
                            </btn>
                            <btn
                                v-else
                                class="success"
                                @click="$emit('groupToggle', 'add', linkGroup['group_id'])"
                            >
                                <i class="fa fa-plus" />
                            </btn>
                        </template>
                        <!-- individual results -->
                        <template v-else>
                            <btn
                                v-if="(linkedVerses.includes(linkVerse.id))"
                                class="danger"
                                @click="$emit('verseToggle', 'remove', linkVerse.id)"
                            >
                                <i class="fa fa-minus" />
                            </btn>
                            <btn
                                v-else
                                class="success"
                                @click="$emit('verseToggle', 'add', linkVerse.id)"
                            >
                                <i class="fa fa-plus" />
                            </btn>
                        </template>
                    </td>
                </tr>
                <tr v-if="linkGroup.total != null && linkGroup.total > linkGroup.group.length">
                    <td
                        colspan="5"
                        class="text-center"
                    >
                        ({{ linkGroup.total }} in total)
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div v-else>
        <h6>No verses found</h6>
    </div>
</template>
<script>
export default {
    props: {
        linkVerses: {
            type: Array,
            default: () => {return []},
        },
        linkedGroups: {
            type: Array,
            default: () => {return []},
        },
        linkedVerses: {
            type: Array,
            default: () => {return []},
        },
        urls: {
            type: Object,
            default: () => {return []},
        },
        edit: {
            type: Boolean,
            default: false,
        },
    },
}
</script>
