import { betaCodeToGreek, greekToBetaCode } from 'beta-code-js';

export const YEAR_MIN = 1;
export const YEAR_MAX = (new Date()).getFullYear();

// don't replace operators such as AND, OR, (, ), -, *
// escape round brackets by doubling them (in betacode)
export function changeMode(from, to, input) {
    const openBracketFrom = from === 'betacode' ? '((' : '(';
    const openBracketFromRegexp = from === 'betacode' ? '[(][(]' : '[(]';
    const openBracketTo = to === 'betacode' ? '((' : '(';
    const closeBracketFrom = from === 'betacode' ? '))' : ')';
    const closeBracketFromRegexp = from === 'betacode' ? '[)][)]' : '[)]';
    const closeBracketTo = to === 'betacode' ? '))' : ')';

    const operators = [
        'AND',
        'OR',
        openBracketFromRegexp,
        closeBracketFromRegexp,
        '[-]',
        '[*][ ]',
        '[*]$',
    ];
    const result = input
        // Make sure things to escape appear as separate elements in array by adding spaces before and after
        .replace(new RegExp(`(${operators.join('|')})`, 'g'), ' $1 ')
        // Replace multiple spaces by a single space
        .replace(/[ ]+/g, ' ')
        .trim()
        .split(' ')
        .map((word) => {
            if (word === openBracketFrom) {
                return openBracketTo;
            }
            if (word === closeBracketFrom) {
                return closeBracketTo;
            }
            if (word === '*') {
                return '**';
            }
            if (operators.includes(word)) {
                return word;
            }
            if (from === 'greek') {
                return greekToBetaCode(word);
            }
            if (to === 'greek') {
                return betaCodeToGreek(word);
            }
            // betacode to latin or latin to betacode
            return word;
        })
        .join(' ')
        // Replace multiple spaces by a single space
        .replace(/[ ]+/g, ' ')
        .replace(`${openBracketTo} `, openBracketTo)
        .replace(` ${closeBracketTo}`, closeBracketTo)
        .replace('- ', '-')
        .replace(' **', '*');
    return result;
}
