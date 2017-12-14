<template>
    <div style="position:relative" class="dropdown" v-bind:class="{'open':open}">
        <input
            class="form-control"
            type="text"
            autocomplete="off"
            :placeholder="schema.placeholder"
            v-model="value"
            @keyup="getSuggestions()"
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
</template>

<script>
    import { abstractField } from 'vue-form-generator'

    export default {
        mixins: [ abstractField ],

        data () {
            return {
                open: false,
                current: 0,
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
            getSuggestions () {
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
            suggestionClick (suggestion) {
                this.value = suggestion
                this.open = false
            }
        }
    };
</script>
