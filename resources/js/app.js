document.addEventListener('alpine:init', () => {
    Alpine.data('moveParentPicker', (config = {}) => ({
        open: false,
        query: '',
        selectedId: config.selectedId ?? null,
        allowNoParent: config.allowNoParent ?? false,
        options: config.options ?? [],

        get selectedLabel() {
            if (this.selectedId === null || this.selectedId === '') {
                return this.allowNoParent ? 'No parent (top level)' : 'Select parent';
            }

            const match = this.options.find((o) => String(o.id) === String(this.selectedId));

            return match ? match.label : 'Select parent';
        },

        get filtered() {
            const q = this.query.trim().toLowerCase();

            if (q === '') {
                return this.options;
            }

            return this.options.filter((o) => o.search.includes(q));
        },

        select(id) {
            this.selectedId = id;
            this.query = '';
            this.open = false;
            this.$wire.set('moveParentId', id);
        },
    }));

    Alpine.data('gnDivisionPicker', (config = {}) => ({
        open: false,
        query: '',
        selected: config.selected ?? '',
        options: config.options ?? [],
        disabled: config.disabled ?? false,
        wireProperty: config.wireProperty ?? 'gnDivision',

        get selectedLabel() {
            return this.selected !== '' ? this.selected : 'Select';
        },

        get filtered() {
            const q = this.query.trim().toLowerCase();

            if (q === '') {
                return this.options;
            }

            return this.options.filter((name) => String(name).toLowerCase().includes(q));
        },

        select(name) {
            this.selected = name ?? '';
            this.query = '';
            this.open = false;
            this.$wire.set(this.wireProperty, this.selected);
        },
    }));

    Alpine.data('searchableSelect', (config = {}) => ({
        open: false,
        query: '',
        selected: config.selected ?? '',
        options: config.options ?? [],
        model: config.model ?? '',
        emptyLabel: config.emptyLabel ?? 'Select',
        disabled: config.disabled ?? false,

        get selectedLabel() {
            if (this.selected === '' || this.selected === null || this.selected === undefined) {
                return '';
            }

            const match = this.options.find((o) => String(o.id) === String(this.selected));

            return match ? match.label : '';
        },

        get inputValue() {
            return this.open ? this.query : this.selectedLabel;
        },

        get filtered() {
            const q = this.query.trim().toLowerCase();

            if (q === '') {
                return this.options;
            }

            return this.options.filter((o) => String(o.label).toLowerCase().includes(q));
        },

        openPanel() {
            if (this.disabled) {
                return;
            }

            this.open = true;
            this.query = this.selectedLabel;
            this.$nextTick(() => {
                const input = this.$refs.input;
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },

        onInput(event) {
            if (this.disabled) {
                return;
            }

            this.query = event.target.value;
            this.open = true;
        },

        close() {
            this.open = false;
            this.query = '';
        },

        select(id) {
            this.selected = id === null || id === undefined ? '' : id;
            this.query = '';
            this.open = false;
            this.$wire.set(this.model, this.selected === '' ? '' : this.selected);
        },
    }));

    Alpine.data('inlineTable', () => ({
        editingCell: null,
        editValue: '',
        rows: [],
        sortCol: null,
        sortDir: 'asc',

        startEdit(rowIdx, col, val) {
            this.editingCell = { rowIdx, col };
            this.editValue = val;
            this.$nextTick(() => {
                const input = this.$el.querySelector('input.cell-input');
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },

        commitEdit() {
            if (!this.editingCell) {
                return;
            }
            this.rows[this.editingCell.rowIdx][this.editingCell.col] = this.editValue;
            this.editingCell = null;
        },

        cancelEdit() {
            this.editingCell = null;
        },

        isEditing(rowIdx, col) {
            return this.editingCell?.rowIdx === rowIdx && this.editingCell?.col === col;
        },

        sortBy(col) {
            if (this.sortCol === col) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortCol = col;
                this.sortDir = 'asc';
            }
        },

        get sortedRows() {
            if (!this.sortCol) {
                return this.rows;
            }

            const col = this.sortCol;
            const dir = this.sortDir;

            return [...this.rows].sort((a, b) => {
                const aVal = String(a[col] ?? '');
                const bVal = String(b[col] ?? '');
                const cmp = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
                return dir === 'asc' ? cmp : -cmp;
            });
        },
    }));
});
