<script>
    (() => {
        // Narrow a list of tables as you type; without scripts the field stays hidden.
        document.querySelectorAll('[data-db-filter]').forEach((input) => {
            const scope = input.closest('[data-db-filter-scope]') || document;
            const items = Array.from(scope.querySelectorAll('[data-db-item]'));
            const empty = scope.querySelector('[data-db-filter-empty]');
            const wrap = input.closest('[data-db-filter-wrap]');

            if (wrap) {
                wrap.hidden = false;
            }

            input.addEventListener('input', () => {
                const term = input.value.trim().toLowerCase();
                let shown = 0;

                items.forEach((item) => {
                    const match = term === '' || (item.dataset.name || '').includes(term);
                    item.hidden = !match;
                    shown += match ? 1 : 0;
                });

                if (empty) {
                    empty.hidden = shown > 0;
                }
            });
        });
    })();
</script>
