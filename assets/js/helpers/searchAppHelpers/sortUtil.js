import {removeGreekAccents} from "@/helpers/formFieldUtils";


const numRegex = /^(\d+)/;
const rgkRegex = /^(I{1,3})[.]([\d]+)(?:, I{1,3}[.][\d]+)*$/;
const vghRegex = /^([\d]+)[.]([A-Z])(?:, [\d]+[.][A-Z])*$/;
const roleCountRegex = /^(?:Patron|Related|Scribe)[ ]\((\d+)\)$/;
const greekRegex = /^([\u0370-\u03ff\u1f00-\u1fff ]*)$/;
const alphaNumRestRegex = /^([^\d]*)(\d+)(.*)$/;

export function sortByName(a, b) {
    const parseIntSafe = (str) => parseInt(str, 10);
    const compareNumbers = (x, y) => (x < y ? -1 : x > y ? 1 : 0);

    if (a.id === -1) return -1;
    if (b.id === -1) return 1;

    if (a.name === 'false' && b.name === 'true') return 1;
    if (a.name === 'true' && b.name === 'false') return -1;

    if (
        (typeof a.name === 'string' || a.name instanceof String) &&
        (typeof b.name === 'string' || b.name instanceof String)
    ) {
        let firstMatch = a.name.match(numRegex);
        let secondMatch = b.name.match(numRegex);
        if (firstMatch && secondMatch) {
            const firstNum = parseIntSafe(firstMatch[1]);
            const secondNum = parseIntSafe(secondMatch[1]);
            const cmp = compareNumbers(firstNum, secondNum);
            if (cmp !== 0) return cmp;
        }

        firstMatch = a.name.match(rgkRegex);
        secondMatch = b.name.match(rgkRegex);
        if (firstMatch && secondMatch) {
            let cmp = compareNumbers(parseIntSafe(firstMatch[1]), parseIntSafe(secondMatch[1]));
            if (cmp !== 0) return cmp;
            return compareNumbers(parseIntSafe(firstMatch[2]), parseIntSafe(secondMatch[2]));
        }

        firstMatch = a.name.match(vghRegex);
        secondMatch = b.name.match(vghRegex);
        if (firstMatch || secondMatch) {
            if (!firstMatch) return 1;
            if (!secondMatch) return -1;
            let cmp = compareNumbers(parseIntSafe(firstMatch[1]), parseIntSafe(secondMatch[1]));
            if (cmp !== 0) return cmp;
            return compareNumbers(firstMatch[2], secondMatch[2]); // Comparing A-Z is OK without parseInt
        }

        firstMatch = a.name.match(roleCountRegex);
        secondMatch = b.name.match(roleCountRegex);
        if (firstMatch && secondMatch) {
            return parseIntSafe(secondMatch[1]) - parseIntSafe(firstMatch[1]); // Descending
        }

        firstMatch = a.name.match(greekRegex);
        secondMatch = b.name.match(greekRegex);
        if (firstMatch && secondMatch) {
            const aName = removeGreekAccents(a.name);
            const bName = removeGreekAccents(b.name);
            if (aName < bName) return -1;
            if (aName > bName) return 1;
            return 0;
        }

        firstMatch = a.name.match(alphaNumRestRegex);
        secondMatch = b.name.match(alphaNumRestRegex);
        if (firstMatch && secondMatch) {
            let cmp = compareNumbers(firstMatch[1], secondMatch[1]);
            if (cmp !== 0) return cmp;

            cmp = compareNumbers(parseIntSafe(firstMatch[2]), parseIntSafe(secondMatch[2]));
            if (cmp !== 0) return cmp;

            return compareNumbers(firstMatch[3], secondMatch[3]);
        }
    }

    if (a.name < b.name) return -1;
    if (a.name > b.name) return 1;
    return 0;
}
