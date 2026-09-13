/*
 * Mike of All Trades Work Tracker V8.8
 *
 * Progressive enhancement:
 * - task compaction
 * - incomplete/all filter
 * - section manual ordering
 * - section open-state memory
 * - photo chooser improvements
 */

(() => {
    'use strict';

    if(!/\/admin\/work\/(?:manage_job|job)\.php$/i.test(location.pathname)){
        return;
    }

    const params = new URLSearchParams(location.search);
    const jobId = params.get('id');
    if(!jobId) return;

    const storageKey = 'mot-workspace-v88-job-' + jobId;

    const loadState = () => {
        try {
            return JSON.parse(localStorage.getItem(storageKey) || '{}');
        } catch (_) {
            return {};
        }
    };

    const saveState = state => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(state));
        } catch (_) {}
    };

    const state = loadState();

    let serverPreferencesLoaded = false;
    let serverSaveTimer = null;

    async function loadServerPreferences(){
        try{
            const response = await fetch(
                '../../api/work/job_workspace_preferences.php?job_id=' +
                encodeURIComponent(jobId),
                {
                    credentials:'same-origin',
                    cache:'no-store'
                }
            );

            const data = await response.json();

            if(!response.ok || !data?.ok){
                throw new Error(
                    data?.error || 'Could not load workspace preferences.'
                );
            }

            const prefs = data.preferences || {};

            if(Array.isArray(prefs.sectionOrder)){
                state.sectionOrder = prefs.sectionOrder;
            }

            if(
                prefs.openSections &&
                typeof prefs.openSections === 'object'
            ){
                state.openSections = prefs.openSections;
            }

            if([
                'focus',
                'active',
                'all',
                'blocked',
                'completed'
            ].includes(prefs.taskFilter)){
                state.taskFilter = prefs.taskFilter;
            }

            saveState(state);
            serverPreferencesLoaded = true;

        }catch(error){
            console.warn(
                'Work Tracker preferences: using local browser copy.',
                error
            );
        }
    }

    function saveServerPreferences(){
        if(!serverPreferencesLoaded) return;

        clearTimeout(serverSaveTimer);

        serverSaveTimer = setTimeout(async () => {
            try{
                const response = await fetch(
                    '../../api/work/job_workspace_preferences.php?job_id=' +
                    encodeURIComponent(jobId),
                    {
                        method:'POST',
                        credentials:'same-origin',
                        headers:{
                            'Content-Type':'application/json'
                        },
                        body:JSON.stringify({
                            sectionOrder:
                                Array.isArray(state.sectionOrder)
                                    ? state.sectionOrder
                                    : [],
                            openSections:
                                state.openSections &&
                                typeof state.openSections === 'object'
                                    ? state.openSections
                                    : {},
                            taskFilter:
                                [
                                    'focus',
                                    'active',
                                    'all',
                                    'blocked',
                                    'completed'
                                ].includes(state.taskFilter)
                                    ? state.taskFilter
                                    : 'focus'
                        })
                    }
                );

                const data = await response.json();

                if(!response.ok || !data?.ok){
                    throw new Error(
                        data?.error ||
                        'Could not save workspace preferences.'
                    );
                }

            }catch(error){
                console.warn(
                    'Work Tracker preferences were kept locally but ' +
                    'could not be saved to the server.',
                    error
                );
            }
        },350);
    }

    function persistState(){
        saveState(state);
        saveServerPreferences();
    }

    function sectionWrappers(){
        return Array.from(
            document.querySelectorAll('.wt83-section')
        );
    }

    function sectionTitle(section){
        return (
            section.querySelector('.wt83-section-title')?.textContent ||
            section.querySelector('h2,h3')?.textContent ||
            ''
        ).trim();
    }

    function sectionKey(section){
        if(section.dataset.wt88Key) return section.dataset.wt88Key;

        let key = sectionTitle(section)
            .toLowerCase()
            .replace(/[^a-z0-9]+/g,'-')
            .replace(/^-|-$/g,'');

        if(!key) key = 'section-' + Math.random().toString(36).slice(2);

        section.dataset.wt88Key = key;
        return key;
    }

    function applySavedOrder(){
        const saved = Array.isArray(state.sectionOrder)
            ? state.sectionOrder
            : [];

        if(!saved.length) return;

        const sections = sectionWrappers();
        if(!sections.length) return;

        const parent = sections[0].parentElement;
        if(!parent) return;

        const byKey = new Map(
            sections.map(s => [sectionKey(s),s])
        );

        for(const key of saved){
            const el = byKey.get(key);
            if(el){
                parent.appendChild(el);
                byKey.delete(key);
            }
        }

        for(const el of byKey.values()){
            parent.appendChild(el);
        }
    }

    function persistOrder(){
        state.sectionOrder = sectionWrappers().map(sectionKey);
        persistState();
    }

    function addSectionControls(){
        for(const section of sectionWrappers()){
            const toggle =
                section.querySelector('.wt83-section-toggle');

            if(!toggle || toggle.querySelector('.wt88-section-controls')){
                continue;
            }

            const controls = document.createElement('span');
            controls.className = 'wt88-section-controls';

            const defs = [
                ['↑','up','Move section up'],
                ['↓','down','Move section down'],
                ['Top','top','Move section to top'],
                ['Bottom','bottom','Move section to bottom']
            ];

            for(const [label,action,title] of defs){
                const b = document.createElement('button');
                b.type = 'button';
                b.textContent = label;
                b.title = title;

                b.addEventListener('click', ev => {
                    ev.preventDefault();
                    ev.stopPropagation();

                    const parent = section.parentElement;
                    if(!parent) return;

                    const sections = sectionWrappers();
                    const index = sections.indexOf(section);

                    if(action === 'up' && index > 0){
                        parent.insertBefore(
                            section,
                            sections[index - 1]
                        );
                    }

                    if(action === 'down' && index >= 0 && index < sections.length - 1){
                        parent.insertBefore(
                            sections[index + 1],
                            section
                        );
                    }

                    if(action === 'top' && sections.length){
                        parent.insertBefore(section,sections[0]);
                    }

                    if(action === 'bottom'){
                        parent.appendChild(section);
                    }

                    persistOrder();
                });

                controls.appendChild(b);
            }

            toggle.appendChild(controls);
        }
    }

    function taskForms(){
        return Array.from(
            document.querySelectorAll(
                'form[action*="update_task.php"]'
            )
        );
    }

    function statusOf(form){
        return (
            form.querySelector('[name="status"]')?.value ||
            ''
        ).toLowerCase();
    }

    function titleOf(form){
        return (
            form.querySelector('[name="title"]')?.value ||
            'Untitled task'
        ).trim();
    }

    function estimateText(form){
        const mikeLow = parseFloat(
            form.querySelector('[name="mike_estimate_low"]')?.value || 0
        );
        const mikeHigh = parseFloat(
            form.querySelector('[name="mike_estimate_high"]')?.value || 0
        );
        const aiLow = parseFloat(
            form.querySelector('[name="ai_estimate_low"]')?.value || 0
        );
        const aiHigh = parseFloat(
            form.querySelector('[name="ai_estimate_high"]')?.value || 0
        );

        const low = mikeLow || aiLow;
        const high = mikeHigh || aiHigh;

        if(!low && !high) return '';

        if(low && high && low !== high){
            return `${low}–${high} h est.`;
        }

        return `${Math.max(low,high)} h est.`;
    }

    function actualText(form){
        const disabled = Array.from(
            form.querySelectorAll('input[disabled]')
        ).find(el => /\bh\b/i.test(el.value || ''));

        if(disabled && disabled.value){
            return disabled.value.replace(/\s+/g,' ') + ' tracked';
        }

        return '';
    }

    function taskOuterCards(){
        return Array.from(
            document.querySelectorAll(
                '#tasks > .task-card, #tasks .task-card'
            )
        ).filter((card, index, all) => {
            /*
             * Only the original outer task card, never a nested element.
             */
            return !card.parentElement?.closest('.task-card');
        });
    }

    function taskForm(card){
        return card.querySelector(
            'form[action*="update_task.php"]'
        );
    }

    function taskId(card){
        return parseInt(
            taskForm(card)
                ?.querySelector('input[name="task_id"]')
                ?.value || '0',
            10
        );
    }

    function taskStatus(card){
        return (
            taskForm(card)
                ?.querySelector('[name="status"]')
                ?.value || ''
        ).toLowerCase();
    }

    function taskTitle(card){
        return (
            taskForm(card)
                ?.querySelector('[name="title"]')
                ?.value || 'Untitled task'
        ).trim();
    }

    function taskEstimate(card){
        const form = taskForm(card);
        if(!form) return '';

        const mikeLow = parseFloat(
            form.querySelector('[name="mike_estimate_low"]')
                ?.value || 0
        );

        const mikeHigh = parseFloat(
            form.querySelector('[name="mike_estimate_high"]')
                ?.value || 0
        );

        const aiLow = parseFloat(
            form.querySelector('[name="ai_estimate_low"]')
                ?.value || 0
        );

        const aiHigh = parseFloat(
            form.querySelector('[name="ai_estimate_high"]')
                ?.value || 0
        );

        const low = mikeLow || aiLow;
        const high = mikeHigh || aiHigh;

        if(!low && !high) return '';

        if(low && high && low !== high){
            return `${low}–${high} h est.`;
        }

        return `${Math.max(low,high)} h est.`;
    }

    function taskActual(card){
        const form = taskForm(card);
        if(!form) return '';

        const inputs = Array.from(
            form.querySelectorAll('input[disabled]')
        );

        const actual = inputs.find(input =>
            /\bh\b/i.test(input.value || '')
        );

        return actual?.value
            ? `${actual.value} tracked`
            : '';
    }

    function activeTaskCards(){
        return Array.from(
            document.querySelectorAll(
                '#wt88-active-task-list > .task-card'
            )
        );
    }

    function completedTaskCards(){
        return Array.from(
            document.querySelectorAll(
                '#wt88-completed-task-list > .task-card'
            )
        );
    }

    async function persistTaskOrder(){
        const ids = activeTaskCards()
            .map(taskId)
            .filter(id => id > 0);

        try{
            const response = await fetch(
                '../../api/work/reorder_tasks.php',
                {
                    method:'POST',
                    credentials:'same-origin',
                    headers:{
                        'Content-Type':'application/json'
                    },
                    body:JSON.stringify({
                        job_id:Number(jobId),
                        active_task_ids:ids
                    })
                }
            );

            const data = await response.json();

            if(!response.ok || !data?.ok){
                throw new Error(
                    data?.error || 'Could not save task order.'
                );
            }

        }catch(error){
            alert(
                'The task moved on screen, but its order could not ' +
                'be saved.\n\n' +
                (error?.message || error)
            );
        }
    }

    function moveActiveTask(card, action){
        const list = document.getElementById(
            'wt88-active-task-list'
        );

        if(!list || card.parentElement !== list) return;

        const cards = activeTaskCards();
        const index = cards.indexOf(card);

        if(index < 0) return;

        if(action === 'up' && index > 0){
            list.insertBefore(card, cards[index - 1]);
        }

        if(
            action === 'down' &&
            index < cards.length - 1
        ){
            list.insertBefore(
                cards[index + 1],
                card
            );
        }

        if(action === 'top' && cards.length){
            list.insertBefore(card, cards[0]);
        }

        if(action === 'bottom'){
            list.appendChild(card);
        }

        updateTaskQueueLabels();
        persistTaskOrder();
    }

    function makeTaskControls(card){
        const controls = document.createElement('div');
        controls.className = 'wt88-task-order-controls';

        const buttons = [
            ['↑','up','Move task up'],
            ['↓','down','Move task down'],
            ['Top','top','Move task to top'],
            ['Bottom','bottom','Move task to bottom']
        ];

        for(const [label, action, title] of buttons){
            const button = document.createElement('button');

            button.type = 'button';
            button.textContent = label;
            button.title = title;

            button.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();

                moveActiveTask(card, action);
            });

            controls.appendChild(button);
        }

        return controls;
    }

    function buildCompactTask(card){
        if(card.dataset.wt88TaskInstalled === '1'){
            return;
        }

        const form = taskForm(card);
        if(!form) return;

        card.dataset.wt88TaskInstalled = '1';
        card.dataset.taskId = String(taskId(card));
        card.dataset.status = taskStatus(card);

        card.classList.add('wt88-task-compact');

        if(taskStatus(card) === 'completed'){
            card.classList.add('wt88-task-completed');
        }

        /*
         * Move every existing element inside a collapsible body.
         * This includes the task form, customer change requests and
         * inline before/after photo panel.
         */
        const originalChildren = Array.from(card.children);

        const body = document.createElement('div');
        body.className = 'wt88-task-body';
        body.hidden = true;

        for(const child of originalChildren){
            body.appendChild(child);
        }

        const summary = document.createElement('div');
        summary.className = 'wt88-task-summary';

        const left = document.createElement('div');
        left.className = 'wt88-task-summary-main';

        const heading = document.createElement('div');
        heading.className = 'wt88-task-title';
        heading.textContent = taskTitle(card);

        const meta = document.createElement('div');
        meta.className = 'wt88-task-meta';

        meta.textContent = [
            taskEstimate(card),
            taskActual(card)
        ].filter(Boolean).join(' · ') ||
            'Open to view/edit details';

        left.append(heading, meta);

        const right = document.createElement('div');
        right.className = 'wt88-task-summary-right';

        const badge = document.createElement('span');
        badge.className = 'wt88-task-status';
        badge.textContent =
            taskStatus(card).replace(/_/g,' ') ||
            'not started';

        right.appendChild(badge);

        if(taskStatus(card) !== 'completed'){
            right.appendChild(
                makeTaskControls(card)
            );
        }

        summary.append(left, right);

        summary.addEventListener('click', event => {
            if(
                event.target.closest(
                    '.wt88-task-order-controls'
                )
            ){
                return;
            }

            body.hidden = !body.hidden;
        });

        card.append(summary, body);
    }

    function queueFocusSet(){
        const cards = activeTaskCards();

        const current = cards.filter(
            card => taskStatus(card) === 'in_progress'
        );

        const next = cards.filter(
            card => taskStatus(card) === 'not_started'
        );

        const blocked = cards.filter(
            card => taskStatus(card) === 'blocked'
        );

        const chosen = [];

        for(const card of current){
            if(!chosen.includes(card)){
                chosen.push(card);
            }
        }

        for(const card of next){
            if(chosen.length >= 4) break;

            if(!chosen.includes(card)){
                chosen.push(card);
            }
        }

        for(const card of blocked){
            if(chosen.length >= 4) break;

            if(!chosen.includes(card)){
                chosen.push(card);
            }
        }

        return new Set(chosen);
    }

    function applyTaskQueueFilter(mode, save=true){
        const allowed = [
            'focus',
            'active',
            'all',
            'blocked',
            'completed'
        ];

        if(!allowed.includes(mode)){
            mode = 'focus';
        }

        state.taskFilter = mode;

        const focusSet = queueFocusSet();

        for(const card of activeTaskCards()){
            const status = taskStatus(card);

            let visible = true;

            if(mode === 'focus'){
                visible = focusSet.has(card);
            }else if(mode === 'blocked'){
                visible = status === 'blocked';
            }else if(mode === 'completed'){
                visible = false;
            }

            card.classList.toggle(
                'wt88-hidden-filter',
                !visible
            );
        }

        const completed = document.getElementById(
            'wt88-completed-group'
        );

        if(completed){
            completed.hidden = ![
                'all',
                'completed'
            ].includes(mode);

            if(mode === 'completed'){
                completed.open = true;
            }
        }

        document
            .querySelectorAll(
                '.wt88-task-filter-button'
            )
            .forEach(button => {
                button.classList.toggle(
                    'active',
                    button.dataset.mode === mode
                );
            });

        if(save){
            persistState();
        }
    }

    function updateTaskQueueLabels(){
        const active = activeTaskCards();
        const completed = completedTaskCards();

        active.forEach((card,index) => {
            const heading = card.querySelector(
                '.wt88-task-title'
            );

            if(!heading) return;

            heading.textContent =
                `${index + 1}. ${taskTitle(card)}`;
        });

        const summary = document.getElementById(
            'wt88-completed-summary'
        );

        if(summary){
            summary.textContent =
                `✓ Completed tasks (${completed.length})`;
        }

        const count = document.getElementById(
            'wt88-task-count'
        );

        if(count){
            count.textContent =
                `${active.length} active · ` +
                `${completed.length} completed`;
        }
    }

    function installTaskQueue(){
        const section = document.getElementById('tasks');
        if(!section) return;

        const taskCards = taskOuterCards();

        if(!taskCards.length) return;

        for(const card of taskCards){
            buildCompactTask(card);
        }

        if(
            document.getElementById(
                'wt88-task-queue'
            )
        ){
            return;
        }

        const queue = document.createElement('div');
        queue.id = 'wt88-task-queue';

        const toolbar = document.createElement('div');
        toolbar.className = 'wt88-task-tools';

        const count = document.createElement('span');
        count.id = 'wt88-task-count';
        count.className = 'wt88-task-count';

        toolbar.appendChild(count);

        const modes = [
            ['focus','Focus'],
            ['active','Active'],
            ['all','All'],
            ['blocked','Blocked'],
            ['completed','Completed']
        ];

        for(const [mode,label] of modes){
            const button = document.createElement('button');

            button.type = 'button';
            button.textContent = label;
            button.dataset.mode = mode;
            button.className =
                'wt88-task-filter-button';

            button.addEventListener('click',() => {
                applyTaskQueueFilter(mode);
            });

            toolbar.appendChild(button);
        }

        const collapse = document.createElement('button');
        collapse.type = 'button';
        collapse.textContent = 'Collapse all';

        collapse.addEventListener('click',() => {
            section
                .querySelectorAll('.wt88-task-body')
                .forEach(body => {
                    body.hidden = true;
                });
        });

        const expand = document.createElement('button');
        expand.type = 'button';
        expand.textContent = 'Expand visible';

        expand.addEventListener('click',() => {
            section
                .querySelectorAll(
                    '.task-card:not(.wt88-hidden-filter) ' +
                    '.wt88-task-body'
                )
                .forEach(body => {
                    body.hidden = false;
                });
        });

        toolbar.append(collapse, expand);

        const activeList = document.createElement('div');
        activeList.id = 'wt88-active-task-list';
        activeList.className = 'wt88-active-task-list';

        const completedGroup =
            document.createElement('details');

        completedGroup.id = 'wt88-completed-group';
        completedGroup.className =
            'wt88-completed-group';

        const completedSummary =
            document.createElement('summary');

        completedSummary.id =
            'wt88-completed-summary';

        const completedList =
            document.createElement('div');

        completedList.id =
            'wt88-completed-task-list';

        completedGroup.append(
            completedSummary,
            completedList
        );

        queue.append(
            toolbar,
            activeList,
            completedGroup
        );

        /*
         * Insert the queue immediately before the first original task.
         */
        taskCards[0].parentNode.insertBefore(
            queue,
            taskCards[0]
        );

        for(const card of taskCards){
            if(taskStatus(card) === 'completed'){
                completedList.appendChild(card);
            }else{
                activeList.appendChild(card);
            }
        }

        updateTaskQueueLabels();

        /*
         * Old V8.8 installations used "incomplete".
         */
        if(state.taskFilter === 'incomplete'){
            state.taskFilter = 'active';
        }

        applyTaskQueueFilter(
            state.taskFilter || 'focus',
            false
        );
    }

    function compactTasks(){
        installTaskQueue();
    }

    function improvePhotoInputs(){
        const selectors = [
            'input[type="file"][name="photo"]',
            'input[type="file"][name="photos[]"]',
            'input[type="file"][accept*="image"]'
        ];

        document
            .querySelectorAll(selectors.join(','))
            .forEach(input => {
                input.removeAttribute('capture');

                if(
                    /photo/i.test(input.name || '') ||
                    input.closest('.photo-upload')
                ){
                    input.classList.add('wt88-photo-input');
                    input.setAttribute(
                        'accept',
                        'image/jpeg,image/png,image/webp'
                    );

                    /*
                     * Multiple upload is only enabled for endpoints
                     * already expecting an array. Single-photo endpoints
                     * remain single-file to avoid breaking PHP handling.
                     */

                    if(!input.nextElementSibling?.classList?.contains(
                        'wt88-photo-help'
                    )){
                        const help = document.createElement('div');
                        help.className = 'wt88-photo-help';
                        help.textContent =
                            'Choose an existing photo from your library/files or take a new photo.';
                        input.insertAdjacentElement('afterend',help);
                    }
                }
            });
    }

    function createToolbar(){
        if(document.querySelector('.wt88-toolbar')) return;

        const wrap =
            document.querySelector('.wrap') ||
            document.querySelector('main') ||
            document.body;

        const bar = document.createElement('div');
        bar.className = 'wt88-toolbar';

        const jobs = document.createElement('a');
        jobs.href = 'index.php';
        jobs.textContent = '← Jobs';

        const materials = document.createElement('a');
        materials.href = `materials.php?id=${encodeURIComponent(jobId)}`;
        materials.textContent = 'Materials / receipts';

        const closeout = document.createElement('a');
        closeout.href = `closeout.php?id=${encodeURIComponent(jobId)}`;
        closeout.textContent = 'Close-out preview';
        closeout.className = 'primary';

        const top = document.createElement('button');
        top.type = 'button';
        top.textContent = '↑ Top';
        top.addEventListener('click',() => {
            scrollTo({top:0,behavior:'smooth'});
        });

        bar.append(jobs,materials,closeout,top);

        wrap.insertBefore(bar,wrap.firstChild);
    }

    function rememberOpenSections(){
        const sections = sectionWrappers();

        for(const section of sections){
            const key = sectionKey(section);
            const button =
                section.querySelector('.wt83-section-toggle');
            const body =
                section.querySelector('.wt83-section-body');

            if(!button || !body) continue;

            if(
                state.openSections &&
                Object.prototype.hasOwnProperty.call(
                    state.openSections,
                    key
                )
            ){
                const shouldOpen = !!state.openSections[key];
                const isHidden =
                    body.hidden ||
                    getComputedStyle(body).display === 'none';

                if(shouldOpen === isHidden){
                    button.click();
                }
            }

            button.addEventListener('click',() => {
                setTimeout(() => {
                    state.openSections =
                        state.openSections || {};

                    state.openSections[key] =
                        !body.hidden &&
                        getComputedStyle(body).display !== 'none';

                    persistState();
                },0);
            });
        }
    }

    async function init(){
        createToolbar();

        /*
         * Load the server copy first. localStorage remains the fallback
         * if the network/server preference endpoint is unavailable.
         */
        await loadServerPreferences();

        /*
         * V8.3 builds its accordion during the initial script run.
         * A short deferred pass lets us enhance the resulting markup.
         */
        setTimeout(() => {
            applySavedOrder();
            addSectionControls();
            compactTasks();
            improvePhotoInputs();
            rememberOpenSections();
        },60);
    }

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded',init);
    } else {
        init();
    }
})();
