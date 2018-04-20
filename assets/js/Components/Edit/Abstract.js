export default {
    props: {
        header: {
            type: String,
            default: ''
        },
        model: {
            type: Object,
            default: () => {return {}}
        },
        values: {
            type: Array,
            default: () => {return []}
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
        model() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
        }
    },
    methods: {
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
    }
}
