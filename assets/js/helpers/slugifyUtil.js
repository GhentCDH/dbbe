
export const slugifyFormID = (schema, prefix = "") => {
    if (typeof schema.id !== "undefined") {
        return prefix + schema.id;
    } else {
        return (
            prefix +
            (schema.inputName || schema.label || schema.model || "")
                .toString()
                .trim()
                .toLowerCase()
                // Spaces & underscores to dashes
                .replace(/ |_/g, "-")
                // Multiple dashes to one
                .replace(/-{2,}/g, "-")
                // Remove leading & trailing dashes
                .replace(/^-+|-+$/g, "")
                // Remove anything that isn't a (English/ASCII) letter, number or dash.
                .replace(/([^a-zA-Z0-9-]+)/g, "")
        );
    }
};