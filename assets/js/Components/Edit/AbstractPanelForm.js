export default {
    props: {
        header: {
            type: String,
            default: '',
        },
        links: {
            type: Array,
            default: () => {return []},
        },
        model: {
            type: Object,
            default: () => {return {}},
        },
        values: {
            type: Array,
            default: () => {return []},
        },
        clone: {
            type: Boolean,
            default: false,
        },
    },
    data () {
        return {
            changes: [],
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: "has-error",
                validationSuccessClass: "success"
            },
            isValid: true,
            originalModel: {},
        }
    },
    computed: {
        fields() {
            return this.schema.fields
        }
    },
    watch: {
        // Only called when a new key is added to a specific model, so make sure to initialize correctly
        model() {
            if (!this.clone) {
                this.init();
            } else {
                this.calcChanges();
            }
        },
    },
    mounted() {
        this.init()
    },
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
        },
        calcChanges() {
            this.changes = []
            if (this.originalModel == null) {
                return
            }
            for (let key of Object.keys(this.model)) {
                if (JSON.stringify(this.model[key]) !== JSON.stringify(this.originalModel[key]) && !(this.model[key] == null && this.originalModel[key] == null)) {
                    this.changes.push({
                        'key': key,
                        'label': this.fields[key].label,
                        'old': this.originalModel[key],
                        'new': this.model[key],
                        'value': this.model[key],
                    })
                }
            }
        },
        validated(isValid, errors) {
            this.isValid = isValid
            this.calcChanges()
            this.$emit('validated', isValid, this.errors, this)
        },
        validate() {
            this.$refs.form.validate()
        },
    }
}
