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
 * Keeps the review page's "On confirming" card in step with the decider's ticks.
 *
 * The server renders the card right for the page as it loads, so the page needs none of this:
 * it only rewrites the outcome sentence, the counts after the decision, the line about the key's
 * group and Confirm's consequence when a group card, the added-groups select or the role changes,
 * from the plain names and seats the server wrote into the markup. Every value goes in through
 * textContent, never as markup.
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
 * @module     enrol_apply/review
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
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
    CONFIRM: '[data-region="consequence-confirm"]',
    GROUPINGFILTER: '[data-region="groupingfilter"]',
    GROUPINGSELECT: '[data-region="groupingselect"]',
};

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
 * The role phrase: the chosen role, or the method's when the select is left alone.
 *
 * @param {HTMLFormElement} form The review form.
 * @param {HTMLElement} outcome The outcome card.
 * @returns {Promise} Resolved with the phrase.
 */
const rolePhrase = (form, outcome) => {
    const select = form.querySelector(SELECTORS.ROLE);
    const fallback = outcome.querySelector(SELECTORS.DEFAULTROLE);
    let name = fallback ? fallback.textContent.trim() : '';
    if (select && select.value !== '0' && select.selectedOptions.length) {
        name = select.selectedOptions[0].textContent.trim();
    }
    if (name === '') {
        return getString('reviewoutcomenorole', 'enrol_apply');
    }
    return getString('reviewoutcomerole', 'enrol_apply', name);
};

/**
 * The sentence's lang string for this many groups.
 *
 * @param {Number} count How many groups the sentence names, the hidden ones included.
 * @returns {String} The string identifier.
 */
const sentenceKey = (count) => {
    if (count === 0) {
        return 'reviewoutcomenone';
    }
    if (count === 1) {
        return 'reviewoutcomeone';
    }
    return 'reviewoutcomemany';
};

/**
 * Rewrite the card and Confirm's consequence from what is ticked now.
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
        const groups = items.join(', ');
        const sentence = await getString(sentenceKey(names.length + hidden), 'enrol_apply', {
            who: outcome.dataset.who,
            role: role,
            groups: groups,
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

        const over = seated.find((group) => group.members >= group.seats);
        let confirm;
        if (over) {
            confirm = await getString('reviewconfirmoverbook', 'enrol_apply', {
                name: over.name,
                after: over.members + 1,
                seats: over.seats,
            });
        } else if (items.length) {
            confirm = await getString('reviewconfirmconsequencegroups', 'enrol_apply', {groups: groups, role: role});
        } else {
            confirm = await getString('reviewconfirmconsequencecourse', 'enrol_apply', role);
        }

        if (mine !== generation) {
            return;
        }
        outcome.querySelector(SELECTORS.SENTENCE).textContent = sentence;
        const afterNode = outcome.querySelector(SELECTORS.AFTER);
        afterNode.textContent = after;
        afterNode.hidden = after === '';
        const notKey = outcome.querySelector(SELECTORS.NOTKEY);
        notKey.hidden = !keyGroup || notKey.textContent.trim() === '' || joinedIds.includes(keyGroup);
        form.querySelectorAll(SELECTORS.CONFIRM).forEach((node) => {
            node.textContent = confirm;
        });
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

export default {init};
