/* global aigDeepResearchAdmin */
document.addEventListener('DOMContentLoaded', () => {
    const config = window.aigDeepResearchAdmin || {};
    const restUrl = (config.restUrl || '').replace(/\/$/, '');
    const nonce = config.nonce || '';
    const i18n = config.i18n || {};

    const form = document.getElementById('aig-deep-research-form');
    const runsRoot = document.getElementById('aig-dr-runs');
    const refreshButton = document.getElementById('aig-dr-refresh-runs');
    const formStatus = document.getElementById('aig-dr-form-status');

    const mainTabs = Array.from(document.querySelectorAll('.aig-dr-main-tab'));
    const mainPanels = Array.from(document.querySelectorAll('.aig-dr-main-panel'));
    const sourcePanels = Array.from(document.querySelectorAll('.aig-dr-source-panel'));

    const sourceNameInput = document.getElementById('aig-dr-source-name');
    const sourceLabelInput = document.getElementById('aig-dr-source-label');
    const sourceUrlInput = document.getElementById('aig-dr-source-url');
    const sourceAuthorizationInput = document.getElementById('aig-dr-source-authorization');
    const sourceActiveInput = document.getElementById('aig-dr-source-active');
    const sourceSaveButton = document.getElementById('aig-dr-save-source');
    const sourceStatus = document.getElementById('aig-dr-source-status');
    const sourceList = document.getElementById('aig-dr-sources-list');
    const sourceOptions = document.getElementById('aig-dr-source-options');

    const vectorStoreNameInput = document.getElementById('aig-dr-vector-store-name');
    const vectorStoreCreateButton = document.getElementById('aig-dr-create-vector-store');
    const vectorStoreStatus = document.getElementById('aig-dr-vector-store-status');
    const vectorStoreList = document.getElementById('aig-dr-vector-stores-list');
    const vectorStoreOptions = document.getElementById('aig-dr-vector-store-options');

    const webhookDetails = document.getElementById('aig-dr-webhook-details');

    const webToggle = form.querySelector('input[name="web_search_enabled"]');
    const fileToggle = form.querySelector('input[name="file_search_enabled"]');
    const mcpToggle = form.querySelector('input[name="mcp_enabled"]');
    const codeToggle = form.querySelector('input[name="code_interpreter_enabled"]');

    const allowAllCheckbox = form.querySelector('input[name="web_domain_allow_all"]');
    const allowOnlyCheckbox = form.querySelector('input[name="web_domain_allow_only"]');
    const blockCheckbox = form.querySelector('input[name="web_domain_block"]');
    const allowDomainsTextarea = form.querySelector('textarea[name="web_domain_allowlist"]');
    const blockDomainsTextarea = form.querySelector('textarea[name="web_domain_blocklist"]');

    let savedSources = [];
    let vectorStores = [];
    let runsPollTimer = null;

    async function request(path, options = {}) {
        const response = await fetch(restUrl + path, {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: options.body ? JSON.stringify(options.body) : undefined,
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error((data && data.message) || i18n.error || 'Request failed.');
        }

        return data;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function checkedValues(root, name) {
        return Array.from(root.querySelectorAll(`input[name="${name}"]:checked`)).map((input) => input.value);
    }

    function formatNumber(value) {
        const number = Number(value);
        return Number.isFinite(number) ? number.toLocaleString() : (i18n.na || 'n/a');
    }

    function formatNullableNumber(value) {
        if (value === null || value === undefined || value === '') {
            return i18n.na || 'n/a';
        }

        return formatNumber(value);
    }

    function formatRunTimestamp(value) {
        if (!value) {
            return '';
        }

        const normalized = String(value).replace(' ', 'T');
        const date = new Date(normalized.endsWith('Z') ? normalized : `${normalized}Z`);

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function getToolLabel(type) {
        const labels = {
            web_search_call: 'Web Search',
            code_interpreter_call: 'Code Interpreter',
            mcp_tool_call: 'MCP',
            file_search_call: 'File Search',
        };

        return labels[type] || type;
    }

    function stopRunsPolling() {
        if (runsPollTimer) {
            window.clearTimeout(runsPollTimer);
            runsPollTimer = null;
        }
    }

    function scheduleRunsPolling(hasActiveRuns) {
        stopRunsPolling();

        if (!hasActiveRuns) {
            return;
        }

        runsPollTimer = window.setTimeout(() => {
            loadRuns(true);
        }, 4000);
    }

    function selectMainTab(tabName) {
        mainTabs.forEach((tab) => {
            const isActive = tab.dataset.mainTab === tabName;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        mainPanels.forEach((panel) => {
            panel.classList.toggle('is-active', panel.dataset.mainPanel === tabName);
        });
    }

    function getSourcePanel(name) {
        return document.querySelector(`.aig-dr-source-panel[data-source-panel="${name}"]`);
    }

    function setSourcePanelEnabled(name, enabled, expand) {
        const panel = getSourcePanel(name);
        if (!panel) {
            return;
        }

        panel.classList.toggle('is-enabled', enabled);
        panel.classList.toggle('is-disabled', !enabled);
        panel.classList.toggle('is-open', enabled && expand);

        const trigger = panel.querySelector('.aig-dr-source-trigger');
        if (trigger) {
            trigger.textContent = enabled && expand ? 'Collapse' : 'Expand';
            trigger.setAttribute('aria-expanded', enabled && expand ? 'true' : 'false');
        }

        panel.querySelectorAll('.aig-dr-source-body input, .aig-dr-source-body textarea, .aig-dr-source-body select, .aig-dr-source-body button').forEach((control) => {
            control.disabled = !enabled;
        });
    }

    function syncDomainModeState() {
        const webEnabled = !!webToggle.checked;
        const mode = allowOnlyCheckbox.checked ? 'allow_only' : (blockCheckbox.checked ? 'block' : 'allow_all');

        allowAllCheckbox.checked = mode === 'allow_all';
        allowOnlyCheckbox.checked = mode === 'allow_only';
        blockCheckbox.checked = mode === 'block';

        allowDomainsTextarea.disabled = !webEnabled || mode !== 'allow_only';
        blockDomainsTextarea.disabled = !webEnabled || mode !== 'block';
    }

    function applySourcePanelStateFromInputs() {
        setSourcePanelEnabled('web', !!webToggle.checked, !!webToggle.checked);
        setSourcePanelEnabled('files', !!fileToggle.checked, false);
        setSourcePanelEnabled('mcp', !!mcpToggle.checked, false);
        setSourcePanelEnabled('code', !!codeToggle.checked, false);

        if (webToggle.checked) {
            getSourcePanel('web').classList.add('is-open');
            const trigger = getSourcePanel('web').querySelector('.aig-dr-source-trigger');
            trigger.textContent = 'Collapse';
            trigger.setAttribute('aria-expanded', 'true');
        }

        syncDomainModeState();
        enforceVectorStoreLimit();
    }

    function getSelectedWebDomainMode() {
        if (allowOnlyCheckbox.checked) {
            return 'allow_only';
        }

        if (blockCheckbox.checked) {
            return 'block';
        }

        return 'allow_all';
    }

    function collectFormData() {
        const data = new FormData(form);

        return {
            title: data.get('title') || '',
            prompt: data.get('prompt') || '',
            model: data.get('model') || '',
            max_tool_calls: Number(data.get('max_tool_calls') || 12),
            background: data.get('background') === '1',
            web_search_enabled: webToggle.checked,
            web_domain_mode: getSelectedWebDomainMode(),
            web_domain_allowlist: allowDomainsTextarea.value || '',
            web_domain_blocklist: blockDomainsTextarea.value || '',
            vector_store_ids: fileToggle.checked ? checkedValues(form, 'vector_store_ids[]') : [],
            saved_source_ids: mcpToggle.checked ? checkedValues(form, 'saved_source_ids[]') : [],
            code_interpreter_enabled: codeToggle.checked,
            code_memory_limit: data.get('code_memory_limit') || '',
        };
    }

    function resetResearchForm() {
        form.reset();
        webToggle.checked = true;
        fileToggle.checked = false;
        mcpToggle.checked = false;
        codeToggle.checked = false;
        allowAllCheckbox.checked = true;
        allowOnlyCheckbox.checked = false;
        blockCheckbox.checked = false;
        selectMainTab('context');
        applySourcePanelStateFromInputs();
        renderVectorStoreOptions();
        renderSourceOptions();
    }

    function renderWebhook(webhook) {
        if (!webhookDetails) {
            return;
        }

        if (!webhook || !webhook.url) {
            webhookDetails.innerHTML = '<p class="description">Webhook details unavailable.</p>';
            return;
        }

        webhookDetails.innerHTML = `
            <div class="aig-dr-webhook-card">
                <p><strong>Endpoint:</strong> <code>${escapeHtml(webhook.url)}</code></p>
                <p><strong>Enabled:</strong> ${webhook.enabled ? 'Yes' : 'No'}</p>
                <p><strong>Secret configured:</strong> ${webhook.secret_configured ? 'Yes' : 'No'}</p>
                <p><strong>Verification:</strong> ${escapeHtml(webhook.verification || 'none')}</p>
            </div>
        `;
    }

    function renderSourceOptions() {
        if (!sourceOptions) {
            return;
        }

        if (!savedSources.length) {
            sourceOptions.innerHTML = '<p class="description">No saved MCP sources yet.</p>';
            return;
        }

        sourceOptions.innerHTML = savedSources.map((source) => {
            const cfg = source.config || {};
            return `
                <label class="aig-dr-option-item">
                    <input type="checkbox" name="saved_source_ids[]" value="${escapeHtml(source.id)}" ${source.status === 'active' ? '' : 'disabled'}>
                    <span>
                        <strong>${escapeHtml(source.name)}</strong>
                        <small>${escapeHtml(cfg.server_url || '')}</small>
                    </span>
                </label>
            `;
        }).join('');
    }

    function renderSourcesList() {
        if (!sourceList) {
            return;
        }

        if (!savedSources.length) {
            sourceList.innerHTML = '<p class="description">No saved MCP sources yet.</p>';
            renderSourceOptions();
            return;
        }

        sourceList.innerHTML = savedSources.map((source) => {
            const cfg = source.config || {};
            return `
                <article class="aig-dr-managed-card" data-source-id="${source.id}">
                    <div class="aig-dr-managed-head">
                        <div>
                            <h3>${escapeHtml(source.name)}</h3>
                            <p class="aig-dr-meta">
                                <span>${escapeHtml(source.source_type || '')}</span>
                                <span>${escapeHtml(source.status || '')}</span>
                                <span>${escapeHtml(cfg.server_label || '')}</span>
                            </p>
                        </div>
                        <button type="button" class="button aig-dr-delete-source">Delete</button>
                    </div>
                    <p><strong>URL:</strong> ${escapeHtml(cfg.server_url || '')}</p>
                </article>
            `;
        }).join('');

        renderSourceOptions();
    }

    function enforceVectorStoreLimit() {
        const inputs = Array.from(document.querySelectorAll('input[name="vector_store_ids[]"]'));
        const checked = inputs.filter((input) => input.checked);

        inputs.forEach((input) => {
            const panelDisabled = !fileToggle.checked;
            input.disabled = panelDisabled || (!input.checked && checked.length >= 2);
        });
    }

    function renderVectorStoreOptions() {
        if (!vectorStoreOptions) {
            return;
        }

        if (!vectorStores.length) {
            vectorStoreOptions.innerHTML = '<p class="description">No vector stores found yet.</p>';
            return;
        }

        vectorStoreOptions.innerHTML = vectorStores.map((store) => `
            <label class="aig-dr-option-item">
                <input type="checkbox" name="vector_store_ids[]" value="${escapeHtml(store.id)}">
                <span>
                    <strong>${escapeHtml(store.name || store.id)}</strong>
                    <small>${escapeHtml(store.id)}</small>
                </span>
            </label>
        `).join('');

        enforceVectorStoreLimit();
    }

    function renderVectorStoresList() {
        if (!vectorStoreList) {
            return;
        }

        if (!vectorStores.length) {
            vectorStoreList.innerHTML = '<p class="description">No vector stores found yet.</p>';
            renderVectorStoreOptions();
            return;
        }

        vectorStoreList.innerHTML = vectorStores.map((store) => {
            const files = store.file_counts || {};
            return `
                <article class="aig-dr-managed-card" data-vector-store-id="${escapeHtml(store.id)}">
                    <div class="aig-dr-managed-head">
                        <div>
                            <h3>${escapeHtml(store.name || store.id)}</h3>
                            <p class="aig-dr-meta">
                                <span>${escapeHtml(store.status || '')}</span>
                                <span>${escapeHtml(store.id || '')}</span>
                            </p>
                        </div>
                        <button type="button" class="button aig-dr-delete-vector-store">Delete</button>
                    </div>
                    <p><strong>Files:</strong> ${escapeHtml(files.total || 0)}</p>
                </article>
            `;
        }).join('');

        renderVectorStoreOptions();
    }

    function renderUsagePanel(run) {
        const usage = run.metrics && run.metrics.usage ? run.metrics.usage : {};
        const estimated = !!usage.estimated;
        const reasoning = usage.reasoning_tokens;

        return `
            <div class="aig-dr-usage-card">
                <div class="aig-dr-usage-head">
                    <strong>${escapeHtml(i18n.tokenUsageTitle || 'Token Usage')}</strong>
                    ${estimated ? `<span class="aig-dr-usage-badge">${escapeHtml(i18n.liveEstimate || 'Live estimate via tiktoken')}</span>` : ''}
                </div>
                <div class="aig-dr-usage-grid">
                    <div><strong>${escapeHtml(i18n.provider || 'Provider')}:</strong> OpenAI</div>
                    <div><strong>${escapeHtml(i18n.model || 'Model')}:</strong> ${escapeHtml(usage.model || run.model || '')}</div>
                    <div><strong>${escapeHtml(i18n.input || 'In')}:</strong> ${escapeHtml(formatNullableNumber(usage.input_tokens))}</div>
                    <div><strong>${escapeHtml(i18n.reasoning || 'Think')}:</strong> ${escapeHtml(formatNullableNumber(reasoning))}</div>
                    <div><strong>${escapeHtml(i18n.output || 'Out')}:</strong> ${escapeHtml(formatNullableNumber(usage.output_tokens))}</div>
                    <div><strong>${escapeHtml(i18n.total || 'Total')}:</strong> ${escapeHtml(formatNullableNumber(usage.total_tokens))}</div>
                </div>
            </div>
        `;
    }

    function renderToolsPanel(run) {
        const toolMetrics = run.metrics && run.metrics.tools ? run.metrics.tools : {};
        const details = toolMetrics.details || {};
        const totalCalls = Number(toolMetrics.total_calls || 0);
        const detailRows = ['web_search_call', 'code_interpreter_call', 'mcp_tool_call', 'file_search_call'].map((type) => {
            const detail = details[type] || {};
            return `
                <tr>
                    <th scope="row">${escapeHtml(getToolLabel(type))}</th>
                    <td>${escapeHtml(formatNumber(detail.count || 0))}</td>
                    <td>${escapeHtml(formatNullableNumber(detail.total_tokens))}</td>
                    <td>${detail.has_usage ? escapeHtml(formatNullableNumber(detail.input_tokens)) : escapeHtml(i18n.na || 'n/a')}</td>
                    <td>${detail.has_usage ? escapeHtml(formatNullableNumber(detail.output_tokens)) : escapeHtml(i18n.na || 'n/a')}</td>
                </tr>
            `;
        }).join('');

        return `
            <details class="aig-dr-tools-panel">
                <summary>
                    <strong>${escapeHtml(i18n.toolUsageTitle || 'Tools')}</strong>
                    <span>${escapeHtml(formatNumber(totalCalls))} total call${totalCalls === 1 ? '' : 's'}</span>
                </summary>
                <div class="aig-dr-tools-table-wrap">
                    <table class="aig-dr-tools-table">
                        <thead>
                            <tr>
                                <th>Tool</th>
                                <th>Calls</th>
                                <th>Total Tokens</th>
                                <th>Input</th>
                                <th>Output</th>
                            </tr>
                        </thead>
                        <tbody>${detailRows}</tbody>
                    </table>
                </div>
            </details>
        `;
    }

    function renderProgressPanel(run) {
        const progress = run.metrics && run.metrics.progress ? run.metrics.progress : {};
        const percent = Math.max(0, Math.min(100, Number(progress.percent || 0)));
        const label = progress.label || run.status || '';

        return `
            <div class="aig-dr-progress-card">
                <div class="aig-dr-progress-head">
                    <strong>${escapeHtml(i18n.progressTitle || 'Progress')}</strong>
                    <span>${escapeHtml(String(percent))}%${progress.estimated ? ` · ${escapeHtml(i18n.estimated || 'Estimated')}` : ''}</span>
                </div>
                <div class="aig-dr-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${escapeHtml(String(percent))}">
                    <span style="width:${escapeHtml(String(percent))}%"></span>
                </div>
                <p class="aig-dr-progress-meta">${escapeHtml(label)}</p>
            </div>
        `;
    }

    function renderPromptPanel(run) {
        return `
            <details class="aig-dr-prompt-panel">
                <summary>
                    <strong>${escapeHtml(i18n.promptTitle || 'Prompt')}</strong>
                </summary>
                <div class="aig-dr-prompt-body">${escapeHtml(run.prompt || '')}</div>
            </details>
        `;
    }

    function renderRuns(runs) {
        if (!Array.isArray(runs) || runs.length === 0) {
            runsRoot.innerHTML = `<p class="description">${escapeHtml(i18n.noRuns || 'No Deep Research runs yet.')}</p>`;
            scheduleRunsPolling(false);
            return;
        }

        const hasActiveRuns = runs.some((run) => ['queued', 'running'].includes(run.status) || ['queued', 'in_progress'].includes(run.response_status));

        runsRoot.innerHTML = runs.map((run) => {
            const annotations = Array.isArray(run.report_annotations) ? run.report_annotations : [];
            const itemSummary = Array.isArray(run.items) ? run.items.map((item) => item.type).join(', ') : '';
            const draftLinks = run.draft_post_id ? `<p><strong>Draft:</strong> #${run.draft_post_id}</p>` : '';
            const title = run.title || run.prompt || (i18n.untitledRun || 'Untitled run');
            const startedAt = formatRunTimestamp(run.created_at);

            return `
                <details class="aig-dr-run-card" data-run-id="${run.id}">
                    <summary class="aig-dr-run-summary">
                        <span class="aig-dr-run-summary-time">${escapeHtml(startedAt)}</span>
                        <strong class="aig-dr-run-summary-title">${escapeHtml(title)}</strong>
                    </summary>
                    <div class="aig-dr-run-body">
                        <div class="aig-dr-run-head">
                            <div>
                                <h3>${escapeHtml(title)}</h3>
                                <p class="aig-dr-meta">
                                    <span>${escapeHtml(run.model || '')}</span>
                                    <span>${escapeHtml(run.status || '')}</span>
                                    <span>${escapeHtml(run.response_status || '')}</span>
                                </p>
                            </div>
                            <div class="aig-dr-run-actions">
                                <button type="button" class="button aig-dr-run-refresh">${escapeHtml(i18n.refresh || 'Refresh')}</button>
                                <button type="button" class="button aig-dr-run-stop" ${run.can_stop ? '' : 'disabled'}>${escapeHtml(i18n.stop || 'Stop')}</button>
                                <button type="button" class="button aig-dr-run-draft" data-post-type="post" ${run.report_message ? '' : 'disabled'}>${escapeHtml(i18n.createPostDraft || 'Create Post Draft')}</button>
                                <button type="button" class="button aig-dr-run-draft" data-post-type="page" ${run.report_message ? '' : 'disabled'}>${escapeHtml(i18n.createPageDraft || 'Create Page Draft')}</button>
                            </div>
                        </div>
                        ${renderProgressPanel(run)}
                        <div class="aig-dr-run-stats">
                            ${renderUsagePanel(run)}
                            ${renderToolsPanel(run)}
                        </div>
                        ${renderPromptPanel(run)}
                        <p><strong>Tool trace:</strong> ${escapeHtml(itemSummary || 'None yet')}</p>
                        <p><strong>Citations:</strong> ${annotations.length}</p>
                        ${draftLinks}
                        <div class="aig-dr-report">${escapeHtml(run.report_message || (i18n.noReport || 'No final report yet.'))}</div>
                        ${run.last_error ? `<p class="aig-dr-error"><strong>Error:</strong> ${escapeHtml(run.last_error)}</p>` : ''}
                    </div>
                </details>
            `;
        }).join('');

        scheduleRunsPolling(hasActiveRuns);
    }

    async function loadRuns(refreshActive = false) {
        refreshButton.disabled = true;

        try {
            const data = await request(`/deep-research/runs${refreshActive ? '?refresh=1' : ''}`);
            renderRuns(data.runs || []);
        } catch (error) {
            runsRoot.innerHTML = `<p class="aig-dr-error">${escapeHtml(error.message)}</p>`;
            scheduleRunsPolling(false);
        } finally {
            refreshButton.disabled = false;
        }
    }

    async function loadSources() {
        try {
            const data = await request('/deep-research/sources');
            savedSources = Array.isArray(data.sources) ? data.sources : [];
            renderSourcesList();
            renderWebhook(data.webhook || null);
            enforceVectorStoreLimit();
        } catch (error) {
            sourceList.innerHTML = `<p class="aig-dr-error">${escapeHtml(error.message)}</p>`;
            sourceOptions.innerHTML = `<p class="aig-dr-error">${escapeHtml(error.message)}</p>`;
        }
    }

    async function loadVectorStores() {
        try {
            const data = await request('/deep-research/vector-stores');
            vectorStores = Array.isArray(data.vector_stores) ? data.vector_stores : [];
            renderVectorStoresList();
        } catch (error) {
            vectorStoreList.innerHTML = `<p class="aig-dr-error">${escapeHtml(error.message)}</p>`;
            vectorStoreOptions.innerHTML = `<p class="aig-dr-error">${escapeHtml(error.message)}</p>`;
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        formStatus.textContent = i18n.creating || 'Starting research…';

        try {
            await request('/deep-research/runs', {
                method: 'POST',
                body: collectFormData(),
            });
            formStatus.textContent = i18n.researchStarted || 'Research started.';
            resetResearchForm();
            await loadRuns(true);
        } catch (error) {
            formStatus.textContent = error.message;
        }
    });

    sourceSaveButton.addEventListener('click', async () => {
        sourceStatus.textContent = 'Saving source…';

        try {
            await request('/deep-research/sources', {
                method: 'POST',
                body: {
                    source_type: 'mcp',
                    name: sourceNameInput.value || '',
                    server_label: sourceLabelInput.value || '',
                    server_url: sourceUrlInput.value || '',
                    authorization: sourceAuthorizationInput.value || '',
                    active: !!sourceActiveInput.checked,
                },
            });
            sourceNameInput.value = '';
            sourceLabelInput.value = 'trusted-mcp';
            sourceUrlInput.value = '';
            sourceAuthorizationInput.value = '';
            sourceActiveInput.checked = true;
            sourceStatus.textContent = i18n.sourceSaved || 'Source saved.';
            await loadSources();
        } catch (error) {
            sourceStatus.textContent = error.message;
        }
    });

    vectorStoreCreateButton.addEventListener('click', async () => {
        vectorStoreStatus.textContent = 'Creating vector store…';

        try {
            await request('/deep-research/vector-stores', {
                method: 'POST',
                body: {
                    name: vectorStoreNameInput.value || '',
                },
            });
            vectorStoreNameInput.value = '';
            vectorStoreStatus.textContent = i18n.vectorStoreCreated || 'Vector store created.';
            await loadVectorStores();
        } catch (error) {
            vectorStoreStatus.textContent = error.message;
        }
    });

    refreshButton.addEventListener('click', loadRuns);

    mainTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            selectMainTab(tab.dataset.mainTab);
        });
    });

    form.addEventListener('change', (event) => {
        if (!event.target) {
            return;
        }

        if (event.target === webToggle || event.target === fileToggle || event.target === mcpToggle || event.target === codeToggle) {
            if (event.target.checked) {
                const panelName = event.target === webToggle ? 'web' : (event.target === fileToggle ? 'files' : (event.target === mcpToggle ? 'mcp' : 'code'));
                setSourcePanelEnabled(panelName, true, true);
                const panel = getSourcePanel(panelName);
                panel.classList.add('is-open');
                panel.querySelector('.aig-dr-source-trigger').textContent = 'Collapse';
                panel.querySelector('.aig-dr-source-trigger').setAttribute('aria-expanded', 'true');
            } else {
                const panelName = event.target === webToggle ? 'web' : (event.target === fileToggle ? 'files' : (event.target === mcpToggle ? 'mcp' : 'code'));
                setSourcePanelEnabled(panelName, false, false);
            }

            syncDomainModeState();
            enforceVectorStoreLimit();
        }

        if (event.target.name === 'vector_store_ids[]') {
            enforceVectorStoreLimit();
        }

        if (event.target === allowAllCheckbox || event.target === allowOnlyCheckbox || event.target === blockCheckbox) {
            if (event.target === allowAllCheckbox && allowAllCheckbox.checked) {
                allowOnlyCheckbox.checked = false;
                blockCheckbox.checked = false;
            }

            if (event.target === allowOnlyCheckbox && allowOnlyCheckbox.checked) {
                allowAllCheckbox.checked = false;
                blockCheckbox.checked = false;
            }

            if (event.target === blockCheckbox && blockCheckbox.checked) {
                allowAllCheckbox.checked = false;
                allowOnlyCheckbox.checked = false;
            }

            if (!allowAllCheckbox.checked && !allowOnlyCheckbox.checked && !blockCheckbox.checked) {
                allowAllCheckbox.checked = true;
            }

            syncDomainModeState();
        }
    });

    document.querySelectorAll('.aig-dr-source-trigger').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const panelName = trigger.dataset.sourceTrigger;
            const panel = getSourcePanel(panelName);

            if (!panel || panel.classList.contains('is-disabled')) {
                return;
            }

            const isOpen = panel.classList.toggle('is-open');
            trigger.textContent = isOpen ? 'Collapse' : 'Expand';
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });

    sourceList.addEventListener('click', async (event) => {
        const button = event.target.closest('.aig-dr-delete-source');
        const card = event.target.closest('[data-source-id]');

        if (!button || !card) {
            return;
        }

        const sourceId = card.getAttribute('data-source-id');

        try {
            button.disabled = true;
            await request(`/deep-research/sources/${sourceId}`, { method: 'DELETE' });
            await loadSources();
        } catch (error) {
            button.disabled = false;
            window.alert(error.message);
        }
    });

    vectorStoreList.addEventListener('click', async (event) => {
        const button = event.target.closest('.aig-dr-delete-vector-store');
        const card = event.target.closest('[data-vector-store-id]');

        if (!button || !card) {
            return;
        }

        const vectorStoreId = card.getAttribute('data-vector-store-id');

        try {
            button.disabled = true;
            await request(`/deep-research/vector-stores/${encodeURIComponent(vectorStoreId)}`, { method: 'DELETE' });
            await loadVectorStores();
        } catch (error) {
            button.disabled = false;
            window.alert(error.message);
        }
    });

    runsRoot.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        const card = event.target.closest('[data-run-id]');

        if (!button || !card) {
            return;
        }

        const runId = card.getAttribute('data-run-id');

        try {
            if (button.classList.contains('aig-dr-run-refresh')) {
                button.disabled = true;
                button.textContent = i18n.refreshing || 'Refreshing…';
                await request(`/deep-research/runs/${runId}/refresh`, { method: 'POST' });
                await loadRuns();
            }

            if (button.classList.contains('aig-dr-run-stop')) {
                button.disabled = true;
                button.textContent = i18n.stopping || 'Stopping…';
                await request(`/deep-research/runs/${runId}/cancel`, { method: 'POST' });
                await loadRuns(true);
            }

            if (button.classList.contains('aig-dr-run-draft')) {
                button.disabled = true;
                button.textContent = i18n.drafting || 'Creating draft…';
                const postType = button.getAttribute('data-post-type');
                const data = await request(`/deep-research/runs/${runId}/create-draft`, {
                    method: 'POST',
                    body: { post_type: postType },
                });
                await loadRuns();
                if (data.result && data.result.edit_link) {
                    window.open(data.result.edit_link, '_blank', 'noopener');
                }
            }
        } catch (error) {
            button.disabled = false;
            button.textContent = 'Retry';
            window.alert(error.message);
        }
    });

    resetResearchForm();
    loadSources();
    loadVectorStores();
    loadRuns();
});
