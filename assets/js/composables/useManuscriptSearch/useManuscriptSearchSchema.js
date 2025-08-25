import { ref } from 'vue';
import VueFormGenerator from 'vue-form-generator';
import {createLanguageToggle, createMultiMultiSelect, createMultiSelect} from "@/helpers/formFieldUtils";
import {YEAR_MAX, YEAR_MIN} from "@/helpers/formatUtil";
import validatorUtil from "@/helpers/validatorUtil";

export function useManuscriptSearchSchema(idList = []) {
    const schema = ref({
        fields: {},
        groups: [],
    });

    const buildSchemaFields = () => {
        const schemaFields = {};

        schemaFields.city = createMultiSelect('City');
        schemaFields.library = createMultiSelect('Library', { dependency: 'city' });
        schemaFields.collection = createMultiSelect('Collection', { dependency: 'library' });
        schemaFields.shelf = createMultiSelect('Shelf number', { model: 'shelf', dependency: 'collection' });

        schemaFields.year_from = {
            type: 'input',
            inputType: 'number',
            label: 'Year from',
            model: 'year_from',
            min: YEAR_MIN,
            max: YEAR_MAX,
            validator: validatorUtil.number,
        };

        schemaFields.year_to = {
            type: 'input',
            inputType: 'number',
            label: 'Year to',
            model: 'year_to',
            min: YEAR_MIN,
            max: YEAR_MAX,
            validator: validatorUtil.number,
        };

        schemaFields.date_search_type = {
            type: 'checkboxes',
            styleClasses: 'field-checkboxes-labels-only field-checkboxes-lg',
            label: 'The occurrence date interval must ... the search date interval:',
            model: 'date_search_type',
            values: [
                { value: 'exact', name: 'exact', toggleGroup: 'exact_included_overlap' },
                { value: 'included', name: 'include', toggleGroup: 'exact_included_overlap' },
                { value: 'overlap', name: 'overlap', toggleGroup: 'exact_included_overlap' },
            ],
        };

        [schemaFields.content_op, schemaFields.content] = createMultiMultiSelect('Content');

        schemaFields.person = createMultiSelect('Person', {}, {
            multiple: true,
            closeOnSelect: false,
        });

        schemaFields.role = createMultiSelect('Role', { dependency: 'person' }, {
            multiple: true,
            closeOnSelect: false,
        });

        [schemaFields.origin_op, schemaFields.origin] = createMultiMultiSelect('Origin');

        schemaFields.comment_mode = createLanguageToggle('comment');

        schemaFields.comment = {
            type: 'input',
            inputType: 'text',
            label: 'Comment',
            model: 'comment',
            validator: VueFormGenerator.validators.string,
        };

        [schemaFields.acknowledgement_op, schemaFields.acknowledgement] = createMultiMultiSelect('Acknowledgements', {
            model: 'acknowledgement',
        });

        return schemaFields;
    };

    schema.value.fields = buildSchemaFields();
    schema.value.groups.push({
        styleClasses: 'collapsible collapsed',
        legend: 'External identifiers',
        fields: idList,
    });

    return {
        schema,
    };
}
