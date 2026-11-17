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
