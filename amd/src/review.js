// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Keeps the review page's "On confirming" card in step with the decider's ticks, and opens the
 * earlier applications in a dialogue.
 *
 * The server renders the card right for the page as it loads, so the page needs none of this:
 * it only rewrites the outcome sentence, the counts after the decision and the line about the
 * key's group when a group card, the added-groups select or the role changes, from the plain
 * names and seats the server wrote into the markup. The sentence carries the role, the groups
 * and the notice in strong elements, as the server's does; they are built here as nodes, from
 * the language string split at placeholder markers, and every name goes in as a text node,
 * never as markup.
 *
 * An empty choice is not "no group": confirm_enrolment() records it and the approval falls back to
 * the method's defaults, which the server lists in the card, so with nothing ticked the sentence
 * names those, the ones this reader may not see counted. A ticked choice joins the method's groups
 * this reader may not choose as well (confirm_enrolment() keeps them), so the sentence adds those,
 * listed in the card the same way.
 *
 * Above the threshold it also narrows the search's suggestions by grouping, through the
 * data-enabled="disabled" attribute core's autocomplete documents for leaving an option out; the
 * grouping control is hidden until this module shows it, because it does nothing without it.
 *
 * The earlier applications are a region the server renders and styles.css hides once the page
 * has JavaScript; initHistory() copies its list and its report link into a core/modal, which handles
 * focus, Escape and focus return, and opens it from the rules' "See the history" button.
 *
 * @module     enrol_apply/review
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import Modal from 'core/modal';
import Notification from 'core/notification';
import Pending from 'core/pending';

const SELECTORS = {
    OUTCOME: '[data-region="enrol_apply-outcome"]',
    SENTENCE: '[data-region="outcome-sentence"]',
    AFTER: '[data-region="outcome-after"]',
    NOTKEY: '[data-region="outcome-notkey"]',
    DEFAULTROLE: '[data-region="outcome-defaultrole"]',
    DEFAULTNAMES: '[data-region="outcome-defaults"] li',
    KEPTNAMES: '[data-region="outcome-kept"] li',
    CARDS: 'input[type="checkbox"][name="groups[]"]',
    ADDED: 'select[name="groups[]"]',
    ROLE: 'select[name="roleid"]',
    GROUPINGFILTER: '[data-region="groupingfilter"]',
    GROUPINGSELECT: '[data-region="groupingselect"]',
    HISTORYBUTTON: '[data-action="enrol_apply-history"]',
    HISTORYTITLE: '[data-region="history-title"]',
    HISTORYLIST: '[data-region="history-list"]',
    HISTORYFOOTER: '[data-region="history-footer"]',
};

/** @var {String} What a placeholder is replaced with before a string is split into nodes. */
const MARKER = '\u0001';

/** @var {Number} Bumped on every change, so a slower earlier rewrite never overwrites a later one. */
let generation = 0;

/**
 * What the markup says about one group, a card's checkbox or an option.
 *
 * @param {HTMLElement} node The checkbox or the option.
 * @returns {Object} Its id, plain name and seats.
 */
const describe = (node) => ({
    id: parseInt(node.value, 10),
    name: node.dataset.name || '',
    hasSeats: node.dataset.hasseats === '1',
    members: parseInt(node.dataset.members, 10) || 0,
    seats: parseInt(node.dataset.seats, 10) || 0,
});

/**
 * Every group the page offers, ticked or not.
 *
 * @param {HTMLFormElement} form The review form.
 * @returns {Array} Checkboxes and options.
 */
const offered = (form) => {
    const nodes = Array.from(form.querySelectorAll(SELECTORS.CARDS));
    const added = form.querySelector(SELECTORS.ADDED);
    if (added) {
        nodes.push(...Array.from(added.options));
    }
    return nodes;
};

/**
 * The groups ticked on a card or picked in the search.
 *
 * @param {HTMLFormElement} form The review form.
 * @returns {Array} One description per chosen group.
 */
const chosen = (form) => offered(form)
    .filter((node) => (node.tagName === 'OPTION' ? node.selected : node.checked))
    .map(describe);

/**
 * A strong element holding plain text.
 *
 * @param {String} text The text, plain.
 * @returns {HTMLElement} The element.
 */
const strong = (text) => {
    const node = document.createElement('strong');
    node.textContent = text;
    return node;
};

/**
 * A language string as nodes, its placeholders replaced by the given parts.
 *
 * The string is fetched with a numbered marker for each part, so its own text is kept as text
 * nodes and each part goes in where its marker is: a node as it is, a string as a text node.
 * Nothing is ever parsed as markup.
 *
 * @param {String} key The string identifier.
 * @param {Object} parts Placeholder name => Node or plain string; the one name 'a' for {$a}.
 * @returns {Promise} Resolved with a DocumentFragment.
 */
const compose = async(key, parts) => {
    const names = Object.keys(parts);
    const markers = {};
    names.forEach((name, index) => {
        markers[name] = MARKER + index + MARKER;
    });
    // A string with a bare placeholder takes its one value as is; core substitutes {$a} only then.
    const param = names.length === 1 && names[0] === 'a' ? markers.a : markers;
    const text = await getString(key, 'enrol_apply', param);
    const fragment = document.createDocumentFragment();
    text.split(MARKER).forEach((piece, index) => {
        // Odd pieces are the marker's number, even ones the string's own text.
        if (index % 2 === 0) {
            if (piece !== '') {
                fragment.append(document.createTextNode(piece));
            }
            return;
        }
        const part = parts[names[parseInt(piece, 10)]];
        fragment.append(part instanceof Node ? part : document.createTextNode(String(part)));
    });
    return fragment;
};

/**
 * The role phrase: the chosen role, or the method's when the select is left alone, in bold.
 *
 * @param {HTMLFormElement} form The review form.
 * @param {HTMLElement} outcome The outcome card.
 * @returns {Promise} Resolved with a DocumentFragment.
 */
const rolePhrase = async(form, outcome) => {
    const select = form.querySelector(SELECTORS.ROLE);
    const fallback = outcome.querySelector(SELECTORS.DEFAULTROLE);
    let name = fallback ? fallback.textContent.trim() : '';
    if (select && select.value !== '0' && select.selectedOptions.length) {
        name = select.selectedOptions[0].textContent.trim();
    }
    if (name === '') {
        const fragment = document.createDocumentFragment();
        fragment.append(strong(await getString('reviewoutcomenorole', 'enrol_apply')));
        return fragment;
    }
    return compose('reviewoutcomerole', {a: strong(name)});
};

/**
 * The sentence's lang string for this many groups.
 *
 * @param {Number} count How many groups the sentence names, the hidden ones included.
 * @returns {String} The string identifier.
 */
const sentenceKey = (count) => {
    if (count === 0) {
        return 'reviewoutcomenonewithnotice';
    }
    if (count === 1) {
        return 'reviewoutcomeonewithnotice';
    }
    return 'reviewoutcomemanywithnotice';
};

/**
 * Rewrite the card from what is ticked now.
 *
 * @param {HTMLFormElement} form The review form.
 * @param {HTMLElement} outcome The outcome card.
 * @returns {Promise} Resolved once the page says what the ticks do.
 */
const update = async(form, outcome) => {
    const mine = ++generation;
    const pending = new Pending('enrol_apply/review:update');
    try {
        const picked = chosen(form);
        const defaultIds = (outcome.dataset.defaultids || '').split(',').filter((id) => id !== '').map(Number);
        const keyGroup = parseInt(outcome.dataset.keygroup, 10) || 0;

        // With nothing ticked the approval falls back to the method's defaults; see the module's docblock.
        // A choice keeps the method's groups this reader may not choose; see the module's docblock.
        let names = picked.map((group) => group.name)
            .concat(Array.from(outcome.querySelectorAll(SELECTORS.KEPTNAMES)).map((node) => node.textContent.trim()));
        let hidden = parseInt(outcome.dataset.kepthidden, 10) || 0;
        let joined = picked;
        if (!picked.length) {
            names = Array.from(outcome.querySelectorAll(SELECTORS.DEFAULTNAMES)).map((node) => node.textContent.trim());
            hidden = parseInt(outcome.dataset.defaulthidden, 10) || 0;
            joined = offered(form).map(describe).filter((group) => defaultIds.includes(group.id));
        }
        const joinedIds = picked.length ? picked.map((group) => group.id) : defaultIds;

        const role = await rolePhrase(form, outcome);
        const items = names.slice();
        if (hidden) {
            items.push(await getString('decisionsummaryhidden', 'enrol_apply', hidden));
        }
        const notice = strong(await getString('reviewoutcomenotice', 'enrol_apply'));
        const sentence = await compose(sentenceKey(names.length + hidden), {
            who: outcome.dataset.who,
            role: role,
            groups: items.length ? strong(items.join(', ')) : '',
            notice: notice,
        });

        const seated = joined.filter((group) => group.hasSeats);
        const parts = await Promise.all(seated.map((group) => getString('reviewafterseats', 'enrol_apply', {
            name: group.name,
            members: group.members + 1,
            seats: group.seats,
        })));
        const places = parseInt(outcome.dataset.places, 10) || 0;
        if (places > 0) {
            parts.push(await getString('reviewafterplaces', 'enrol_apply', {
                taken: (parseInt(outcome.dataset.placestaken, 10) || 0) + 1,
                total: places,
            }));
        }
        const after = parts.length ? await getString('reviewoutcomeafter', 'enrol_apply', parts.join('; ')) : '';

        if (mine !== generation) {
            return;
        }
        outcome.querySelector(SELECTORS.SENTENCE).replaceChildren(sentence);
        const afterNode = outcome.querySelector(SELECTORS.AFTER);
        afterNode.textContent = after;
        afterNode.hidden = after === '';
        const notKey = outcome.querySelector(SELECTORS.NOTKEY);
        notKey.hidden = !keyGroup || notKey.textContent.trim() === '' || joinedIds.includes(keyGroup);
    } finally {
        pending.resolve();
    }
};

/**
 * Narrow the search's suggestions to one grouping, and show the control that does it.
 *
 * Core's autocomplete reads only the select's direct options, so a grouping cannot be an
 * optgroup; it leaves out every option marked data-enabled="disabled" when it builds the
 * suggestions, and a group in several groupings stays one option found through any of them.
 *
 * @param {HTMLFormElement} form The review form.
 */
const narrowByGrouping = (form) => {
    const filter = form.querySelector(SELECTORS.GROUPINGFILTER);
    const grouping = form.querySelector(SELECTORS.GROUPINGSELECT);
    const added = form.querySelector(SELECTORS.ADDED);
    if (!filter || !grouping || !added) {
        return;
    }
    filter.hidden = false;
    grouping.addEventListener('change', () => {
        const wanted = grouping.value === '0' ? '' : ',' + grouping.value + ',';
        Array.from(added.options).forEach((option) => {
            if (wanted === '' || (option.dataset.groupings || '').includes(wanted)) {
                option.removeAttribute('data-enabled');
            } else {
                option.setAttribute('data-enabled', 'disabled');
            }
        });
    });
};

/**
 * Start following the review form.
 *
 * @param {String} selector The review form's selector.
 */
export const init = (selector) => {
    const form = document.querySelector(selector);
    if (!form) {
        return;
    }
    narrowByGrouping(form);

    const outcome = form.querySelector(SELECTORS.OUTCOME);
    if (!outcome) {
        return;
    }
    // Core's autocomplete dispatches a bubbling change on the select at every add and remove.
    const watched = [SELECTORS.CARDS, SELECTORS.ADDED, SELECTORS.ROLE].join(', ');
    form.addEventListener('change', (e) => {
        if (e.target.matches(watched)) {
            update(form, outcome).catch(Notification.exception);
        }
    });
};

/**
 * Open the earlier applications in a dialogue from the rules' button.
 *
 * The dialogue is built once, on the first press, from copies of the server's own list and
 * report link; the region they come from stays in the page, hidden by styles.css, which is what
 * a page without JavaScript shows instead. Core's modal records the button as the element to
 * return focus to.
 *
 * @param {String} selector The history region's selector.
 */
export const initHistory = (selector) => {
    const region = document.querySelector(selector);
    const button = document.querySelector(SELECTORS.HISTORYBUTTON);
    if (!region || !button) {
        return;
    }
    const list = region.querySelector(SELECTORS.HISTORYLIST);
    const title = region.querySelector(SELECTORS.HISTORYTITLE);
    const footer = region.querySelector(SELECTORS.HISTORYFOOTER);
    if (!list || !title) {
        return;
    }

    let opening = null;
    button.addEventListener('click', (e) => {
        e.preventDefault();
        if (!opening) {
            /* Strings of the server's own markup, re-read as it was rendered: the list and the
               link are copied, never built from text. */
            opening = Modal.create({
                title: title.textContent.trim(),
                body: list.outerHTML,
                footer: footer ? footer.innerHTML : '',
                large: true,
                removeOnClose: false,
                returnElement: button,
            });
        }
        opening.then((modal) => {
            modal.show();
            return modal;
        }).catch(Notification.exception);
    });
};

export default {init, initHistory};
