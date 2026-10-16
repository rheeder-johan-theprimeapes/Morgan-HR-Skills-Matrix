for (const form of document.querySelectorAll('[data-review-autosave]')) {
    const status = form.querySelector('[data-save-status]');
    const revision = form.querySelector('[name="expected_revision"]');
    let dirty = false;
    let saving = false;
    let failed = false;
    let allowUnload = false;
    let timer;

    const setStatus = (message) => {
        status.textContent = message;
    };

    const save = async () => {
        clearTimeout(timer);
        if (saving) {
            return;
        }
        if (!dirty || failed) {
            return;
        }
        saving = true;
        dirty = false;
        setStatus('Saving…');
        const body = new FormData(form);
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.redirected) {
                throw new Error('Session changed. Sign in and reload before saving.');
            }
            const result = await response.json().catch(() => null);
            if (!response.ok || !Number.isInteger(result?.revision)) {
                const messages = result?.errors ? Object.values(result.errors).flat().join(' ') : result?.message;
                throw new Error(messages || 'The request could not be saved. Your input is retained.');
            }
            revision.value = String(result.revision);
            setStatus(dirty ? 'Saving pending changes…' : 'Saved · ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
        } catch (error) {
            failed = true;
            dirty = true;
            setStatus('Failed — ' + (error instanceof Error ? error.message : 'Your input is retained. Reload before trying again.'));
        } finally {
            saving = false;
            if (dirty && !failed) {
                clearTimeout(timer);
                timer = setTimeout(save, 200);
            }
        }
    };

    const markDirty = () => {
        dirty = true;
        clearTimeout(timer);
        if (failed) {
            setStatus('Failed — input retained. Review the error, reload, or try Save draft now.');
            return;
        }
        setStatus('Unsaved changes');
        timer = setTimeout(save, 650);
    };
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        failed = false;
        dirty = true;
        save();
    });
    for (const reload of document.querySelectorAll('[data-draft-reload]')) {
        reload.addEventListener('click', () => { allowUnload = true; });
    }
    window.addEventListener('beforeunload', (event) => {
        if (!allowUnload && (dirty || saving)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    const objectiveButton = form.querySelector('[data-add-objective]');
    objectiveButton?.addEventListener('click', () => {
        const objectives = form.querySelector('[data-objectives]');
        const template = form.querySelector('[data-objective-template]');
        const index = objectives.querySelectorAll('[data-objective-row]').length;
        if (index >= 20) {
            objectiveButton.disabled = true;
            return;
        }
        objectives.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        const row = objectives.lastElementChild;
        row.querySelector('legend').textContent = 'Objective ' + (index + 1);
        row.querySelector('textarea').focus();
        if (index === 19) {
            objectiveButton.disabled = true;
        }
    });

    const outcome = form.querySelector('[data-proposal-outcome]');
    const updateProposal = () => {
        const explicitPay = outcome?.value === 'propose';
        for (const field of form.querySelectorAll('[data-proposal-amounts] input, [data-proposal-amounts] select')) {
            field.disabled = !explicitPay;
        }
    };
    outcome?.addEventListener('change', updateProposal);
    updateProposal();
}


document.querySelectorAll('[data-decision-form]').forEach((form) => {
    const action = form.querySelector('[data-decision-action]');
    const outcome = form.querySelector('[data-pay-outcome]');
    const update = () => {
        const approving = action.value === 'approve';
        outcome.disabled = !approving;
        form.querySelectorAll('[data-approved-terms] input, [data-approved-terms] select').forEach((field) => {
            field.disabled = !approving || outcome.value !== 'change';
        });
    };
    action.addEventListener('change', update);
    outcome.addEventListener('change', update);
    update();
});


document.querySelectorAll('[data-development-progress]').forEach((form) => {
    const state = form.querySelector('[data-development-state]');
    const completed = form.querySelector('[data-development-completed]');
    const update = () => { completed.disabled = state.value !== 'completed'; };
    state.addEventListener('change', update);
    update();
});
document.querySelectorAll('[data-development-origin]').forEach((form) => {
    const kind = form.querySelector('[data-origin-kind]');
    const update = () => {
        form.querySelectorAll('[data-origin-fields]').forEach((group) => {
            const active = group.dataset.originFields === kind.value;
            group.hidden = !active;
            group.querySelectorAll('input,select').forEach((field) => { field.disabled = !active; });
        });
    };
    kind.addEventListener('change', update);
    update();
});
