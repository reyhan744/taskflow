/**
 * TaskFlow – task-centric views: Dashboard, Tasks list, Task form modal,
 * Kanban board, Task detail drawer.
 */
(function () {
        'use strict';

        var store = window.TaskFlowStore;
        var api = window.TaskFlowApi;
        var ui = window.TaskFlowUi;
        var t = function (key, args) { return window.TaskFlowI18n.t(key, args); };

        var STATUSES = ['new', 'in_progress', 'completed'];
        var PRIORITIES = ['low', 'medium', 'high', 'urgent'];

        /** Notify list views that tasks changed. */
        function emitTasksChanged() {
                window.dispatchEvent(new CustomEvent('taskflow:tasks-changed'));
        }

        // ---------------------------------------------------------------- Dashboard

        var TfDashboard = {
                name: 'TfDashboard',
                components: { TfUser: ui.TfUser },
                data: function () {
                        return { stats: null, loading: true, error: '' };
                },
                computed: {
                        c: function () { return this.stats ? this.stats.counts : {}; },
                        cards: function () {
                                return [
                                        { key: 'total', label: t('dash_total'), value: this.c.total, icon: 'dashicons-portfolio', tone: 'blue' },
                                        { key: 'open', label: t('dash_open'), value: this.c.open, icon: 'dashicons-unlock', tone: 'amber' },
                                        { key: 'completed', label: t('dash_completed'), value: this.c.completed, icon: 'dashicons-yes-alt', tone: 'green' },
                                        { key: 'overdue', label: t('dash_overdue'), value: this.c.overdue, icon: 'dashicons-clock', tone: 'red' },
                                        { key: 'due_today', label: t('dash_due_today'), value: this.c.due_today, icon: 'dashicons-calendar-alt', tone: 'purple' },
                                        { key: 'unassigned', label: t('dash_unassigned'), value: this.c.unassigned, icon: 'dashicons-admin-users', tone: 'gray' }
                                ];
                        },
                        priorityRows: function () {
                                if (!this.stats) { return []; }

                                var map = this.stats.by_priority;
                                var max = Math.max(1, PRIORITIES.reduce(function (sum, key) { return sum + (map[key] || 0); }, 0));

                                return PRIORITIES.map(function (key) {
                                        return {
                                                key: key,
                                                label: store.priorityLabel(key),
                                                count: map[key] || 0,
                                                pct: Math.round(100 * (map[key] || 0) / max)
                                        };
                                });
                        },
                        t: function () { return t; },
                        fmtDuration: function () { return store.formatDuration; },
                        fmtDate: function () { return store.formatDate; },
                        ago: function () { return store.timeAgo; }
                },
                mounted: function () { this.load(); },
                methods: {
                        load: async function () {
                                this.loading = true;
                                this.error = '';

                                try {
                                        var data = await api.get('/dashboard');
                                        this.stats = data.stats;
                                } catch (err) {
                                        this.error = err.message || t('error_generic');
                                }

                                this.loading = false;
                        },
                        openTask: function (id) { if (id) { store.openTask(id); } }
                },
                template: '#tf-tpl-dashboard'
        };

        // ---------------------------------------------------------------- Task form

        var TfTaskForm = {
                name: 'TfTaskForm',
                components: { TfModal: ui.TfModal },
                data: function () {
                        return {
                                open: false,
                                taskId: 0,
                                saving: false,
                                errors: {},
                                form: this.emptyForm()
                        };
                },
                computed: {
                        isEdit: function () { return this.taskId > 0; },
                        title: function () { return this.isEdit ? t('edit_task') : t('new_task'); },
                        statuses: function () { return STATUSES; },
                        priorities: function () { return PRIORITIES; },
                        users: function () { return store.state.users; },
                        projects: function () { return store.state.projects; },
                        tags: function () { return store.state.tags; },
                        t: function () { return t; }
                },
                mounted: function () {
                        var self = this;

                        window.addEventListener('taskflow:open-task-form', function (event) {
                                self.openFor(event.detail || null);
                        });
                },
                methods: {
                        emptyForm: function () {
                                return {
                                        title: '',
                                        description: '',
                                        status: 'new',
                                        priority: 'medium',
                                        due_date: '',
                                        project_id: 0,
                                        assignee_id: 0,
                                        tag_ids: []
                                };
                        },
                        openFor: async function (task) {
                                this.errors = {};

                                if (task && task.id) {
                                        this.taskId = task.id;
                                        this.form = {
                                                title: task.title,
                                                description: task.description || '',
                                                status: task.status,
                                                priority: task.priority,
                                                due_date: task.due_date ? String(task.due_date).slice(0, 16).replace(' ', 'T') : '',
                                                project_id: task.project_id || 0,
                                                assignee_id: task.assignee_id || 0,
                                                tag_ids: (task.tag_ids || []).slice()
                                        };
                                } else {
                                        this.taskId = 0;
                                        this.form = this.emptyForm();
                                }

                                this.open = true;

                                try {
                                        await Promise.all([store.loadUsers(), store.loadProjects(), store.loadTags()]);
                                } catch (err) {
                                        // Reference data is optional for rendering the form.
                                }
                        },
                        close: function () { this.open = false; },
                        toggleTag: function (id) {
                                var index = this.form.tag_ids.indexOf(id);

                                if (-1 === index) { this.form.tag_ids.push(id); }
                                else { this.form.tag_ids.splice(index, 1); }
                        },
                        validate: function () {
                                this.errors = {};

                                if (!String(this.form.title).trim()) {
                                        this.errors.title = t('title_required');
                                }

                                return !Object.keys(this.errors).length;
                        },
                        save: async function () {
                                if (!this.validate()) { return; }

                                this.saving = true;

                                var payload = {
                                        title: this.form.title,
                                        description: this.form.description,
                                        status: this.form.status,
                                        priority: this.form.priority,
                                        due_date: this.form.due_date,
                                        project_id: parseInt(this.form.project_id, 10) || 0,
                                        assignee_id: parseInt(this.form.assignee_id, 10) || 0,
                                        tag_ids: this.form.tag_ids
                                };

                                try {
                                        var result;

                                        if (this.isEdit) {
                                                result = await api.put('/tasks/' + this.taskId, payload);
                                        } else {
                                                result = await api.post('/tasks', payload);
                                        }

                                        store.toast(result.message || t('app_title'));
                                        this.open = false;
                                        emitTasksChanged();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }

                                this.saving = false;
                        }
                },
                template: '#tf-tpl-task-form'
        };

        // ---------------------------------------------------------------- Tasks list

        var TfTasks = {
                name: 'TfTasks',
                components: {
                        TfBadgeStatus: ui.TfBadgeStatus,
                        TfBadgePriority: ui.TfBadgePriority,
                        TfUser: ui.TfUser,
                        TfEmpty: ui.TfEmpty,
                        TfSkeleton: ui.TfSkeleton,
                        TfError: ui.TfError,
                        TfPagination: ui.TfPagination
                },
                data: function () {
                        return {
                                items: [],
                                total: 0,
                                page: 1,
                                perPage: (window.TaskFlowBoot.settings && window.TaskFlowBoot.settings.items_per_page) || 10,
                                totalPages: 1,
                                loading: true,
                                error: '',
                                filters: {
                                        search: '',
                                        status: '',
                                        priority: '',
                                        assignee_id: '',
                                        project_id: '',
                                        tag_id: '',
                                        overdue: false,
                                        orderby: 'created_at',
                                        order: 'DESC'
                                },
                                debounceId: 0
                        };
                },
                computed: {
                        statuses: function () { return STATUSES; },
                        priorities: function () { return PRIORITIES; },
                        hasFilters: function () {
                                var f = this.filters;

                                return !!(f.search || f.status || f.priority || f.assignee_id || f.project_id || f.tag_id || f.overdue);
                        },
                        projects: function () { return store.state.projects; },
                        tags: function () { return store.state.tags; },
                        users: function () { return store.state.users; },
                        canEdit: function () { return window.TaskFlowBoot.caps.edit; },
                        t: function () { return t; },
                        fmtDate: function () { return store.formatDate; }
                },
                mounted: function () {
                        var self = this;

                        this.load();

                        Promise.all([store.loadUsers(), store.loadProjects(), store.loadTags()]).catch(function () {});

                        this._onChange = function () { self.load(); };
                        window.addEventListener('taskflow:tasks-changed', this._onChange);

                        this._onFilterProject = function (event) {
                                self.filters.project_id = event.detail ? String(event.detail) : '';
                                self.load(1);
                        };

                        window.addEventListener('taskflow:filter-project', this._onFilterProject);
                },
                unmounted: function () {
                        window.removeEventListener('taskflow:tasks-changed', this._onChange);
                        window.removeEventListener('taskflow:filter-project', this._onFilterProject);
                },
                watch: {
                        'filters.search': function () {
                                var self = this;

                                window.clearTimeout(this.debounceId);
                                this.debounceId = window.setTimeout(function () { self.load(1); }, 300);
                        }
                },
                methods: {
                        buildQuery: function (page) {
                                var f = this.filters;
                                var parts = [
                                        'page=' + (page || this.page),
                                        'per_page=' + this.perPage,
                                        'orderby=' + (f.orderby || 'created_at'),
                                        'order=' + (f.order || 'DESC')
                                ];

                                if (f.search) { parts.push('search=' + encodeURIComponent(f.search)); }
                                if (f.status) { parts.push('status=' + f.status); }
                                if (f.priority) { parts.push('priority=' + f.priority); }
                                if (f.assignee_id) { parts.push('assignee_id=' + f.assignee_id); }
                                if (f.project_id) { parts.push('project_id=' + f.project_id); }
                                if (f.tag_id) { parts.push('tag_id=' + f.tag_id); }
                                if (f.overdue) { parts.push('overdue=1'); }

                                return '/tasks?' + parts.join('&');
                        },
                        load: async function (page) {
                                if (page) { this.page = page; }

                                this.loading = true;
                                this.error = '';

                                try {
                                        var data = await api.get(this.buildQuery());

                                        this.items = data.items || [];
                                        this.total = data.total || 0;
                                        this.totalPages = data.total_pages || 1;
                                } catch (err) {
                                        this.error = err.message || t('error_generic');
                                }

                                this.loading = false;
                        },
                        resetFilters: function () {
                                this.filters = {
                                        search: '', status: '', priority: '', assignee_id: '',
                                        project_id: '', tag_id: '', overdue: false,
                                        orderby: 'created_at', order: 'DESC'
                                };

                                this.load(1);
                        },
                        openTask: function (task) { store.openTask(task.id); },
                        newTask: function () {
                                window.dispatchEvent(new CustomEvent('taskflow:open-task-form', { detail: null }));
                        },
                        editTask: function (task) {
                                window.dispatchEvent(new CustomEvent('taskflow:open-task-form', { detail: task }));
                        },
                        deleteTask: async function (task) {
                                var ok = await store.confirm(t('delete_task_confirm'), { danger: true });

                                if (!ok) { return; }

                                try {
                                        var result = await api.del('/tasks/' + task.id);
                                        store.toast(result.message || t('app_title'));
                                        emitTasksChanged();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }
                        },
                        goPage: function (page) { this.load(page); },
                        statusClass: function (status) { return 'tf-col-status--' + status; },
                        quickStatus: async function (task) {
                                var original = task.status;

                                try {
                                        var result = await api.post('/tasks/' + task.id + '/status', { status: task.status });

                                        var updated = (result.item && result.item[0]) || null;

                                        if (updated) {
                                                this.items = this.items.map(function (item) {
                                                        return item.id === updated.id ? updated : item;
                                                });
                                        }

                                        store.toast(result.message || t('app_title'));
                                        emitTasksChanged();
                                } catch (err) {
                                        task.status = original;
                                        store.toast(err.message || t('error_generic'), 'error');
                                }
                        }
                },
                template: '#tf-tpl-tasks'
        };

        // ---------------------------------------------------------------- Kanban

        var TfKanban = {
                name: 'TfKanban',
                components: { TfBadgePriority: ui.TfBadgePriority, TfEmpty: ui.TfEmpty, TfSkeleton: ui.TfSkeleton, TfError: ui.TfError },
                data: function () {
                        return { tasks: [], loading: true, error: '', dragId: 0, dragOver: '' };
                },
                computed: {
                        columns: function () {
                                var self = this;

                                return STATUSES.map(function (status) {
                                        var cards = self.tasks
                                                .filter(function (task) { return task.status === status; })
                                                .sort(function (a, b) { return a.position - b.position; });

                                        return { status: status, label: store.statusLabel(status), cards: cards };
                                });
                        },
                        canChange: function () { return true; },
                        t: function () { return t; },
                        fmtDate: function () { return store.formatDate; }
                },
                mounted: function () {
                        var self = this;

                        this.load();

                        this._onChange = function () { self.load(); };
                        window.addEventListener('taskflow:tasks-changed', this._onChange);
                },
                unmounted: function () {
                        window.removeEventListener('taskflow:tasks-changed', this._onChange);
                },
                methods: {
                        load: async function () {
                                this.loading = true;
                                this.error = '';

                                try {
                                        var data = await api.get('/tasks?per_page=100&orderby=position&order=ASC');

                                        this.tasks = data.items || [];
                                } catch (err) {
                                        this.error = err.message || t('error_generic');
                                }

                                this.loading = false;
                        },
                        openTask: function (task) { store.openTask(task.id); },
                        newTask: function () {
                                window.dispatchEvent(new CustomEvent('taskflow:open-task-form', { detail: null }));
                        },
                        onDragStart: function (task, event) {
                                this.dragId = task.id;

                                if (event.dataTransfer) {
                                        event.dataTransfer.effectAllowed = 'move';
                                        event.dataTransfer.setData('text/plain', String(task.id));
                                }
                        },
                        onDragOver: function (status, event) {
                                this.dragOver = status;

                                if (event.dataTransfer) { event.dataTransfer.dropEffect = 'move'; }
                        },
                        onDragLeave: function () { this.dragOver = ''; },
                        onDrop: function (status) {
                                this.dragOver = '';

                                if (this.dragId) { this.move(this.dragId, status); }

                                this.dragId = 0;
                        },
                        move: async function (taskId, status) {
                                var task = this.tasks.filter(function (item) { return item.id === taskId; })[0];

                                if (!task || task.status === status) { return; }

                                var target = this.tasks.filter(function (item) { return item.status === status; }).length;

                                try {
                                        var result = await api.post('/tasks/' + taskId + '/status', { status: status, position: target });

                                        this.tasks = this.tasks.map(function (item) {
                                                return item.id === taskId ? result.item[0] : item;
                                        });

                                        store.toast(result.message || t('app_title'));
                                        emitTasksChanged();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }
                        }
                },
                template: '#tf-tpl-kanban'
        };

        // ---------------------------------------------------------------- Task drawer

        var TfTaskDrawer = {
                name: 'TfTaskDrawer',
                components: { TfBadgeStatus: ui.TfBadgeStatus, TfBadgePriority: ui.TfBadgePriority, TfUser: ui.TfUser, TfSkeleton: ui.TfSkeleton, TfError: ui.TfError },
                data: function () {
                        return {
                                task: null,
                                activity: [],
                                comments: [],
                                timeLogs: [],
                                loading: false,
                                error: '',
                                commentText: '',
                                sendingComment: false,
                                newTagName: ''
                        };
                },
                computed: {
                        state: function () { return store.state; },
                        isOpen: function () { return this.state.drawerOpen && this.state.drawerTaskId > 0; },
                        statuses: function () { return STATUSES; },
                        priorities: function () { return PRIORITIES; },
                        users: function () { return store.state.users; },
                        projects: function () { return store.state.projects; },
                        tags: function () { return store.state.tags; },
                        timerOnThis: function () {
                                var timer = this.state.activeTimer;

                                return timer && this.task && parseInt(timer.task_id, 10) === parseInt(this.task.id, 10);
                        },
                        dueLocal: function () {
                                return this.task && this.task.due_date
                                        ? String(this.task.due_date).slice(0, 16).replace(' ', 'T')
                                        : '';
                        },
                        t: function () { return t; },
                        fmtDate: function () { return store.formatDate; },
                        fmtDuration: function () { return store.formatDuration; },
                        ago: function () { return store.timeAgo; }
                },
                watch: {
                        isOpen: function (open) {
                                if (open) { this.load(); }
                        }
                },
                mounted: function () {
                        var self = this;

                        this._onChange = function () {
                                if (self.isOpen && self.task) { self.load(); }
                        };

                        window.addEventListener('taskflow:tasks-changed', this._onChange);

                        this._escHandler = function (event) {
                                if ('Escape' === event.key && self.isOpen) { self.close(); }
                        };

                        document.addEventListener('keydown', this._escHandler);
                },
                unmounted: function () {
                        window.removeEventListener('taskflow:tasks-changed', this._onChange);
                        document.removeEventListener('keydown', this._escHandler);
                },
                methods: {
                        load: async function () {
                                this.loading = true;
                                this.error = '';

                                try {
                                        var data = await api.get('/tasks/' + this.state.drawerTaskId);

                                        this.task = (data.item && data.item[0]) || null;
                                        this.activity = data.activity || [];
                                        this.comments = data.comments || [];
                                        this.timeLogs = data.time_logs || [];

                                        try {
                                                await Promise.all([store.loadUsers(), store.loadProjects(), store.loadTags()]);
                                        } catch (e) { /* optional */ }
                                } catch (err) {
                                        this.error = err.message || t('error_generic');
                                }

                                this.loading = false;
                        },
                        close: function () { store.closeTask(); },
                        patchTask: async function (payload, silent) {
                                try {
                                        var result = await api.put('/tasks/' + this.task.id, payload);

                                        this.task = (result.item && result.item[0]) || this.task;

                                        if (true !== silent) { store.toast(result.message || t('app_title')); }

                                        emitTasksChanged();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }
                        },
                        setStatus: function (status) {
                                if (!this.task || this.task.status === status) { return; }

                                var self = this;

                                api.post('/tasks/' + this.task.id + '/status', { status: status })
                                        .then(function (result) {
                                                self.task = (result.item && result.item[0]) || self.task;
                                                store.toast(result.message || t('app_title'));
                                                emitTasksChanged();
                                        })
                                        .catch(function (err) { store.toast(err.message || t('error_generic'), 'error'); });
                        },
                        editTask: function () {
                                window.dispatchEvent(new CustomEvent('taskflow:open-task-form', { detail: this.task }));
                        },
                        deleteTask: async function () {
                                var ok = await store.confirm(t('delete_task_confirm'), { danger: true });

                                if (!ok) { return; }

                                try {
                                        var result = await api.del('/tasks/' + this.task.id);

                                        store.toast(result.message || t('app_title'));
                                        this.close();
                                        emitTasksChanged();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }
                        },
                        addComment: async function () {
                                if (!String(this.commentText).trim()) { return; }

                                this.sendingComment = true;

                                try {
                                        await api.post('/tasks/' + this.task.id + '/comments', { content: this.commentText });

                                        this.commentText = '';

                                        var data = await api.get('/tasks/' + this.task.id + '/comments');

                                        this.comments = data.items || [];
                                        this.task.comments_count = this.comments.length;
                                        emitTasksChanged();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }

                                this.sendingComment = false;
                        },
                        toggleTimer: async function () {
                                try {
                                        if (this.timerOnThis) {
                                                await api.post('/time/stop');
                                                store.state.activeTimer = null;
                                        } else {
                                                var result = await api.post('/time/start', { task_id: this.task.id });

                                                store.state.activeTimer = result.item;
                                        }

                                        var logs = await api.get('/tasks/' + this.task.id + '/time');

                                        this.timeLogs = logs.items || [];

                                        var fresh = await api.get('/tasks/' + this.task.id);

                                        this.task = (fresh.item && fresh.item[0]) || this.task;
                                        emitTasksChanged();
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }
                        },
                        activityIcon: function (type) {
                                var icons = {
                                        created: 'dashicons-plus-alt',
                                        updated: 'dashicons-edit',
                                        title_changed: 'dashicons-edit',
                                        priority_changed: 'dashicons-leftright',
                                        due_changed: 'dashicons-calendar-alt',
                                        project_changed: 'dashicons-category',
                                        assigned: 'dashicons-admin-users',
                                        status_changed: 'dashicons-update',
                                        comment_added: 'dashicons-admin-comments',
                                        time_logged: 'dashicons-clock',
                                        deleted: 'dashicons-trash'
                                };

                                return icons[type] || 'dashicons-marker';
                        },
                        addTag: async function () {
                                var name = String(this.newTagName || '').trim();

                                if (!name) { return; }

                                try {
                                        var result = await api.post('/tags', { name: name });

                                        store.invalidateTags();
                                        await store.loadTags();

                                        this.newTagName = '';

                                        var ids = (this.task.tag_ids || []).slice();
                                        ids.push(result.item.id);

                                        this.patchTask({ tag_ids: ids }, true);
                                } catch (err) {
                                        store.toast(err.message || t('error_generic'), 'error');
                                }
                        },
                        toggleTag: function (tagId) {
                                var ids = (this.task.tag_ids || []).slice();
                                var index = ids.indexOf(tagId);

                                if (-1 === index) { ids.push(tagId); }
                                else { ids.splice(index, 1); }

                                this.patchTask({ tag_ids: ids }, true);
                        },
                        statusLabel: function (status) { return store.statusLabel(status); }
                },
                template: '#tf-tpl-drawer'
        };

        window.TaskFlowViews = window.TaskFlowViews || {};
        window.TaskFlowViews.TfDashboard = TfDashboard;
        window.TaskFlowViews.TfTaskForm = TfTaskForm;
        window.TaskFlowViews.TfTasks = TfTasks;
        window.TaskFlowViews.TfKanban = TfKanban;
        window.TaskFlowViews.TfTaskDrawer = TfTaskDrawer;
})();
