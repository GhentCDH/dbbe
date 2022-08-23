import Vue from 'vue';

export default {
    data() {
        return {
            config: {
                groupIsOpen: [],
            },
            defaultConfig: {
                groupIsOpen: [],
            },
        };
    },
    methods: {
        collapseGroup(e) {
            const group = e.target.parentElement;
            // get element index
            const index = Array.from(group.parentNode.children).indexOf(group) - 1;
            Vue.set(
                this.config.groupIsOpen,
                index,
                this.config.groupIsOpen[index] !== undefined ? !this.config.groupIsOpen[index] : true
            );
        },
    },
    mounted() {
        // make legends clickable
        const collapsableLegends = this.$el.querySelectorAll('.vue-form-generator .collapsible legend');
        collapsableLegends.forEach((legend) => legend.onclick = this.collapseGroup);

        // update group visibility on config change
        this.$on('config-changed', function (config) {
            if (config && this.schema.groups) {
                this.schema.groups.forEach((group, index) => {
                    group.styleClasses = group.styleClasses.replace(' collapsed', '') + ((config.groupIsOpen[index] !== undefined && config.groupIsOpen[index]) ? '' : ' collapsed');
                });
            }
        });
    },
};