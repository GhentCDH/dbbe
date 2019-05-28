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
        keys: {
            type: Object,
            default: () => {return {}},
        },
        reloads: {
            type: Array,
            default: () => {return []},
        },
    },
    data () {
        return {
            changes: [],
            formOptions: {
                validateAfterChanged: true,
                validationErrorClass: 'has-error',
                validationSuccessClass: 'success',
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
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model));
            this.enableFields();
        },
        enableFields(enableKeys) {
            for (let key of Object.keys(this.keys)) {
                if ((this.keys[key].init && enableKeys == null) || (enableKeys != null && enableKeys.includes(key))) {
                    if (Array.isArray(this.values)) {
                        this.fields[this.keys[key].field].values = this.values;
                        this.fields[this.keys[key].field].originalValues = JSON.parse(JSON.stringify(this.values));
                    } else {
                        this.fields[this.keys[key].field].values = this.values[key];
                        this.fields[this.keys[key].field].originalValues = JSON.parse(JSON.stringify(this.values[key]));
                    }
                    this.enableField(this.fields[this.keys[key].field]);
                }
            }
        },
        disableFields(disableKeys) {
            for (let key of Object.keys(this.keys)) {
                if (disableKeys.includes(key)) {
                    this.disableField(this.fields[this.keys[key].field]);
                }
            }
        },
        reload(type) {
            if (!this.reloads.includes(type)) {
                this.$emit('reload', type);
            }
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
