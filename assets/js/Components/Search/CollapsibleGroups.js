;

export default {
    data() {
        return {
            groupConfig: {
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
                this.groupConfig.groupIsOpen,
                index,
                this.groupConfig.groupIsOpen[index] !== undefined ? !this.groupConfig.groupIsOpen[index] : true
            );
        },
    },
    mounted() {
        // make legends clickable
        const collapsableLegends = this.$el.querySelectorAll('.vue-form-generator .collapsible legend');
        collapsableLegends.forEach((legend) => legend.onclick = this.collapseGroup);

        // update group visibility on config change
        this.$on('config-changed', function (groupConfig) {
            if (groupConfig && this.schema.groups) {
                this.schema.groups.forEach((group, index) => {
                    group.styleClasses = group.styleClasses.replace(' collapsed', '') + ((groupConfig.groupIsOpen[index] !== undefined && groupConfig.groupIsOpen[index]) ? '' : ' collapsed');
                });
            }
        });
    },
};