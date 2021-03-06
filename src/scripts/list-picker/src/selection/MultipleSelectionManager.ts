/**
 * Copyright (c) Enalean, 2020 - present. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

import { ListPickerItem, ListPickerSelectionStateMultiple, SelectionManager } from "../type";
import { DropdownToggler } from "../helpers/DropdownToggler";
import { sanitize } from "dompurify";
import { GetText } from "../../../tuleap/gettext/gettext-init";
import { findListPickerItemInItemMap } from "../helpers/list-picker-items-helper";

export class MultipleSelectionManager implements SelectionManager {
    private readonly selection_state: ListPickerSelectionStateMultiple;
    private readonly clear_selection_state_button_element: Element;

    constructor(
        private readonly source_select_box: HTMLSelectElement,
        private readonly selection_element: Element,
        private readonly search_field_element: HTMLInputElement,
        private readonly placeholder_text: string,
        private readonly dropdown_toggler: DropdownToggler,
        private readonly item_map: Map<string, ListPickerItem>,
        private readonly gettext_provider: GetText
    ) {
        this.selection_state = {
            selected_items: new Map(),
            selected_value_elements: new Map(),
        };

        this.clear_selection_state_button_element = this.createClearSelectionStateButton();
    }

    public processSelection(item: Element): void {
        const list_item = findListPickerItemInItemMap(this.item_map, item.id);
        if (list_item.is_selected) {
            this.removeListItemFromSelection(list_item);
            this.togglePlaceholder();
            this.toggleClearValuesButton();
            return;
        }

        this.selection_state.selected_items.set(list_item.id, list_item);
        const badge = this.createItemBadgeElement(list_item);
        this.selection_state.selected_value_elements.set(list_item.id, badge);

        this.selection_element.insertBefore(badge, this.search_field_element.parentElement);
        list_item.is_selected = true;
        list_item.element.setAttribute("aria-selected", "true");
        list_item.target_option.setAttribute("selected", "selected");

        this.togglePlaceholder();
        this.toggleClearValuesButton();
    }

    public initSelection(): void {
        this.source_select_box.querySelectorAll("option[selected]").forEach((option: Element) => {
            if (!(option instanceof HTMLElement)) {
                return;
            }

            const item_id = option.dataset?.itemId;
            if (item_id) {
                this.processSelection(findListPickerItemInItemMap(this.item_map, item_id).element);
            }
        });
    }

    public handleBackspaceKey(event: KeyboardEvent): void {
        const nb_selected_items = this.selection_state.selected_items.size;
        if (nb_selected_items === 0 && this.search_field_element.value.length === 1) {
            // User has deleted the last letter of the query, and no item is selected so let's only display the placeholder
            this.togglePlaceholder();
            return;
        }

        if (nb_selected_items === 0 || this.search_field_element.value !== "") {
            // Either there is no selected item anymore, either the user is deleting the query, so do nothing
            return;
        }

        const last_selected_item = Array.from(this.selection_state.selected_items.values())[
            this.selection_state.selected_items.size - 1
        ];

        this.removeListItemFromSelection(last_selected_item);
        this.toggleClearValuesButton();

        this.search_field_element.value = last_selected_item.template;
        event.preventDefault();
        event.cancelBubble = true;
    }

    private togglePlaceholder(): void {
        if (this.selection_state.selected_value_elements.size === 0) {
            this.search_field_element.setAttribute("placeholder", this.placeholder_text);
            // Add the "remove all" cross
            return;
        }

        this.search_field_element.removeAttribute("placeholder");
    }

    private toggleClearValuesButton(): void {
        if (this.source_select_box.disabled) {
            return;
        }

        if (
            this.selection_state.selected_value_elements.size === 0 &&
            this.selection_element.contains(this.clear_selection_state_button_element)
        ) {
            this.selection_element.removeChild(this.clear_selection_state_button_element);
            return;
        }

        if (!this.selection_element.contains(this.clear_selection_state_button_element)) {
            this.selection_element.insertAdjacentElement(
                "beforeend",
                this.clear_selection_state_button_element
            );
        }
    }

    private createClearSelectionStateButton(): Element {
        const remove_value_button = document.createElement("span");
        remove_value_button.classList.add("list-picker-selected-value-remove-button");
        remove_value_button.innerHTML = sanitize("&times");
        remove_value_button.setAttribute(
            "title",
            this.gettext_provider.gettext("Remove all values")
        );

        remove_value_button.addEventListener("click", (event: Event) => {
            event.preventDefault();
            event.cancelBubble = true;

            this.clearSelectionState();
            this.togglePlaceholder();
            this.toggleClearValuesButton();
            this.dropdown_toggler.openListPicker();
        });

        return remove_value_button;
    }

    private createItemBadgeElement(list_item: ListPickerItem): Element {
        const remove_badge_button = document.createElement("span");
        remove_badge_button.innerHTML = sanitize("&times");
        remove_badge_button.setAttribute("role", "presentation");
        remove_badge_button.classList.add("list-picker-value-remove-button");

        const badge = document.createElement("span");
        badge.classList.add("list-picker-badge");
        badge.appendChild(remove_badge_button);
        badge.appendChild(document.createTextNode(list_item.template));
        badge.setAttribute("title", list_item.template);

        if (this.source_select_box.disabled) {
            return badge;
        }

        remove_badge_button.addEventListener("click", (event: Event) => {
            event.preventDefault();
            event.cancelBubble = true;

            this.processSelection(list_item.element);
            this.dropdown_toggler.openListPicker();
        });

        return badge;
    }

    private removeListItemFromSelection(list_item: ListPickerItem): void {
        const badge = this.selection_state.selected_value_elements.get(list_item.id);
        const selected_item = this.selection_state.selected_items.get(list_item.id);

        if (!badge || !selected_item) {
            throw new Error("Item not found in selection state.");
        }

        this.selection_element.removeChild(badge);
        this.selection_state.selected_value_elements.delete(list_item.id);
        this.selection_state.selected_items.delete(list_item.id);

        list_item.is_selected = false;
        list_item.element.setAttribute("aria-selected", "false");
        list_item.target_option.removeAttribute("selected");
    }

    private clearSelectionState(): void {
        Array.from(this.selection_state.selected_items.values()).forEach((item) => {
            this.removeListItemFromSelection(item);
        });
    }
}
