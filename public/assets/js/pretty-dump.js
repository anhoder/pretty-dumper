(() => {
    const dumps = document.querySelectorAll('.pretty-dump');
    if (dumps.length === 0) {
        return;
    }

    let promptOverlay;
    let promptInput;
    let promptResolve;
    let toastContainer;
    let lastSearchTerm = '';

    dumps.forEach((dump) => {
        applyAutoTheme(dump);
        dump._tableMetaEnabled = dump.getAttribute('data-table-meta') === '1';

        dump.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) {
                return;
            }

            const button = target.closest('.node-action');
            if (!button) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            const node = button.closest('[data-node-type]');
            if (!node) {
                return;
            }

            const action = button.getAttribute('data-action');
            if (action === 'search') {
                showPrompt('Search within value', 'Enter text to search', lastSearchTerm).then((term) => {
                    if (typeof term !== 'string' || term.trim() === '') {
                        return;
                    }
                    lastSearchTerm = term;
                    performSearch(dump, node, term);
                });
                return;
            }

            if (action === 'copy') {
                copyNodeJson(node);
                return;
            }

            if (action === 'table') {
                renderNodeTable(node);
                return;
            }
        }, true);
    });

    function applyAutoTheme(dump) {
        const palette = dump.querySelectorAll('.theme-profile');
        if (palette.length === 0) {
            return;
        }

        const preference = dump.getAttribute('data-theme-preference') || 'auto';
        if (preference !== 'auto') {
            dump.setAttribute('data-theme', preference);
            if (dump._tablePanel) {
                dump._tablePanel.setAttribute('data-theme', preference);
            }
            ensureThemeObserver(dump);
            return;
        }

        const rootTheme = document.documentElement.getAttribute('data-theme');
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const targetTheme = rootTheme === 'dark' || rootTheme === 'light'
            ? rootTheme
            : (prefersDark ? 'dark' : 'light');

        dump.setAttribute('data-theme', targetTheme);
        if (dump._tablePanel) {
            dump._tablePanel.setAttribute('data-theme', targetTheme);
        }
        ensureThemeObserver(dump);
    }

    function performSearch(root, scopeNode, term) {
        root.querySelectorAll('[data-node-type].search-result-target').forEach((el) => el.classList.remove('search-result-target'));
        root.querySelectorAll('[data-node-type].search-result-context').forEach((el) => el.classList.remove('search-result-context'));

        const trimmed = typeof term === 'string' ? term.trim() : '';
        if (trimmed === '') {
            return;
        }

        const search = trimmed.toLowerCase();
        const candidates = [scopeNode, ...scopeNode.querySelectorAll('[data-node-type]')];
        let firstHit = null;

        candidates.forEach((candidate) => {
            const label = candidate.querySelector('.node-summary-label') || candidate.querySelector('.node-label');
            const labelText = label?.textContent ? label.textContent.toLowerCase() : '';
            const expressionText = (candidate.getAttribute('data-expression') || '').toLowerCase();
            const jsonText = (candidate.getAttribute('data-json') || '').toLowerCase();

            if (!labelText && !expressionText && !jsonText) {
                return;
            }

            const labelMatch = labelText.includes(search);
            const expressionMatch = expressionText.includes(search);
            const jsonMatch = jsonText.includes(search);
            const isBranch = candidate.matches('details[data-node-type], [data-node-type].node-branch');
            const isDirectMatch = labelMatch || expressionMatch || (jsonMatch && !isBranch);

            if (!isDirectMatch && !jsonMatch) {
                return;
            }

            if (candidate.tagName === 'DETAILS') {
                candidate.open = true;
            }

            if (isDirectMatch) {
                candidate.classList.add('search-result-target');
            } else {
                candidate.classList.add('search-result-context');
            }

            let parent = candidate.parentElement;
            while (parent) {
                if (parent.tagName === 'DETAILS') {
                    parent.open = true;
                }
                if (parent instanceof HTMLElement && parent.hasAttribute('data-node-type') && !parent.classList.contains('search-result-target')) {
                    parent.classList.add('search-result-context');
                }
                parent = parent.parentElement;
            }

            if (isDirectMatch && !firstHit) {
                firstHit = candidate;
            }
        });

        if (firstHit) {
            firstHit.scrollIntoView({ behavior: 'smooth', block: 'center' });
            showToast('Search results highlighted');
        } else {
            showToast(`No matches found for "${trimmed}"`, 'error');
        }
    }

    function copyNodeJson(node) {
        const raw = node.getAttribute('data-json');
        if (!raw) {
            showToast('This value cannot be copied as JSON', 'error');
            return;
        }

        let payload = raw;
        try {
            const parsed = JSON.parse(raw);
            payload = JSON.stringify(parsed, null, 2);
        } catch (error) {
            // ignore, fall back to raw
        }

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(payload)
                .then(() => showToast('Copied JSON to clipboard'))
                .catch(() => fallbackCopy(payload));
            return;
        }

        fallbackCopy(payload);
    }

    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();

        let copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        document.body.removeChild(textarea);
        showToast(copied ? 'Copied JSON to clipboard' : 'Unable to copy value', copied ? 'success' : 'error');
    }

    function ensurePrompt() {
        if (promptOverlay) {
            return;
        }

        promptOverlay = document.createElement('div');
        promptOverlay.className = 'pretty-prompt-overlay';
        Object.assign(promptOverlay.style, {
            position: 'fixed',
            inset: '0',
            background: 'rgba(15, 23, 42, 0.35)',
            display: 'none',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: '9999',
        });

        const dialog = document.createElement('div');
        Object.assign(dialog.style, {
            background: '#ffffff',
            borderRadius: '8px',
            boxShadow: '0 10px 35px rgba(15, 23, 42, 0.25)',
            padding: '20px',
            width: 'min(320px, 90vw)',
            display: 'flex',
            flexDirection: 'column',
            gap: '12px',
            fontFamily: 'inherit',
        });

        const title = document.createElement('div');
        title.className = 'pretty-prompt-title';
        title.style.fontWeight = '600';
        title.style.fontSize = '0.95rem';

        promptInput = document.createElement('input');
        promptInput.type = 'text';
        Object.assign(promptInput.style, {
            padding: '8px 10px',
            border: '1px solid rgba(148, 163, 184, 0.6)',
            borderRadius: '6px',
            fontSize: '0.9rem',
            fontFamily: 'inherit',
        });

        const buttonRow = document.createElement('div');
        Object.assign(buttonRow.style, {
            display: 'flex',
            justifyContent: 'flex-end',
            gap: '8px',
        });

        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.textContent = 'Cancel';
        Object.assign(cancelButton.style, {
            padding: '6px 12px',
            border: '1px solid rgba(148, 163, 184, 0.6)',
            background: '#fff',
            borderRadius: '6px',
            cursor: 'pointer',
        });

        const confirmButton = document.createElement('button');
        confirmButton.type = 'button';
        confirmButton.textContent = 'Search';
        Object.assign(confirmButton.style, {
            padding: '6px 12px',
            border: 'none',
            background: '#2563eb',
            color: '#fff',
            borderRadius: '6px',
            cursor: 'pointer',
        });

        buttonRow.appendChild(cancelButton);
        buttonRow.appendChild(confirmButton);

        dialog.appendChild(title);
        dialog.appendChild(promptInput);
        dialog.appendChild(buttonRow);
        promptOverlay.appendChild(dialog);
        document.body.appendChild(promptOverlay);

        promptOverlay.addEventListener('click', (event) => {
            if (event.target === promptOverlay) {
                closePrompt(null);
            }
        });

        cancelButton.addEventListener('click', () => closePrompt(null));
        confirmButton.addEventListener('click', () => closePrompt(promptInput.value));

        promptInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                closePrompt(promptInput.value);
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                closePrompt(null);
            }
        });

        promptOverlay._title = title;
    }

    function showPrompt(message, placeholder, defaultValue) {
        ensurePrompt();

        promptOverlay._title.textContent = message;
        promptInput.placeholder = placeholder || '';
        promptInput.value = defaultValue || '';
        promptOverlay.style.display = 'flex';
        setTimeout(() => {
            promptInput.focus();
            promptInput.select();
        }, 0);

        return new Promise((resolve) => {
            promptResolve = resolve;
        });
    }

    function closePrompt(value) {
        if (!promptOverlay) {
            return;
        }

        promptOverlay.style.display = 'none';
        if (typeof promptResolve === 'function') {
            const resolver = promptResolve;
            promptResolve = null;
            resolver(value);
        }
    }

    function ensureToastContainer() {
        if (toastContainer) {
            return;
        }

        toastContainer = document.createElement('div');
        Object.assign(toastContainer.style, {
            position: 'fixed',
            right: '16px',
            bottom: '16px',
            display: 'flex',
            flexDirection: 'column',
            gap: '8px',
            zIndex: '10000',
        });

        document.body.appendChild(toastContainer);
    }

    function showToast(message, variant = 'success') {
        ensureToastContainer();

        const toast = document.createElement('div');
        toast.textContent = message;
        Object.assign(toast.style, {
            padding: '10px 14px',
            borderRadius: '6px',
            fontSize: '0.85rem',
            color: '#f8fafc',
            background: variant === 'error' ? '#dc2626' : '#2563eb',
            boxShadow: '0 8px 20px rgba(15, 23, 42, 0.25)',
            opacity: '0',
            transition: 'opacity 0.25s ease',
        });

        toastContainer.appendChild(toast);
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
        });

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.remove();
            }, 250);
        }, 2500);
    }

    function renderNodeTable(node) {
        const raw = node.getAttribute('data-json');
        if (!raw) {
            showToast('Table view unavailable for this value', 'error');
            return;
        }

        const dataset = buildTabularDataset(raw);
        if (!dataset) {
            showToast('Not a compatible 2D array structure', 'error');
            return;
        }

        const dump = node.closest('.pretty-dump');
        if (!dump) {
            return;
        }

        const panel = ensureTablePanel(dump);
        const showMeta = dump._tableMetaEnabled === true;

        const label = node.querySelector('.node-summary-label, .node-label');
        const title = label && label.textContent ? label.textContent.trim() : 'Array';
        const expression = node.getAttribute('data-expression') || title;

        let card = null;
        panel.querySelectorAll('.pretty-dump-table-card').forEach((existingCard) => {
            if (!card && existingCard.dataset.expression === expression) {
                card = existingCard;
            }
        });

        if (!card) {
            card = document.createElement('section');
            card.className = 'pretty-dump-table-card';
            card.dataset.expression = expression;

            const header = document.createElement('div');
            header.className = 'pretty-dump-table-header';

            const heading = document.createElement('div');
            heading.className = 'pretty-dump-table-heading';

            const titleEl = document.createElement('div');
            titleEl.className = 'pretty-dump-table-title';
            heading.appendChild(titleEl);

            let metaEl = null;
            if (showMeta) {
                metaEl = document.createElement('div');
                metaEl.className = 'pretty-dump-table-meta';
                heading.appendChild(metaEl);
            }

            header.appendChild(heading);

            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'pretty-dump-table-close';
            closeButton.setAttribute('aria-label', 'Remove table view');
            closeButton.textContent = '✕';
            closeButton.addEventListener('click', () => {
                if (card.parentNode) {
                    card.parentNode.removeChild(card);
                }
                if (dump._tablePanel && !dump._tablePanel.children.length) {
                    dump._tablePanel.remove();
                    dump._tablePanel = null;
                }
            });
            header.appendChild(closeButton);

            const container = document.createElement('div');
            container.className = 'pretty-dump-table-container';

            card.appendChild(header);
            card.appendChild(container);
            panel.appendChild(card);

            card._headingEl = heading;
            card._titleEl = titleEl;
            card._metaEl = metaEl;
        } else {
            if (!card._headingEl) {
                card._headingEl = card.querySelector('.pretty-dump-table-heading');
            }
            if (!card._titleEl) {
                card._titleEl = card.querySelector('.pretty-dump-table-title');
            }
            if (showMeta) {
                if (!card._metaEl) {
                    const holder = card._headingEl || card.querySelector('.pretty-dump-table-heading');
                    if (holder) {
                        card._metaEl = document.createElement('div');
                        card._metaEl.className = 'pretty-dump-table-meta';
                        holder.appendChild(card._metaEl);
                    }
                }
            } else if (card._metaEl) {
                if (card._metaEl.parentNode) {
                    card._metaEl.parentNode.removeChild(card._metaEl);
                }
                card._metaEl = null;
            }
        }

        if (card._titleEl) {
            card._titleEl.textContent = title;
        }

        if (card._metaEl) {
            card._metaEl.textContent = expression || '';
        }

        let containerNode = card.querySelector('.pretty-dump-table-container');
        if (!containerNode) {
            containerNode = document.createElement('div');
            containerNode.className = 'pretty-dump-table-container';
            card.appendChild(containerNode);
        }
        containerNode.innerHTML = '';

        const table = document.createElement('table');
        table.className = 'pretty-dump-table';

        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        dataset.columns.forEach((column) => {
            const th = document.createElement('th');
            th.textContent = column;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);

        const tbody = document.createElement('tbody');
        dataset.rows.forEach((row) => {
            const tr = document.createElement('tr');
            row.forEach((cell) => {
                const td = document.createElement('td');
                td.textContent = cell;
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });

        table.appendChild(thead);
        table.appendChild(tbody);
        containerNode.appendChild(table);

        panel.appendChild(card);
        requestAnimationFrame(() => {
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

    function ensureTablePanel(dump) {
        if (dump._tablePanel && dump._tablePanel.parentNode) {
            return dump._tablePanel;
        }

        const panel = document.createElement('div');
        panel.className = 'pretty-dump-table-panel';
        const currentTheme = dump.getAttribute('data-theme');
        const preference = dump.getAttribute('data-theme-preference');
        if (currentTheme) {
            panel.setAttribute('data-theme', currentTheme);
        } else if (preference) {
            panel.setAttribute('data-theme', preference);
        }
        dump.appendChild(panel);
        dump._tablePanel = panel;
        ensureThemeObserver(dump);
        return panel;
    }

    function ensureThemeObserver(dump) {
        if (dump._themeObserver) {
            return;
        }

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme' && dump._tablePanel) {
                    const nextTheme = dump.getAttribute('data-theme');
                    if (nextTheme) {
                        dump._tablePanel.setAttribute('data-theme', nextTheme);
                    } else {
                        dump._tablePanel.removeAttribute('data-theme');
                    }
                }
            });
        });

        observer.observe(dump, { attributes: true, attributeFilter: ['data-theme'] });
        dump._themeObserver = observer;
    }

    const rootThemeObserver = new MutationObserver(() => {
        const theme = document.documentElement.getAttribute('data-theme');
        if (!theme) {
            return;
        }

        dumps.forEach((dump) => {
            const preference = dump.getAttribute('data-theme-preference') || 'auto';
            if (preference !== 'auto') {
                return;
            }

            dump.setAttribute('data-theme', theme);
            if (dump._tablePanel) {
                dump._tablePanel.setAttribute('data-theme', theme);
            }
        });
    });

    rootThemeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    function buildTabularDataset(raw) {
        let parsed;
        try {
            parsed = JSON.parse(raw);
        } catch (error) {
            return null;
        }

        const rows = normaliseRows(parsed);
        if (!rows || rows.length === 0) {
            return null;
        }

        const columnOrder = [];
        const columnSet = new Set();
        const normalisedRows = [];

        for (const row of rows) {
            const record = normaliseRow(row);
            if (!record) {
                return null;
            }

            normalisedRows.push(record);

            Object.keys(record).forEach((key) => {
                if (!columnSet.has(key)) {
                    columnSet.add(key);
                    columnOrder.push(key);
                }
            });
        }

        if (columnOrder.length === 0) {
            return null;
        }

        const tableRows = normalisedRows.map((record) =>
            columnOrder.map((column) => formatCellValue(record[column]))
        );

        return { columns: columnOrder, rows: tableRows };
    }

    function normaliseRows(value) {
        if (Array.isArray(value)) {
            return value;
        }

        if (value && typeof value === 'object') {
            if (Array.isArray(value.__items__)) {
                return value.__items__;
            }

            return Object.keys(value)
                .filter((key) => !key.startsWith('__'))
                .map((key) => value[key]);
        }

        return null;
    }

    function normaliseRow(row) {
        let workingRow = row;

        if (workingRow && typeof workingRow === 'object' && !Array.isArray(workingRow) && Array.isArray(workingRow.__items__)) {
            workingRow = workingRow.__items__;
        }

        const record = {};

        if (Array.isArray(workingRow)) {
            workingRow.forEach((value, index) => {
                record[String(index)] = value;
            });
        } else if (workingRow && typeof workingRow === 'object') {
            if (workingRow.properties && typeof workingRow.properties === 'object' && !Array.isArray(workingRow.properties)) {
                Object.keys(workingRow.properties).forEach((key) => {
                    if (key.startsWith('__')) {
                        return;
                    }
                    record[key] = workingRow.properties[key];
                });
            }

            Object.keys(workingRow).forEach((key) => {
                if (key === 'properties' || key.startsWith('__')) {
                    return;
                }
                record[key] = workingRow[key];
            });
        } else {
            return null;
        }

        return Object.keys(record).length ? record : null;
    }

    function formatCellValue(value) {
        if (value === undefined) {
            return '';
        }

        if (value === null) {
            return 'null';
        }

        if (typeof value === 'boolean') {
            return value ? 'true' : 'false';
        }

        if (typeof value === 'object') {
            try {
                return JSON.stringify(value);
            } catch (error) {
                return Object.prototype.toString.call(value);
            }
        }

        return String(value);
    }
})();
