document.addEventListener('alpine:init', () => {
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
