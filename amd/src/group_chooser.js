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
 * A searchable list of the groups an applicant may choose, over the plain select it enhances.
 *
 * The select stays in the form, hidden, and carries the posted value; without this module it is
 * the whole chooser (apply.php with no JavaScript). The list follows the ARIA combobox pattern:
 * an input with role="combobox", a listbox, aria-activedescendant and a polite live region for
 * the counts. It opens inline, pushing the fields below it down, because core's modal body
 * scrolls and would clip a popup placed over its edge (C-D23 in
 * docs/design/applicant-group-choice.md).
 *
 * What it lists (C-D24): opened with nothing typed, up to eight groups with places in the
 * server's natural order, and a note when more exist; typing searches every offered group,
 * ignoring case and accents, those with places first and then the full ones, disabled, under a
 * heading, at most fifty. "No preference" heads the list when the choice is optional. Everything
 * it shows is read from the select's options; the server judges the posted group again.
 *
 * @module     enrol_apply/group_chooser
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';
import {exception as displayException} from 'core/notification';
import Pending from 'core/pending';

/** @type {Number} Groups with places shown before anything is typed, as groupfilter::THRESHOLD. */
const LIMIT = 8;

/** @type {Number} Matches shown for a search before the applicant is asked to refine it. */
const MAX_MATCHES = 50;

const SELECTORS = {
    select: 'select[data-enrol-apply-groupchooser]',
};

const STRING_KEYS = [
    'chosengroupcount',
    'chosengroupcountone',
    'chosengroupempty',
    'chosengroupfull',
    'chosengroupmore',
    'chosengroupnomatch',
    'chosengroupplace',
    'chosengroupplaceholder',
    'chosengroupplaces',
    'chosengrouprefine',
    'chosengroupshow',
];

/** @type {Promise<Object>|null} The strings, fetched once per page with their placeholders. */
let stringsPromise = null;

/** @type {Number} Distinguishes the ids of two choosers on one page. */
let sequence = 0;

/** @type {WeakMap<HTMLSelectElement, Object>} The chooser built over each select. */
const choosers = new WeakMap();

/**
 * The strings this module shows, keyed by identifier, with their {$a} placeholders kept.
 *
 * @returns {Promise<Object>} Identifier => raw string.
 */
const loadStrings = () => {
    if (!stringsPromise) {
        stringsPromise = getStrings(STRING_KEYS.map((key) => ({key, component: 'enrol_apply'})))
            .then((values) => Object.fromEntries(STRING_KEYS.map((key, index) => [key, values[index]])));
    }
    return stringsPromise;
};

/**
 * Put values into a string's placeholders, {$a} or {$a->name}.
 *
 * @param {String} template The raw string.
 * @param {*} param A value for {$a}, or an object for {$a->name}.
 * @returns {String} The string filled in.
 */
const fill = (template, param) => template.replace(
    /\{\$a(?:->(\w+))?\}/g,
    (match, name) => String(name ? param[name] : param)
);

/**
 * Text folded for comparison: lower case, accents removed.
 *
 * @param {String} text Any text.
 * @returns {String} The folded text.
 */
const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/**
 * The groups the select offers, as the server rendered them.
 *
 * @param {HTMLSelectElement} select The select being enhanced.
 * @returns {Array<Object>} One entry per option except the required mode's empty placeholder.
 */
const readOptions = (select) => Array.from(select.options)
    .filter((option) => option.value !== '')
    .map((option) => {
        const name = option.dataset.name || option.textContent;
        return {
            value: option.value,
            none: option.dataset.none === '1',
            name,
            folded: fold(name),
            places: option.dataset.places === undefined ? null : parseInt(option.dataset.places, 10),
            full: option.dataset.full === '1',
        };
    });

/**
 * What the list shows for a query, and the note under it.
 *
 * @param {Array<Object>} groups From readOptions().
 * @param {String} query What the applicant typed.
 * @param {Object} strings From loadStrings().
 * @returns {{items: Array<Object>, note: String}} The rows, in order, and the note ('' for none).
 */
const listModel = (groups, query, strings) => {
    const offered = groups.filter((group) => !group.none);
    const term = fold(query.trim());

    if (term === '') {
        const open = offered.filter((group) => !group.full);
        const shown = open.slice(0, LIMIT);
        let note = '';
        if (!open.length && offered.length) {
            note = strings.chosengroupempty;
        } else if (shown.length < offered.length) {
            note = fill(strings.chosengroupmore, {shown: shown.length, available: open.length, all: offered.length});
        }
        return {items: groups.filter((group) => group.none).concat(shown), note};
    }

    const matches = offered.filter((group) => group.folded.includes(term));
    const items = matches.filter((group) => !group.full).concat(matches.filter((group) => group.full)).slice(0, MAX_MATCHES);
    let note = '';
    if (!matches.length) {
        note = fill(strings.chosengroupnomatch, query.trim());
    } else if (matches.length > MAX_MATCHES) {
        note = fill(strings.chosengrouprefine, MAX_MATCHES);
    }
    return {items, note};
};

/**
 * The places of a group in words, or '' where seats are not counted.
 *
 * @param {Object} group From readOptions().
 * @param {Object} strings From loadStrings().
 * @returns {String} The words.
 */
const placesText = (group, strings) => {
    if (group.places === null) {
        return '';
    }
    if (group.full) {
        return strings.chosengroupfull;
    }
    return group.places === 1 ? strings.chosengroupplace : fill(strings.chosengroupplaces, group.places);
};

/**
 * Append a name to an element, the part matching the query marked.
 *
 * @param {HTMLElement} target Where the name goes.
 * @param {String} name The group's plain name.
 * @param {String} query What the applicant typed.
 */
const appendName = (target, name, query) => {
    const term = fold(query.trim());
    // Folding keeps one character per character only for text without combining marks.
    const at = term === '' || fold(name).length !== name.length ? -1 : fold(name).indexOf(term);
    if (at < 0) {
        target.append(name);
        return;
    }
    const mark = document.createElement('mark');
    mark.textContent = name.slice(at, at + term.length);
    target.append(name.slice(0, at), mark, name.slice(at + term.length));
};

/**
 * One chooser over one select.
 */
class Chooser {
    /**
     * Build the combobox beside the select and hide the select.
     *
     * @param {HTMLSelectElement} select The select to enhance.
     * @param {Object} strings From loadStrings().
     */
    constructor(select, strings) {
        this.select = select;
        this.strings = strings;
        this.groups = readOptions(select);
        this.items = [];
        this.active = -1;
        this.open = false;
        this.query = '';

        const base = `enrol_apply-chooser-${++sequence}`;
        this.root = document.createElement('div');
        this.root.className = 'enrol_apply-chooser';

        const field = document.createElement('div');
        field.className = 'enrol_apply-chooser-field';

        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.id = `${base}-input`;
        this.input.className = 'form-control enrol_apply-chooser-input';
        this.input.autocomplete = 'off';
        this.input.spellcheck = false;
        this.input.placeholder = strings.chosengroupplaceholder;
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-controls', `${base}-list`);
        if (select.dataset.required === '1') {
            this.input.setAttribute('aria-required', 'true');
        }
        // The error formslib writes under the select describes the input now.
        if (select.getAttribute('aria-describedby')) {
            this.input.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
        }
        if (select.classList.contains('is-invalid')) {
            this.input.classList.add('is-invalid');
            this.input.setAttribute('aria-invalid', 'true');
        }

        this.toggle = document.createElement('button');
        this.toggle.type = 'button';
        this.toggle.className = 'enrol_apply-chooser-toggle';
        this.toggle.tabIndex = -1;
        this.toggle.setAttribute('aria-label', strings.chosengroupshow);
        this.toggle.setAttribute('aria-controls', `${base}-list`);
        const icon = document.createElement('i');
        icon.className = 'icon fa fa-chevron-down fa-fw';
        icon.setAttribute('aria-hidden', 'true');
        this.toggle.append(icon);

        field.append(this.input, this.toggle);

        this.list = document.createElement('ul');
        this.list.id = `${base}-list`;
        this.list.className = 'enrol_apply-chooser-list';
        this.list.setAttribute('role', 'listbox');
        this.list.hidden = true;

        this.live = document.createElement('div');
        this.live.className = 'visually-hidden';
        this.live.setAttribute('aria-live', 'polite');

        // The select's label names the input and the list.
        const label = select.id ? select.form?.querySelector(`label[for="${select.id}"]`) : null;
        if (label) {
            label.id = label.id || `${base}-label`;
            label.htmlFor = this.input.id;
            this.list.setAttribute('aria-labelledby', label.id);
        }

        this.root.append(field, this.list, this.live);
        select.after(this.root);
        select.hidden = true;
        select.dataset.enrolApplyEnhanced = '1';

        this.showChoice();
        this.wire();
    }

    /**
     * The group the select holds now, or null for the required mode's empty placeholder.
     *
     * @returns {Object|null} From readOptions().
     */
    chosen() {
        return this.groups.find((group) => group.value === this.select.value) || null;
    }

    /**
     * Show the chosen group's name in the input, with nothing typed.
     */
    showChoice() {
        const chosen = this.chosen();
        this.input.value = chosen ? chosen.name : '';
    }

    /**
     * Listen to the input, the toggle and the list.
     */
    wire() {
        this.input.addEventListener('click', () => this.openList());
        this.input.addEventListener('input', () => {
            this.query = this.input.value;
            this.active = -1;
            this.openList(false);
            this.render();
        });
        this.input.addEventListener('keydown', (e) => this.onKey(e));
        // Leaving the input closes the list; a press on a row keeps the focus (see render()).
        this.input.addEventListener('blur', () => this.closeList());

        this.toggle.addEventListener('mousedown', (e) => {
            e.preventDefault();
            if (this.open) {
                this.closeList();
            } else {
                this.input.focus();
                this.openList();
            }
        });
    }

    /**
     * Keyboard: Down and Up move through the rows that can be chosen, Enter chooses, Escape closes.
     *
     * Escape stops there while the list is open, so it closes the list and not the modal.
     *
     * @param {KeyboardEvent} e The key event.
     */
    onKey(e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!this.open) {
                this.openList();
            }
            this.move(e.key === 'ArrowDown' ? 1 : -1);
        } else if (e.key === 'Enter' && this.open) {
            // Never the form's implicit submission while the list is open.
            e.preventDefault();
            this.choose(this.active);
        } else if (e.key === 'Escape' && this.open) {
            e.preventDefault();
            e.stopPropagation();
            this.closeList();
        }
    }

    /**
     * Open the list.
     *
     * @param {Boolean} [clear=true] Whether to empty the input first, to show the opening list.
     */
    openList(clear = true) {
        if (this.open) {
            return;
        }
        this.open = true;
        if (clear) {
            this.input.value = '';
            this.query = '';
            this.active = -1;
        }
        this.input.setAttribute('aria-expanded', 'true');
        this.list.hidden = false;
        this.render();
    }

    /**
     * Close the list and show the chosen group again.
     */
    closeList() {
        if (!this.open) {
            return;
        }
        this.open = false;
        this.active = -1;
        this.query = '';
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
        this.list.hidden = true;
        this.list.replaceChildren();
        this.showChoice();
    }

    /**
     * Move the active row to the next one that can be chosen, wrapping.
     *
     * @param {Number} delta 1 for down, -1 for up.
     */
    move(delta) {
        const count = this.items.length;
        let index = this.active;
        for (let step = 0; step < count; step++) {
            index = (index + delta + count) % count;
            if (!this.items[index].full) {
                this.active = index;
                break;
            }
        }
        this.render(false);
    }

    /**
     * Choose a row: the select takes its value, and the list closes.
     *
     * @param {Number} index The row's index in this.items.
     */
    choose(index) {
        const group = this.items[index];
        if (!group || group.full) {
            return;
        }
        this.select.value = group.value;
        // Formslib's own listeners clear the element's error on a change.
        this.select.dispatchEvent(new Event('change', {bubbles: true}));
        this.input.classList.remove('is-invalid');
        this.input.removeAttribute('aria-invalid');
        this.closeList();
    }

    /**
     * Draw the list for what is typed now, and say the count in the live region.
     *
     * @param {Boolean} [announce=true] Whether the rows changed, so the count is said again; a move
     *     of the active row changes nothing worth repeating.
     */
    render(announce = true) {
        const query = this.query;
        const {items, note} = listModel(this.groups, query, this.strings);
        this.items = items;
        if (this.active >= items.length) {
            this.active = -1;
        }

        const rows = [];
        items.forEach((group, index) => {
            if (query.trim() !== '' && group.full && (index === 0 || !items[index - 1].full)) {
                const heading = document.createElement('li');
                heading.className = 'enrol_apply-chooser-heading';
                heading.setAttribute('role', 'presentation');
                heading.textContent = this.strings.chosengroupfull;
                rows.push(heading);
            }
            rows.push(this.row(group, index, query));
        });
        if (note) {
            const line = document.createElement('li');
            line.className = 'enrol_apply-chooser-note';
            line.setAttribute('role', 'presentation');
            line.textContent = note;
            rows.push(line);
        }
        this.list.replaceChildren(...rows);

        if (announce) {
            const count = items.filter((group) => !group.none).length;
            const said = count === 1 ? this.strings.chosengroupcountone : fill(this.strings.chosengroupcount, count);
            this.live.textContent = note ? `${said} ${note}` : said;
        }

        if (this.active >= 0) {
            const active = this.list.querySelector(`#${this.list.id}-${this.active}`);
            this.input.setAttribute('aria-activedescendant', active.id);
            active.scrollIntoView({block: 'nearest'});
        } else {
            this.input.removeAttribute('aria-activedescendant');
        }
    }

    /**
     * One option row: the name, then the places as a status label.
     *
     * @param {Object} group From readOptions().
     * @param {Number} index Its index in this.items.
     * @param {String} query What the applicant typed.
     * @returns {HTMLLIElement} The row.
     */
    row(group, index, query) {
        const row = document.createElement('li');
        row.id = `${this.list.id}-${index}`;
        row.className = 'enrol_apply-chooser-option';
        row.setAttribute('role', 'option');
        row.setAttribute('aria-selected', group.value === this.select.value ? 'true' : 'false');
        if (index === this.active) {
            row.classList.add('enrol_apply-chooser-active');
        }
        if (group.full) {
            row.setAttribute('aria-disabled', 'true');
        }

        const name = document.createElement('span');
        name.className = 'enrol_apply-chooser-name';
        if (group.none) {
            name.textContent = group.name;
        } else {
            appendName(name, group.name, query);
        }
        row.append(name);

        const places = placesText(group, this.strings);
        if (places) {
            const status = document.createElement('span');
            status.className = 'enrol_apply-status ' + (group.full ? 'enrol_apply-status--neutral' : 'enrol_apply-status--success');
            status.textContent = places;
            row.append(status);
        }

        // Pressed rather than clicked, and the press kept from the input, so the input keeps the focus.
        row.addEventListener('mousedown', (e) => {
            e.preventDefault();
            this.choose(index);
        });
        return row;
    }
}

/**
 * Enhance every group chooser under a root that is not enhanced yet.
 *
 * Called again on every body the modal renders: a re-render after a validation error brings a new
 * select, holding the posted value, which is enhanced afresh.
 *
 * @param {HTMLElement|Document} root Where to look.
 * @returns {Promise} Resolved once every chooser is built.
 */
export const enhance = (root) => {
    const selects = Array.from(root.querySelectorAll(SELECTORS.select))
        .filter((select) => select.dataset.enrolApplyEnhanced !== '1');
    if (!selects.length) {
        return Promise.resolve();
    }
    // Behat waits on this until the input stands where the select was.
    const pending = new Pending('enrol_apply/group_chooser:enhance');
    return loadStrings()
        .then((strings) => {
            selects.forEach((select) => choosers.set(select, new Chooser(select, strings)));
            return strings;
        })
        .catch(displayException)
        .finally(() => pending.resolve());
};

/**
 * Enhance the choosers of a page, apply.php when JavaScript is on.
 */
export const init = () => {
    enhance(document);
};
