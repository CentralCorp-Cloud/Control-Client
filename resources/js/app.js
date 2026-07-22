const applyThemeControls = () => {
    const selected = localStorage.getItem('theme') || 'system';
    document.querySelectorAll('[data-theme-value]').forEach((button) => {
        const active = button.dataset.themeValue === selected;
        button.setAttribute('aria-pressed', String(active));
        button.classList.toggle('bg-slate-100', active);
        button.classList.toggle('text-slate-950', active);
        button.classList.toggle('dark:bg-slate-800', active);
        button.classList.toggle('dark:text-white', active);
    });
};

document.querySelectorAll('[data-theme-value]').forEach((button) => {
    button.addEventListener('click', () => {
        localStorage.setItem('theme', button.dataset.themeValue);
        window.applyTheme(button.dataset.themeValue);
        applyThemeControls();
    });
});
applyThemeControls();

document.querySelectorAll('[data-app-shell]').forEach((shell) => {
    const navigation = shell.querySelector('[data-shell-navigation]');
    const overlay = shell.querySelector('[data-shell-overlay]');
    const openButton = shell.querySelector('[data-shell-open]');
    const close = () => {
        navigation?.classList.remove('translate-x-0');
        overlay?.setAttribute('hidden', '');
        openButton?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
    };
    const open = () => {
        navigation?.classList.add('translate-x-0');
        overlay?.removeAttribute('hidden');
        openButton?.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
        navigation?.focus();
    };
    openButton?.addEventListener('click', open);
    overlay?.addEventListener('click', close);
    shell.querySelectorAll('[data-shell-close]').forEach((element) => element.addEventListener('click', close));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
});

const closeModal = (modal) => {
    modal.setAttribute('hidden', '');
    modal.previouslyFocused?.focus();
};

document.querySelectorAll('[data-open-modal]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
        const modal = document.querySelector(`[data-modal="${CSS.escape(trigger.dataset.openModal)}"]`);
        if (!modal) return;
        modal.previouslyFocused = trigger;
        modal.removeAttribute('hidden');
        modal.querySelector('[role="dialog"]')?.focus();
    });
});

document.querySelectorAll('[data-modal]').forEach((modal) => {
    modal.querySelectorAll('[data-close-modal]').forEach((trigger) => trigger.addEventListener('click', () => closeModal(modal)));
    modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(modal); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.hidden) closeModal(modal); });
});

document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
    const trigger = dropdown.querySelector('[data-dropdown-trigger]');
    const menu = dropdown.querySelector('[data-dropdown-menu]');
    trigger?.addEventListener('click', () => {
        const willOpen = menu.hidden;
        menu.toggleAttribute('hidden', !willOpen);
        trigger.setAttribute('aria-expanded', String(willOpen));
    });
    document.addEventListener('click', (event) => {
        if (!dropdown.contains(event.target)) {
            menu?.setAttribute('hidden', '');
            trigger?.setAttribute('aria-expanded', 'false');
        }
    });
});

document.querySelectorAll('[data-two-factor-method]').forEach((container) => {
    const buttons = container.querySelectorAll('[data-method]');
    const panels = container.querySelectorAll('[data-method-panel]');
    buttons.forEach((button) => button.addEventListener('click', () => {
        buttons.forEach((candidate) => {
            const active = candidate === button;
            candidate.setAttribute('aria-pressed', String(active));
            candidate.classList.toggle('bg-white', active);
            candidate.classList.toggle('shadow-xs', active);
            candidate.classList.toggle('dark:bg-slate-900', active);
            candidate.classList.toggle('text-slate-500', !active);
            candidate.classList.toggle('dark:text-slate-400', !active);
        });
        panels.forEach((panel) => panel.toggleAttribute('hidden', panel.dataset.methodPanel !== button.dataset.method));
    }));
});

document.querySelectorAll('[data-project-domain-form]').forEach((form) => {
    const plan = form.querySelector('[data-plan-select]');
    const name = form.querySelector('[data-project-name]');
    const centralRadio = form.querySelector('input[name="domain_mode"][value="CENTRALCLOUD"]');
    const customRadio = form.querySelector('input[name="domain_mode"][value="CUSTOM"]');
    const customChoice = form.querySelector('[data-custom-domain-choice]');
    const customLock = form.querySelector('[data-custom-domain-lock]');
    const lockedMessage = form.querySelector('[data-custom-domain-locked-message]');
    const centralPanel = form.querySelector('[data-central-domain-panel]');
    const customPanel = form.querySelector('[data-custom-domain-panel]');
    const centralInput = form.querySelector('[data-central-subdomain]');
    const customInput = form.querySelector('[data-custom-domain]');
    const preview = form.querySelector('[data-domain-preview]');
    let subdomainWasEdited = Boolean(centralInput?.value);

    const slugify = (value) => value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 63);
    const render = () => {
        const selectedPlan = plan?.selectedOptions[0];
        const isFree = selectedPlan?.dataset.isFree === 'true';
        if (isFree) centralRadio.checked = true;
        customRadio.disabled = isFree;
        customChoice?.setAttribute('aria-disabled', String(isFree));
        if (customChoice) customChoice.tabIndex = isFree ? 0 : -1;
        customChoice?.classList.toggle('cursor-not-allowed', isFree);
        customChoice?.classList.toggle('border-amber-300', isFree);
        customChoice?.classList.toggle('bg-amber-50/50', isFree);
        customChoice?.classList.toggle('dark:border-amber-900', isFree);
        customChoice?.classList.toggle('dark:bg-amber-950/20', isFree);
        customLock?.toggleAttribute('hidden', !isFree);
        if (!isFree) lockedMessage?.setAttribute('hidden', '');
        const custom = !isFree && customRadio.checked;
        centralPanel?.toggleAttribute('hidden', custom);
        customPanel?.toggleAttribute('hidden', !custom);
        centralInput?.toggleAttribute('required', !custom);
        customInput?.toggleAttribute('required', custom);
        if (preview) preview.textContent = centralInput?.value ? `${centralInput.value}.${form.dataset.domainSuffix}` : '—';
    };

    plan?.addEventListener('change', render);
    form.querySelectorAll('input[name="domain_mode"]').forEach((radio) => radio.addEventListener('change', render));
    const showPaidOnlyMessage = (event) => {
        const isFree = plan?.selectedOptions[0]?.dataset.isFree === 'true';
        if (!isFree) return;
        event.preventDefault();
        centralRadio.checked = true;
        lockedMessage?.removeAttribute('hidden');
    };
    customChoice?.addEventListener('click', showPaidOnlyMessage);
    customChoice?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') showPaidOnlyMessage(event);
    });
    centralInput?.addEventListener('input', () => { subdomainWasEdited = true; render(); });
    name?.addEventListener('input', () => {
        if (!subdomainWasEdited && centralInput) centralInput.value = slugify(name.value);
        render();
    });
    render();
});

document.querySelectorAll('[data-logs-viewer]').forEach((viewer) => {
    const output = viewer.querySelector('[data-logs-output]');
    const error = viewer.querySelector('[data-logs-error]');
    const loading = viewer.querySelector('[data-logs-loading]');
    const loadButton = viewer.querySelector('[data-logs-load]');
    const copyButton = viewer.querySelector('[data-logs-copy]');
    let cursor = null;
    let busy = false;
    const lines = [];

    const load = async () => {
        if (busy) return;
        busy = true;
        loading?.removeAttribute('hidden');
        loadButton?.setAttribute('disabled', '');
        error?.setAttribute('hidden', '');
        const target = new URL(viewer.dataset.logsViewer, window.location.origin);
        target.searchParams.set('limit', '200');
        if (cursor) target.searchParams.set('cursor', cursor);
        try {
            const response = await fetch(target, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('request_failed');
            const data = await response.json();
            lines.push(...(data.lines || []));
            cursor = data.next_cursor || null;
            output.textContent = lines.join('\n');
            output.scrollTop = output.scrollHeight;
            loadButton?.toggleAttribute('hidden', !cursor);
        } catch (_) {
            if (error) {
                error.textContent = 'Les logs sont momentanément indisponibles. Réessayez dans quelques instants.';
                error.removeAttribute('hidden');
            }
        } finally {
            busy = false;
            loading?.setAttribute('hidden', '');
            loadButton?.removeAttribute('disabled');
        }
    };
    loadButton?.addEventListener('click', load);
    copyButton?.addEventListener('click', () => navigator.clipboard.writeText(lines.join('\n')));
    load();
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-submit-once], form[method="POST"], form[method="post"]');
    if (!form || event.defaultPrevented) return;
    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => { control.disabled = true; });
});
