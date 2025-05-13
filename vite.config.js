import symfonyPlugin from 'vite-plugin-symfony';
import {inject} from "vue";
import path from 'path';
import vue from '@vitejs/plugin-vue2';

export default {

    plugins: [
        symfonyPlugin(),
        vue({
            // This option ensures that the "compiler-included" build of Vue is used
            template: {
                compilerOptions: {
                    // Disable the runtime-only build warning
                    isCustomElement: tag => tag.startsWith('custom-')
                }
            }
        })
    ],

    optimizeDeps: {
        include: ['jquery'],
    },
    base: '/', //if you remove this then it makes public/build in dev (so without running pnpm build)
    outDir: 'public/build',
    manifest: true,
    build: {
        rollupOptions: {

            input: {
                acknowledgementsedit: 'assets/js/main/acknowledgementsedit.js',
                articleedit: 'assets/js/main/articleedit.js',
                bibliographysearch: 'assets/js/main/bibliographysearch.js',
                bibvariaedit: 'assets/js/main/bibvariaedit.js',
                blogedit: 'assets/js/main/blogedit.js',
                blogpostedit: 'assets/js/main/blogpostedit.js',
                bookedit: 'assets/js/main/bookedit.js',
                bookchapteredit: 'assets/js/main/bookchapteredit.js',
                bookclustersedit: 'assets/js/main/bookclustersedit.js',
                bookseriessedit: 'assets/js/main/bookseriessedit.js',
                contentsedit: 'assets/js/main/contentsedit.js',
                feedback: 'assets/js/main/feedback.js',
                genresedit: 'assets/js/main/genresedit.js',
                journalsedit: 'assets/js/main/journalsedit.js',
                journalissuesedit: 'assets/js/main/journalissuesedit.js',
                keywordsedit: 'assets/js/main/keywordsedit.js',
                lightbox: 'assets/websites/bower_components/ekko-lightbox/dist/ekko-lightbox.min.js',
                locationsedit: 'assets/js/main/locationsedit.js',
                main: 'assets/js/main/main.js',
                managementsedit: 'assets/js/main/managementsedit.js',
                manuscriptedit: 'assets/js/main/manuscriptedit.js',
                manuscriptsearch: 'assets/js/main/manuscriptsearch.js',
                metresedit: 'assets/js/main/metresedit.js',
                newseventedit: 'assets/js/main/newseventedit.js',
                occurrenceedit: 'assets/js/main/occurrenceedit.js',
                occurrencesearch: 'assets/js/main/occurrencesearch.js',
                officesedit: 'assets/js/main/officesedit.js',
                onlinesourceedit: 'assets/js/main/onlinesourceedit.js',
                originsedit: 'assets/js/main/originsedit.js',
                pageedit: 'assets/js/main/pageedit.js',
                personedit: 'assets/js/main/personedit.js',
                personsearch: 'assets/js/main/personsearch.js',
                phdedit: 'assets/js/main/phdedit.js',
                regionsedit: 'assets/js/main/regionsedit.js',
                rolesedit: 'assets/js/main/rolesedit.js',
                selfdesignationsedit: 'assets/js/main/selfdesignationsedit.js',
                statusesedit: 'assets/js/main/statusesedit.js',
                typeedit: 'assets/js/main/typeedit.js',
                typesearch: 'assets/js/main/typesearch.js',
                screen: 'assets/scss/screen.scss',
            },
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors:true,
        extensions: ['.js', '.ts', '.tsx', '.jsx', '.vue'],
    },
    publicDir: 'assets/websites/static',
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'assets/js'),
            'vue$': 'vue/dist/vue.esm.js',
        },
        extensions: ['.js', '.ts', '.tsx', '.jsx', '.vue'],

    },


};
