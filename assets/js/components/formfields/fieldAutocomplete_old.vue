<template>
    <div style="position:relative">
        <div class="form-group dropdown" :class="{'open':open}">
            <label class="control-label" :for="schema.name">{{ schema.label }}</label>
            <input
                type="text"
                class="form-control"
                autocomplete="off"
                :name="schema.name"
                :placeholder="schema.placeholder"
                v-model="value"
                @keyup="valueChanged()"
            >
            <ul class="dropdown-menu" style="width:100%">
                <li v-for="suggestion in suggestions"
                    @click="suggestionClick(suggestion)"
                >
                  <a href="#">{{ suggestion }}
                  </a>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            schema: {}
        },

        data () {
            return {
                value: '',
                open: false,
                suggestions: []
            }
        },

        mounted () {
            this.$nextTick( () => {
                if (!window.axios) {
                    console.warn('axios is missing. Please download from https://github.com/axios/axios and load the script in the HTML head section!');
                }
            })
        },

        methods: {
            valueChanged () {
                this.$emit(this.schema.update, this.schema.model, this.value)

                if (this.value === '') {
                    this.open = false;
                    return;
                }

                this.open = true

                axios.get(this.schema.url + this.value)
                    .then( (response) => {
                        this.suggestions = response.data
                    })
                    .catch( (error) => {
                        console.log(error)
                    })
            },
            suggestionClick () {
                this.open = false
                this.$emit(this.schema.event)
            }
        }
    };
</script>
